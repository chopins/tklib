<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use Toknot\Type\TextStaticAbstract;
use Toknot\Type\ReflectionFunction;
use RuntimeException;
use InvalidArgumentException;

class Text extends TextStaticAbstract implements \ArrayAccess
{

    const NAME = 'string';

    protected $wordSplitOffset = 0;
    protected $objNoMbstr = false;
    protected array $splitWordArray = [];
    protected int $splitWordOffset = 0;
    protected static $cacheFunc = [];

    public function __construct(string $str = '', bool $noMbstr = false)
    {
        $this->value = (string) $str;
        if ($noMbstr) {
            $this->objNoMbstr = false;
        } elseif (self::$mbEnable === null) {
            $this->objNoMbstr = extension_loaded('mbstring');
            self::$mbEnable = $this->objNoMbstr;
        } else {
            $this->objNoMbstr = self::$mbEnable;
        }
    }

    public static function fromFile($file)
    {
        return new static(file_get_contents($file));
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

    public function __call($name, $argv)
    {
        $func = parent::mayStringFuncName($name, $this->objNoMbstr);
        $realArg = $this->internalStrFuncObjCallOrder($func, $argv);
        return $func(...$realArg);
    }

    protected function internalStrFuncObjCallOrder($func, $argv)
    {
        if (isset(self::$cacheFunc[$func])) {
            if (self::$cacheFunc[$func] < 0) {
                $this->undefinedMethod($func);
            }
            $realArg = [];
            if(self::$cacheFunc[$func] == 0) {
                array_unshift($argv, $this->value);
                return $argv;
            }
            foreach ($argv as $i => $v) {
                if ($i === self::$cacheFunc[$func]) {
                    $realArg[] = $this->value;
                }
                $realArg[] = $v;
            }
            return $realArg;
        }
        $ref = new ReflectionFunction($func);
        if (!$ref->isInternal()) {
            self::$cacheFunc[$func] = -1;
            $this->undefinedMethod($func);
        }

        $findStr = $inserted = false;
        $vn = ['subject', 'string', 'str', 'haystack'];
        foreach ($vn as $n) {
            if (($inserted = $ref->hasParamName($n)) !== false) {
                break;
            }
        }

        if ($inserted === 0) {
            array_unshift($argv, $this->value);
        } else {
            $realArg = [];
            foreach ($argv as $i => $v) {
                if ($i === $inserted) {
                    $realArg[] = $this->value;
                }
                $realArg[] = $v;
            }
            $argv = $realArg;
        }

        if ($inserted === false && $ref->hasType(0, 'string')) {
            self::$cacheFunc[$func] = -1;
            $this->undefinedMethod($func);
        }
        if ($inserted === false) {
            $inserted = 0;
            array_unshift($argv, $this->value);
        }
        self::$cacheFunc[$func] = $inserted;
        return $argv;
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
        if (is_string($start)) {
            $startlen = mb_strlen($start);
        } else {
            $startlen = $start[1];
            $start = $start[0];
        }
        if ($offset < 0) {
            $startPos = mb_strrpos($content, $start, $offset);
        } else {
            $startPos = mb_strpos($content, $start, $offset);
        }
        if ($startPos === false) {
            return false;
        }
        $endPos = mb_strpos($content, $end, $startPos + $startlen);
        if ($endPos === false) {
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
        if ($field) {
            if (isset($result[$field])) {
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
        foreach ($prefix as $p) {
            if (strpos($str, $p) === 0) {
                return true;
            }
        }
        return false;
    }

    public static function hasStr($str, $list = [])
    {
        foreach ($list as $s) {
            if (strpos($str, $s) >= 0) {
                return true;
            }
        }
        return false;
    }

    public static function checkStrSuffix($str, $endStr)
    {
        $idx = strpos($str, $endStr);
        if ((strlen($str) - $idx) === strlen($endStr)) {
            return true;
        }
        return false;
    }

    public static function isUpDomain($subDomain, $upDomain)
    {
        $subLvl = substr_count($subDomain, '.');
        $upLvl = substr_count($upDomain, '.');
        if ($upLvl == $subLvl && $subDomain == $upDomain) {
            return 0;
        } elseif ($upLvl < $subLvl && checkStrSuffix($subDomain, ".$upDomain")) {
            return 1;
        }
        return -1;
    }

    public static function strCountNumerOfLetter($str, $isnum)
    {
        $letter = $isnum ? range(0, 9) : range('A', 'Z');
        $count = 0;
        foreach ($letter as $num) {
            $count += mb_substr_count($str, $num);
        }
        return $count;
    }

    public static function strEndPos($str, $needle)
    {
        return mb_strrpos($str, $needle) == (mb_strlen($str) - mb_strlen($needle));
    }

    public function __toString()
    {
        return $this->value;
    }

    public function getIterator()
    {
        if (!$this->splitWordArray) {
            $this->initWordSplit();
        }
        return $this->splitWordArray;
    }

    protected function invalidIdx($idx)
    {
        if (!is_int($idx)) {
            throw new InvalidArgumentException('paramter #1 $idx need int');
        }
    }

    public function offsetExists($idx): bool
    {
        $this->invalidIdx($idx);
        return $idx >= 0 && $idx < mb_strlen($this->value);
    }

    public function offsetGet($idx)
    {
        $this->invalidIdx($idx);
        if ($this->splitWordArray) {
            return $this->splitWordArray[$idx];
        }
        return $this->value[$idx];
    }

    public function offsetSet($idx, $value): void
    {
        $this->invalidIdx($idx);
        if (is_scalar($value)) {
            throw new InvalidArgumentException('paramter #2 $value need scalar');
        }
        $this->value = mb_substr($this->value, 0, $idx) . $value . mb_substr($this->value, $idx + 1);
    }

    public function offsetUnset($idx): void
    {
        $this->invalidIdx($idx);
        $this->value = mb_substr($this->value, 0, $idx) . mb_substr($this->value, $idx + 1);
    }

    public function sortSubPermutationCount(array $p1, array $p2)
    {
        $res = $p2[1] - $p1[1];
        if ($res === 0) {
            $res = $p2[2] - $p1[2];
        }
        return $res;
    }

    protected function firstCharWordSplit(array $strArr, int $pos): array
    {
        $permutationCount = $this->firstSubPermutationCount($strArr, $pos);
        uasort($permutationCount, [$this, 'sortSubPermutationCount']);
        $lsl = $lc = 0;
        foreach ($permutationCount as $i => $c) {
            if ($c[1] <= $lc && $c[2] < $lsl) {
                unset($permutationCount[$i]);
            } else {
                $lsl = $c[2];
                $lc = $c[1];
            }
            if (empty($c)) {
                unset($permutationCount[$i]);
            }
        }
        return $permutationCount;
    }

    public function initWordSplit()
    {
        $this->splitWordArray = $this->split();
    }

    public function wordSplit(string $str): array
    {
        $strArr = parent::split($str);
        $perm = [];
        $len = 0;
        do {
            $perm = array_merge($perm, $this->firstCharWordSplit($strArr, $len));
            array_shift($strArr);
            $len++;
        } while (count($strArr) > 1);
        $this->wordSplitOffset += $len;
        $permSize = count($perm);
        for ($i = 0; $i < $permSize; $i++) {
            if (empty($perm[$i])) {
                continue;
            }
            if ($i + $i > $permSize) {
                break;
            }
            $p = $perm[$i];
            for ($j = $i + 1; $j < $permSize; $j++) {
                if (empty($perm[$j])) {
                    continue;
                }
                if ($p[1] >= $perm[$j][1] && ($p[3] + $p[2]) >= ($perm[$j][2] + $perm[$j][3])) {
                    unset($perm[$j]);
                }
            }
        }
        $collect = [];
        foreach ($perm as $pc) {
            $collect[$pc[3]][] = [$pc[0], $pc[1]];
        }

        return $collect;
    }

    /**
     * 子字符串各排列出现次数
     * 
     * @param string|array $str
     * @return array
     */
    public function subPermutationCount(string $str)
    {
        $strArr = is_array($str) ? $str : parent::split($str);
        $perm = [];
        $pos = 0;
        do {
            $perm[] = $this->firstSubPermutationCount($strArr, $pos);
            array_shift($strArr);
            $pos++;
        } while (count($strArr) > 1);
        return $perm;
    }

    /**
     * 首字排列出现次数
     * 
     * @param array $strArr
     * @param int   $pos
     * @return array           array(组合字符串, 次数, 组合长度)
     */
    public function firstSubPermutationCount(array $strArr, int $pos): array
    {
        $strLen = count($strArr);
        $ks = '';
        $permutationCount = [];
        for ($s = 0; $s < $strLen; $s++) {
            $ks = $ks . $strArr[$s];
            $permutationCount[$s] = [$ks, 0, $s + 1, $pos];
        }
        array_shift($permutationCount);
        $len = $this->len();

        $first = $strArr[0];
        $i = $this->wordSplitOffset;
        while ($i < $len) {
            if ($first !== $this->splitWordArray[$i]) {
                $i++;
                continue;
            }
            $cs = $this->splitWordArray[$i];
            $j = 1;

            foreach ($permutationCount as $ns => &$sc) {
                $k = $i + $j;
                if ($k >= $len) {
                    break;
                }
                $cs .= $this->splitWordArray[$k];
                if ($sc[0] === $cs) {
                    $sc[1]++;
                } else {
                    $i = $k;
                    break;
                }
                $j++;
            }
            $i++;
        }
        return $permutationCount;
    }
}
