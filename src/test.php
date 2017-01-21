<?php
include 'Loader.php';

$task = (new EasyTask\EchoTask("hehe\n"))->after(1000)->trigger();
// (new EasyTask\queue\RedisQueue)->putTask($task);