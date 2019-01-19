<?php
use Toknot\Date\Nongli;

include_once __DIR__.'/autoload.php';

$nongli = new Nongli;

$date = '2020-07-06';
var_dump($date);
var_dump($nongli->getDay($date));