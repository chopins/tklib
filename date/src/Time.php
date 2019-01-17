<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Date;

use Toknot\Date;

class Time
{

    const DAY_SEC = 86400;
    const WEEK_SEC = 604800;
    const HOUR_SEC = 3600;
    const C_YEAR_SEC = 31536000;
    const L_YEAR_SEC = 31622400;

    /**
     * return current unix timestamp with millisecond
     *
     * @return int
     */
    public static function millisecond()
    {
        list($ms, $s) = explode(' ', microtime());
        $msec = floor($ms * 1000);
        return $s . $msec;
    }

    /**
     * sleep millisecond
     *
     * @param int $int  a milisecond
     */
    public static function msleep($int)
    {
        usleep($int * 1000);
    }
    /**
     * sleep second
     *
     * @param int $int    a second
     * @return void
     */
    public static function sleep($int)
    {
        sleep($int);
    }

    /**
     * get second for more day
     *
     * @param int $day
     * @return int
     */
    public static function getDaySec($day)
    {
        return $day * self::DAY_SEC;
    }

    public static function getHourSec($hour)
    {
        return $hour * self::HOUR_SEC;
    }

    /**
     * get time second for a year
     *
     * @param int $year
     * @return void
     */
    public static function getYearSec($year)
    {
        if (Date::isLeap($year)) {
            return self::L_YEAR_SEC;
        } else {
            return self::C_YEAR_SEC;
        }
    }
    public static function getYearDay($year)
    {
        if (Date::isLeap($year)) {
            return 366;
        }
        return 365;
    }

    /**
     * get second for a year month. if not give year will use current year
     *
     * @param int $month     month, 1 - 12
     * @param int $year      default is current year, value is date('Y)
     * @return void
     */
    public static function getMonthDay($month, $year = null)
    {
        $year = $year ?? date('Y');
        $isleap = Date::isLeap();
        if ($month == 2 && $isleap) {
            return 29;
        } elseif ($month == 2) {
            return 28;
        } elseif (in_array($month, [1, 3, 5, 7, 8, 10, 12])) {
            return 31;
        } else {
            return 30;
        }
    }
    /**
     * get human time ago for time
     *
     * @param int $time
     * @return array
     */
    public static function getAgo($time)
    {
        $dayAgo = self::getHumanTime($time);
        $currentInfo = getdate();
        //less than a month ago
        if ($dayAgo['day'] < $currentInfo['mday']) {
            return $dayAgo;
        }
        //less than a year ago
        if ($dayAgo['day'] < $currentInfo['yday']) {
            $rmd = $dayAgo['day'] - $currentInfo['mday'];
            $pass = 1;
            $agomday = 0;
            for ($i = $currentInfo['mon'] - 1; $i > 0 && $rmd > $agomday; $i--) {
                $agomday = self::getMonthDay($i);
                $rmd = $rmd - $agomday;
                $pass++;
            }
            return ['month' => $pass, 'day' => $rmd, 'hour' => $dayAgo['h'], 'minute' => $dayAgo['m'], 'second' => $dayAgo['s']];
        }
        //more than a year ago
        $ryd = $dayAgo['day'] - $currentInfo['yday'];
        $pass = 1;
        $agoyday = 0;
        for ($i == $currentInfo['year'] - 1; $i > 0 && $ryd > $agoyday; $i--) {
            $agoyday = self::getYearDay($i);
            $ryd = $ryd - $agoyday;
            $pass++;
        }
        //remaining months
        $month = 0;
        $mday = 0;
        for ($m = 12; $m >= 1 && $mday < $ryd; $m--) {
            $mday = self::getMonthDay($m, $i);
            $ryd = $ryd - $mday;
            $month++;
        }
        return ['year' => $pass, 'month' => $month, 'day' => $ryd, 'hour' => $dayAgo['h'], 'minute' => $dayAgo['m'], 'second' => $dayAgo['s']];
    }

    /**
     * get human time for a second number
     *
     * @param int $time
     * @return array
     */
    public static function getHumanTime($time)
    {
        $day = $hour = $minute = 0;
        if ($time > self::DAY_SEC) {
            $day = floor($time / self::DAY_SEC);
            $time = $time - self::DAY_SEC * $day;
        }
        if ($time > self::HOUR_SEC) {
            $hour = floor($time / self::HOUR_SEC);
            $time = $time - self::HOUR_SEC * $hour;
        }
        if ($time > 60) {
            $minute = floor($time / 60);
            $time = $time - 60 * 60;
        }
        return ['day' => $day, 'hour' => $hour, 'minute' => $minute, 'second' => $time];
    }

    public static function getMonthSec($month, $year = null)
    {
        return self::getMonthDay($month, $year) * self::DAY_SEC;
    }
}
