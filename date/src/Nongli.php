<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */
namespace Toknot\Date;

use Toknot\Digital\Chinese;

class Nongli
{
    const MONTH_ZH_NAME   = [1 => '正', 11 => '冬', 12 => '腊'];
    const DAY_ZH_PREFIX   = '初';
    const MONTH_ZH_LEAP   = '闰';
    const NONGLI_MAX_YEAR = 2100;
    const NONGLI_START    = [1, 1];
    const NONGLI_END      = [12, 1];
    const YEAR_START      = [1900, 1, 31];
    const YEAR_END        = [2100, 12, 31];
    const GANZHI_START    = 36;
    const GANZHI_MAP      = ['甲子', '乙丑', '丙寅', '丁卯', '戊辰', '己巳', '庚午', '辛未', '壬申', '癸酉', '甲戌', '乙亥',
        '丙子', '丁丑', '戊寅', '己卯', '庚辰', '辛巳', '壬午', '癸未', '甲申', '乙酉', '丙戌', '丁亥',
        '戊子', '己丑', '庚寅', '辛卯', '壬辰', '癸巳', '甲午', '乙未', '丙申', '丁酉', '戊戌', '己亥',
        '庚子', '辛丑', '壬寅', '癸卯', '甲辰', '乙巳', '丙午', '丁未', '戊申', '己酉', '庚戌', '辛亥',
        '壬子', '癸丑', '甲寅', '乙卯', '丙辰', '丁巳', '戊午', '己未', '庚申', '辛酉', '壬戌', '癸亥'];

    //本数据基于香港天文台数据(https://www.hko.gov.hk/gts/time/conversionc.htm)
    //第0个数据基于网上数据填充
    const NONGLI_MAP = [0x4bd8, 0x4ae0, 0xa570, 0x54d5, 0xd260, 0xd950, 0x16554, 0x56a0, 0x9ad0, 0x55d2,
        0x4ae0, 0xa5b6, 0xa4d0, 0xd250, 0x1d255, 0xb540, 0xd6a0, 0xada2, 0x95b0, 0x14977,
        0x4970, 0xa4b0, 0xb4b5, 0x6a50, 0x6d40, 0x1ab54, 0x2b60, 0x9570, 0x52f2, 0x4970,
        0x6566, 0xd4a0, 0xea50, 0x16a95, 0x5ad0, 0x2b60, 0x186e3, 0x92e0, 0x1c8d7, 0xc950,
        0xd4a0, 0x1d8a6, 0xb550, 0x56a0, 0x1a5b4, 0x25d0, 0x92d0, 0xd2b2, 0xa950, 0xb557,
        0x6ca0, 0xb550, 0x15355, 0x4da0, 0xa5b0, 0x14573, 0x52b0, 0xa9a8, 0xe950, 0x6aa0,
        0xaea6, 0xab50, 0x4b60, 0xaae4, 0xa570, 0x5260, 0xf263, 0xd950, 0x5b57, 0x56a0,
        0x96d0, 0x4dd5, 0x4ad0, 0xa4d0, 0xd4d4, 0xd250, 0xd558, 0xb540, 0xb6a0, 0x195a6,
        0x95b0, 0x49b0, 0xa974, 0xa4b0, 0xb27a, 0x6a50, 0x6d40, 0xaf46, 0xab60, 0x9570,
        0x4af5, 0x4970, 0x64b0, 0x74a3, 0xea50, 0x6b58, 0x5ac0, 0xab60, 0x96d5, 0x92e0,
        0xc960, 0xd954, 0xd4a0, 0xda50, 0x7552, 0x56a0, 0xabb7, 0x25d0, 0x92d0, 0xcab5,
        0xa950, 0xb4a0, 0xbaa4, 0xad50, 0x55d9, 0x4ba0, 0xa5b0, 0x15176, 0x52b0, 0xa930,
        0x7954, 0x6aa0, 0xad50, 0x5b52, 0x4b60, 0xa6e6, 0xa4e0, 0xd260, 0xea65, 0xd530,
        0x5aa0, 0x76a3, 0x96d0, 0x26fb, 0x4ad0, 0xa4d0, 0x1d0b6, 0xd250, 0xd520, 0xdd45,
        0xb5a0, 0x56d0, 0x55b2, 0x49b0, 0xa577, 0xa4b0, 0xaa50, 0x1b255, 0x6d20, 0xada0,
        0x14b63, 0x9370, 0x49f8, 0x4970, 0x64b0, 0x168a6, 0xea50, 0x6aa0, 0x1a6c4, 0xaae0,
        0x92e0, 0xd2e3, 0xc960, 0xd557, 0xd4a0, 0xda50, 0x5d55, 0x56a0, 0xa6d0, 0x55d4,
        0x52d0, 0xa9b8, 0xa950, 0xb4a0, 0xb6a6, 0xad50, 0x55a0, 0xaba4, 0xa5b0, 0x52b0,
        0xb273, 0x6930, 0x7337, 0x6aa0, 0xad50, 0x14b55, 0x4b60, 0xa570, 0x54e4, 0xd160,
        0xe968, 0xd520, 0xdaa0, 0x16aa6, 0x56d0, 0x4ae0, 0xa9d4, 0xa2d0, 0xd150, 0xf252,
        0xd520];
    private $year            = 0;
    private $month           = 0;
    private $day             = 0;
    private $dayNum          = 0;
    private $yearData        = 0;
    private $toDays          = 0;
    private $nongliDate      = [];
    private $time            = 0;
    private $tradition       = false;
    protected $srcDate       = 0;
    protected $startYearDays = 0;

    public function __construct()
    {
        $this->startYearDays = date('z', strtotime(\implode('-', self::YEAR_START)));
    }

