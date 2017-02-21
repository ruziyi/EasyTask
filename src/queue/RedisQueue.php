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
        $task_id = $redis->lpop('task1-failed');
        if (!$task_id) {
            $data = $redis->bRpop('task1', 10);
            if (!$data) {
                return;
            }
            $task_id = $data[1];
        }
        $task = $redis->hget('all-task', $task_id);
        return $task;
    }

    public function remBak($task_id)
    {
        $redis = $this->getRedis();
        $ret = $redis->hdel('all-task', $task_id);
    }

    public function getRedis()
    {
        if (!$this->connected) {
            $this->redis = new Redis();
            $ret = $this->redis->connect($this->host, $this->port);
            if (!$ret) {
                trigger_error('connect redis-server failed');
            }
            $this->connected = 1;
        }
        return $this->redis;
    }

    public function ping()
    {
        $this->getRedis();
        swoole_timer_tick(1000, function () {
            $this->redis->ping();
        });

    }
    public function putTask($task, $type = "l")
    {
        $redis = $this->getRedis();

        if (!is_string($task)) {
            if (!$task->id) {
                $task->id = spl_object_hash($task);
            }
            $id = $task->id;
            $redis->hset('all-task', $id, serialize($task));
        }
        if ($type == 'l') {
            $redis->lpush('task1', $id);
        } else {
            $redis->rpush('task1', $id);
        }
    }

    public function putFailedTask($task)
    {
        $redis = $this->getRedis();
        $redis->hSet('all-task', $task->id, serialize($task));
        $redis->rpush('task1-failed', $task->id);
    }
}
