<?php

class Loader
{
    private static $namespace = [];

    public static function addNamespace($namespace, $path)
    {
        static::$namespace[$namespace] = $path;
    }

    public static function autoload($class)
    {
        $class = str_replace("\\", "/", $class);
        list($namespace, $class) = explode("/", $class, 2);
        if (isset(static::$namespace[$namespace])) {
            require static::$namespace[$namespace] . '/' . $class . '.php';
        }
    }
}

Loader::addNamespace('EasyTask', __DIR__);
spl_autoload_register(['Loader', 'autoload']);