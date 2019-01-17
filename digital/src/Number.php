<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Digital;

use Toknot\Misc\Scalar;

class Number extends Scalar {
    const NAME = 'number';
    public function __construct($int = 0) {
        if(\is_numeric($int)) {
            $this->value = $int > PHP_INT_MAX || $int <PHP_INT_MIN ? intval($int) : floatval($int);
        } elseif(is_bool($int)) {
            $this->value = intval($int);
        } else {
            $this->value = 0;
        }
    }
}