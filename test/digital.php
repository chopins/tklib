<?php

use Toknot\Digital\Chinese;
use Toknot\Digital\Byte;

include_once __DIR__.'/autoload.php';

echo new Chinese(1073741824, true);
echo PHP_EOL;
echo Byte::toByte('832PB 23GB 3MB 3KB 224');
echo PHP_EOL;
Byte::$isZh = true;
var_dump(Byte::toHuman('21542121314', 2, ' '));

echo Byte::toByte('2GB 32MB') . PHP_EOL;