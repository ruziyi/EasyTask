# EasyTask
基于swoole的任务队列
可多进程并发执行, 主进程监听多个任务进程, 任务进程挂掉会被主进程自动拉起
## example

启动任务 php src/start.php

触发任务
```php
include 'Loader.php';

//立即执行
$task = (new EasyTask\EchoTask("hehe\n"));
//延迟1000ms立即执行
$task = (new EasyTask\EchoTask("hehe\n"))->after(1000);
//每1000ms执行一次, 共执行5次。不设置次数, 则一直重复执行
$task = (new EasyTask\EchoTask("hehe\n"))->every(1000, 5);
//1000ms后, 每1000ms执行一次, 共执行5次。不设置次数, 则一直重复执行
$task = (new EasyTask\EchoTask("hehe\n"))->after(1000)->every(1000, 5);

(new EasyTask\queue\RedisQueue)->putTask($task);
```