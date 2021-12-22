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
use RuntimeException;

class Text extends Scalar implements \Iterator, \ArrayAccess
{

    const NAME = 'string';

    protected $offset = 0;
    private static $mbEnable = null;
    public static $noMbstr = false;
    protected $objNoMbstr = false;
    protected static $cacheFunc = [];
    protected static $standardFuncCache = [];

    public function __construct(string $str = '', bool $noMbstr = false)
    {
        $this->value = (string) $str;
        if($noMbstr) {
            $this->objNoMbstr = false;
        } elseif(self::$mbEnable === null) {
            $this->objNoMbstr = extension_loaded('mbstring');
            self::$mbEnable = $this->objNoMbstr;
        } else {
            $this->objNoMbstr = self::$mbEnable;
        }
    }

    public static function checkMbstring()
    {
        if(self::$noMbstr) {
            self::$mbEnable = false;
        }
        if(self::$mbEnable !== null) {
            return;
        }
        self::$mbEnable = extension_loaded('mbstring');
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

    public function __callStatic($name, $argv)
    {
        self::checkMbstring();
        $func = self::mayFuncName($name, self::$mbEnable);
        return $func(...$argv);
    }

    public function __call($name, $argv)
    {
        $func = self::mayFuncName($name, $this->objNoMbstr);
        $realArg = $this->internalStrFuncCallOrder($func, $argv);
        return $func(...$realArg);
    }

    protected function undefinedMethod($name)
    {
        $class = __CLASS__;
        throw new BadMethodCallException("Undefined $class::$name()");
    }

    protected function internalStrFuncCallOrder($func, $argv)
    {
        if(isset(self::$cacheFunc[$func])) {
            if(self::$cacheFunc[$func] < 0) {
                $this->undefinedMethod($func);
            }
            $realArg = [];
            $realArg[self::$cacheFunc[$func]] = $this->value;
            $k = 0;
            foreach($argv as $i => $v) {
                if(!isset($realArg[$i])) {
                    $realArg[$k] = $v;
                } else {
                    $k++;
                }
                $k++;
            }
            return $realArg;
        }
        $ref = new \ReflectionFunction($func);
        if(!$ref->isInternal()) {
            self::$cacheFunc[$func] = -1;
            $this->undefinedMethod($func);
        }

        $vn = ['subject', 'string', 'str', 'haystack'];
        $findStr = $inserted = false;
        $realArgv = [];
        $k = 0;
        foreach($ref->getParameters() as $i => $p) {
            $name = $p->getName();
            $type = $p->getType();
            if(in_array($name, $vn)) {
                $realArgv[$k] = $this->value;
                $k++;
                $inserted = $i;
            } else {
                $realArgv[$k] = $argv[$i];
            }
            if(strpos((string) $type, 'string') !== false) {
                $findStr = $i;
            }
            $k++;
        }
        if($findStr === false || $findStr !== 0) {
            self::$cacheFunc[$func] = -1;
            $this->undefinedMethod($func);
        }
        if($inserted === false) {
            $inserted = 0;
            array_unshift($argv, $this->value);
        }
        self::$cacheFunc[$func] = $inserted;
        return $realArgv;
    }

    protected static function mayFuncName($name, $mbstatus)
    {
        $supportName = ['ord', 'sub' => 'substr', 'subCount' => 'substr_count', 'split' => 'str_split'];
        if($mbstatus) {
            if(isset($supportName[$name])) {
                return "mb_{$supportName[$name]}";
            }
            if(function_exists("mb_str$name")) {
                return "mb_str$name";
            }
        }
        if(isset($supportName[$name])) {
            return $supportName[$name];
        }
        $nn = preg_replace('([A-Z])', '_$1', $name);
        if(function_exists("str_$nn")) {
            return "str_$nn";
        } else if(function_exists("str$nn")) {
            return "str$nn";
        }
        if(!self::$standardFuncCache) {
            self::$standardFuncCache = get_extension_funcs('standard');
        }
        if(in_array($name, self::$standardFuncCache)) {
            $this->undefinedMethod($name);
        }
        return $name;
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

    public function __toString()
    {
        return $this->value;
    }

    protected function invalidIdx($idx)
    {
        if(!is_int($idx)) {
            throw new InvalidArgumentException('paramter #1 $idx need int');
        }
    }

    public function offsetExists($idx)
    {
        $this->invalidIdx($idx);
        return $idx >= 0 && $idx < mb_strlen($this->value);
    }

    public function offsetGet($idx)
    {
        $this->invalidIdx($idx);
        return $this->value[$idx];
    }

    public function offsetSet($idx, $value)
    {
        $this->invalidIdx($idx);
        if(is_scalar($value)) {
            throw new InvalidArgumentException('paramter #2 $value need scalar');
        }
        $this->value = mb_substr($this->value, 0, $idx) . $value . mb_substr($this->value, $idx + 1);
    }

    public function offsetUnset($idx)
    {
        $this->invalidIdx($idx);
        $this->value = mb_substr($this->value, 0, $idx) . mb_substr($this->value, $idx + 1);
    }

    public function current()
    {
        return $this->value[$this->offset];
    }

    public function key()
    {
        return $this->offset;
    }

    public function next()
    {
        $this->offset++;
    }

    public function rewind()
    {
        $this->offset = 0;
    }

    public function valid()
    {
        return $this->offsetExists($this->offset);
    }

}
