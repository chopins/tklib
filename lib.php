<?php
/**
 * 根据二维数组的子数组的键值排序
 *
 * @param array $arr       传引用
 * @param string|int $subKey    子数组键名
 * @param int $sortFlag  排序标志与，sort同
 * @param integer $reverse  是否为降序
 * @return void
 */
function sortBySubVal(array &$arr, $subKey, $sortFlag = SORT_REGULAR, $reverse = 0) {
    usort($arr, function($a, $b) use($subKey, $sortFlag, $reverse){
        if($reverse) {
            list($b, $a) = [$a, $b];
        }
        if($sortFlag & SORT_STRING & SORT_FLAG_CASE) {
            return strcasecmp($a[$subKey], $b[$subKey]);
        } else if($sortFlag & SORT_NATURAL & SORT_FLAG_CASE) {
            return strnatcasecmp($a[$subKey], $b[$subKey]);
        }
        switch($sortFlag) {
            case  SORT_LOCALE_STRING:
                return strcoll($a, $b);
            case SORT_NATURAL:
                return strnatcmp($a[$subKey], $b[$subKey]);
            case SORT_STRING:
                return strcmp($a[$subKey], $b[$subKey]);
            case SORT_NUMERIC:
                $av = is_numeric($a[$subKey]) ? $a[$subKey] * 1 : (int)$a[$subKey];
                $bv = is_numeric($b[$subKey]) ? $b[$subKey] * 1 : (int)$b[$subKey];
                return $av > $bv ? 1 : ($av == $bv ? 0 : -1);
            case SORT_REGULAR:
                return $a[$subKey] > $b[$subKey] ? 1 : ($a[$subKey] == $b[$subKey] ? 0 : -1);
        }
    });
}

/**
 * 查找字符串,返回开始字符串与结束字符串之间的字符串
 *
 * @param string $content   在其中查找
 * @param array $start      开始字符串及其长度，值类似 array($开始字符串,$长度);
 * @param string $end       结束字符串
 * @param int $offset       偏移量，如果是负数，将查找以开始字符串最后出现的位置为起点
 * @return string|bool      返回false即未找到
 */
function strFind(string $content, array $start, $end, $offset) {
    if($offset < 0) {
        $startPos = mb_strrpos($content, $start[0], $offset);
    } else {
        $startPos = mb_strpos($content, $start[0], $offset);
    }
    if($startPos === false) {
        return false;
    }
    $endPos = mb_strpos($content, $end, $startPos + $start[1]);
    if($endPos === false) {
        return false;
    }
    return trim(mb_substr($content, $startPos + $start[1], $endPos - $startPos - $start[1]));
}

function getVideoMeta($file, &$returnVar = 0) {
    $command = "ffmpeg -hide_banner -i '$file' 2>&1";
    $output = '';
    exec($command, $output, $returnVar);
    exec("mplayer -nolirc -vo null -ao null -frames 0 -identify '$file' 2>&1",$output, $returnVar);
    return $output;
}

function arrayFindStr($array, $needle) {
    if(is_array($needle)) {
        $return = [];
        foreach($res as $line) {
            foreach($needle as $v) {
                if(strpos($line, $v) !== false) {
                    $return[$needle] = $line;
                }
            }
        }
        return $return;
    } else {
        foreach($res as $line) {
            if(strpos($line, $v) !== false) {
                return $line;
            }
        }
    }
    return false;
}

function getStrFieldValue(string $str, array $seplist = ['=',':'], string &$field = '') {
    $line = trim($str);
    if(!$field) {
        foreach($seplist as $sep) {
            if(strpos($line, $sep) > 0) {
                list($field,$val) = explode($sep, $line, 2);
                return trim($val);
            }
        }
    }elseif(strpos($str, $field) >= 0) {
        foreach($seplist as $sep) {
            if(strpos($line, $sep) > 0) {
                list($fieldNew,$val) = explode($sep, $line, 2);
                if(trim($fieldNew) == $field) {
                    return trim($val);
                }
            }
        }
    }
    return null;
}

