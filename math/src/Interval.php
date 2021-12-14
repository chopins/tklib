<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2021 Toknot.com
 * @license    http://toknot.com/GPL-2,0.txt GPL-2.0
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Math;

use TypeError;

/**
 * Interval
 *
 * @author chopin
 */
class Interval
{

    protected $ranges = [];
    protected $set = '';

    const SYMBOL = ['(', '[', ')', ']'];
    const L_INFINITAS = '-∞';
    const R_INFINITAS = '+∞';

    /**
     * 整数
     */
    const SET_ZAHLEN = 'Z';

    /**
     * 自然数
     */
    const SET_NATURAL = 'N';

    /**
     * 正整数
     */
    const SET_P_ZAHLEN = 'Z+';

    /**
     * 负整数
     */
    const SET_N_ZAHLEN = 'Z-';

    /**
     * 有理数
     */
    const SET_RATIONAL = 'Q';

    /**
     * 无理数
     */
    const SET_IRRATIONAL = 'CrQ';

    /**
     * 实数
     */
    const SET_REAL = 'R';

    /**
     * 虚数
     */
    const SET_IMAGINARY = 'I';

    /**
     * 复数
     */
    const SET_COMPLEX = 'C';
    const SETS_ALL = [self::SET_N_ZAHLEN, self::SET_P_ZAHLEN, self::SET_ZAHLEN, self::SET_NATURAL, self::SET_RATIONAL];

    public function __construct(string|array $range = '', $set = self::SET_RATIONAL)
    {
        if(!in_array($set, self::SETS_ALL)) {
            throw new TypeError('unspport number set');
        }
        $this->set = $set;
        if($range) {
            $this->parse($range);
        }
    }

    public function addLOpen($l, $r)
    {
        $this->add($l, $r, '(', ']');
    }

    public function addROpen($l, $r)
    {
        $this->add($l, $r, '[', ')');
    }

    public function addOpen($l, $r)
    {
        $this->add($l, $r, '(', ')');
    }

    public function addClose($l, $r)
    {
        $this->add($l, $r, '[', ']');
    }

    public function inNumberSet($value)
    {
        if(!is_numeric($value)) {
            return false;
        }
        switch($this->set) {
            case self::SET_ZAHLEN:
                return strpos($value, '.') === false;
            case self::SET_NATURAL:
                return strpos($value, '.') === false && $value >= 0;
            case self::SET_P_ZAHLEN:
                return strpos($value, '.') === false && $value > 0;
            case self::SET_N_ZAHLEN:
                return strpos($value, '.') === false && $value < 0;
            default:
                return true;
        }
    }

    protected function add($l, $r, $ls, $rs)
    {
        if(!is_numeric($l) && $l !== self::L_INFINITAS) {
            throw new TypeError('left iterval farmat error');
        }
        if(!is_numeric($r) && $r !== self::R_INFINITAS) {
            throw new TypeError('right iterval farmat error');
        }
        $this->ranges[] = [$l, $r, $ls, $rs];
    }

    public function in($value)
    {
        if(!$this->inNumberSet($value)) {
            return false;
        }
        foreach($this->ranges as $set) {
            if($set[2] == '(' && $set[0] != self::L_INFINITAS && $value <= $set[0]) {
                continue;
            } else if($set[2] == '[' && $set[0] != self::L_INFINITAS && $value < $set[0]) {
                continue;
            } else if($set[3] == ']' && $set[1] != self::R_INFINITAS && $value > $set[1]) {
                continue;
            } else if($set[3] == ')' && $set[1] != self::R_INFINITAS && $value >= $set[1]) {
                continue;
            }
            return true;
        }
        return false;
    }

    protected function parse($range)
    {
        if(is_array($range)) {
            foreach($range as $r) {
                $this->parse($r);
            }
        } else {
            $len = strlen($range);
            $end = $range[$len - 1];
            if(!in_array($range[0], self::SYMBOL) || !in_array($end, self::SYMBOL)) {
                throw new TypeError('Iterval farmat error');
            }
            $range = [];
            $range = str_replace('_', '', $range);
            list($range[0], $range[1]) = explode(',', substr($range, 1, -1));
            if(isset($range[2]) || (!is_numeric($range[0]) && $range[0] != self::L_INFINITAS) || (!is_numeric($range[1]) && $range[1] != self::R_INFINITAS)) {
                throw new TypeError('Iterval farmat error');
            }
            $range[2] = $range[0];
            $range[3] = $$end;
        }
        $this->ranges[] = $range;
    }

}
