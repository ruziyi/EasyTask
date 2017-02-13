<?php

namespace EasyTask;

class EchoTask extends Task
{
    private $msg;
    public function __construct($msg)
    {
        parent::__construct();
        $this->msg = $msg;
    }

    public function run()
    {
        echo date('Y-m-d H:i:s'), ': ', $this->msg, PHP_EOL;
    }
}