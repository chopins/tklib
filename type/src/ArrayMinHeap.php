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
 * ArrayMinHeap
 *
 * @author chopin
 */
class ArrayMinHeap extends ArrayMaxHeap
{

    protected function compare($v1, $v2)
    {
        return parent::compare($v2, $v1);
    }

}
