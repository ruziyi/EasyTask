<?php
namespace EasyTask;
class Task
{
    protected $after = 0;
    protected $create_at;
    protected $every = 0;
    protected $retry = 3;//失败重试次数
    public $exec_num;

    public function trigger()
    {
        $after = $this->after;
        $delay = $after - floor(microtime(true) * 1000) + $this->create_at;

        if ($delay <= 0) {
            $this->fire();
        } else {
            $max_delay = 86400000;
            if ($delay > $max_delay) {
                swoole_timer_after($max_delay, function(){
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
                }
            });
        } else {
            $this->run();
        }
    }

    public function at($time)
    {
        if (!is_int($time)) {
            $time = strtotime($time) * 1000;
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

    public function __construct()
    {
        $this->create_at = floor(microtime(true) * 1000);
    }

    public function run()
    {}
}
