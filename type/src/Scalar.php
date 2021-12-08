<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

class Scalar
{

    protected $value = '';

    public function __toString()
    {
        return $this->value;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public static function isFloatNumber($number)
    {
        if(!is_numeric($number)) {
            return false;
        }
        if(strpos($number, '.')) {
            return true;
        }
        return false;
    }

    public static function getDecimal($number)
    {
        if(isFloatNumber($number)) {
            list(, $decimal) = explode('.', $number);
            return $decimal;
        }
        return 0;
    }

}
