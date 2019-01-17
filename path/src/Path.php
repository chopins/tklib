<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

class Path {

    /**
     * join string to path
     * 
     * @param string $path1
     * @return string
     */
    public static function join(...$paths) {
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths);
    }

    /**
     * generate the path realpath use given root path
     * 
     * @param string $path
     * @param string $cwd
     * @return string
     */
    public static function realpath($path, $cwd = '') {
        if (empty($cwd)) {
            $cwd = getcwd();
        }
        if ($path{0} == '/') {
            return $path;
        }

        if (preg_match('/^[a-z]:\\\//i', $path)) {
            return $path;
        }

        $relative = rtrim($cwd, DIRECTORY_SEPARATOR);
        return $relative . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * remove a dir, if set recursion will remove sub dir and file
     * 
     * @param string $folder
     * @param boolean $recursion
     * @return boolean
     */
    public static function rmdir($folder, $recursion = false) {
        if ($recursion === false) {
            return rmdir($folder);
        }
        $dir = rtrim($folder, DIRECTORY_SEPARATOR);
        return self::dirWalk($dir, 'unlink', 'rmdir');
    }

    /**
     * apply user supplied function to every number of folder dir and file
     * 
     * @param string $dir
     * @param callable $callable        opreate file
     * @param callable $dirCallable     opreate dir
     */
    public static function dirWalk($dir, $callable, $dirCallable = null) {
        $d = dir($dir);
        while (false !== ($enter = $d->read())) {
            if ($enter == '.' || $enter == '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $enter;
            if (is_dir($path)) {
                self::dirWalk($path, $callable, $dirCallable);
            } else {
                $callable($path);
            }
        }
        if ($dirCallable) {
            return $dirCallable($dir);
        }
        return true;
    }

    /**
     * generator a random path
     * 
     * @param string $dir
     * @param string $filePrefix
     * @param string $ext
     * @return string
     */
    public static function randPath($dir, $filePrefix = '', $ext = '') {
        $char = md5(uniqid($filePrefix, true));
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filePrefix . $char . $ext;
    }

    public static function getDayPath($dir, $prefix = '', $ext = '') {
        $char = date('Y-m-d');
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . $char . $ext;
    }

    public static function getTimePath($dir, $prefix = '', $ext = '') {
        $char = time();
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . $char . $ext;
    }

}
