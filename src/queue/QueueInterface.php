<?php
namespace EasyTask\queue;
interface QueueInterface
{
    public function getTask();
    public function putTask($task);
    public function putFailedTask($task);
}