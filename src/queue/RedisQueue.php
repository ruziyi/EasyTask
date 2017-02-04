<?php
namespace EasyTask\queue;

use Redis;

class RedisQueue implements QueueInterface
{
    public function getTask()
    {
        $redis = $this->createRedis();
        $task = $redis->lpop('task1-failed');
        if (!$task) {
            $task = $redis->blpop('task1', 10);
        }
        $task = $task ? $task[1] : null;
        $redis->close();
        unset($redis);
        return $task;
    }

    private function createRedis()
    {
        $redis = new Redis();
        $ret = $redis->connect('127.0.0.1');
        if (!$ret) {
            trigger_error('connect redis-server failed');
        }
        return $redis;
    }

    public function putTask($task, $type="r")
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $redis = $this->createRedis();
        if ($type == 'l') {
            $redis->lpush('task1', $task);
        } else {
            $redis->rpush('task1', $task);
        }
        $redis->close();
    }

    public function putFailedTask($task)
    {
        if (!is_string($task)) {
            $task = serialize($task);
        }
        $redis = $this->createRedis();
        $redis->rpush('task1-failed', $task);
        $redis->close();
    }
}
