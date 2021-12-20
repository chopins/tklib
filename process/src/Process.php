<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Process;

use Toknot\Date\Time;
use Toknot\Input\CommandInput;
use Toknot\Process\ShareMemory;

/**
 * Process
 *
 * @todo Test the class
 */
class Process
{

    private $lock = null;
    private $shm = [];
    protected $mainSockPool = [];
    protected $childSock = null;
    protected $scheduleTable = [];
    protected $myChildProcess = [];
    protected $argv = [];
    protected $parentPid = 0;
    public bool $shmIpc = false;
    public static $SHM_IPC_FLAG = 'P';
    public static $SLEEP_USEC = 100000;

    const CMD_LOCK = 'LOCK';
    const CMD_UNLOCK = 'UNLOCK';
    const CMD_QUIT = 'QUIT';
    const CMD_SUCC = 'SUCCESS';
    const CMD_FAIL = 'FAIL';
    const CMD_ALREADY = 'ALREADY';
    const CMD_UNKNOW = 'UNKOWN';
    const ANY_LOCK_SOCK = 'tcp://127.0.0.1:';
    const QUEUE_ADD = 'QADD';
    const QUEUE_GET = 'QGET';
    const QUEUE_EMPTY = 'EMPTY';

    public function __construct(bool $shmIpc = false)
    {
        if(!extension_loaded('pcntl')) {
            throw new \RuntimeException('pcntl extension un-loaded');
        }
        if(!extension_loaded('posix')) {
            throw new \RuntimeException('posix extension un-loaded');
        }
        $this->shmIpc = $shmIpc;
        if($shmIpc && !extension_loaded('sysvshm')) {
            throw new \RuntimeException('sysvshm extension un-loaded');
        }
        $this->argv = CommandInput::instance();
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public static function loadProcessExtension($shmIpc = false)
    {
        dl('pcntl.' . PHP_SHLIB_SUFFIX);
        dl('posix.' . PHP_SHLIB_SUFFIX);
        if($shmIpc) {
            dl('sysvshm' . PHP_SHLIB_SUFFIX);
        }
    }

    public function setProcessTitle($title)
    {
        if(PHP_MAJOR_VERSION < 5) {
            throw new \RuntimeException('setProcessTitle() is avaiabled when only php version greater then 5.5');
        }
        return cli_set_process_title($title);
    }

    public function ipcChannel($channelName, $size)
    {
        if($channelName && $this->shmIpc) {
            if(empty($this->shm[$channelName])) {
                $this->shm[$channelName] = new ShareMemory($channelName, self::$SHM_IPC_FLAG, $size);
            }
            return [0, 0];
        }
        return stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
    }

    public function setBlocking($pipe, $enable)
    {
        if($this->shmIpc && is_array($pipe)) {
            $this->shm[$pipe[0]]->setBlocking($pipe[1], true);
        } else {
            stream_set_blocking($pipe, $enable);
        }
    }

    public function quit($pipe = null)
    {
        $pipe = $pipe ? $pipe : $this->childSock;
        $this->setBlocking($pipe, 1);
        $pid = $this->getpid();
        $this->send($pipe, self::CMD_QUIT . "|$pid");
        $res = $this->read($pipe);
        if(is_resource($pipe)) {
            fclose($pipe);
        }
        return $res;
    }

    public function send($sock, $data)
    {
        if($this->shmIpc && is_array($sock)) {
            return $this->shm[$sock[0]]->put($sock[1], $data);
        }
        return fwrite($sock, $data . PHP_EOL);
    }

    public function read($sock)
    {
        if($this->shmIpc && is_array($sock)) {
            return trim($this->shm[$sock[0]]->get($sock[1]));
        } else {
            return trim(fgets($sock));
        }
    }

    /**
     * start a task queue
     *
     * <code>
     * //queue demon process
     * $p = new Process;
     * $p->taskQueue('tcp://127.0.0.1:9111');
     *
     * //other process
     * $p = new Process;
     * $p->addTask('tcp://127.0.0.1:9111', $functionName);
     * </code>
     *
     * @param string $local
     * @param string $port
     * @param callable $taskCall
     */
    public function taskQueue($local, $port, $taskCall)
    {
        list($add, $addServer) = $this->ipcChannel(false, 100);
        list($get, $getServer) = $this->ipcChannel(false, 100);

        $mpid = $this->queueManager($addServer, $getServer);
        $taskpid = $this->taskManager($get, $taskCall);

        $addpid = $this->recvTask($add, $local, $port);

        while(true) {
            $pid = $this->wait(0, $status, 1);
            switch($pid) {
                case $mpid:
                    $mpid = $this->queueManager($addServer, $getServer);
                    break;
                case $taskpid:
                    $taskpid = $this->taskManager($get, $taskCall);
                    break;
                case $addpid:
                    $addpid = $this->recvTask($add, $local, $port);
            }
            usleep(self::$SLEEP_USEC);
        }
        return 1;
    }

    /**
     * recvice other task message from other process
     *
     * @param resource $add
     * @param string $local
     * @param string $port
     * @return int
     */
    protected function recvTask($add, $local, $port)
    {
        $addpid = $this->fork();
        if($addpid > 0) {
            return $addpid;
        }

        $errno = $errstr = 0;
        $recvSock = stream_socket_server("tcp://$local:$port", $errno, $errstr);

        while(($acp = stream_socket_accept($recvSock, -1))) {
            $message = $this->read($acp);
            $this->send($add, self::QUEUE_ADD . $message);
            $res = $this->read($add);
            $this->send($acp, $res);
            usleep(self::$SLEEP_USEC);
        }
        exit;
    }

    /**
     * add a task message to queue
     *
     * @param string $local
     * @param string $port
     * @param string|array $message
     * @param array $args
     * @return boolean
     * @throws \Exception
     */
    public function addTask($local, $port, $message)
    {
        $desc = serialize([$message, time(), $this->getpid()]);

        try {
            $sock = stream_socket_client("tcp://$local:$port", $errno, $errstr, 2, STREAM_CLIENT_CONNECT);
        } catch(\Exception $e) {
            throw $e;
        }

        $this->setBlocking(false, $sock, 1);
        $this->send($sock, $desc);
        if($this->read($sock) == self::CMD_SUCC) {
            return true;
        }
        return false;
    }

    /**
     * opreate task message
     *
     * @param resource $get
     * @param callable $taskCall
     * @return int
     * @throws \Exception
     */
    protected function taskManager($get, $taskCall)
    {
        $taskpid = $this->fork();
        if($taskpid > 0) {
            return $taskpid;
        }

        while(true) {
            $r = [];
            $w = [$get];
            $except = null;
            $change = stream_select($r, $w, $except, 0, self::$SLEEP_USEC);
            if(false === $change) {
                throw new \RuntimeException('task queue select fail');
            }

            if($change > 0) {
                $this->execTask($w, $taskCall);
            }
            usleep(self::$SLEEP_USEC);
        }
        exit;
    }

    /**
     * exec task
     *
     * @param array $w
     * @param callable $taskCall
     * @return int
     */
    protected function execTask($w, $taskCall)
    {
        $execpid = $this->fork();
        if($execpid > 0) {
            return $this->wait($execpid);
        }

        foreach($w as $rsock) {
            $this->send($rsock, self::QUEUE_GET);
            $line = $this->read($rsock);
            if($line == self::QUEUE_EMPTY) {
                exit;
            }
            $taskInfo = unserialize($line);
            if($taskInfo) {
                $this->send($rsock, self::QUEUE_GET . self::CMD_SUCC);
            } else {
                $this->send($rsock, self::QUEUE_GET . self::CMD_FAIL);
            }
            $callPid = $this->fork();
            if($callPid === 0) {
                call_user_func_array($taskCall, $taskInfo);
                exit;
            } else {
                $this->wait($callPid);
            }
        }
        exit;
    }

    /**
     * store task message
     *
     * @param resource $addServer
     * @param resource $getServer
     * @return int
     * @throws Exception
     */
    protected function queueManager($addServer, $getServer)
    {
        $mpid = $this->fork();
        if($mpid > 0) {
            return $mpid;
        }

        $taskQueue = new \SplQueue();
        while(true) {
            $r = [$addServer, $getServer];
            $w = [];
            $except = null;

            $change = stream_select($r, $w, $except, 0, self::$SLEEP_USEC);
            if(false === $change) {
                throw new \RuntimeException('task queue select fail');
            }

            if($change > 0) {
                $this->queueRequest($r, $taskQueue);
            }
            usleep(self::$SLEEP_USEC);
        }
        exit;
    }

    /**
     * push or get a task message
     *
     * @param array $r
     * @param SplQueue $queue
     */
    protected function queueRequest($r, $queue)
    {
        foreach($r as $rsock) {
            $line = $this->read($rsock);
            $flag = substr($line, 0, 4);
            if($flag == self::QUEUE_GET) {
                $this->readGet($rsock, $queue);
            } elseif($flag == self::QUEUE_ADD) {
                $message = substr($line, 4);
                $queue->enqueue($message);
                $this->send($rsock, self::CMD_SUCC);
            }
        }
    }

    /**
     * Get a task message
     *
     * @param resource $wsock
     * @param SplQueue $queue
     * @return boolean
     */
    protected function readGet($wsock, $queue)
    {

        if($queue->count() == 0) {
            $this->send($wsock, self::QUEUE_EMPTY);
            $res = $this->read($wsock);
            return;
        }
        $task = $queue->dequeue();
        $cnt = 5;
        do {
            $this->send($wsock, $task);
            $res = $this->read($wsock);
            if(substr($res, 3, 3) == self::CMD_SUCC) {
                return true;
            } else {
                $queue->enqueue($task);
            }
            $cnt--;
            usleep(self::$SLEEP_USEC);
        } while($cnt > 0);
    }

    /**
     * init process lock for any process
     *
     * <code>
     * $port = 4040;
     * //lock handle demon process
     * $p = new Process;
     * $p->anyLock($port);
     *
     * //other process
     * $p = new Process;
     * $p->aLock($port);
     * $p->aUnlock();
     * </code>
     *
     * @param int $port
     * @throws Exception
     */
    public function anyLock($port = 9088)
    {
        $errno = 0;
        $errstr = '';

        while(true) {
            if(($cpid = $this->fork()) > 0) {
                $this->wait($cpid);
                continue;
            } else {
                break;
            }
        }
        $lock = stream_socket_server(self::ANY_LOCK_SOCK . $port, $errno, $errstr);
        if(!$lock) {
            throw new \RuntimeException($errstr, $errno);
        }
        $lockpid = 0;

        while($acp = stream_socket_accept($lock, -1)) {
            $this->readAccept($acp, $lockpid);
        }
    }

    public function aLock($port = 9088)
    {
        $errno = 0;
        $errstr = '';
        try {
            $alock = stream_socket_client(self::ANY_LOCK_SOCK . $port, $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT);
        } catch(\Exception $e) {
            return false;
        }
        return $this->sendLockMessage($alock, self::CMD_LOCK);
    }

    public function aUnlock($unlockId, $port = 9088)
    {
        try {
            $errno = 0;
            $errstr = '';
            $alock = stream_socket_client(self::ANY_LOCK_SOCK . $port, $errno, $errstr, 1, STREAM_CLIENT_ASYNC_CONNECT);
        } catch(\Exception $e) {
            return false;
        }
        return $this->sendLockMessage($alock, self::CMD_UNLOCK, $unlockId);
    }

    protected function readAccept($rs, &$lockpid)
    {
        $acp = $this->read($rs);

        list($cmd, $pid, $unlockId) = explode('|', $acp);
        if($cmd == self::CMD_LOCK) {
            if(!$lockpid) {
                $lockpid = uniqid($pid, true);
                $this->send($rs, self::CMD_SUCC . "|$lockpid");
            } elseif($unlockId != $lockpid) {
                $this->send($rs, self::CMD_FAIL);
            } else {
                $this->send($rs, self::CMD_ALREADY . "|$lockpid");
            }
        } elseif($cmd == self::CMD_UNLOCK) {
            if($pid == $unlockId) {
                $lockpid = 0;
                $this->send($rs, self::CMD_SUCC);
            } else {
                $this->send($rs, self::CMD_FAIL);
            }
        } else {
            $this->send($rs, self::CMD_UNKNOW);
        }
    }

    protected function lockAccept($key, $s)
    {
        $lockpid = 0;
        while(true) {
            $write = $except = [];
            $read = $s;
            $num = $this->select($key, $read, $write, $except, self::$SLEEP_USEC);
            if(!$num) {
                continue;
            }
            foreach($read as $rs) {
                $this->readAccept($rs, $lockpid);
            }
            usleep(self::$SLEEP_USEC);
        }
    }

    /**
     * init process lock, only in parent and child process
     *
     * <code>
     * $p = new Process;
     * $pid = $p->bloodLock(3);
     * if($pid > 0) {
     *      //after loop parent thend code
     * } else {
     *      //run 3 child thend
     *      $this->lock();
     *      $this->unlock();
     * }
     *
     * </code>
     *
     * @return type
     */
    public function bloodLock($childnum = 1)
    {
        $s = [];
        $key = uniqid(__FUNCTION__);
        $this->parentPid = $this->getpid();
        $channelSize = $childnum * 30;
        for($i = 0; $i < $childnum; $i++) {
            list($lock, $m) = $this->ipcChannel($key, $channelSize);
            if(($cpid = $this->fork()) === 0) {
                $this->sockpid($key, $lock);
                $this->lock = $lock;
                return 0;
            }
            $this->sockpid($key, $m, $cpid);
            $s[] = $m;
        }

        $this->lockAccept($key, $s);
        $this->destoryShm($key);
        return 1;
    }

    protected function sendLockMessage($lock, $type, $unlockId = '')
    {
        $this->setBlocking($lock, 1);
        $pid = $this->getpid();
        $this->send($lock, $type . "|$pid|$unlockId");
        $ret = $this->read($lock);
        $resArr = explode('|', $ret);
        if($resArr[0] == self::CMD_SUCC || $resArr[0] == self::CMD_ALREADY) {
            return isset($resArr[1]) ? $resArr[1] : true;
        }
        return false;
    }

    /**
     * get lock
     *
     * @return boolean
     */
    public function lock()
    {
        if(!is_resource($this->lock)) {
            throw new \RuntimeException('blood lock server not runing');
        }
        return $this->sendLockMessage($this->lock, self::CMD_LOCK);
    }

    /**
     * release lock
     *
     * @return boolean
     */
    public function unlock()
    {
        if(!is_resource($this->lock)) {
            throw new \RuntimeException('blood lock server not runing');
        }
        return $this->sendLockMessage($this->lock, self::CMD_UNLOCK);
    }

    /**
     * tell main process child quit
     *
     * @param resource $cport
     */
    protected function childClean($cport)
    {
        $this->childSock = $cport;
        register_shutdown_function(function () use ($cport) {
            $this->quit($cport);
        });
    }

    protected function waitMain($cport)
    {
        $this->childSock = null;
        $res = $this->read($cport);
        if($res == self::CMD_ALREADY) {
            $this->childClean($cport);
            return false;
        }
        return false;
    }

    protected function sockpid($key, &$sock, $pid = 0)
    {
        if(!$sock && $pid) {
            $sock = [$key, $pid];
        } else if(!$sock && $this->shmIpc) {
            $sock = [$key, $this->getpid()];
        }
    }

    /**
     * init specil number porcess, child prcess return false, parent return true
     *
     * @param int $number
     * @param resource $mport
     * @param resource $cport
     * @return boolean|int
     */
    protected function initMutiProcess($number, $mainId, $callable = null)
    {
        if(!is_callable($callable) && is_array($callable)) {
            if(count($callable) !== $number) {
                throw new \LengthException("callable number must eq #1 paramter value this is $number");
            }
            foreach($callable as $i => $func) {
                if(!is_callable($func)) {
                    throw new \InvalidArgumentException("passed #2 paramter of index $i is not callable");
                }
            }
        }
        $channelSize = $number * 30;
        for($i = 0; $i < $number; $i++) {
            list($this->mainSockPool[$mainId][$i], $cport) = $this->ipcChannel($mainId, $channelSize);
            $pid = $this->fork();
            if($pid > 0) {
                $this->myChildProcess[$mainId][$pid] = $i;
                $this->sockpid($mainId, $this->mainSockPool[$mainId][$i], $pid);
                continue;
            } else {
                $this->sockpid($mainId, $cport);
                $this->setBlocking($cport, 1);

                $waitStatus = $this->waitMain($cport);
                if(is_callable($callable)) {
                    return call_user_func($callable);
                } else if(is_array($callable)) {
                    return call_user_func($callable[$i]);
                }
                return $waitStatus;
            }
        }

        foreach($this->mainSockPool[$mainId] as $s) {
            $this->send($s, self::CMD_ALREADY);
        }

        return true;
    }

    /**
     * multi-process run until task exit
     *
     * <code>
     * $p = new Process();
     * $status = $p->multiProcess(10);
     * if($status) {
     *      //your parent process
     * } else {
     *      //your child process
     *      $p->quit();
     * }
     * </code>
     *
     * @param int $number   if the number equal 0 is child process,  like fork() return
     * @param $callable     child process will call function
     * @param $forkEndCallable      after all child process forked, pass $mainId and  $this
     * @param $loopCallable      main process loop call, pass $mainId and  $this
     * @return int      返回0时，表示当前为子进程，1时为父进程
     */
    public function multiProcess($number, $callable = null, $forkEndCallable = null, $loopCalllable = null)
    {

        $mainSockPoolId = uniqid(__FUNCTION__);
        $this->mainSockPool[$mainSockPoolId] = [];
        $this->parentPid = $this->getpid();
        if(!$this->initMutiProcess($number, $mainSockPoolId, $callable)) {
            return 0;
        }
        if($forkEndCallable) {
            try {
                $forkEndCallable($mainSockPoolId, $this);
            } catch(\Exception|\Error $e) {
                echo $e;
            }
        }
        $this->processLoop($mainSockPoolId, null, $loopCalllable);
        $this->wait();
        $this->destoryShm($mainSockPoolId);
        return 1;
    }

    protected function poolCall($mainId, $mport, $callable = null)
    {
        $acp = $this->read($mport);
        $value = 0;
        if($acp) {
            list($cmd, $pid) = explode('|', $acp);

            $this->send($mport, self::CMD_SUCC);
            $res = $this->wait($pid);
            if($res == $pid) {
                $exitIdx = $this->myChildProcess[$mainId][$pid];
                unset($this->myChildProcess[$mainId][$pid]);
                if(is_callable($callable)) {
                    return call_user_func($callable, $exitIdx);
                }
            }
        }
        return true;
    }

    private function select($key, &$read, &$write, &$except, $tv_usec = 0)
    {
        if($key && $this->shmIpc) {
            $update = [];
            $changeNum = 0;
            foreach($read as $pid) {
                if($this->shm[$key]->hasChange($pid[1])) {
                    $update[] = $pid;
                    $changeNum++;
                }
            }
            $read = $update;
            $update = [];
            foreach($write as $pid) {
                if($this->shm[$key]->hasChange($pid[1])) {
                    $update[] = $pid;
                    $changeNum++;
                }
            }
            $write = $update;
            $update = [];
            usleep($tv_usec);
            return $changeNum;
        }
        return stream_select($read, $write, $except, 0, $tv_usec);
    }

    private function processLoop($mainId, $callable = null, $loopCallable = null)
    {
        while(count($this->myChildProcess[$mainId])) {
            $write = $except = [];
            $read = $this->mainSockPool[$mainId];
            $num = $this->select($mainId, $read, $write, $except, self::$SLEEP_USEC);
            if(!$num) {
                usleep(self::$SLEEP_USEC);
                continue;
            }
            foreach($read as $rs) {
                $pid = $this->poolCall($mainId, $rs, $callable);
                if($pid === 0) {
                    return 0;
                }
            }
            if($loopCallable) {
                try {
                    $loopCallable($mainId, $this);
                } catch(\Exception | \Error $e) {
                    echo $e;
                }
            }
            usleep(self::$SLEEP_USEC);
        }
    }

    /**
     * keep specil number process is runing
     *
     * <code>
     * $p = new Process();
     * $status = $p->processPool(10);
     * if($status) {
     *      //your parent process
     * } else {
     *      //your child process
     *    $p->quit();
     * }
     * </code>
     *
     * @param int $number
     * @return int
     */
    public function processPool($number, $callable = null)
    {
        $mainSockPoolId = uniqid(__FUNCTION__);
        $this->mainSockPool[$mainSockPoolId] = [];
        $this->parentPid = $this->getpid();

        if(!$this->initMutiProcess($number, $mainSockPoolId, $callable)) {
            return 0;
        }
        $channelSize = $number * 200;
        if(!$this->processLoop($mainSockPoolId, function ($exitIdx) use ($mainSockPoolId, $callable, $channelSize) {
                list($nport, $cport) = $this->ipcChannel($mainSockPoolId, $channelSize);
                $npid = $this->fork();
                $this->sockpid($mainSockPoolId, $nport, $npid);
                if($npid == 0) {
                    $this->sockpid($mainSockPoolId, $cport);
                    $this->waitMain($cport);
                    if(is_callable($callable)) {
                        return call_user_func($callable);
                    } elseif(is_array($callable)) {
                        return call_user_func($callable[$exitIdx]);
                    }
                    return 0;
                }
                $this->send($nport, self::CMD_ALREADY);
                $this->mainSockPool[$mainSockPoolId][] = $nport;
                return $npid;
            })) {
            return 0;
        }
        $this->wait();
        return 1;
    }

    public function getpid()
    {
        return getmypid();
    }

    public function destoryShm($mid)
    {
        if($this->shmIpc) {
            $this->shm[$mid]->destroy();
        }
    }

    public function fork()
    {
        $pid = pcntl_fork();
        if($pid < 0) {
            throw new \RuntimeException('process fork fail');
        } elseif($pid == 0) {
            return 0;
        }
        $this->myChildProcess['fork'][$pid] = time();
        return $pid;
    }

    public function daemon()
    {
        if($this->fork() > 0) {
            exit;
        }
        if($this->fork() > 0) {
            exit;
        }

        chdir('/');
        umask('0');
        posix_setsid();
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        if($this->fork() > 0) {
            exit;
        }
    }

    public function kill($pid, $sig)
    {
        return posix_kill($pid, $sig);
    }

    public function wait($pid = 0, &$status = 0, $unblock = 0)
    {
        if($unblock) {
            $unblock = WNOHANG;
        }
        if($pid > 0) {
            return pcntl_waitpid($pid, $status, WUNTRACED | $unblock);
        }
        return pcntl_wait($status, WUNTRACED | $unblock);
    }

    /**
     * run a parent process and a child process, when a child process exit, start other child process
     * when the method return 0, then process is child's thead, 1 is return is parent's thead, parent
     * whill loop and call $exitLoopCallable until the function return $exitFlag value
     *
     * <code>
     * $p = new Process;
     * $res = $p->guardFork(function($sock) {
     *      sleep(10);
     *      return 'exit;
     * });
     * if($res >0) {
     *  //your parent's thead
     * } else {
     *  //your child's thead
     * }
     * </code>
     *
     * @param callable $exitLoopCallable
     * @param mix $exitFlag
     * @return int
     */
    public function guardFork($exitLoopCallable = null, $exitFlag = 'exit')
    {
        $mainId = uniqid(__FUNCTION__);
        $this->parentPid = $this->getpid();
        do {
            $pid = $this->fork();
            list($m, $c) = $this->ipcChannel($mainId, 30);
            if($pid == 0) {
                $this->sockpid($mainId, $c);
                $this->childSock = $c;
                return 0;
            }
            $this->sockpid($mainId, $m, $pid);
            do {
                $res = $this->wait($pid, $status, 1);

                if($exitLoopCallable && call_user_func($exitLoopCallable, $m) == $exitFlag) {
                    break 2;
                }
                if($res == $pid) {
                    break;
                }
                usleep(self::$SLEEP_USEC);
            } while(true);
            usleep(self::$SLEEP_USEC);
        } while(true);
        $this->destoryShm($mainId);
        return 1;
    }

    public function schedule()
    {
        $status = $this->guardFork(function ($sock) {
            $res = $this->read($sock);
            if($res == self::CMD_QUIT) {
                return 'exit';
            }
        });
        if($status) {
            $this->wait();
            return true;
        }

        $exculePool = [];
        while(true) {
            if(empty($this->scheduleTable)) {
                $this->send($this->childSock, self::CMD_QUIT);
                exit;
            }

            foreach($this->scheduleTable as $k => $task) {
                $pid = $this->execScheduleTask($task, $k);
                if($pid) {
                    $exculePool[$pid] = 1;
                }
                $this->waitPool($exculePool);
            }

            $this->waitPool($exculePool);
            Time::msleep(1);
        }
        $this->waitPool($exculePool, true);
    }

    public function waitPool(&$pool, $loop = false)
    {
        do {
            foreach($pool as $pid => $c) {
                $this->wait($pid, $status, 1);
                unset($pool[$pid]);
            }
            usleep(self::$SLEEP_USEC);
        } while($loop && count($pool));
    }

    protected function execScheduleTask($task, $k)
    {
        if(!is_numeric($task['startTime'])) {
            $startTime = strtotime($task['startTime']);
        } else {
            $startTime = $task['startTime'];
        }
        if($startTime != null && $startTime >= time()) {
            return false;
        }
        if(!is_numeric($task['endTime'])) {
            $endTime = strtotime($task['endTime']);
        } else {
            $endTime = $task['endTime'];
        }
        if($endTime != null && $endTime <= time()) {
            return false;
        }
        if($task['execTimes'] > $task['times']) {
            return false;
        }

        if($task['interval'] > 0 && ($task['lastExecTime'] + $task['interval']) > Time::millisecond()) {
            return false;
        }
        if(!is_numeric($task['interval'])) {
            $execTime = strtotime($task['interval']) * 1000;
            if($execTime > Time::millisecond() || $task['lastExecTime'] > $execTime) {
                return false;
            }
        }

        $this->scheduleTable[$k]['execTimes']++;
        $this->scheduleTable[$k]['lastExecTime'] = Time::millisecond();

        $pid = $this->fork();
        if($pid > 0) {
            return $pid;
        }
        call_user_func($task['func']);
        exit;
    }

    /**
     * add a schedule task
     *
     * @param callable $task    task function
     * @param mixed $interval   task run interval , the value is number, the iterval is $iterval millisecond,
     *                          if the value is string and suffix s,m,h,d,w, iterval is one times run after $interval
     *                          seconds, minutes, hours, days, weeks. other value will convert to current time of every day
     *                          this time is the task whill run
     * @param int $times        the task run times
     * @param mixed $start      the task first run time
     * @param mixed $end        the task last run time
     */
    public function addScheduleTask($task, $interval, $times = null, $start = null, $end = null)
    {
        if(!is_numeric($interval)) {
            $unit = strtolower(substr($interval, -1));
            $number = substr($interval, 0, -1);
            switch($unit) {
                case 's':
                    $interval = $number * 1000;
                    break;
                case 'm':
                    $interval = $number * 60000;
                    break;
                case 'h':
                    $interval = $number * 3600000;
                    break;
                case 'd':
                    $interval = $number * 86400000;
                    break;
                case 'w':
                    $interval = $number * 604800000;
                    break;
            }
        }
        $taskInfo = [];
        $taskInfo['func'] = $task;
        $taskInfo['startTime'] = $start;
        $taskInfo['endTime'] = $end;
        $taskInfo['lastExecTime'] = 0;
        $taskInfo['times'] = $times;
        $taskInfo['execTimes'] = 0;
        $taskInfo['interval'] = $interval;
        $this->scheduleTable[] = $taskInfo;
    }

    public function restart($mainId = 'fork')
    {
        foreach($this->myChildProcess[$mainId] as $pid => $time) {
            $this->kill($pid, SIGTERM);
            $this->wait($pid);
        }
        $args = $this->argv->getArg();
        $path = realpath($args[0]);
        array_shift($args);
        pcntl_exec($path, $args);
    }

    public static function destoryAllIpcShm()
    {
        ShareMemory::destroyAll(self::$SHM_IPC_FLAG);
    }

    public function __destruct()
    {
        if($this->shmIpc) {
            $curPid = $this->getpid();
            if($curPid == $this->parentPid) {
                self::destoryAllIpcShm();
            }
        }
    }

}
