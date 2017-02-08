<?php
namespace EasyTask;

use swoole_process;

class TaskProcess
{
    public $process_list = [];
    public $process_use = [];
    private $current_num;
    private $queue;
    private $config = [
        'listen_queue' => 'task1', //监听队列
        'min_worker_num' => 1, //初始任务进程数
        'max_worker_num' => 2, //最大任务进程数
        'queue' => [
            'type' => 'redis',
            'host' => '127.0.0.1',
            'port' => 6379,
        ],
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
    }

    public function set($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function run()
    {
        //监听子进程退出信号
        swoole_process::signal(SIGCHLD, function ($sig) {
            while ($ret = swoole_process::wait(false)) {
                echo "{$ret['pid']} exit";
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
                    echo "process {$pid} start";
                } else {
                    echo "restart process failed";
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
            $task = $this->queue->getTask();

            if ($task) {
                $free_process = $this->getFreeProcess();

                if ($free_process) {
                    $free_process->write($task);
                } else {
                    $this->queue->putTask($task, 'l');
                }
            }
        });

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
        swoole_event_add($worker->pipe, function ($pipe) use ($worker) {
            $data = $worker->read();
            if ($data == 'exit') {
                $worker->exit();
                exit;
            }
            try {
                $task = unserialize($data);
                $task->trigger();
            } catch (Exception $e) {
                //失败压入失败队列 进行重试
                $task->retry--;
                if ($task->retry > 0) {
                    $this->queue->putFailedTask($task);
                }
            }
            $worker->write(1);
        });
    }
}
