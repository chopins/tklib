<?php
/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */
namespace Toknot\Math;

use Toknot\Math\Math;

class Byte
{
    const KB             = 1024;
    const MB             = 1048576;
    const GB             = 1073741824;
    const TB             = '1099511627776';
    const PB             = '1125899906842624';
    const EB             = '1152921504606846976';
    const ZB             = '1180591620717411303424';
    const YB             = '1208925819614629174706176';
    const UNIT = ['K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];
    const ZHUNIT = ['K' =>'千', 'M' => '兆', 'G' => '吉', 'T' => '太', 'P' => '拍', 'E' => '艾', 'Z' => '泽' , 'Y' => '尧'];
    public static $isZh = false;

    protected static function math(&$byte, $op)
    {
        $rb   = floor(Math::div($byte, $op));
        $byte = Math::sub($byte, Math::mul($rb, $op));
        return $rb;
    }

    protected static function checkBase($base) {
        if($base != 2 && $base != 10) {
            throw new \Exception('base only 2 or 10');
        }
    }

    protected static function getBytes($unit = '', $base = 2)
    {
        if(empty($unit)) {
            return 1;
        }
        $unit = strtoupper($unit);
        if (false === ($idx = array_search($unit, self::UNIT, true))) {
            throw new \Exception("passed unknown byte unit '$unit'");
        }
        if($base == 10) {
            return Math::pow(1000, $idx + 1);
        }
        return constant("\Toknot\Digital\Byte::{$unit}B");
    }

    /**
     * convert byte number to human byte info
     * 
     * <cod>
     * Byte::toHuman('21542121314', 2, ' '); //20吉字节 64兆字节 171千字节 866字节
     * </code>
     * 
     * @param number $byte
     * @param int $base	        base 10 is 1000 bytes, base 2 is 1024 bytes
     * @param mixed $getString    passed false return array, else return string, is not bool will be set for boundary string
     * @param bool $isIEC	    true will use KiB, true use KB
     * @return mixed	 if $getString is not false will return array, string otherwise
     */
    public static function toHuman($byte, $base = 2, $getString = false, $isIEC = true)
    {
        $pb = $tb = $gb = $mb = $kb = 0;
        if (!Math::isInteger($byte)) {
            throw new \Exception('must give integer digital or integer string, donot pass float digital');
        }
        self::checkBase($base);
        $res   = [];
        $start = count(self::UNIT) - 1;
        for ($i = $start; $i >= 0; $i--) {
            $defByte = self::getBytes(self::UNIT[$i], $base);
            if ($byte > $defByte) {
                $res[self::UNIT[$i]] = self::math($byte, $defByte);
            }
        }
        if($getString !== false) {
            $var = '';
            $prefix = $isIEC ? 'iB' : 'B';
            $prefix = self::$isZh ? '字节' : $prefix;
            $sep = \is_string($getString) ? $getString : '';
            foreach($res as $u => $v) {
                $u = self::ZHUNIT[$u];
                $var .= "{$v}{$u}{$prefix}{$sep}";
            }
            return "{$var}{$byte}{$prefix}";
        }
        $res['B']  = $byte;
        return $res;
    }

    /**
     * convert byte string to byte number
     * 
     * <code>
     * echo Byte::toByte('2GB 32MB'); //2181038080
     * </code>
     * 
     * @param  string  $string
     * @param  int      $base  value 2 is base 2 for 1024 bytes, 10 is base 10 for 1000 bytes
     * @return number  
     */
    public static function toByte($string, $base = 2)
    {
        self::checkBase($base);
        if (!preg_match_all('/([\d]+)(\s*)([PTGMK])(iB|B|)\s*/', $string, $matches)) {
            return 0;
        }
        $number = $matches[1];
        $unit   = $matches[3];

        $res    = 0;
        $end = count($unit) - 1;
        foreach ($number as $i => $v) {
            $numUnit = $unit[$i];
            if($i != $end && empty($numUnit)) {
                throw new \Exception('give data size string of format error');
            }
            $bytes = self::getBytes($numUnit, $base);
            $res = Math::add($res,Math::mul($v, $bytes));
        }
        return $res;
    }
}
