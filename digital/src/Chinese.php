<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Digital;

class Chinese {
    protected $zhnum = '';
    protected $zht = false;
    public function __construct($number, $zht = false) {
        if(!\is_numeric($number)) {
            throw new \Exception('paramter 1 must be a numeric');
        }
        $this->zht = $zht;
        $this->zhnum = $this->number2zh($number);
    }

    public function getZhnum() {
        return $this->zhnum;
    }

    public function __toString() {
        return $this->zhnum;
    }

    protected function number2zh($number) {
        $numberTable = ['零', '一', '二', '三', '四', '五', '六', '七', '八', '九'];
        $numberTTable = ['零', '壹', '贰', '叁', '肆', '伍', '陆', '柒', '捌', '玖'];
        $unitTable = ['十', '百', '千', '万', '亿'];
        $unitTTable = ['拾', '佰', '仟', '万', '亿'];
        $sign = $number < 0 ? '负' : '';
        $number = abs($number);
        $dot = '点';
        return $this->number2Word($number, $this->zht ? $numberTTable : $numberTable,
                 $this->zht ? $unitTTable :$unitTable, $sign, $dot);
    }

    protected function number2Word($number, $numberTable, $unitTable, $sign, $dot) {
        $p = explode('.', $number);

        $int = $sign.$this->addUnit($p[0], $numberTable, $unitTable);

        $dec = '';
        if (isset($p[1])) {
            $dec = $this->convertDecimal($p[1], $numberTable, $dot);
        }
        return $int . $dec;
    }

    protected function addUnit($int, $table, $unitTable) {
        $intStr = strrev($int);
        $np = array_reverse(str_split($intStr, 4));

        $len = count($np);
        $res = '';
        foreach ($np as $i => $sn) {
            $len--;
            $res .= $this->thousand($sn, $table, $unitTable);
            if ($len > 0) {
                $res .= $len % 2 == 0 ? $unitTable[4] : $unitTable[3];
            }
        }
        return $res;
    }

    protected function thousand($sn, $table, $unitTable) {
        $sn = strrev($sn);

        $len = strlen($sn);
        $res = '';
        $iszero = 0;

        for ($i = 0; $i < $len; $i++) {
            $u = $len - $i - 2;
            $n = $sn{$i};
            $number = $table[$n];

            if ($u < 0 && $n != 0) {
                $res .= $number;
            } else {
                if ($n == 0) {
                    $iszero++;
                    $iszero === 1 && $i != ($len - 1) && $res .= $table[0];
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
        
        return rtrim($res, $table[0]);
    }

    protected function convertDecimal($number, $table, $dot) {
        $rs = $dot;
        $len = strlen($number);
        $iszero = 0;
        for ($i = 0; $i < $len; $i++) {
            $k = intval($number{$i});
            if ($k == 0) {
                $iszero++;
                if ($iszero > 1) {
                    continue;
                }
            } else {
                $iszero = 0;
            }
            $rs .= $table[$k];
        }
        return $rs;
    }
}