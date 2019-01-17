<?php
define('ROOT', dirname(__DIR__));
spl_autoload_register(function($class) {
    if(strpos($class, '\Toknot') === 0 || strpos($class, 'Toknot') === 0) {
        list(,$libname,$last) = explode('\\', $class, 3);
        $classFile = str_replace('\\', DIRECTORY_SEPARATOR, $last) . '.php';
        $path =  implode(DIRECTORY_SEPARATOR, [ROOT, strtolower($libname), 'src', $classFile]);
        include_once $path;
    }
});