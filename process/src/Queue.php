<?php

class Queue
{


    public function __construct() {}

    public function initPhpQueue($host, $port)
    {
        $sync = new \parallel\Sync();
        $run = new \parallel\Runtime();
        $run->run(function () use ($sync) {
            $queue = new SplQueue;
            while (true) {
                $value = $sync->get();
                $queue->enqueue($value);
            }
        });
        $run->run(function ($host, $port) use($sync) {
            $socket = stream_socket_server("tcp://$host:$port", $ecode, $emsg, STREAM_SERVER_LISTEN);
            if(!$socket) {
                exit;
            }
            stream_set_blocking($socket, 0);
            while(true) {
                $read = [$socket];
                $write = [$socket];
                $except = null;
                $num = stream_select($read, $write, $except, 0, 300000);
                if($num === false) {
                    continue;
                }
                foreach($read as $r) {

                }
                foreach($write as $w) {

                }
                $acp = stream_socket_accept($socket, 0);
            }
        }, [$host, $port]);
    }

    public function initReidsQueue() {}

    public function queueDataProcess() {}

    public function enqueueProcess() {}

    public function dequeueProcess() {}
}
