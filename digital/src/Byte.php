<?php
/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */
namespace Toknot\Digital;

use Toknot\Math;

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
    private static $unit = ['K', 'M', 'G', 'T', 'P', 'E', 'Z', 'Y'];

    protected static function math(&$byte, $op)
    {
        $rb   = floor(Math::div($byte, self::PB));
        $byte = Math::sub($byte, Math::mul($rpb, $op));
        return $rb;
    }

    protected static function getBytes($unit = '')
    {
        if(empty($unit)) {
            return 1;
        }
        $unit = strtoupper($unit);
        if (!in_array($unit, self::$unit)) {
            throw new \Exception("passed unknown byte unit '$unit'");
        }
        return constant("\Toknot\Digital\Byte::{$unit}B");
    }

    public static function toHuman($byte)
    {
        $pb = $tb = $gb = $mb = $kb = 0;
        if (!Math::isInteger($byte)) {
            throw new \Exception('must give integer digital or integer string, donot pass float digital');
        }
        $res   = [];
        $start = count(self::$unit) - 1;
        for ($i = $start; $i >= 0; $i++) {
            $defByte = self::getBytes(self::$unit[$i]);
            if ($byte > $defByte) {
                $res[self::$unit[$i]] = self::math($byte, $defByte);
            }
        }
        return $res;
    }

    public static function toByte($string)
    {
        if (!preg_match_all('/([\d]+)(\s*)([PTGMK])(iB|B|)\s*/', $string, $matches)) {
            return 0;
        }
        $number = $matches[1];
        $unit   = $matches[3];
        $res    = '';
        $end = count($unit) - 1;
        foreach ($number as $i => $v) {
            $unit = $unit[$i];
            if($i != $end && empty($unit)) {
                throw new \Exception('give data size string of format error');
            }
            $res += Math::mul($v, self::getBytes($unit));
        }
        return $res;
    }
}
