<?php
namespace EasyTask;

class Task
{
    protected $after = 0;
    protected $create_at;
    protected $every = 0;
    protected $retry = 3; //失败重试次数
    protected $exec_num = 0; //需执行多少次
    protected $cb;
    public $id;

    public function __construct()
    {
        $this->create_at = floor(microtime(true) * 1000);
    }
    public function trigger($cb)
    {
        $this->cb = $cb;

        $after = $this->after;
        $delay = $after - floor(microtime(true) * 1000) + $this->create_at;

        if ($delay <= 0) {
            $this->fire();
        } else {
            $max_delay = 86400000;
            if ($delay > $max_delay) {
                swoole_timer_after($max_delay, function () {
                    $this->trigger();
                });
            } else {
                swoole_timer_after($delay, function () {
                    $this->fire();
                });
            };
        }
    }

    public function fire()
    {
        if ($this->every > 0) {
            swoole_timer_tick($this->every, function ($timer_id) {
                $this->run();
                if ($this->exec_num && --$this->exec_num <= 0) {
                    swoole_timer_clear($timer_id);
                    $this->onTaskFinish();
                }
                $queue = new \EasyTask\queue\RedisQueue();
                $obj = clone $this;
                unset($obj->cb);
                $queue->getRedis()->hSet('all-task', $obj->id, serialize($obj));
            });
        } else {
            $this->run();
            $this->onTaskFinish();

        }
    }

    public function onTaskFinish()
    {
        if ($this->cb) {
            call_user_func($this->cb);
        }
        unset($this->cb);
    }

    public function at($time)
    {
        if (!is_int($time)) {
            $time = strtotime($time) * 1000;
        } else {
            $time = $time * 1000;
        }
        $this->after = $time - $this->create_at;
        return $this;
    }

    public function after($delay)
    {
        $this->after = $delay;
        return $this;
    }

    public function every($delay, $num = 0)
    {
        $this->every = $delay;
        if ($num > 0) {
            $this->exec_num = $num;
        }
        return $this;
    }
    
    public function run()
    {}
}
