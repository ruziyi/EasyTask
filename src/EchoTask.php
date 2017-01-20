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
        echo $this->msg;
    }
}