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
 * BigString
 *
 * @author chopin
 */
class BigString extends Text
{

    public function __construct($str = '')
    {
        $this->value = (string) $str;
    }
    
    public static function fromFile($file)
    {
        return new static(file_get_contents($file));
    }

}
