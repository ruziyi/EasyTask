<?php
namespace EasyTask\queue;

use Redis;

class RedisQueue implements QueueInterface
{
    private $connected = 0;
    private $redis;

    public function getTask()
    {
        $redis = $this->getRedis();
        $task = $redis->lpop('task1-failed');
        if (!$task) {
            $task = $redis->blpop('task1', 10);
        }
        $task = $task ? $task[1] : null;
        unset($redis);
        return $task;
    }

    private function getRedis()
    {
        if (!$this->connected) {
            $this->redis = new Redis();
            $ret = $this->redis->pconnect('127.0.0.1');
            if (!$ret) {
                trigger_error('connect redis-server failed');
            }
            $this->connected = 1;
        }
        return $this->redis;
    }

    public function putTask($task, $type="r")
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $redis = $this->getRedis();
        if ($type == 'l') {
            $redis->lpush('task1', $task);
        } else {
            $redis->rpush('task1', $task);
        }
    }

    public function putFailedTask($task)
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $redis = $this->getRedis();
        $redis->rpush('task1-failed', $task);
    }
}
