<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2021 Toknot.com
 * @license    http://toknot.com/GPL-2,0.txt GPL-2.0
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

/**
 * TextStatic
 *
 * @author chopin
 */
abstract class TextStaticAbstract extends Scalar
{

    protected static $mbEnable = null;
    public static $noMbstr = false;
    protected static $standardFuncCache = [];

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

    protected static function undefinedMethod($name)
    {
        $class = get_called_class();
        throw new \BadMethodCallException("Undefined $class::$name()");
    }

    protected static function mayStringFuncName($name, $mbstatus)
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
            self::undefinedMethod($name);
        }
        return $name;
    }

    public static function __callStatic($name, $argv)
    {
        self::checkMbstring();
        $func = self::mayStringFuncName($name, self::$mbEnable);
        return $func(...$argv);
    }

}
