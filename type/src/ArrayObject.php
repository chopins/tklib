<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Type;

/**
 * ArrayObject
 *
 * @author chopin
 */
class ArrayObject extends \ArrayObject
{

    /**
     * 根据二维数组的子数组的键值排序
     *
     * @param array $arr       传引用
     * @param string|int $subKey    子数组键名
     * @param int $sortFlag  排序标志与，sort同
     * @param integer $reverse  是否为降序
     * @return void
     */
    public static function sortBySubVal(array &$arr, $subKey, $sortFlag = SORT_REGULAR, $reverse = 0)
    {
        usort($arr, function ($a, $b) use ($subKey, $sortFlag, $reverse) {
            if($reverse) {
                list($b, $a) = [$a, $b];
            }
            if($sortFlag & SORT_STRING & SORT_FLAG_CASE) {
                return strcasecmp($a[$subKey], $b[$subKey]);
            } else if($sortFlag & SORT_NATURAL & SORT_FLAG_CASE) {
                return strnatcasecmp($a[$subKey], $b[$subKey]);
            }
            switch($sortFlag) {
                case SORT_LOCALE_STRING:
                    return strcoll($a, $b);
                case SORT_NATURAL:
                    return strnatcmp($a[$subKey], $b[$subKey]);
                case SORT_STRING:
                    return strcmp($a[$subKey], $b[$subKey]);
                case SORT_NUMERIC:
                    $av = is_numeric($a[$subKey]) ? $a[$subKey] * 1 : (int) $a[$subKey];
                    $bv = is_numeric($b[$subKey]) ? $b[$subKey] * 1 : (int) $b[$subKey];
                    return $av > $bv ? 1 : ($av == $bv ? 0 : -1);
                case SORT_REGULAR:
                    return $a[$subKey] > $b[$subKey] ? 1 : ($a[$subKey] == $b[$subKey] ? 0 : -1);
            }
        });
    }

    /**
     * 在数组查找包含给定字符串的元素
     *
     * @param array $array
     * @param string|array $needle
     * @param bool $equal    是否判断完全等于，否则只检测是否包含
     * @return mixed
     */
    public static function arrayFind(array $array, $needle, $equal = true)
    {
        if(is_array($needle)) {
            $return = [];
            foreach($needle as $v) {
                $return[$v] = arrayFind($array, $v, $equal);
            }
            return $return;
        } else {
            if($equal) {
                return array_search($needle, $array);
            }
            foreach($array as $idx => $line) {
                if(strpos($line, $needle) !== false) {
                    return $idx;
                }
            }
        }
        return false;
    }

    /**
     * 在数组查找类似`FieldName=FieldValue` 或者`FieldName: FieldValue`的值
     *
     * @param array $array
     * @param array|string $needle
     * @return array|string
     */
    public static function arrayFindFieldValue(array $array, $needle, array $addSep = [], string $parentSep = ':')
    {
        $sep = ['=', ':'];
        $sep = array_merge($sep, $addSep);
        if(is_array($needle)) {
            $return = [];
            foreach($needle as $v) {
                $hasParent = false;
                $field = $v;
                if(strpos($v, $parentSep) > 0) {
                    list($parent, $field) = explode($parentSep, $v, 2);
                    $parent = trim($parent);
                    $field = trim($field);
                    $hasParent = true;
                }

                foreach($array as $n => $line) {
                    if($hasParent) {
                        $pval = getStrFieldValue($line, [$parentSep], $parent);
                        if($pval !== null) {
                            $hasParent = false;
                        }
                    }
                    if(!$hasParent) {
                        $val = getStrFieldValue($line, $sep, $field);
                        if($val !== null) {
                            if(isset($return[$v])) {
                                if(!is_array($return[$v])) {
                                    $return[$v] = [$return[$v], $val];
                                } else {
                                    $return[$v][] = $return[$v];
                                }
                            } else {
                                $return[$v] = $val;
                            }
                        }
                    }
                }
            }
            return empty($return) ? null : $return;
        } else {
            foreach($array as $line) {
                $val = getStrFieldValue($line, $sep, $needle);
                if($val !== null) {
                    return $val;
                }
            }
        }
        return null;
    }

    /**
     * 与array_walk_recursive类似，
     * 不同处：
     * 1、只是当$callback返回非NULL时，会停止
     * 2、如果元素是数组,会先传递该数组给$callback,然后才递归处理该数组，不会直接递归进数组
     *
     * @param array $array
     * @param callable $callback    返回非NULL时停止后续执行
     * @param mixed $userdata
     * @return mixed                返回$callback返回的值或NULL
     */
    public static function rloopArray(array &$array, callable $callback, $userdata = null)
    {
        foreach($array as $k => &$v) {
            $res = $callback($v, $k, $userdata);
            if($res !== null) {
                return $res;
            }
            if(is_array($v) && ($res = rloopArray($v, $callback, $userdata)) !== null) {
                return $res;
            }
        }
        return null;
    }

    /**
     * 树查找，返回找到的路径
     * A B C
     * D B C
     * @param [array $array
     * @param mixed $value      可为函数或具体值
     * @return array
     */
    public static function findTree(array $array, $value)
    {
        $treePath = [];
        foreach($array as $k => $v) {
            if($v === $value || (is_callable($value) && $value($v, $k) === 0)) {
                $treePath[] = $k;
                return $treePath;
            } elseif(is_array($v)) {
                $subTree = findTree($v, $value);
                if(!empty($subTree)) {
                    array_unshift($subTree, $k);
                    return $subTree;
                }
            }
        }
        return $treePath;
    }

    public static function numberChunk($total, $chunkNum)
    {
        $unitNum = floor($total / $chunkNum);
        $unitArr = [[]];
        $k = $unitArrIdx = 0;
        for($i = 0; $i < $total; $i++) {
            if($k >= $unitNum) {
                $k = 0;
                $unitArrIdx++;
            }
            $unitArr[$unitArrIdx][] = $i;
            $k++;
        }
        return $unitArr;
    }

}
