<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Process;

use Toknot\Process\ShareMemory;

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

    public static $ESCAPE_CODE = "\033";
    public static $lastTime = 0;

    /**
     * 清理命令行
     *
     * @param int $selfWidth
     * @return void
     */
    public static function clearTTYLine($selfWidth = null)
    {
        static $ttywidth;
        if (!$ttywidth && !$selfWidth) {
            list(, $ttywidth) = self::getTTYSize();
        } elseif ($selfWidth) {
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
        if (isset($_ENV['WGET_USER_AGENT'])) {
            $defOpt['--user-agent'] = $_ENV['WGET_USER_AGENT'];
        }
        foreach ($opt as $k => $v) {
            $defOpt[$k] = $v;
        }
        $option = '';
        foreach ($defOpt as $k => $v) {
            $v = escapeshellarg($v);
            if (is_numeric($k)) {
                $option .= " $v";
            } elseif (strlen($k) > 2) {
                $option .= " $k=$v";
            } else {
                $option .= " $k $v";
            }
        }
        $returnVar = 0;
        passthru("wget $option $url", $returnVar);
        if (file_exists($output) && !filesize($output) && $returnVar) {
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
        list(, $ttywidth) = self::getTTYSize();
        $tabNum = floor($ttywidth / $totalTaskNum / $tabSize);
        $fn = $cur / $total * 100;
        $p = 100 / ($tabSize * $tabNum);
        $maskNum = floor($fn / $p);
        $mask = str_repeat('=', $maskNum);
        $mod = $fn % $p;
        if ($mod >= $p / 2 && $mod < $p) {
            $mask .= '-';
        }
        if ($maskNum < ($totalTaskNum - 1)) {
            $mask .= ($cur % 2 == 0 ? '\\' : '/');
        }
        echo str_repeat("\t", $taskIdx * $tabNum) . $mask . "\r";
    }

    /**
     * 
     * @param int $num
     * @return string
     */
    public static function tab($num)
    {
        return str_repeat("\t", $num);
    }

    /**
     * only support ANSI escape code/sequence terminal available
     * 
     * @param int $line
     * @return string
     */
    public static function cursorUp($line)
    {
        return self::$ESCAPE_CODE . "[{$line}A";
    }

    public static function cursorDown($line)
    {
        return self::$ESCAPE_CODE . "[{$line}B";
    }

    public static function cursorRight($num)
    {
        return self::$ESCAPE_CODE . "[{$num}C";
    }

    public static function cursorLeft($num)
    {
        return self::$ESCAPE_CODE . "[{$num}D";
    }

    public static function saveCursorPos()
    {
        echo self::$ESCAPE_CODE . '[s';
    }

    public static function restoreCursorPos()
    {
        echo self::$ESCAPE_CODE . '[u';
    }

    public static function multiLinePrint($total, $line, $msg)
    {
        if ($line == 1) {
            echo PHP_EOL;
            echo self::cursorUp($total);
        }
        $v = str_repeat("\v", $line - 1);
        echo "$v\r$msg";
    }

    public static function indentPrint(int $part, string $msg, $total = 1, $pIdx = 0)
    {
        static $partMsg = [];
        list(, $ttywidth) = self::getTTYSize();
        echo "\r";
        $wtab = floor(floor($ttywidth / 8) / $total);
        echo self::tab($wtab * $pIdx);
        $partMsg[$part] = $msg;
        ksort($partMsg);
        $preTab = 0;
        foreach ($partMsg as $k => $m) {
            if ($k === $part) {
                break;
            }
            $preTab += ceil(strlen($m) / 8);
        }
        echo self::tab($preTab) . $msg;
    }

    /**
     * 
     * @param string $string
     * @param int $style
     * @return string
     */
    public static function colorString($string, int $style)
    {
        $map = [
            self::STYLE_BLOD => 1,
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
            self::STYLE_BG_COLOR_WHITE => 47,
        ];
        $setVar = [];
        foreach ($map as $k => $v) {
            if ($style & $k) {
                $setVar[] = $v;
            }
        }
        return self::$ESCAPE_CODE . "[" . implode(';', $setVar) . "m$string" . self::$ESCAPE_CODE . "[0m";
    }

    public static function debugHrtime($flag)
    {
        $ct = hrtime(true);
        $t = round(($ct - self::$lastTime) / 1000000000, 4);
        echo "[$flag] CT:" . $ct . ' - UT:' . $t . PHP_EOL;
        self::$lastTime = $ct;
    }

    public static function cutPrintMsg($s)
    {
        list(, $width) = Console::getTTYSize();
        $len  = mb_strlen($s);
        $ow = 0;
        $ret = '';
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($s, $i, 1);
            strlen($char) > 1 ? $ow += 2 : $ow++;
            if ($ow > $width) {
                break;
            }
            $ret .= $char;
        }
        if ($ow < $width) {
            $ret .= str_repeat(' ', $width - $ow);
        }
        return $ret;
    }

    public static function mpShmMessage(ShareMemory $mainShm, array $childsShm)
    {
        do {
            $n = $mainShm->get('mp');
            echo "start process num:$n\n";
            usleep(100000);
        } while ($n < 2);
        while (true) {
            $msg = "";
            foreach ($childsShm as $i => $chm) {
                $msg .= self::cutPrintMsg($chm->get('p' . $i)) . PHP_EOL;
            }
            echo $msg;
            echo Console::cursorUp($n - 1);

            if ($mainShm->get('mp') < 2) {
                break;
            }
            sleep(1);
        }
        ShareMemory::destroyAll();
    }
}
