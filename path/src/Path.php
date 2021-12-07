<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

use Toknot\Math\Number;

class Path
{

    /**
     * join string to path
     * 
     * @param string $path1
     * @return string
     */
    public static function join(...$paths)
    {
        return DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths);
    }

    /**
     * generate the path realpath use given root path
     * 
     * @param string $path
     * @param string $cwd
     * @return string
     */
    public static function realpath($path, $cwd = '')
    {
        if(empty($cwd)) {
            $cwd = getcwd();
        }
        if($path[0] == '/') {
            return $path;
        }

        if(preg_match('/^[a-z]:\\\//i', $path)) {
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
    public static function rmdir($folder, $recursion = false)
    {
        if($recursion === false) {
            return rmdir($folder);
        }
        $dir = rtrim($folder, DIRECTORY_SEPARATOR);
        return self::dirWalk($dir, 'unlink', 'rmdir');
    }

    /**
     * apply user supplied function to every number of folder dir and file
     * 
     * @param string $dir
     * @param callable $callable        opreate file    smaliar : function($path) {}
     * @param callable $dirCallable     opreate dir
     * @param boolean $skipRoot         whehter skip top directory, default not skip
     */
    public static function dirWalk($dir, $callable, $dirCallable = null, $skipRoot = false)
    {
        $d = dir($dir);
        while(false !== ($enter = $d->read())) {
            if($enter == '.' || $enter == '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $enter;
            if(is_dir($path)) {
                self::dirWalk($path, $callable, $dirCallable);
            } elseif($callable) {
                $callable($path);
            }
        }
        if($dirCallable && !$skipRoot) {
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
    public static function randPath($dir, $filePrefix = '', $ext = '')
    {
        $char = md5(uniqid($filePrefix, true));
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filePrefix . $char . $ext;
    }

    public static function getDayPath($dir, $prefix = '', $ext = '')
    {
        $char = date('Y-m-d');
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . $char . $ext;
    }

    public static function getTimePath($dir, $prefix = '', $ext = '')
    {
        $char = time();
        return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $prefix . $char . $ext;
    }

    public static function chmod($path, $perms)
    {
        $valueErrorMessage = 'paramter #2($perms) only int or is [ugoa...][[+-=...][rwxXst...]...] format';
        try {
            $perm = new Number($perms);
            return chmod($path, $this->value);
        } catch(\ValueError $ex) {
            if(!preg_match('/^[ugoa+-=rwxXst]+$/', $perms, $m)) {
                throw new \ValueError($valueErrorMessage);
            }
        }
        $dtpl = ['user' => [], 'mask' => []];
        $operms = $fperms = fileperms($path);
        $u = ['u' => 6, 'g' => 3, 'o' => 0, 'a' => -1];
        $p = ['+', '-', '='];
        $m = ['r' => 2, 'w' => 1, 'x' => 0, 'X' => 0, 's' => ['u' => 11, 'g' => 10], 't' => 9];
        $len = strlen($perms);
        $chunk = [$dtpl];
        $eqperms = $k = $uidx = $pidx = 0;
        $j = 0;
        $spuser = false;
        for($i = 0; $i < $len; $i++) {
            $bit = $perms[$i];
            if(isset($u[$bit])) {
                if($k != 0 || ($k != 1 && $uidx != 1)) {
                    throw new \ValueError($valueErrorMessage);
                }
                if($bit == 'a') {
                    $chunk[$j]['user'] = ['u' => $u['u'], 'g' => $u['g'], 'o' => $u['o']];
                } else {
                    $chunk[$j]['user'][$bit] = $u[$bit];
                }
                $spuser = true;
                $uidx++;
            } elseif(in_array($bit, $p)) {
                if($k != $uidx) {
                    throw new \ValueError($valueErrorMessage);
                }
                if($k == 0) {
                    $chunk[$j]['user'] = ['u' => $u['u'], 'g' => $u['g'], 'o' => $u['o']];
                }
                $pidx = $k;
                $chunk[$j]['op'] = $bit;
            } elseif(isset($m[$bit])) {
                if($k == 0 || $k > $pidx) {
                    throw new \ValueError($valueErrorMessage);
                }
                if($bit != 'X' || ($bit == 'X' && ($operms & 1 || $operms & 8 || $operms & 64))) {
                    $chunk[$j]['mask'][$bit] = $m[$bit];
                }
            }
            $k++;
            if($bit == ';') {
                $j++;
                $chunk[$j] = $dtpl;
                $k = $uidx = $pidx = 0;
            }
        }
        foreach($chunk as $seg) {
            foreach($seg['mask'] as $bn => $b) {
                foreach($seg['user'] as $un => $us1) {
                    if($bn == 's') {
                        if($un == 'o') {
                            continue;
                        }
                        $maskBit = 1 < $b[$un];
                    } elseif($bn == 't') {
                        $maskBit = 1 << $b;
                    } else {
                        $maskBit = 1 < ($b + $us1);
                    }
                    if($seg['op'] == '+' && ($spuser || (!$spuser && $bn != 'w'))) {
                        $fperms = $fperms & $maskBit;
                    } elseif($seg['op'] == '-' && !($fperms & $maskBit) && ($spuser || (!$spuser && $bn != 'w'))) {
                        $fperms = $fperms ^ $maskBit;
                    } elseif($seg['op'] == '=' && ($spuser || (!$spuser && $bn != 'w'))) {
                        $eqperms = $eqperms & $maskBit;
                    }
                }
            }
        }
        if($eqperms) {
            $fperms = $eqperms;
        }
        return chmod($path, $fperms);
    }

    /**
     * same as __FILE__
     * 
     * @return string
     */
    public static function getFilePath()
    {
        $trace = debug_backtrace(0, 1);
        return $trace[0]['file'];
    }

    /**
     * Same as __DIR__
     * 
     * @return string
     */
    public static function getFileDir()
    {
        $trace = debug_backtrace(0, 1);
        return dirname($trace[0]['file']);
    }

}