function hasStr($str, $list = []) {
    foreach($list as $s) {
        if(strpos($str, $s) >= 0) {
            return true;
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
function arrayFindFieldValue(array $array, $needle, array $addSep = [], string $parentSep = ':') {
    $sep = ['=', ':'];
    $sep = array_merge($sep, $addSep);
    if(is_array($needle)) {
        $return = [];
        foreach($needle as $v) {
            $hasParent = false;
            $field = $v;
            if(strpos($v, $parentSep) > 0) {
                list($parent, $field) = explode($parentSep, $v,2);
                $parent = trim($parent);
                $field = trim($field);
                $hasParent = true;
            }

            foreach($array as $n=> $line) {
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
                            }else { 
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
        foreach($res as $line) {
            $val = getStrFieldValue($line, $sep, $needle);
            if($val !== null) {
                return $val;
            }
        }
    }
    return null;
}

function getDirFile($path) {
    $d =  dir($path);
    $file = [];
    while(false !== ($f = $d->read())) {
        if($f == '.' || $f == '..') {
            continue;
        }
        $file[] = $f;
    }
    return $file;
}

function calThreshold($number, $threshold, $sep = '') {
    $res = [];
    $int = floor($number);
    foreach($threshold as $val) {
        if($int >= $val) {
            $res[] = floor($int/$val);
            $int = $int%$val;
        } else {
            $res[] = 0;
        }
    }
    $res[] = $int;
    return strlen($sep)>0 ? join($sep, $res): $res;
}

function getTTYSize() {
    return explode(' ',exec('stty size'));
}

function isFloatNumber($number) {
    if(!is_numeric($number)) {
        return false;
    }
    if(strpos($number,'.')) {
        return true;
    }
    return false;
}

function getDecimal($number) {
    if(isFloatNumber($number)) {
        list(, $decimal) = explode('.', $number);
        return $decimal;
    }
    return 0;
}

function videoTime($time) {
    if(is_numeric($time)) {
        $res = calThreshold($time, [3600, 60]);
        array_walk($res, function(&$v) {
            $v = str_pad($v, 2, 0, STR_PAD_LEFT);
        });
        $str = join(':', $res);
        $res[] = str_pad(getDecimal($time), 3, 0);
        return $str .".{$res[3]}";
    } else {
        $res = explode(':', $time);

        $len = count($res);
        if($len >= 3) {
            $res[0] = $res[0] * 3600;
            $res[1] = $res[1] * 60;
        } else {
            $res[0] = $res[0] * 60;
        }
        return array_sum($res);
    }
}

function fetch($url, $opt = []) {
    return new Fetch($url, $opt);
}

function checkStrSuffix($str, $endStr) {
    $idx = strpos($str, $endStr);
    if((strlen($str) - $idx) === strlen($endStr)) {
        return true;
    }
    return false;
}

function isUpDomain($subDomain, $upDomain) {
    $subLvl = substr_count($subDomain, '.');
    $upLvl = substr_count($upDomain, '.');
    if($upLvl == $subLvl && $subDomain == $upDomain) {
        return 0;
    } elseif($upLvl < $subLvl && checkStrSuffix($subDomain,".$upDomain")) {
        return 1;
    }
    return -1;
}

class Fetch{
    private $data = '';
    private $retCode = 0;
    private $error = '';
    private $errCode = 0;
    public static $CURLOPT_USERAGENT = null;
    public static $CURLOPT_COOKIE = NULL;
    public static $CURLOPT_CONNECTTIMEOUT = 10;
    public static $CURLOPT_FOLLOWLOCATION = 1;
    public static $CURLOPT_MAXREDIRS = 10;
    public function __construct($url, $opt = []) {
        $ch1 = curl_init();
        $defOpt  = [
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=> self::$CURLOPT_CONNECTTIMEOUT,
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => self::$CURLOPT_FOLLOWLOCATION,
            CURLOPT_MAXREDIRS =>  self::$CURLOPT_MAXREDIRS,
        ];
    
        if(self::$CURLOPT_USERAGENT !== null) {
            $defOpt[CURLOPT_USERAGENT] = self::$CURLOPT_USERAGENT;
        }
        if(self::$CURLOPT_COOKIE !== null) {
            if(is_array(self::$CURLOPT_COOKIE)) {
                $host = parse_url($url,  PHP_URL_HOST);
                foreach(self::$CURLOPT_COOKIE as $cookeDomain => $cookie) {
                    if(isUpDomain($cookeDomain, $host) >= 0) {
                        $defOpt[CURLOPT_COOKIE] = $cookie;
                    }
                }
            } else {
                $defOpt[CURLOPT_COOKIE] = self::$CURLOPT_COOKIE;
            }
        }
        foreach($opt as $key => $val) {
            $defOpt[$key] = $val;
        }
        curl_setopt_array($ch1, $defOpt);
        $this->data = curl_exec($ch1);
        $this->retCode = curl_getinfo($ch1,  CURLINFO_HTTP_CODE);
        $this->errCode  = curl_errno($ch1);
        $this->error = curl_error($ch1);
        curl_close($ch1);
    }

    public function __get($name) {
        return $this->$name;
    }

    public function __toString() {
        return (string)$this->data;
    }
}

function wget($url, $output, $opt = []) {
    $defOpt = [
        '-w' => 5,
        '-T' => 5,
        '-t' => 1,
        '-q',
        '-O' => $output,
    ];
    $url = escapeshellarg($url);
    if(isset($_ENV['WGET_USER_AGENT'])) {
        $defOpt['--user-agent'] =$_ENV['WGET_USER_AGENT'];
    }
    foreach($opt as $k => $v) {
        $defOpt[$k] = $v;
    }
    $option = '';
    foreach($defOpt as $k => $v) {
        $v = escapeshellarg($v);
        if(is_numeric($k)) {
            $option .= " $v";
        } elseif(strlen($k) > 2) {
            $option .= " $k=$v";
        } else {
            $option .= " $k $v";
        }
    }
    $returnVar = 0;
    passthru("wget $option $url", $returnVar);
    if(file_exists($output) && !filesize($output)) {
        echo "wget Warning: $output file size is 0, Removed!" . PHP_EOL;
        unlink($output);
    }
    return $returnVar;
}

function clearTTYLine($selfWidth = null) {
    static $ttywidth;
    if(!$ttywidth && !$selfWidth) {
        list(,$ttywidth) = getTTYSize();
    } elseif($selfWidth) {
        $ttywidth = $selfWidth;
    }
    echo "\r" . str_repeat(' ', $ttywidth);
}
