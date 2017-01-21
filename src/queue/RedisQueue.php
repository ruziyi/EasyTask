<?php
namespace EasyTask\queue;

use Redis;

class RedisQueue implements QueueInterface
{
    public function __construct()
    {
        $this->redis = new Redis();
    }

    public function getTask()
    {
        $ret = $this->redis->connect('127.0.0.1');
        if (!$ret) {
            trigger_error('connect redis-server failed');
        }
        $task = $this->redis->lpop('task1-failed');
        if (!$task) {
            list(, $task) = $this->redis->blpop('task1');
        }
        $this->redis->close();
        return $task;
    }

    public function putTask($task)
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $ret = $this->redis->connect('127.0.0.1');
        if (!$ret) {
            trigger_error('connect redis-server failed');
        }
        $this->redis->rpush('task1', $task);
        $this->redis->close();
    }

    public function putFailedTask($task)
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $ret = $this->redis->connect('127.0.0.1');
        if (!$ret) {
            trigger_error('connect redis-server failed');
        }
        $this->redis->rpush('task1-failed', $task);
        $this->redis->close();
    }
}
