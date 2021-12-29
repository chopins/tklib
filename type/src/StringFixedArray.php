<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2021 Toknot.com
 * @license    http://toknot.com/GPL-2,0.txt GPL-2.0
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use Toknot\Type\Text;

/**
 * StringArray
 *
 * @author chopin
 */
class StringFixedArray extends FixedArray
{

    protected $head = false;
    protected $headOffset = 0;

    /**
     * 
     * @param string $str
     * @return \SplFixedArray
     */
    public static function fromString($str)
    {
        return self::fromArray(self::split($str));
    }

    public static function __callStatic($name, $args)
    {
        return Text::$name(...$args);
    }

    public function onlyStringTail()
    {
        $this->head = true;
    }

}
