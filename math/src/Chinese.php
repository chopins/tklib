<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Math;

class Chinese
{
    protected $zhnum      = '';
    protected $zht        = false;
    protected $zhInstend  = false;
    const DIGITAL_TABLE   = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
    const DIGITAL_T_TABLE = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
    const UNIT_TABLE      = ['十', '百', '千', '万', '亿'];
    const UNIT20          = '廿';
    const UNIT_T_TABLE    = ['拾', '佰', '仟', '万', '亿'];
    const DOT             = '点';

    /**
     * convert arabic numerals to chinese numerals
     *
     * <code>
     * echo new Chinese(23343);  //二万三千三百四十三
     * echo new Chinese(21423, true); //贰万壹仟肆佰贰拾叁
     * $ch = new Chinese(83242, true);
     * echo $ch->getZhnum(); //捌万叁仟贰佰肆拾二
     * </code>
     * @param number $number   a number
     * @param bool   $zht      whether use capital number of chinese
     * @param int $zhInstend   whether enable special express,is 1 if 11 is 十一 instead of 一十一,
     *                         is 2 if 21 is 廿一 when number between 21 - 29 that instead of 二十一 - 二十九
     *                         but of $zht is true will invaild
     */
    public function __construct($number, $zht = false, $zhInstend = 0)
    {
        if (!\is_numeric($number)) {
            throw new \InvalidArgumentException('paramter 1 must be a numeric');
        }
        $this->zht       = $zht;
        $this->zhInstend = $zhInstend;
        $this->zhnum     = $this->number2zh($number);
    }

    public static function convert($number, $zht = false, $zhsk = 0)
    {
        $s = new static($number, $zht, $zhsk);
        return $s->getZhnum();
    }

    /**
     * get chinese number string
     */
    public function getZhnum()
    {
        return $this->zhnum;
    }

    public function __toString()
    {
        return $this->zhnum;
    }

    protected function number2zh($number)
    {
        $sign   = $number < 0 ? '负' : '';
        $number = abs($number);
        return $this->number2Word($number, $sign);
    }

    protected function number2Word($number, $sign)
    {
        $p            = explode('.', $number);
        $digitalTable = $this->zht ? self::DIGITAL_T_TABLE : self::DIGITAL_TABLE;
        $unitTable    = $this->zht ? self::UNIT_T_TABLE : self::UNIT_TABLE;
        if($number == 0) {
            return $digitalTable[0];
        }
        $int          = $sign . $this->addUnit($p[0], $digitalTable, $unitTable);
        $dec          = '';
        if (isset($p[1])) {
            $dec = $this->convertDecimal($p[1], $digitalTable);
        }
        return $int . $dec;
    }

    protected function addUnit($int, $table, $unitTable)
    {
        $intStr = strrev($int);
        $np     = array_reverse(str_split($intStr, 4));

        $len = count($np);
        $res = '';
        foreach ($np as $i => $sn) {
            $len--;
            $thousand = $this->thousand($sn, $table, $unitTable);

            $res .= $thousand;
            if ($len > 0 && $thousand !== $table[0]) {
                $res .= $len % 2 == 0 ? $unitTable[4] : $unitTable[3];
            }
        }
        if ($this->zhInstend > 0 && !$this->zht && strpos($res, $table[1] . $unitTable[0]) === 0) {
            return substr($res, 3);
        } elseif ($this->zhInstend == 2 && $int > 20 && $int < 30) {
            return self::UNIT20 . substr($res, 6);
        }
        return $res;
    }

    protected function thousand($sn, $table, $unitTable)
    {
        $sn = strrev($sn);
        $len     = strlen($sn);
        $res     = '';
        $iszero  = 0;
        $zeroStr = '';
        for ($i = 0; $i < $len; $i++) {
            $u      = $len - $i - 2;
            $n      = $sn{$i};
            $number = $table[$n];
            if ($u < 0 && $n != 0) {
                $res .= $zeroStr;
                $zeroStr = '';
                $res .= $number;
            } else {
                if ($n == 0) {
                    $iszero++;
                    $iszero === 1 && $i != ($len - 1) && $zeroStr = $table[0];
                } else {
                    $iszero = 0;
                }
                if ($iszero > 0) {
                    continue;
                }
                $unit = is_array($unitTable[$u]) ?
                $unitTable[$u][$n] : $unitTable[$u];
                $res .= $number . $unit;
            }
        }
        if (!$res) {
            $res .= $zeroStr;
        }
        return $res;
    }

    protected function convertDecimal($number, $table)
    {
        $rs     = self::DOT;
        $len    = strlen($number);
        $iszero = 0;
        for ($i = 0; $i < $len; $i++) {
            $k = intval($number{$i});
            $rs .= $table[$k];
        }
        return $rs;
    }
}
