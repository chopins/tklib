<?php

define('HAVE_TKLIB_AUTOLOAD_' . md5(__FILE__), true);
spl_autoload_register(function ($class) {
    $classPathInfo = explode('\\', $class);
    $filePath = [__DIR__, strtolower($classPathInfo[1]), 'src'];
    array_shift($classPathInfo);
    array_shift($classPathInfo);
    $filePath = array_merge($filePath, $classPathInfo);
    $classFile = join(DIRECTORY_SEPARATOR, $filePath) . '.php';
    if(file_exists($classFile)) {
        include($classFile);
    }
});

include 'lib.php';
