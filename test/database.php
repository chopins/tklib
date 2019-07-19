<?php
use Toknot\Database\DB;
include_once __DIR__.'/autoload.php';
DB::$forceFlushDatabaseCache = true;
$dns = 'mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=test;charset=utf8';
$opt = [DB::DB_ATTR_CACHE_DIR => TEMP_DIR .'/database', DB::DB_ATTR_TABLE_PREFIX => 'c_'];
$db = new DB($dns, 'root', null, $opt);