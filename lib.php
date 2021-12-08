<?php

defined('HAVE_TKLIB_AUTOLOAD_' . md5(__DIR__ . '/autoload.php')) || die('must be include ' . __DIR__ . '/autoload.php');

use Toknot\Type\Scalar;
use Toknot\Type\Char;
use Toknot\Path\Path;
use Toknot\Type\ArrayObject;
use Toknot\Path\File;
use Toknot\Type\SmartString;
use Toknot\Network\SimpleCurl;
use Toknot\Process\Console;
use Toknot\Path\Csv as CsvFile;
use Toknot\Path\Video;

/**
 * 根据二维数组的子数组的键值排序
 *
 * @param array $arr       传引用
 * @param string|int $subKey    子数组键名
 * @param int $sortFlag  排序标志与，sort同
 * @param integer $reverse  是否为降序
 * @return void
 */
function sortBySubVal(array &$arr, $subKey, $sortFlag = SORT_REGULAR, $reverse = 0)
{
    ArrayObject::sortBySubVal($arr, $subKey, $sortFlag, $reverse);
}

/**
 * 查找字符串,返回开始字符串与结束字符串之间的字符串，不包括起始与结束字符串
 *
 * @param string $content   在其中查找
 * @param array $start      开始字符串及其长度，值类似 array($开始字符串,$长度);
 * @param string $end       结束字符串
 * @param int $offset       指定查找偏移量，如果是负数，将以开始字符串$start最后出现的位置为起点
 * @param int $findPos      查找到的偏移量
 * @return string|bool      返回false即未找到
 */
function strFind(string $content, $start, $end, $offset, &$findPos = 0)
{
    return Char::strFind($content, $start, $end, $offset, $findPos);
}

/**
 * @property-read int $offset
 * @property-read int $trail
 * @property-read string $content
 * @property-read int $contentLen
 * @property-read string $bakContent
 */
class SmartStrPos extends SmartString
{
    
}

function smartStrPos(string $content, int $offset = 0)
{
    return new SmartString($content, $offset);
}

function ffmpegGetMediaMeta($file, $key, $flag)
{
    return Video::ffmpegGetMediaMeta($file, $key, $flag);
}

function getVideoMeta($file): array
{
    return Video::getVideoMeta($file);
}

/**
 * 在数组查找包含给定字符串的元素
 *
 * @param array $array
 * @param string|array $needle
 * @param bool $equal    是否判断完全等于，否则只检测是否包含
 * @return mixed
 */
function arrayFind(array $array, $needle, $equal = true)
{
    return ArrayObject::arrayFind($array, $needle, $equal);
}

/**
 * 获取字符串
 *
 * @param string $str
 * @param array $seplist
 * @param string $field
 * @return void
 */
function getStrFieldValue(string $str, array $seplist = ['=', ':'], string &$field = '')
{
    return Char::getStrFieldValue($str, $seplist, $field);
}

function isPrefix($str, $prefix)
{
    return Char::isPrefix($str, $prefix);
}

function hasStr($str, $list = [])
{
    return Char::hasStr($str, $list);
}

/**
 * 在数组查找类似`FieldName=FieldValue` 或者`FieldName: FieldValue`的值
 *
 * @param array $array
 * @param array|string $needle
 * @return array|string
 */
function arrayFindFieldValue(array $array, $needle, array $addSep = [], string $parentSep = ':')
{
    return ArrayObject::arrayFindFieldValue($array, $needle, $addSep, $parentSep);
}

function getDirFile($path)
{
    return Path::getDirFile($path);
}

function calThreshold($number, $threshold, $sep = '')
{
    return Video::calThreshold($number, $threshold, $sep);
}

function getTTYSize()
{
    return Console::getTTYSize();
}

function isFloatNumber($number)
{
    return Scalar::isFloatNumber($number);
}

function getDecimal($number)
{
    return Scalar::getDecimal($number);
}

function videoTime($time)
{
    return Video::videoTime($time);
}

/**
 * 使用CURL获取内容，
 *
 * @param string $url
 * @param array $opt
 * @return Fetch
 */
function fetch(string $url, $opt = [])
{
    return new Fetch($url, $opt);
}

function checkStrSuffix($str, $endStr)
{
    return Char::checkStrSuffix($str, $endStr);
}

function isUpDomain($subDomain, $upDomain)
{
    return Char::isUpDomain($subDomain, $upDomain);
}

/**
 * @property-read string $data
 * @property-read int $retCode
 * @property-read string $error
 * @property-read int $errCode
 */
class Fetch extends SimpleCurl
{
    
}

/**
 * 使用Wget命令下载文件
 *
 * @param string $url
 * @param string $output 保存到文件
 * @param array $opt    命令行选项
 * @return void
 */
function wget(string $url, string $output, $opt = [])
{
    return Console::wget($url, $output, $opt);
}

/**
 * 清理命令行
 *
 * @param int $selfWidth
 * @return void
 */
function clearTTYLine($selfWidth = null)
{
    return Console::clearTTYLine($selfWidth);
}

function strCountNumerOfLetter($str, $isnum)
{
    return Char::strCountNumerOfLetter($str, $isnum);
}

/**
 * 保存数组
 *
 * @param string $file
 * @param array $array
 * @param integer $option    参数值与 file_put_contents 的 flags 一样，另增加 FILE_NEW_APPEND 用于附加保存时重置
 * @return string
 */
function saveArray($file, $array, $option = 0)
{
    return File::saveArray($file, $array, $option);
}

function pathJoin(...$args)
{
    return Path::join(...$args);
}

function rPathJoin(...$args)
{
    return Path::realpath(Path::join(...$args));
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
function rloopArray(array &$array, callable $callback, $userdata = null)
{
    return ArrayObject::rloopArray($array, $callback, $userdata);
}

/**
 * 树查找，返回找到的路径
 * A B C
 * D B C
 * @param [array $array
 * @param mixed $value      可为函数或具体值
 * @return array
 */
function findTree(array $array, $value)
{
    return ArrayObject::findTree($array, $value);
}

function strEndPos($str, $needle)
{
    return Char::strEndPos($str, $needle);
}

class Csv extends CsvFile
{
    
}

/**
 * 命令行下的多任务进度
 *
 * @param int $cur           当前任务已处理数据量
 * @param int $total         当前任务总数据量
 * @param int $totalTaskNum  总任务数    
 * @param int $taskIdx       当前任务索引数
 * @return void
 */
function multitaskProgress($cur, $total, $totalTaskNum, $taskIdx)
{
    return Console::multitaskProgress($cur, $total, $totalTaskNum, $taskIdx);
}
