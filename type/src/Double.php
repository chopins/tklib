<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

use Toknot\Type\Scalar;

class Double extends Scalar  {
    const NAME = 'float';
    public function __construct($int = 0) {
        $this->value = \floatval($int);
    }
}