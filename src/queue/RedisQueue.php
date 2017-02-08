<?php
namespace EasyTask\queue;

use Redis;

class RedisQueue implements QueueInterface
{
    private $connected = 0;
    private $redis;
    private $host;
    private $port;

    public function __construct($host = '127.0.0.1', $port = 6379)
    {
        $this->host = $host;
        $this->port = $port;
    }

    public function getTask()
    {
        $redis = $this->getRedis();
        $task = $redis->lpop('task1-failed');
        if (!$task) {
            $task = $redis->bRpopLpush('task1', 'task1-backup', 10);
        }
        unset($redis);
        return $task;
    }

    public function remBak($task)
    {
        $redis = $this->getRedis();
        $redis->lrem('task1-backup', $task, 1);
    }

    public function getRedis()
    {
        if (!$this->connected) {
            $this->redis = new Redis();
            $ret = $this->redis->pconnect($this->host, $this->port);
            if (!$ret) {
                trigger_error('connect redis-server failed');
            }
            $this->connected = 1;
        }
        return $this->redis;
    }

    public function putTask($task, $type="l")
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
