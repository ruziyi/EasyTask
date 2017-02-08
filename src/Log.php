<?php
namespace EasyTask;

class Log
{
    private $file_path;

    public function __construct($path = './log.txt')
    {
        $this->file_path = $path;
    }

    public function write($msg, $level = 'info')
    {
        $msg = "[$level]". date('Y-m-d H:i:s') . ": $msg" . PHP_EOL;
        file_put_contents($this->file_path, $msg, FILE_APPEND);
    }
}