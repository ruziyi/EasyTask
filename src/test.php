<?php
include 'Loader.php';
date_default_timezone_set('PRC');
$task = (new EasyTask\EchoTask("hehe"))->after(1000)->every(1000, 5);
// $task->trigger();
(new EasyTask\queue\RedisQueue)->putTask($task);
