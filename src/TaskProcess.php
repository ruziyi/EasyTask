<?php
namespace EasyTask;
use swoole_process;
use Redis;

class TaskProcess
{
    public $process_list = [];
    public $process_use = [];
    private $current_num;
    private $config = [
        'listen_queue' => 'task1',//监听队列
        'min_worker_num' => 1,//初始任务进程数
        'max_worker_num' => 2,//最大任务进程数
    ];

    public function __construct($debug = false, $config = []) 
    {
        if (!$debug) {
            swoole_process::daemon();
        }
        $this->set($config);
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
                $data = $process->read();
                $this->process_use[$process->pid] = 0;
                if ($data != 1) {
                    $redis = new Redis();
                    $redis->connect('127.0.0.1', 6379);
                    $redis->rpush('task1-failed', $data);
                    $redis->close();
                }
            });
        }

        swoole_timer_tick(20, function () {
            $redis = new Redis();
            $redis->connect('127.0.0.1', 6379);
            $task = $redis->lpop('task1-failed');
            if (!$task) {
                $ret = $redis->blpop('task1', 10);
            }
            if (!empty($ret)) {
                $task = $ret[1];
                $free_process = $this->getFreeProcess();

                if ($free_process) {
                    $free_process->write($task);
                } else {
                    $ret1 = $redis->lpush('task1', $task);
                }
            }
            $redis->close();
        });

    }

    public function addTask($task)
    {
        $redis = new Redis();
        $redis->connect('127.0.0.1');
        $redis->rpush('task1_wait', $task);
        $redis->close();
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
                $data = $process->read();

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
                $worker->write(1);
            } catch (Exception $e) {
                //失败将任务回传给主进程
                $worker->write($data);
            }
        });
    }
}

