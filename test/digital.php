<?php

use Toknot\Math\Chinese;
use Toknot\Math\Byte;

include_once __DIR__.'/../autoload.php';
echo new Chinese(0, false, 2);
echo PHP_EOL;
echo new Chinese(100001274, true);

echo PHP_EOL;
echo Byte::toByte('832PB 23GB 3MB 3KB 224');
echo PHP_EOL;
Byte::$isZh = true;
var_dump(Byte::toHuman('21542121314', 2, ' '));

echo Byte::toByte('2GB 32MB') . PHP_EOL;