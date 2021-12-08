<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use Toknot\Type\Scalar;

class Char extends Scalar
{

    const NAME = 'string';

    public function __construct($int = '')
    {
        $this->value = (string) $int;
    }

    public function find($start, $end, $offset, &$findPos = 0)
    {
        return self::strFind($this->value, $start, $end, $offset);
    }

    public function feildVal(array $seplist = ['=', ':'], string &$field = '')
    {
        return self::getStrFieldValue($field, $seplist, $field);
    }

    public function hasPrefix($prefix)
    {
        return self::isPrefix($this->value, $prefix);
    }

    /**
     * 查找字符串,返回开始字符串与结束字符串之间的字符串，不包括起始与结束字符串
     *
     * @param string $content   在其中查找
     * @param array $start      开始字符串及其长度，值类似 array($开始字符串,$长度);
     * @param string $end       结束字符串
     * @param int $offset       指定查找偏移量，如果是负数，将以开始字符串$start最后出现的位置为起点
     * @param int $findPos      查找到的偏移量
     * @return string|bool      返回false即未找到
     */
    public static function strFind(string $content, $start, $end, $offset, &$findPos = 0)
    {
        if(is_string($start)) {
            $startlen = mb_strlen($start);
        } else {
            $startlen = $start[1];
            $start = $start[0];
        }
        if($offset < 0) {
            $startPos = mb_strrpos($content, $start, $offset);
        } else {
            $startPos = mb_strpos($content, $start, $offset);
        }
        if($startPos === false) {
            return false;
        }
        $endPos = mb_strpos($content, $end, $startPos + $startlen);
        if($endPos === false) {
            return false;
        }
        $findPos = $startPos;
        return trim(mb_substr($content, $startPos + $startlen, $endPos - $startPos - $startlen));
    }

    /**
     * 获取字符串
     *
     * @param string $str
     * @param array $seplist
     * @param string $field
     * @return void
     */
    public static function getStrFieldValue(string $str, array $seplist = ['=', ':'], string &$field = '')
    {
        $line = trim($str);
        $line = str_replace($seplist, '=', $line);
        parse_str($line, $result);
        if($field) {
            if(isset($result[$field])) {
                return trim($result[$field]);
            }
            return null;
        } else {
            $field = trim(key($result));
            return trim(current($result));
        }
    }

    public static function isPrefix($str, $prefix)
    {
        $prefix = is_scalar($prefix) ? [$prefix] : $prefix;
        foreach($prefix as $p) {
            if(strpos($str, $p) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function hasStr($str, $list = [])
    {
        foreach($list as $s) {
            if(strpos($str, $s) >= 0) {
                return true;
            }
        }
        return false;
    }

    public static function checkStrSuffix($str, $endStr)
    {
        $idx = strpos($str, $endStr);
        if((strlen($str) - $idx) === strlen($endStr)) {
            return true;
        }
        return false;
    }

    public static function isUpDomain($subDomain, $upDomain)
    {
        $subLvl = substr_count($subDomain, '.');
        $upLvl = substr_count($upDomain, '.');
        if($upLvl == $subLvl && $subDomain == $upDomain) {
            return 0;
        } elseif($upLvl < $subLvl && checkStrSuffix($subDomain, ".$upDomain")) {
            return 1;
        }
        return -1;
    }

    public static function strCountNumerOfLetter($str, $isnum)
    {
        $letter = $isnum ? range(0, 9) : range('A', 'Z');
        $count = 0;
        foreach($letter as $num) {
            $count += mb_substr_count($str, $num);
        }
        return $count;
    }

    public static function strEndPos($str, $needle)
    {
        return mb_strrpos($str, $needle) == (mb_strlen($str) - mb_strlen($needle));
    }

}
