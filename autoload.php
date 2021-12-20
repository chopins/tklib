<?php

define('HAVE_TKLIB_AUTOLOAD_' . md5(__FILE__), true);
spl_autoload_register(function ($class) {
    $classPathInfo = explode('\\', $class);
    if($classPathInfo[0] != 'Toknot')  {
        return;
    }
    $filePath = [__DIR__, strtolower($classPathInfo[1]), 'src'];
    array_shift($classPathInfo);
    array_shift($classPathInfo);
    $filePath = array_merge($filePath, $classPathInfo);
    $classFile = join(DIRECTORY_SEPARATOR, $filePath) . '.php';
    if(file_exists($classFile)) {
        return include($classFile);
    }
    
    if(isset($_SERVER['SCRIPT_FILENAME'])) {
        $pwd = dirname(dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
    } elseif(PHP_SAPI == 'cli' && isset($_SERVER['argv'])) {
        $pwd = dirname(dirname(realpath($_SERVER['argv'][0])));
    }
    array_shift($filePath);
    array_unshift($filePath, $pwd);
    $classFile = join(DIRECTORY_SEPARATOR, $filePath) . '.php';
    if(file_exists($classFile)) {
        return include($classFile);
    }
});

include 'lib.php';
