<?php

include 'lib.php';

spl_autoload_register(function ($class) {
    $classPathInfo = explode('\\', $class);
    $filePath = [__DIR__, strtolower($classPathInfo[1]), 'src'];
    array_shift($classPathInfo);
    array_shift($classPathInfo);
    $filePath = array_merge($filePath, $classPathInfo);
    $classFile = join(DIRECTORY_SEPARATOR, $filePath) . '.php';
    if (file_exists($classFile)) {
        include($classFile);
    }
});
