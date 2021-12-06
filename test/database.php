<?php
use Toknot\Database\DB;
include_once __DIR__.'/../autoload.php';
DB::$forceFlushDatabaseCache = true;
$dns = 'mysql:unix_socket=/var/lib/mysql/mysql.sock;dbname=test;charset=utf8';
$dns = 'mysql:host=127.0.0.1;port=3306;dbname=test;charset=utf8';
$opt = [ DB::DB_ATTR_TABLE_PREFIX => 'c_'];
$cache = TEMP_DIR .'/database';
$db = new DB($dns, $cache, 'root', null, $opt);

$m = $db->table('coupon', 9);
var_dump($m->count());
var_dump($m->id);
$nm = $m->findOne(['coupon_code' => '5D1D7190249C0', 'type' => 2]);
$nm->value = 1;
$nm->type = ['=', 2];
$res = $nm->save();
var_dump($res);
var_dump($nm->id);

$m3 = $db->table('coupon');
$m3->type =['>', 0];
$m3->id =['>', 4];
$arr = $m3->findAll('fee_defray=1');

