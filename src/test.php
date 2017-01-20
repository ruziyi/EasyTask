<?php
include 'Loader.php';


$task = (new EasyTask\EchoTask("hehe\n"));
(new EasyTask\queue\RedisQueue)->putTask($task);