<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Math;

use Toknot\Type\Scalar;

class Number extends Scalar {
    const NAME = 'number';
    public function __construct($int = 0) {
        if(\is_numeric($int)) {
            if(strpos('.', $int)) {
                return floatval($int);
            }
            $this->value = $int > PHP_INT_MAX || $int <PHP_INT_MIN ? intval($int) : floatval($int);
        } elseif(is_bool($int)) {
            $this->value = intval($int);
        } else {
            throw new \ValueError('passed paramater #1 is not number');
        }
    }
}