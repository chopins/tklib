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

    const STYLE_BLOD = 1;
    const STYLE_UNDERLINE = 2;
    const STYLE_BLINK = 4;
    const STYLE_FB_SWAP = 8;
    const STYLE_COLOR_BLACK = 16;
    const STYLE_COLOR_RED = 32;
    const STYLE_COLOR_GREEN = 64;
    const STYLE_COLOR_YELLOW = 128;
    const STYLE_COLOR_BLUE = 256;
    const STYLE_COLOR_PURPLE = 512;
    const STYLE_COLOR_TEAL = 1024;
    const STYLE_COLOR_WHITE = 2048;
    const STYLE_BG_COLOR_BLACK = 1 << 12;
    const STYLE_BG_COLOR_RED = 2 << 12;
    const STYLE_BG_COLOR_GREEN = 4 << 12;
    const STYLE_BG_COLOR_YELLOW = 8 << 12;
    const STYLE_BG_COLOR_BLUE = 16 << 12;
    const STYLE_BG_COLOR_PURPLE = 32 << 12;
    const STYLE_BG_COLOR_TEAL = 64 << 12;
    const STYLE_BG_COLOR_WHITE = 128 << 12;

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

    public static function tab($num)
    {
        return str_repeat("\t", $num);
    }

    /**
     * only support ANSI escape code/sequence terminal available
     * 
     * @param int $line
     */
    public static function backLine($line)
    {
        return "\033[{$line}A";
    }

    public static function colorString($string, int $style = 0)
    {
        $map = [self::STYLE_BLOD => 1,
            self::STYLE_UNDERLINE => 4,
            self::STYLE_BLINK => 5,
            self::STYLE_FB_SWAP => 7,
            self::STYLE_COLOR_BLACK => 30,
            self::STYLE_COLOR_RED => 31,
            self::STYLE_COLOR_GREEN => 32,
            self::STYLE_COLOR_YELLOW => 33,
            self::STYLE_COLOR_BLUE => 34,
            self::STYLE_COLOR_PURPLE => 35,
            self::STYLE_COLOR_TEAL => 36,
            self::STYLE_COLOR_WHITE => 37,
            self::STYLE_BG_COLOR_BLACK => 40,
            self::STYLE_BG_COLOR_RED => 41,
            self::STYLE_BG_COLOR_GREEN => 42,
            self::STYLE_BG_COLOR_YELLOW => 43,
            self::STYLE_BG_COLOR_BLUE => 44,
            self::STYLE_BG_COLOR_PURPLE => 45,
            self::STYLE_BG_COLOR_TEAL => 46,
            self::STYLE_BG_COLOR_WHITE => 47];
        $setVar = [];
        foreach($map as $k => $v) {
            if($style & $k) {
                $setVar[] = $v;
            }
        }
        return "\033[" . implode(',', $setVar) . "m$string\033[0m";
    }

}
