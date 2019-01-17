<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Misc;

use Toknot\Misc\Scalar;

class Integer extends Scalar {
    const NAME = 'int';
    const MAX = PHP_INT_MAX;
    const MIN = PHP_INT_MIN;
    public function __construct($int = 0) {
        $this->value = \intval($int);
    }
}