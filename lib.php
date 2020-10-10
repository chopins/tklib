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
function sortBySubVal(array &$arr, $subKey, $sortFlag = SORT_REGULAR, $reverse = 0)
{
    usort($arr, function ($a, $b) use ($subKey, $sortFlag, $reverse) {
        if ($reverse) {
            list($b, $a) = [$a, $b];
        }
        if ($sortFlag & SORT_STRING & SORT_FLAG_CASE) {
            return strcasecmp($a[$subKey], $b[$subKey]);
        } else if ($sortFlag & SORT_NATURAL & SORT_FLAG_CASE) {
            return strnatcasecmp($a[$subKey], $b[$subKey]);
        }
        switch ($sortFlag) {
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
 * @param int $offset       偏移量，如果是负数，将以开始字符串$start最后出现的位置为起点
 * @return string|bool      返回false即未找到
 */
function strFind(string $content, $start, $end, $offset, &$findPos = 0)
{
    if (is_string($start)) {
        $startlen = mb_strlen($start);
    } else {
        $startlen = $start[1];
        $start = $start[0];
    }
    if ($offset < 0) {
        $startPos = mb_strrpos($content, $start, $offset);
    } else {
        $startPos = mb_strpos($content, $start, $offset);
    }
    if ($startPos === false) {
        return false;
    }
    $endPos = mb_strpos($content, $end, $startPos + $startlen);
    if ($endPos === false) {
        return false;
    }
    $findPos = $startPos;
    return trim(mb_substr($content, $startPos + $startlen, $endPos - $startPos - $startlen));
}

class SmartStrPos
{
    private $offset = 0;
    private $trail = 0;
    private $content = '';
    private $contentLen = 0;
    private $bakContent;
    public function __construct(string $content, string $needle, int $offset = 0, bool $pre = false)
    {
        $this->content = $content;
        $this->contentLen = mb_strlen($content);
        $this->bakContent = $this->content;
        if (!$pre) {
            $this->offset = $offset;
            $this->next($needle);
        }
    }
    public function reset()
    {
        $this->offset = 0;
        $this->trail  = 0;
        $this->content = $this->bakContent;
    }

    public function limit($needle)
    {
        if ($needle === 0) {
            $this->content = $this->bakContent;
            return 0;
        } elseif (is_int($needle)) {
            $this->content = mb_substr($this->content, 0, $needle);
            return $needle;
        }
        $limit = mb_strpos($this->content, $needle);
        $this->content = mb_substr($this->content, 0, $limit);
        return $limit;
    }

    private function trailPos(string $needle)
    {
        $this->trail = $this->offset + mb_strlen($needle);
    }
    /**
     * 从当前偏移量向后查找第一个出现的$needle
     *
     * @param string $needle
     * @param boolean $movie
     * @return int
     */
    public function back(string $needle, bool $movie = true)
    {
        $coffset = mb_strrpos($this->content, $needle, $this->offset - $this->contentLen);
        if ($coffset !== false && $movie) {
            $this->offset  = $coffset;
            $this->trailPos($needle);
        }
        return $coffset;
    }

    public function next(string $needle)
    {
        $coffset = mb_strpos($this->content, $needle, $this->trail);
        if ($coffset !== false) {
            $this->offset  = $coffset;
            $this->trailPos($needle);
        }
        return  $coffset;
    }

    public function nextSub(string $start, string $end = null, $move = false)
    {
        $findPos = 0;
        if (!$end) {
            $findPos = $this->next($start);
            if (false === $findPos) {
                return false;
            }
            $str = $this->sub($findPos + mb_strlen($start));
        } else {
            $str = strFind($this->content, $start, $end, $this->trail, $findPos);
        }
        if ($move && $str) {
            $this->offset = $findPos;
            $this->trailPos($str);
        }
        return $str;
    }

    /**
     * 从当前偏移位置，向后查找第一个$start,然后向前查找到$end处，返回期间字符串
     *
     * @param string $start
     * @param string $end
     * @param boolean $move
     * @return string
     */
    public function backSub(string $start, string $end, $move = false)
    {
        $findPos = 0;
        $str = strFind($this->content, $start, $end, $this->offset - $this->contentLen, $findPos);
        if ($move && $str) {
            $this->offset = $findPos;
            $this->trailPos($str);
        }
        return $str;
    }

    public function sub($start, $length = null)
    {
        if ($length === null) {
            return mb_substr($this->content, $start);
        }
        return mb_substr($this->content, $start, $length);
    }

    /**
     * 会移动偏移量
     *
     * @param string $start
     * @param string $end
     * @return SmartStrPos
     */
    public function nextRange(string $start, string $end)
    {
        $str = $this->nextSub($start, $end,  true);
        if ($str) {
            return self::begin($str);
        }
        return false;
    }

    public function backRange(string $start, string $end)
    {
        $str = $this->backSub($start, $end, true);
        if ($str) {
            return self::begin($str);
        }
        return false;
    }

    protected function match($str, $suffixArr, $offset, &$len)
    {
        $pos = false;
        foreach ($suffixArr as $suffix) {
            $len = mb_strlen($str . $suffix);
            $pos = mb_strpos($this->content, $str . $suffix, $offset);
            if ($pos) {
                break;
            }
        }
        return $pos;
    }

    public function count($needle, $start = false)
    {
        $offsetContent = $this->content;
        if ($start && $this->offset) {
            $offsetContent = mb_substr($this->content, $this->offset);
        }
        return mb_substr_count($offsetContent, $needle);
    }

    public function nextPair($startPairFlag, $startPair, $endPair)
    {
        return $this->nextPairMatch($startPairFlag, $startPair, null, $endPair, null);
    }

    /**
     * 移动偏移量
     *
     * @param string $startPairFlag
     * @param string $startPair
     * @param array|null $startPairSuffix
     * @param string $endPair
     * @param array|null $endPairSuffix
     * @return SmartStrPos
     */
    public function nextPairMatch(string $startPairFlag, string $startPair, ?array $startPairSuffix, string $endPair, ?array $endPairSuffix)
    {
        $startPos = $this->next($startPairFlag);
        if ($startPos === false) {
            return false;
        }
        $needleLen = mb_strlen($startPairFlag);
        $endPosOffset = $pairPosOffset = ($startPos + $needleLen);

        $pairLen = mb_strlen($endPair);
        $endPairLen = mb_strlen($endPair);

        do {
            if ($endPairSuffix) {
                $endPos = $this->match($endPair, $endPairSuffix, $endPosOffset, $endPairLen);
            } else {
                $endPos = mb_strpos($this->content, $endPair, $endPosOffset);
            }
            if (!$endPos) {
                return false;
            }
            if ($startPairSuffix) {
                $pairPos = $this->match($startPair, $startPairSuffix, $pairPosOffset, $pairLen);
            } else {
                $pairPos = mb_strpos($this->content, $startPair, $pairPosOffset);
            }

            if (!$pairPos || $pairPos > $endPos) {
                $str = mb_substr($this->content, $startPos + $needleLen, $endPos - $startPos - $needleLen);
                $this->offset = $startPos;
                $this->trail = $endPos + mb_strlen($endPair);
                return self::begin($str);
            } elseif ($pairPos === $endPos) {
                trigger_error('start and end pair name is ambiguous', E_USER_WARNING);
                return false;
            }
            $endPosOffset = $endPos + $endPairLen;
            $pairPosOffset = $pairPos + $pairLen;
            if ($endPosOffset > $this->contentLen || $pairPosOffset > $this->contentLen) {
                return false;
            }
        } while (true);
    }

    public function trail()
    {
        return $this->trail;
    }
    public function pos()
    {
        return $this->offset;
    }

    public static function begin(string $content)
    {
        return new static($content, '', 0, true);
    }
    public function __get($name)
    {
        return $this->$name;
    }
    public function __toString()
    {
        return $this->content;
    }
}

function smartStrPos(string $content, string $needle, int $offset = 0)
{
    return new SmartStrPos($content, $needle, $offset);
}

function ffmpegGetMediaMeta($file,$key, $flag)
{
    $flags = ['AV_DICT_MATCH_CASE' =>1, 'AV_DICT_IGNORE_SUFFIX' =>2, 
    'AV_DICT_DONT_STRDUP_KEY'=>4,'AV_DICT_DONT_STRDUP_VAL'=>8, 
    'AV_DICT_DONT_OVERWRITE'=>16,'AV_DICT_APPEND'=>32,'AV_DICT_MULTIKEY'=>64];
    if(!isset($flags[$flag])) {
        trigger_error('av dict flags error', E_USER_NOTICE);
        return false;
    }

    $cCode = <<<EOF
    typedef struct AVDictionaryEntry {char *key;char *value;} AVDictionaryEntry;
    typedef struct AVDictionary {int count;AVDictionaryEntry *elems;} AVDictionary;
    typedef struct AVIOInterruptCB {int (*callback)(void*); void *opaque;} AVIOInterruptCB;
    typedef struct AVInputFormat {
    const char *name;const char *long_name;int flags;const char *extensions;
    const void *codec_tag;const void *priv_class;
    const char *mime_type;struct AVInputFormat *next;int raw_codec_id;int priv_data_size;
    int (*read_probe)(const void *);int (*read_header)(struct AVFormatContext *);
    int (*read_packet)(struct AVFormatContext *, void *pkt);int (*read_close)(struct AVFormatContext *);
    int (*read_seek)(struct AVFormatContext *,int stream_index, int64_t timestamp, int flags);
    int64_t (*read_timestamp)(struct AVFormatContext *s, int stream_index,int64_t *pos, int64_t pos_limit);
    int (*read_play)(struct AVFormatContext *);int (*read_pause)(struct AVFormatContext *);
    int (*read_seek2)(struct AVFormatContext *s, int stream_index, int64_t min_ts, int64_t ts, int64_t max_ts, int flags);
    int (*get_device_list)(struct AVFormatContext *s, struct AVDeviceInfoList *device_list);
    int (*create_device_capabilities)(struct AVFormatContext *s, struct AVDeviceCapabilitiesQuery *caps);
    int (*free_device_capabilities)(struct AVFormatContext *s, struct AVDeviceCapabilitiesQuery *caps);
    } AVInputFormat;
    typedef int (*av_format_control_message)(struct AVFormatContext *s, int type,
                                         void *data, size_t data_size);
    typedef struct AVFormatContext {
    const void *av_class;struct AVInputFormat *iformat;void *oformat;void *priv_data;
    void *pb;int ctx_flags;unsigned int nb_streams;void **streams;char *url;int64_t start_time;
    int64_t duration;int64_t bit_rate;unsigned int packet_size;int max_delay;int flags;int64_t probesize;
    int64_t max_analyze_duration;const uint8_t *key;int keylen;unsigned int nb_programs;
    void **programs;int video_codec_id;int audio_codec_id;int subtitle_codec_id;
    unsigned int max_index_size;unsigned int max_picture_buffer;unsigned int nb_chapters;
    void **chapters;AVDictionary *metadata;int64_t start_time_realtime;int fps_probe_size;
    int error_recognition;AVIOInterruptCB interrupt_callback;int debug;int64_t max_interleave_delta;
    int event_flags;int max_ts_probe;int avoid_negative_ts;int ts_id;int audio_preload;
    int max_chunk_duration;int max_chunk_size;int use_wallclock_as_timestamps;int avio_flags;
    int duration_estimation_method;int64_t skip_initial_bytes;unsigned int correct_ts_overflow;
    int seek2any;int flush_packets;int probe_score;int format_probesize;char *codec_whitelist;
    char *format_whitelist;void *internal;int io_repositioned;void *video_codec;void *audio_codec;
    void *subtitle_codec;void *data_codec;int metadata_header_padding;void *opaque;
    av_format_control_message control_message_cb;int64_t output_ts_offset;uint8_t *dump_separator;
    int data_codec_id;char *protocol_whitelist;
    int (*io_open)(struct AVFormatContext *s, void **pb, const char *url,int flags, AVDictionary **options);
    void (*io_close)(struct AVFormatContext *s, void *pb);char *protocol_blacklist;
    int max_streams;int skip_estimate_duration_from_pts; int max_probe_packets;
    } AVFormatContext;
    int avformat_open_input(AVFormatContext **ps, const char *url, AVInputFormat *fmt, AVDictionary **options);
    AVDictionaryEntry *av_dict_get(const AVDictionary *m, const char *key, const AVDictionaryEntry *prev, int flags);
    void avformat_close_input(AVFormatContext **ps);
    EOF;

    $ffi = FFI::cdef($cCode, '/usr/share/code/libffmpeg.so');
    $fmtCtx = FFI::addr($ffi->new('AVFormatContext', false, true)); 
    $tag = FFI::addr($ffi->new('AVDictionaryEntry'));

    $ffi->avformat_open_input(FFI::addr($fmtCtx), $file, NULL, NULL);
    $ret = [];
    if(empty($key)) {
        $key  ='';
        while(($tag = $ffi->av_dict_get($fmtCtx->metadata, $key, $tag, $flags[$flag]))) {
            var_dump($tag);
            $key  =FFI::string($tag->key);
            $tagValue = FFI::string($tag->value);
            $ret[$key] = $tagValue;
        }
    } else {
        $tag = $ffi->av_dict_get($fmtCtx->metadata, '', $tag, $flags[$flag]);
        $key  =FFI::string($tag->key);
        $tagValue = FFI::string($tag->value);
        $ret[$key] = $tagValue;
    }
    $ffi->avformat_close_input(FFI::addr($fmtCtx)); 
    return $ret;
}

function getVideoMeta($file): array
{
    $command = "ffmpeg -hide_banner -i '$file' 2>&1";
    $output = [];
    exec($command, $output, $returnVar);
    exec("mplayer -nolirc -vo null -ao null -frames 0 -identify '$file' 2>&1", $output, $returnVar);
    if ($returnVar > 0) {
        trigger_error('use mplayer get video meta error');
        return [];
    }
    $meta = arrayFindFieldValue($output, [
        'major_brand',
        'minor_version',
        'compatible_brands',
        'encoder',
        'handler_name',
        'Duration',
        'Stream #0',
        'ID_VIDEO_BITRATE',
        'ID_AUDIO_BITRATE',
        'ID_SEEKABLE',
        'ID_VIDEO_FPS',
        'ID_VIDEO_WIDTH',
        'ID_VIDEO_HEIGHT'
    ]);
    $videoInfoArr = explode(',', $meta['Stream #0'][0]);
    $sarWH = [0, 0];
    $sarValue = 0;
    foreach ($videoInfoArr as $vinfo) {
        if (($idx = strpos($vinfo, 'Video:')) !== false) {
            list($codename) = explode(' ', trim(substr($vinfo, $idx)), 2);
        } elseif (strpos($vinfo, '[SAR') !== false) {
            $size = explode(' ', trim($vinfo));
            $sarWH = explode(':', $size[2]);
            $sarValue = round($sarWH[0] / $sarWH[1], 6);
        }
    }
    $pixfmt = trim($videoInfoArr[1]);
    $audioInfo = explode(',', $meta['Stream #0'][1]);
    foreach ($audioInfo as $ainfo) {
        if (($idx = strpos($ainfo, 'Audio:')) !== false) {
            list($acodename) = explode(' ', trim(substr($ainfo, $idx)), 2);
        } elseif (strpos($ainfo, 'Hz') !== false) {
            list($hz) = explode(' ', trim($ainfo), 2);
        }
    }
    $return = [];
    $durationInfo = explode(',', $meta['Duration']);
    $return['time'] = trim($durationInfo[0]);
    $return['width'] = $meta['ID_VIDEO_WIDTH'];
    $return['height'] = $meta['ID_VIDEO_HEIGHT'];
    $return['fps'] = $meta['ID_VIDEO_FPS'];
    $return['video_bitrate'] = $meta['ID_VIDEO_BITRATE'][0];
    $return['mjor'] = $meta['major_brand'][0];
    $return['minor'] = $meta['minor_version'][0];
    $return['compatible_brands'] = $meta['compatible_brands'][0];
    $return['encoder'] = $meta['encoder'][0];
    $return['seekable'] = $meta['ID_SEEKABLE'];
    $return['video_codename'] = $codename;
    $return['video_handler_name'] = $meta['handler_name'][0];
    $return['video_pix_fmt'] = $pixfmt;
    $return['video_aspect_ratio'] = $sarValue;
    $return['video_aspect_v'] = $sarWH[0];
    $return['video_aspect_h'] = $sarWH[1];
    $return['audio_codename'] = $acodename;
    $return['audio_rate'] = $hz;
    $return['audio_fmt'] = $audioInfo[3];
    $return['audio_handler_name'] = $meta['handler_name'][1];
    return $return;
}

function arrayFindStr($array, $needle)
{
    if (is_array($needle)) {
        $return = [];
        foreach ($array as $line) {
            foreach ($needle as $v) {
                if (strpos($line, $v) !== false) {
                    $return[$needle] = $line;
                }
            }
        }
        return $return;
    } else {
        foreach ($array as $line) {
            if (strpos($line, $needle) !== false) {
                return $line;
            }
        }
    }
    return false;
}

function getStrFieldValue(string $str, array $seplist = ['=', ':'], string &$field = '')
{
    $line = trim($str);
    if (!$field) {
        foreach ($seplist as $sep) {
            if (strpos($line, $sep) > 0) {
                list($field, $val) = explode($sep, $line, 2);
                return trim($val);
            }
        }
    } elseif (strpos($str, $field) >= 0) {
        foreach ($seplist as $sep) {
            if (strpos($line, $sep) > 0) {
                list($fieldNew, $val) = explode($sep, $line, 2);
                if (trim($fieldNew) == $field) {
                    return trim($val);
                }
            }
        }
    }
    return null;
}

function hasStr($str, $list = [])
{
    foreach ($list as $s) {
        if (strpos($str, $s) >= 0) {
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
function arrayFindFieldValue(array $array, $needle, array $addSep = [], string $parentSep = ':')
{
    $sep = ['=', ':'];
    $sep = array_merge($sep, $addSep);
    if (is_array($needle)) {
        $return = [];
        foreach ($needle as $v) {
            $hasParent = false;
            $field = $v;
            if (strpos($v, $parentSep) > 0) {
                list($parent, $field) = explode($parentSep, $v, 2);
                $parent = trim($parent);
                $field = trim($field);
                $hasParent = true;
            }

            foreach ($array as $n => $line) {
                if ($hasParent) {
                    $pval = getStrFieldValue($line, [$parentSep], $parent);
                    if ($pval !== null) {
                        $hasParent = false;
                    }
                }
                if (!$hasParent) {
                    $val = getStrFieldValue($line, $sep, $field);
                    if ($val !== null) {
                        if (isset($return[$v])) {
                            if (!is_array($return[$v])) {
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
        foreach ($array as $line) {
            $val = getStrFieldValue($line, $sep, $needle);
            if ($val !== null) {
                return $val;
            }
        }
    }
    return null;
}

function getDirFile($path)
{
    $d =  dir($path);
    $file = [];
    while (false !== ($f = $d->read())) {
        if ($f == '.' || $f == '..') {
            continue;
        }
        $file[] = $f;
    }
    return $file;
}

function calThreshold($number, $threshold, $sep = '')
{
    $res = [];
    $int = floor($number);
    foreach ($threshold as $val) {
        if ($int >= $val) {
            $res[] = floor($int / $val);
            $int = $int % $val;
        } else {
            $res[] = 0;
        }
    }
    $res[] = $int;
    return strlen($sep) > 0 ? join($sep, $res) : $res;
}

function getTTYSize()
{
    return explode(' ', exec('stty size'));
}

function isFloatNumber($number)
{
    if (!is_numeric($number)) {
        return false;
    }
    if (strpos($number, '.')) {
        return true;
    }
    return false;
}

function getDecimal($number)
{
    if (isFloatNumber($number)) {
        list(, $decimal) = explode('.', $number);
        return $decimal;
    }
    return 0;
}

function videoTime($time)
{
    if (is_numeric($time)) {
        $res = calThreshold($time, [3600, 60]);
        array_walk($res, function (&$v) {
            $v = str_pad($v, 2, 0, STR_PAD_LEFT);
        });
        $str = join(':', $res);
        $res[] = str_pad(getDecimal($time), 3, 0);
        return $str . ".{$res[3]}";
    } else {
        $res = explode(':', $time);

        $len = count($res);
        if ($len >= 3) {
            $res[0] = $res[0] * 3600;
            $res[1] = $res[1] * 60;
        } else {
            $res[0] = $res[0] * 60;
        }
        return array_sum($res);
    }
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
    $idx = strpos($str, $endStr);
    if ((strlen($str) - $idx) === strlen($endStr)) {
        return true;
    }
    return false;
}

function isUpDomain($subDomain, $upDomain)
{
    $subLvl = substr_count($subDomain, '.');
    $upLvl = substr_count($upDomain, '.');
    if ($upLvl == $subLvl && $subDomain == $upDomain) {
        return 0;
    } elseif ($upLvl < $subLvl && checkStrSuffix($subDomain, ".$upDomain")) {
        return 1;
    }
    return -1;
}

class Fetch
{
    private $data = '';
    private $retCode = 0;
    private $error = '';
    private $errCode = 0;
    public static $CURLOPT_USERAGENT = null;
    public static $CURLOPT_COOKIE = NULL;
    public static $CURLOPT_CONNECTTIMEOUT = 10;
    public static $CURLOPT_FOLLOWLOCATION = 1;
    public static $CURLOPT_MAXREDIRS = 10;
    public static $autoLastReferer = false;
    public static $lastUrl = '';
    public function __construct($url, $opt = [])
    {
        $ch1 = curl_init();
        $defOpt  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::$CURLOPT_CONNECTTIMEOUT,
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => self::$CURLOPT_FOLLOWLOCATION,
            CURLOPT_MAXREDIRS =>  self::$CURLOPT_MAXREDIRS,
        ];
        if (self::$autoLastReferer) {
            $defOpt[CURLOPT_REFERER] = self::$lastUrl;
        }

        self::$lastUrl = $url;
        if (isset($opt[CURLOPT_URL])) {
            self::$lastUrl = $opt[CURLOPT_URL];
        }

        if (self::$CURLOPT_USERAGENT !== null) {
            $defOpt[CURLOPT_USERAGENT] = self::$CURLOPT_USERAGENT;
        }
        if (self::$CURLOPT_COOKIE !== null) {
            if (is_array(self::$CURLOPT_COOKIE)) {
                $host = parse_url($url,  PHP_URL_HOST);
                foreach (self::$CURLOPT_COOKIE as $cookeDomain => $cookie) {
                    if (isUpDomain($host, $cookeDomain) >= 0) {
                        $defOpt[CURLOPT_COOKIE] = $cookie;
                    }
                }
            } else {
                $defOpt[CURLOPT_COOKIE] = self::$CURLOPT_COOKIE;
            }
        }
        foreach ($opt as $key => $val) {
            $defOpt[$key] = $val;
        }
        curl_setopt_array($ch1, $defOpt);
        $this->data = curl_exec($ch1);
        $this->retCode = curl_getinfo($ch1,  CURLINFO_HTTP_CODE);
        $this->errCode  = curl_errno($ch1);
        $this->error = curl_error($ch1);
        curl_close($ch1);
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __toString()
    {
        return (string)$this->data;
    }
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
    $defOpt = [
        '-w' => 5,
        '-T' => 5,
        '-t' => 1,
        '-q',
        '-O' => $output,
    ];
    $url = escapeshellarg($url);
    if (isset($_ENV['WGET_USER_AGENT'])) {
        $defOpt['--user-agent'] = $_ENV['WGET_USER_AGENT'];
    }
    foreach ($opt as $k => $v) {
        $defOpt[$k] = $v;
    }
    $option = '';
    foreach ($defOpt as $k => $v) {
        $v = escapeshellarg($v);
        if (is_numeric($k)) {
            $option .= " $v";
        } elseif (strlen($k) > 2) {
            $option .= " $k=$v";
        } else {
            $option .= " $k $v";
        }
    }
    $returnVar = 0;
    passthru("wget $option $url", $returnVar);
    if (file_exists($output) && !filesize($output) && $returnVar) {
        trigger_error("wget has error and file($output) size is 0, Removed!", E_USER_WARNING);
        unlink($output);
    }
    return $returnVar;
}

/**
 * 清理命令行
 *
 * @param int $selfWidth
 * @return void
 */
function clearTTYLine($selfWidth = null)
{
    static $ttywidth;
    if (!$ttywidth && !$selfWidth) {
        list(, $ttywidth) = getTTYSize();
    } elseif ($selfWidth) {
        $ttywidth = $selfWidth;
    }
    echo "\r" . str_repeat(' ', $ttywidth);
}

function strCountNumerOfLetter($str, $isnum)
{
    $letter = $isnum ? range(0, 9) : range('A', 'Z');
    $count = 0;
    foreach ($letter as $num) {
        $count += mb_substr_count($str, $num);
    }
    return $count;
}

function saveArray($file, $array)
{
    file_put_contents($file, '<?php return ' . var_export($array, true) . ';');
}

function pathJoin(...$args)
{
    $root =  '';
    if (strpos($args[0], DIRECTORY_SEPARATOR) === 0) {
        $root = DIRECTORY_SEPARATOR;
    }
    array_walk($args, function (&$v) {
        $v = trim($v, '\\/ ');
    });

    return  $root . join(DIRECTORY_SEPARATOR, $args);
}

function rPathJoin(...$args)
{
    return realpath(call_user_func_array('pathJoin', $args));
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
    foreach ($array as $k => &$v) {
        $res = $callback($v, $k, $userdata);
        if ($res !== null) {
            return $res;
        }
        if (is_array($v) && ($res = rloopArray($v, $callback, $userdata)) !== null) {
            return $res;
        }
    }
    return null;
}
/**
 * 树查找，返回找到的路径
 *
 * @param [array $array
 * @param mixed $value      可为函数或具体值
 * @return array
 */
function findTree(array $array, $value)
{
    static $treePath = [];
    foreach ($array as $k => $v) {
        if ($v === $value || (is_callable($value) && $value($v, $k) === 0)) {
            $treePath[] = $k;
            return $treePath;
        } elseif (is_array($array)) {
            $treePath[] = $k;
            $subTree = findTree($v, $value);
            if ($subTree !== $treePath) {
                return $subTree;
            }
        }
    }
    array_pop($treePath);
    return $treePath;
}
