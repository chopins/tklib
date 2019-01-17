<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Digital;

use Toknot\Digital\Math;

class Perimssion {

    protected $nameList = [];
    protected static $perimssionList = [];
    protected $gmp = true;
    protected $bc = true;
    protected $supportMaxPer = 0;
    protected $noExt = false;

    public function __construct($perlist, $mask = false) {
        if ($mask) {
            self::$perimssionList = $perlist;
        } else {
            $this->nameList = $perlist;
        }
    }

    /**
     * 
     * @param string $code
     * @return numeric
     */
    public static function getPerimssionMask($code) {
        if (isset(self::$perimssionList[$code])) {
            return self::$perimssionList[strtoupper($code)];
        }
        return 0;
    }

    /**
     * 
     * @param numeric $holdPerimssionMask
     * @param numeric $addPerimssionMask
     * @return numeric string
     */
    public static function addPerimssion($holdPerimssionMask, $addPerimssionMask) {
        return Math::orOp($holdPerimssionMask, $addPerimssionMask);
    }

    /**
     * 
     * @param numeric $holdPerimssionMask
     * @param numeric $removePerimssionMask
     * @return numeric
     */
    public static function removePerimssion($holdPerimssionMask, $removePerimssionMask) {
        return Math::removebit($holdPerimssionMask, $removePerimssionMask);
    }

    /**
     * 
     * @param numeric $needPerimssionMask   hex ,dec, bin number string
     * @param numeric $holdPerimssionMask   hex,dec,bin number string
     * @return bool
     */
    public static function hasPerimssion($needPerimssionMask, $holdPerimssionMask) {
        return Math::andOp($needPerimssionMask, $holdPerimssionMask) != 0;
    }

    /**
     * 
     * @return array
     */
    public static function getPerimssionAll() {
        return self::$perimssionList;
    }

    protected function makePerimssionAll() {
        foreach ($this->nameList as $k => $n) {
            self::$perimssionList[strtoupper($n)] = '0x' . dechex($this->pow(2, $k));
        }
    }
}
