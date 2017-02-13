<?php
include 'Loader.php';
$task = (new EasyTask\EchoTask("hehe"))->after(1000)->every(1000, 5);

(new EasyTask\queue\RedisQueue)->putTask($task);
