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
 * MaxHeap
 *
 * @author chopin
 */
class ArrayMaxHeap extends \SplMaxHeap
{

    protected $compareKey = 0;
    
    public function __construct($key)
    {
        parent::__construct();
        $this->setCompareKey($key);
    }

    public function setCompareKey($key)
    {
        $this->compareKey = $key;
    }

    protected function compare($v1, $v2)
    {
        if(is_string($v1[$this->compareKey]) || is_string($v2[$this->compareKey])) {
            return strcmp($v1[$this->compareKey], $v2[$this->compareKey]);
        }
        return (int) ceil($v1[$this->compareKey] - $v2[$this->compareKey]);
    }

    public static function fromArray($array, $key)
    {
        $obj = new static;
        $obj->setCompareKey($key);
        foreach($array as $v) {
            $obj->insert($v);
        }
        return $obj;
    }

}
