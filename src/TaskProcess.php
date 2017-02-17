<?php
namespace EasyTask;

use swoole_process;

class TaskProcess
{
    public $process_list = [];
    public $process_use = [];
    private $current_num;
    private $queue;
    private $log;

    private $config = [
        'listen_queue' => 'task1', //监听队列
        'min_worker_num' => 2, //初始任务进程数
        'max_worker_num' => 2, //最大任务进程数
        'queue' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
        'log_path' => './log.txt',
        'process_name' => 'swoole_task',
    ];

    public function __construct($debug = false, $config = [])
    {
        if (!$debug) {
            swoole_process::daemon();
        }
        $this->set($config);

        $queue_class = $this->config['queue']['type'];
        $queue_class = "\\EasyTask\\queue\\" . ucfirst($queue_class) . 'Queue';
        $this->queue = new $queue_class($this->config['queue']['host'], $this->config['queue']['port']);

        $this->log = new \EasyTask\Log($this->config['log_path']);
        register_shutdown_function([$this, 'registerShutdown']);

        cli_set_process_title($this->config['process_name']);
    }

    public function registerShutdown()
    {
        $error = error_get_last();
        if (isset($error['type'])) {
            switch ($error['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                    $message = $error['message'];
                    $file = $error['file'];
                    $line = $error['line'];
                    $log = "$message ($file:$line)\nStack trace:\n";
                    $trace = debug_backtrace();
                    foreach ($trace as $i => $t) {
                        if (!isset($t['file'])) {
                            $t['file'] = 'unknown';
                        }
                        if (!isset($t['line'])) {
                            $t['line'] = 0;
                        }
                        if (!isset($t['function'])) {
                            $t['function'] = 'unknown';
                        }
                        $log .= "#$i {$t['file']}({$t['line']}): ";
                        if (isset($t['object']) and is_object($t['object'])) {
                            $log .= get_class($t['object']) . '->';
                        }
                        $log .= "{$t['function']}()\n";
                    }
                    if (isset($_SERVER['REQUEST_URI'])) {
                        $log .= '[QUERY] ' . $_SERVER['REQUEST_URI'];
                    }
                    error_log($log, 3, './error.log');
                default:
                    break;
            }
        }
    }

    public function set($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function moveBakToQueue()
    {
        $redis = $this->queue->getRedis();
        $all_uncompleted_task_ids = $redis->hkeys('all-task');
        foreach ($all_uncompleted_task_ids as $id) {
            $redis->lpush('task1', $id);
        }
    }
    public function run()
    {
        //将未执行的任务移到正式队列
        $this->moveBakToQueue();
        //监听子进程退出信号
        swoole_process::signal(SIGCHLD, function ($sig) {
            while ($ret = swoole_process::wait(false)) {
                $this->log->write("{$ret['pid']} exit", 'error');
                $process = $this->process_list[$ret['pid']];
                unset($this->process_list[$ret['pid']], $this->process_use[$ret['pid']]);
                swoole_event_del($process->pipe);
                //进程重启
                $pid = $process->start();
                if ($pid) {
                    $this->process_list[$pid] = $process;
                    $this->process_use[$pid] = 0;

                    swoole_event_add($process->pipe, function ($pipe) use ($process) {
                        $data = $process->read();
                        $this->process_use[$process->pid] = 0;
                    });
                    $this->log->write("process {$pid} start");
                } else {
                    $this->log->write("restart process failed", 'error');
                }
            }
        });

        for ($i = 0; $i < $this->config['min_worker_num']; $i++) {
            $process = new swoole_process([$this, 'task_run'], false, 2);
            $pid = $process->start();
            $this->process_list[$pid] = $process;
            $this->process_use[$pid] = 0;
            $this->current_num++;
        }

        foreach ($this->process_list as $process) {
            swoole_event_add($process->pipe, function ($pipe) use ($process) {
                $this->process_use[$process->pid] = 0; //收到任务进程数据， 设为可用
            });
        }

        swoole_timer_tick(20, function () {
            $data = $this->queue->getTask();

            if ($data) {
                $free_process = $this->getFreeProcess();

                if ($free_process) {
                    $free_process->write($data);
                } else {
                    $task = unserialize($data);
                    $this->queue->putTask($task->id, 'r');
                }
            }
        });

        echo 'easy-task run succeed: listening task1' . PHP_EOL;
        $this->log->write('easy-task run succeed: listening task1' . PHP_EOL);
    }

    private function getFreeProcess()
    {
        foreach ($this->process_use as $k => $v) {
            if ($v == 0) {
                $this->process_use[$k] = 1;
                return $this->process_list[$k];
            }
        }
        if ($this->current_num < $this->config['max_worker_num']) {
            $process = new swoole_process([$this, 'task_run'], false, 2);
            $pid = $process->start();
            $this->process_list[$pid] = $process;
            $this->process_use[$pid] = 1;
            $this->current_num++;
            swoole_event_add($process->pipe, function ($pipe) use ($process) {
                $this->process_use[$process->pid] = 0;
            });
            return $process;
        }
        return false;
    }

    public function task_run($worker)
    {
        $worker->name('swoole_worker_' . $worker->pid);
        $queue = new \EasyTask\queue\RedisQueue();
        swoole_event_add($worker->pipe, function ($pipe) use ($worker, $queue) {
            $data = $worker->read();
            if ($data == 'exit') {
                $worker->exit();
                exit;
            }
            try {
                $task = unserialize($data);
                $this->log->write($data . 'start');
                $task->trigger(function () use ($data, $task, $queue) {
                    $this->log->write($data . 'succeed');
                    $queue->remBak($task->id);
                });
            } catch (Exception $e) {
                //失败压入失败队列 进行重试
                $task->retry--;
                if ($task->retry > 0) {
                    $queue->putFailedTask($task);
                } else {
                    $this->log->write(json_encode($task) . ' failed', 'error');
                }
            }
            $worker->write(1);
        });
    }
}
