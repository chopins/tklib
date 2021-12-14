<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Math;

class Math
{

    private static $mathFunc = [];
    private static $anyMath = false;

    const BASE_TABLE = [0 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 'a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15, 'g' => 16, 'h' => 17, 'i' => 18, 'j' => 19, 'k' => 20, 'l' => 21, 'm' => 22, 'n' => 23, 'o' => 24, 'p' => 25, 'q' => 26, 'r' => 27, 's' => 28, 't' => 29, 'u' => 30, 'v' => 31, 'w' => 32, 'x' => 33, 'y' => 34, 'z' => 35];

    private static $intMaxLen = [];

    protected static function checkParameter($op1, $op2)
    {
        if(!\is_numeric($op1) || !\is_numeric($op2)) {
            throw new \InvalidArgumentException("parameter (#1 $op1, #2$op2) must be numeric");
        }
    }

    /**
     * check give params whether is interger number or digital string
     *
     * @param string|int $op1
     * @return boolean
     */
    public static function isInteger($op1)
    {
        if(!is_numeric($op1)) {
            return false;
        }
        if(is_float($op1)) {
            return false;
        }
        if(strpos($op1, '.') !== false) {
            return false;
        }
        return true;
    }

    public static function eq($v1, $v2, $strict = false)
    {
        return $strict ? $v1 === $v2 : $v1 == $v2;
    }

    /**
     * check give aribitrary base number whether big than PHP_INT_MAX
     *
     * @param number  $number
     * @return boolean
     */
    public static function isBigInt($number, $base = 0)
    {
        $base = $base > 1 && $base < 36 ? $base : self::detectBase($number);
        $len = self::integerStrMaxLen($base);
        return strlen($number) > $len;
    }

    public static function integerStrMaxLen($base = 10)
    {
        if(!isset(self::$intMaxLen[$base])) {
            self::$intMaxLen[$base] = floor(PHP_INT_SIZE * 8 / log($base, 2));
        }
        return self::$intMaxLen[$base];
    }

    public static function comp($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('comp', 'cmp');
        if(!$f) {
            return $f($op1, $op2);
        }
        if($op1 == $op2) {
            return 0;
        } elseif($op1 > $op2) {
            return 1;
        } elseif($op1 < $op2) {
            return -1;
        }
    }

    /**
     * check use function, check order is gmp, bc
     *
     * @param string $func
     * @return boolean|string
     */
    public static function checkMathFunc($func, $gmpFunc = null)
    {
        if(!empty(self::$mathFunc[$func])) {
            return self::$mathFunc[$func];
        } elseif(isset(self::$mathFunc[$func]) && self::$mathFunc[$func] === false) {
            return false;
        }
        if(function_exists("bc{$func}")) {
            self::$mathFunc[$func] = "bc{$func}";
            return self::$mathFunc[$func];
        }

        if(function_exists("gmp_{$func}")) {
            self::$mathFunc[$func] = $gmpFunc === null ? "gmp_{$func}" : "gmp_{$gmpFunc}";
            return self::$mathFunc[$func];
        }
        self::$mathFunc[$func] = false;
        return false;
    }

    /**
     * product of $op1 and $op2
     *
     * @param number $op1
     * @param number $op2
     * @return number
     */
    public static function mul($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('mul');
        return $f === false ? $op1 * $op2 : $f($op1, $op2);
    }

    /**
     * detect give number string scale base
     *
     * @param string $number
     * @return int
     */
    public static function detectBase($number)
    {
        $prefix = substr($number, 0, 2);
        if($prefix == '0x') {
            return 16;
        } elseif($prefix == '0b') {
            return 2;
        } elseif(strpos($number, '0') === 0) {
            return 8;
        } else {
            $table = range('z', 'a');
            foreach($table as $i => $k) {
                if(strpos($number, $k) !== false) {
                    return 36 - $i;
                }
            }
            return 10;
        }
    }

    /**
     * sum of $op1 and $op2
     *
     * @param number $op1
     * @param number $op2
     * @return number
     */
    public static function add($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('add');
        if($f === false) {
            return self::operIsBig($op1, $op2) ? self::decAdd($op1, $op2) : $op1 + $op2;
        } else {
            return $f($op1, $op2);
        }
    }

    /**
     * difference of $op1 and $op2
     *
     * @param number $op1
     * @param number $op2
     * @return number
     */
    public static function sub($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('sub');
        if($f === false) {
            return self::operIsBig($op1, $op2) ? self::decSub($op1, $op2) : $op1 - $op2;
        } else {
            return $f($op1, $op2);
        }
    }

    /**
     * Quotient of $op1 and $op2
     *
     * @param number $op1
     * @param number $op2
     * @return number
     */
    public static function div($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('div');
        if($f == false) {
            return self::operIsBig($op1, $op2) ? self::decDiv($op1, $op2) : $op1 / $op2;
        }
        return $f($op1, $op2);
    }

    /**
     * Remainder of $op1 and $op2
     *
     * @param number $op1   only decimal hex
     * @param number $op2
     * @return number
     */
    public static function mod($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('mod');
        if($f === false) {
            return self::operIsBig($op1, $op2) ? self::decDiv($op1, $op2, $mod) : $op1 % $op2;
        }
        return $f($op1, $op2);
    }

    /**
     * Result of raising $op1 to the $op2'th power
     *
     * @param number $op1
     * @param number $op2
     * @return number
     */
    public static function pow($op1, $op2)
    {
        self::checkParameter($op1, $op2);
        $f = self::checkMathFunc('pow');
        return $f === false ? $op1 ** $op2 : $f($op1, $op2);
    }

    /**
     * Bits that are set in both $left and $right are set.
     *
     * @param number $left  only decimal and hexadecimal number support
     * @param number $right only decimal and hexadecimal number support
     * @return number
     */
    public static function andOp($left, $right)
    {
        self::checkParameter($left, $right);
        $f = self::checkMathFunc('and');
        $bignumber = $f || self::operIsBig($left, $right);
        if($f) {
            return $f($left, $right);
        } elseif($bignumber) {
            $len = self::bitLen($left, $right);
            return self::strBitOp($left, $right, $len, function ($l, $r) {
                    return $l & $r;
                });
        }
        return $left & $right;
    }

    /**
     * Bits that are set in either $left or $right are set.
     *
     * @param number $left  only decimal and hexadecimal number support
     * @param number $right only decimal and hexadecimal number support
     * @return number
     */
    public static function orOp($left, $right)
    {
        self::checkParameter($left, $right);
        $f = self::checkMathFunc('or');
        $bignumber = $f || self::operIsBig($left, $right);
        if($f) {
            return $f($left, $right);
        } elseif($bignumber) {
            $len = self::bitLen($left, $right);
            return self::strBitOp($left, $right, $len, function ($l, $r) {
                    return $l | $r;
                });
        }
        return $left | $right;
    }

    /**
     * set bit
     *
     * @param number $current   only decimal and hexadecimal number support
     * @param int $index
     * @param boolean $bitOn
     * @return number
     */
    public static function setbit($current, $index, $bitOn = true)
    {
        $f = self::checkMathFunc('setbit');
        $bignumber = $f || self::isBigInt($current);
        $bit = $bitOn ? '1' : '0';
        if($f) {
            $gmpNumber = gmp_init($current);
            return $f($gmpNumber, $bitIndex, $bitOn);
        } elseif($bignumber) {
            $current = self::decHex($current);
            $len = strlen($current);
            $charoffset = $index / 4 + 2; //0x is perfix so add 2
            $mod = $index % 4 - 1; //offset start 0, so sub 1
            $char = substr($current, $charoffset, 1);
            $bin = decbin(intval("0x$char", 16));
            $bin = str_repeat('0', 4 - strlen($bin)) . $bin;
            $setChar = dechex(intval('0b' . substr($bin, 0, $mod) . $bit . substr($bin, $mod + 1), 2));
            return substr($current, 0, $charoffset) . $setChar . substr($current, $charoffset + 1);
        }
        $bin = decbin($current);
        $left = strlen($bin) - $index;
        $setbin = substr($bin, 0, $left) . $bit . substr($bin, $left + 1);
        return intval('0b' . $setbin, 2);
    }

    /**
     * remove bit
     *
     * @param number $current
     * @param number $remove
     * @return number
     */
    public static function removebit($current, $remove)
    {
        $bignumber = $f || self::operIsBig($current, $remove);
        if(!$bignumber) {
            return ~(~$current | $remove);
        }
        $maxlen = self::integerStrMaxLen(16);
        $c = ltrim(self::decHex($current), '0x');
        $r = ltrim(self::decHex($remove), '0x');
        $len = self::bitLen($c, $r);
        $res = '';
        for($i = $maxlen; $i < $len; $i = $i + $maxlen) {
            $less = $len - $i;
            $sublen = $less > $maxlen ? $maxlen : $less;
            $op1 = substr($c, -$i, $sublen);
            $op2 = substr($r, -$i, $sublen);
            $res .= dechex(~(~intval("0x$op1", 16) | intval("0x$op2", 2)));
        }
        return $res;
    }

    protected static function operIsBig($op1, $op2)
    {
        return (self::isBigInt($op1) || self::isBigInt($op2));
    }

    /**
     * Arbitrary Precision decimal add
     *
     * @param number $op1   arbitrary decimal
     * @param number $op2   arbitrary decimal
     * @return number
     */
    protected static function decAdd($op1, $op2)
    {
        $op1 = (string) $op1;
        $op2 = (string) $op2;
        $maxlen = self::integerStrMaxLen() - 1;
        $c1 = strlen($op1);
        $c2 = strlen($op2);
        $looplen = $c1 > $c2 ? $c2 : $c1;
        $carry = 0;
        $res = '';
        for($i = $maxlen; $i < $looplen; $i = $i + $maxlen) {
            $less = $looplen - $i;
            $sublen = $less > $maxlen ? $maxlen : $less;
            $mop1 = substr($op1, -$i, $sublen);
            $mop2 = substr($op2, -$i, $sublen);
            $sum = $mop1 + $mop2 + $carry;
            $carry = substr($sum, -$maxlen, 1);
            if(!$carry) {
                $carry = 0;
                $sum = substr($sum, 1);
            }
            $res = $sum . $res;
        }
        return $res;
    }

    protected static function decSub($op1, $op2)
    {
        $op1 = (string) $op1;
        $op2 = (string) $op2;
        $maxlen = self::integerStrMaxLen() - 1;
        $c1 = strlen($op1);
        $c2 = strlen($op2);
        $looplen = $c1 > $c2 ? $c2 : $c1;
        $carry = 0;
        $res = '';
        for($i = $maxlen; $i < $looplen; $i = $i + $maxlen) {
            $less = $looplen - $i;
            $sublen = $less > $maxlen ? $maxlen : $less;
            $mop1 = substr($op1, -$i, $sublen);
            $mop2 = substr($op2, -$i, $sublen);
            $sub = $mop1 - $mop2 - $carry;
            if($sub >= 0) {
                $carry = 0;
            } else {
                $carry = 1;
                $sub = abs($sub);
            }
            $res = $sub . $res;
        }
        if($carry) {
            $res = '-' . $res;
        }

        return $res;
    }

    protected static function decDiv($op1, $op2, &$mod = 0)
    {
        $big = self::isBigInt($op2);
        if($big) {
            throw new \OutOfRangeException('unsupport divider is big integer, number char length must less than PHP_INT_MAX of length');
        }
        $op1 = (string) $op1;
        $op2 = (string) $op2;
        $c1 = strlen($op1);
        $c2 = strlen($op2);

        $maxlen = self::integerStrMaxLen() - 1;
        $check = true;
        $mod = '';
        $res = '';
        for($i = 0; $i < $c1; $i = $i + $maxlen) {
            $nextlen = $maxlen - strlen($mod) + 1; //at least add one number
            $mop1 = $mod . substr($op1, $i, $nextlen);
            $dot = $mop1 / $op2;
            $res .= floor($dot);
            $mod = $mop1 % $op2;
            if($mod == 0) {
                $mod == '';
            }
        }
        if($mod == '') {
            $mod = 0;
        }

        return $res . '.' . $dot;
    }

    /**
     * covert a ribitrary base number to a decimal number
     *
     * @param number $number    give number
     * @param int $base         give number bases
     * @return number
     */
    public static function base2dec($number, $base)
    {
        if($base > 36) {
            throw new \OutOfRangeException('base must less 37');
        }
        $maxlen = self::integerStrMaxLen($base);
        $len = strlen($number);
        if($maxlen >= $len) {
            return base_convert($number, $base, 10);
        }
        $sum = 0;
        for($i = 0; $i < $len; $i++) {
            $one = self::BASE_TABLE[$number[$i]] * ($base ** ($len - 1 - $i));
            $sum = self::decAdd($sum, $one);
        }
        return $sum;
    }

    /**
     * convert a aribitrary decimal number to aribitrary bases number
     *
     * @param integer $number
     * @param int $base   2 - 36
     * @return string
     */
    public static function dec2base($number, $base)
    {
        if($base > 36) {
            throw new \OutOfRangeException('base must less 37');
        }
        $len = self::isBigInt($number);
        if(!len) {
            return base_convert($number, 10, $base);
        }
        $res = '';
        $gnumber = $number;
        while(true) {
            $div = self::decDiv($gnumber, $base, $mod);
            $res .= self::BASE_TABLE[$mod];
            if($mod == $gnumber) {
                break;
            }
            $gnumber = $div;
        }
        return $res;
    }

    protected static function bitLen($left, $right)
    {
        $n1 = strlen($left);
        $n2 = strlen($right);
        return $n1 > $n2 ? $n1 : $n2;
    }

    /**
     * convert a aribitrary decimal to hexadecimal
     *
     * @param [type] $number
     * @return number
     */
    public static function decHex($number)
    {
        $base = self::detectBase($number);
        if($base == 16) {
            if(strpos($number, '0x') === false) {
                $number .= '0x' . $number;
            }
            return $number;
        }
        if($base === 10) {
            return '0x' . self::dec2base($number, 10);
        }
        throw new \InvalidArgumentException('give number is invaild decimal or hexadecimal');
    }

    protected static function strBitOp($left, $right, $len, $callable)
    {
        $left = self::decHex($left);
        $right = self::decHex($right);
        $maxlen = self::integerStrMaxLen(16);
        $llen = strlen($left) - 2;
        $rlen = strlen($right) - 2;
        //$leftr  = strrev($left);
        //$rightr = strrev($right);
        $resr = '';
        for($i = $maxlen; $i < $len; $i++) {
            $lless = $llen - $i;
            $rless = $rlen - $i;
            if($rless > $lless) {
                $subless = $lless;
            } else {
                $subless = $rless;
            }
            $sublen = $subless > $maxlen ? $maxlen : $subless;
            $leftr = $lless <= 0 ? 0 : substr($left, -$i, $sublen);
            $rightr = $lless <= 0 ? 0 : substr($left, -$i, $sublen);

            $resr .= $callable($leftr, $rightr);
        }
        return '0x' . ltrim(strrev($resr), '0');
    }

}