    /**
     * Get between 1900-01-31 -- 2100-12-31 date of Chinese Nongli
     *
     * <code>
     * $nongli = new Nongli;
     * $res = $nongli->getDay('1900-03-20');
     * echo $res['nl']; //庚子年二月二十
     * </code>
     *
     * @param string $day
     * @return array  the value keys within this array are :
     *              gd:  date string
     *              ut:  unixtime
     *              gc:  date array, within keys : y, d, m
     *              nl:  Nongli date string
     *              nd:  Nongli date array, element value is chinese digital, within keys : y, d, m
     *              ndn: Nongle date array, and element value is arabic numerals, within keys : y, d, m,l
     *                   the 'l' key of value determine whether leap month
     *                   the 'y' key of value is offset of self::GANZHI_MAP
     */
    public function getDay($day, $tradition = false)
    {
        $this->srcDate = $day;
        $this->time    = \strtotime($day);
        if ($this->time === false) {
            throw new \Exception('give time error');
        }
        $this->tradition = $tradition;
        $this->setDayInfo();
        return $this->getNongliDay();
    }

    protected function getNongliDay()
    {
        $this->loopAllData();
        $dateData       = [];
        $dateData['gd'] = date('Y-m-d', $this->time);
        $dateData['ut'] = $this->time;
        $dateData['gc'] = ['y' => $this->year, 'm' => $this->month, 'd' => $this->day];

        $monthName = Chinese::convert($this->nongliDate[1], false, true);
        $month     = $this->nongliDate[3] == 1 ? self::MONTH_ZH_LEAP . $monthName : $monthName;
        if ($this->tradition && isset(self::MONTH_ZH_NAME[$this->nongliDate[1]])) {
            $month = self::MONTH_ZH_NAME[$this->nongliDate[1]];
        }
        $day = Chinese::convert($this->nongliDate[2], false, 2);
        if ($this->nongliDate[2] < 11) {
            $day = self::DAY_ZH_PREFIX . $day;
        }
        $ganzhi          = self::GANZHI_MAP[$this->nongliDate[0]];
        $dateData['nl']  = "{$ganzhi}年{$month}月{$day}";
        $dateData['nd']  = ['y' => $ganzhi, 'm' => $month, 'd' => $day];
        $dateData['ndn'] = ['y' => $this->nongliDate[0], 'm' => $this->nongliDate[1], 'd' => $this->nongliDate[2], 'l' => $this->nongliDate[3]];
        return $dateData;
    }

    protected function calToYearDays()
    {
        $days = 0;
        for ($i = self::YEAR_START[0]; $i < $this->year; $i++) {
            $days += ((($i % 4 === 0 && $i % 100 !== 0) || $i % 400 === 0) ? 366 : 365);
        }
        $this->toDays = $days + $this->dayNum - $this->startYearDays;
    }

    protected function setDayInfo()
    {
        list($this->year, $this->month, $this->day, $this->dayNum) = explode(' ', date('Y n j z', $this->time));
        if ($this->year > self::YEAR_END[0] || $this->year < self::YEAR_START[0]
            || ($this->year == self::YEAR_START[0] && $this->dayNum < $this->startYearDays)) {
            $start = \implode('-', self::YEAR_START);
            $end   = \implode('-', self::YEAR_END);
            throw new \Exception("give date({$this->srcDate}) out of range, must between  $start -- $end (contain the start and end date)");
        }
        $this->dayNum += 1;
        $this->calToYearDays();
    }

    protected function getYearDataByOffset($offset)
    {
        return sprintf('%017s', decbin(self::NONGLI_MAP[$offset]));
    }

    protected function loopAllData()
    {
        $offset = $this->year - self::YEAR_START[0];
        $days   = $this->toDays;
        foreach (self::NONGLI_MAP as $i => $v) {
            $days = $days - $this->getYearDaysByOffset($i, $days);
            if ($days <= 0) {
                break;
            }
        }
    }

    protected function getYearDaysByOffset($yearOffset, $endDays)
    {
        $yearData     = $this->getYearDataByOffset($yearOffset);
        $leapMonth    = bindec(substr($yearData, -4, 4));
        $leapMonthDay = $yearData[0] == 1 ? 30 : 29;
        $days         = [];
        $start        = 1;
        $startDay     = 0;
        if ($yearOffset === 0) {
            $start    = self::NONGLI_START[0];
            $startDay = self::NONGLI_START[1] - 1;
        }
        $yd = 0;
        for ($i = $start; $i <= 12; $i++) {
            $m = $yearData{$i} === '1' ? 30 : 29;
            if ($i === $start) {
                $m -= $startDay;
            } elseif ($i !== 0) {
                $startDay = 0;
            }
            if ($this->setNongliDate($i, $m, $yd, $endDays, $yearOffset, 0)) {
                break;
            }

            if ($leapMonth == $i) {
                if ($this->setNongliDate($i, $leapMonthDay, $yd, $endDays, $yearOffset, 1)) {
                    break;
                }
            }
        }

        return $yd;
    }

    protected function setNongliDate($month, $mDay, &$yd, &$endDays, $yearOffset, $leap)
    {
        if ($mDay > $endDays) {
            $ganzhi           = $this->calGanzhi($yearOffset);
            $this->nongliDate = [$ganzhi, $month, $endDays, $leap];
            $yd += $mDay;
            return 1;
        } else {
            $endDays -= $mDay;
        }
        $yd += $mDay;
        return 0;
    }

    protected function calGanzhi($yearOffset)
    {
        return $yearOffset == 0 ? self::GANZHI_START : (self::GANZHI_START + $yearOffset) % 60;
    }

}
