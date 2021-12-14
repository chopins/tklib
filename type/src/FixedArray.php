<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use Toknot\Math\Math;

/**
 * FixedArray
 *
 * @author chopin
 */
class FixedArray extends \SplFixedArray
{

    /**
     * 支持类似PHP字符串转义规则，除单双引号反斜线转义外，还支持转义的字符有\s\n\r\t\v\e\f
     * 
     * @param string $str    similar [1,2,3] or ['a','b','c'] string
     */
    public static function fromDeclare(string $s): FixedArray
    {
        $len = strlen($s);
        if($s[0] != '[' || $s[$len - 1] != ']') {
            throw new \TypeError('Parse Error');
        }
        $est = substr_count($s, '\',') + substr_count($s, '",') + 1;
        $o = new static($est);
        $schar = ['s' => ' ', 'n' => "\n", 'r' => "\r", 't' => "\t", 'v' => "\v", 'e' => "\e", 'f' => "\f"];
        $ec = $f = $fsq = $fdq = $fc = 0;
        $sq = '\'';
        $dq = '"';
        $c = '\\';
        $cs = $e = '';
        for($i = 0; $i < $len; $i++) {
            if($s[$i] == $c) {
                $fc++;
                $cs .= $s[$i];
            } elseif($fc > 0 && $s[$i] != $c) {
                if($fc % 2 === 0) {
                    $e .= str_repeat($c, $fc / 2);
                    $ec = 0;
                } else {
                    $e .= str_repeat($c, ($fc - 1) / 2);
                    $ec = 1;
                }
                if($ec && $fdq === 1 && $s[$i] !== $dq) {
                    $e .= isset($schar[$s[$i]]) ? $schar[$s[$i]] : ($c . $s[$i]);
                } elseif($ec && $fdq === 1 && $s[$i] === $dq) {
                    $e .= $sd;
                } else if($ec && $fsq === 1) {
                    $e .= $s[$i] != $sq ? ($c . $s[$i]) : $sq;
                } elseif($fsq === 1 && !$ec && $s[$i] === $sq) {
                    $fsq--;
                } elseif(!$ec && $fdq === 1 && $s[$i] === $dq) {
                    $fdq--;
                } else {
                    $e .= $s[$i];
                }
                $ec = 0;
            } elseif($s[$i] === $sq && $fdq === 0) {
                if($e !== '') {
                    throw new \TypeError('Parse Error');
                }
                $fsq++;
            } elseif($s[$i] === $dq && $fsq === 0) {
                if($e !== '') {
                    throw new \TypeError('Parse Error');
                }
                $fdq++;
            } elseif($s[$i] === $sq && $fsq === 1) {
                $fsq--;
            } elseif($s[$i] === $dq && $fdq === 1) {
                $fdq--;
            } elseif($fdq === 0 && $fdq === 0 && $s[$i] === ',') {
                $i->offsetSet($f, $e);
                $f++;
                $e = '';
            } else {
                $e .= $s[$i];
            }
        }
        $o->setSize($f + 1);
        return $o;
    }

    public function toDeclare(): string
    {
        $str = '[';
        foreach($this as $v) {
            $str .= "\"$v\",";
        }
        return $str . ']';
    }

    public function in($value, $strict = false)
    {
        return $this->search($value, $strict) !== false;
    }

    public function search($value, $strict)
    {
        $size = $this->getSize();
        $hfs = floor($size / 2);
        for($i = 0; $i < $hfs; $i++) {
            if(Math::eq($this->offsetGet($i), $valu, $strict)) {
                return $i;
            }
            $hfi = $hfs + $i;
            if(Math::eq($this->offsetGet($hfi), $value, $strict)) {
                return $i;
            }
        }
        if($size % 2 == 1 && Math::eq($this->offsetGet($size - 1), $value, $strict)) {
            return $size - 1;
        }
        return false;
    }

}
