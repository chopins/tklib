<?php
use Toknot\Date\Nongli;

include_once __DIR__.'/autoload.php';

$nongli = new Nongli;

$date = '1900-03-20';
var_dump($date);
var_dump($nongli->getDay($date, true)['nl']);