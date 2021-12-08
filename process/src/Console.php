<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Process;

/**
 * Console
 *
 * @author chopin
 */
class Console
{

    /**
     * 清理命令行
     *
     * @param int $selfWidth
     * @return void
     */
    public static function clearTTYLine($selfWidth = null)
    {
        static $ttywidth;
        if(!$ttywidth && !$selfWidth) {
            list(, $ttywidth) = getTTYSize();
        } elseif($selfWidth) {
            $ttywidth = $selfWidth;
        }
        echo "\r" . str_repeat(' ', $ttywidth);
    }

    /**
     * 使用Wget命令下载文件
     *
     * @param string $url
     * @param string $output 保存到文件
     * @param array $opt    命令行选项
     * @return void
     */
    public static function wget(string $url, string $output, $opt = [])
    {
        $defOpt = [
            '-w' => 5,
            '-T' => 5,
            '-t' => 1,
            '-q',
            '-O' => $output,
        ];
        $url = escapeshellarg($url);
        if(isset($_ENV['WGET_USER_AGENT'])) {
            $defOpt['--user-agent'] = $_ENV['WGET_USER_AGENT'];
        }
        foreach($opt as $k => $v) {
            $defOpt[$k] = $v;
        }
        $option = '';
        foreach($defOpt as $k => $v) {
            $v = escapeshellarg($v);
            if(is_numeric($k)) {
                $option .= " $v";
            } elseif(strlen($k) > 2) {
                $option .= " $k=$v";
            } else {
                $option .= " $k $v";
            }
        }
        $returnVar = 0;
        passthru("wget $option $url", $returnVar);
        if(file_exists($output) && !filesize($output) && $returnVar) {
            trigger_error("wget has error and file($output) size is 0, Removed!", E_USER_WARNING);
            unlink($output);
        }
        return $returnVar;
    }

    public static function getTTYSize()
    {
        return explode(' ', exec('stty size'));
    }

    /**
     * 命令行下的多任务进度
     *
     * @param int $cur           当前任务已处理数据量
     * @param int $total         当前任务总数据量
     * @param int $totalTaskNum  总任务数    
     * @param int $taskIdx       当前任务索引数
     * @return void
     */
    public static function multitaskProgress($cur, $total, $totalTaskNum, $taskIdx)
    {
        $tabSize = 8;
        list(, $ttywidth) = getTTYSize();
        $tabNum = floor($ttywidth / $totalTaskNum / $tabSize);
        $fn = $cur / $total * 100;
        $p = 100 / ($tabSize * $tabNum);
        $maskNum = floor($fn / $p);
        $mask = str_repeat('=', $maskNum);
        $mod = $fn % $p;
        if($mod >= $p / 2 && $mod < $p) {
            $mask .= '-';
        }
        if($maskNum < ($totalTaskNum - 1)) {
            $mask .= ($cur % 2 == 0 ? '\\' : '/');
        }
        echo str_repeat("\t", $taskIdx * $tabNum) . $mask . "\r";
    }

}
