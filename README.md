# EasyTask
基于swoole的任务队列
可多进程并发执行, 主进程监听多个任务进程, 任务进程挂掉会被主进程自动拉起
##install
composer require ruziyi/easy-task:dev-master
## example
创建task, 把要实现的业务逻辑放到run方法里
```php
class EchoTask extends EasyTask\Task
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
```
触发任务
```php
$task = (new EasyTask\EchoTask("hehe\n"))->after(1000);
(new EasyTask\queue\RedisQueue)->putTask($task);
```
创建任务消费进程文件server.php
```php
$taskProcess = new EasyTask\TaskProcess();
$taskProcess->run();
```

启动任务消费进程
```php
php server.php
```
### 例子
```php
include 'Loader.php';

//立即执行
$task = (new EasyTask\EchoTask("hehe\n"));
//3分钟后执行
$task = (new EasyTask\EchoTask("hehe\n"))->at(strtotime('+3 minute'));
//延迟1000ms立即执行
$task = (new EasyTask\EchoTask("hehe\n"))->after(1000);
//每1000ms执行一次, 共执行5次。不设置次数, 则一直重复执行
$task = (new EasyTask\EchoTask("hehe\n"))->every(1000, 5);
//1000ms后, 每1000ms执行一次, 共执行5次。不设置次数, 则一直重复执行
$task = (new EasyTask\EchoTask("hehe\n"))->after(1000)->every(1000, 5);

(new EasyTask\queue\RedisQueue)->putTask($task);
```