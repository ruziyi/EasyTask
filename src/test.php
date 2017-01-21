<?php
include 'Loader.php';

$task = (new EasyTask\EchoTask("hehe\n"))->after(1000);
(new EasyTask\queue\RedisQueue)->putTask($task);