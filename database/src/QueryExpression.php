<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 chopin xiao (xiao@toknot.com)
 */

namespace Toknot\Database;

abstract class QueryExpression {

    const SP = ' ';
    const LP = '(';
    const RP = ')';

    abstract function getExpression();

    public function __toString() {
        return $this->getExpression();
    }

    final public static function isBitSet($value, $bit) {
        return ($value & $bit ) === $bit;
    }
}
