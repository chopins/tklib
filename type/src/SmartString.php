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
 * SmartString
 *
 * @author chopin
 * @method int gBack(string $needle, bool $move)    see back()
 * @method int gNext(string $needle, bool $move)    see next()
 * @method int gNextSub(string $start, string $end, bool $move)     see nextSub()
 * @method SmartString|bool gBackRange()    see backRange()
 * @methodd string gBackSub(string $start, string $end)  see backSub()
 * @method SmartString gNextPair($startPairFlag, $startPair, $endPair) see nextPair()
 * @method SmartString gNextPairMatch(string $startPairFlag, string $startPair, ?array $startPairSuffix, string $endPair, ?array $endPairSuffix)        see nextPairMatch()
 * @method SmartString gNextRange(string $start, string $end)  see nextRange()
 */
class SmartString
{
    
    private $offset = 0;
    private $trail = 0;
    private $content = '';
    private $contentLen = 0;
    private $bakContent;

    /**
     * 初始化字符串查找类
     *
     * @param string $content   内容
     * @param integer $offset   开始查找时的起始偏移量
     */
    public function __construct(string $content, int $offset = 0)
    {
        $this->content = $content;
        $this->contentLen = mb_strlen($content);
        $this->bakContent = $this->content;
        $this->offset = $offset;
    }

    /**
     * 重置当前偏移量和尾偏移记录，还原内容
     *
     * @return void
     */
    public function reset()
    {
        $this->offset = 0;
        $this->trail = 0;
        $this->content = $this->bakContent;
    }

    /**
     * 限制到指定长度内容
     *
     * @param int|string $needle    设置内容长度上限，
     *                              0时为全部；
     *                              仅整数类型时将截取从0到指定值长度内容；
     *                              字符串时（包括字符串数字）将查找到该字符串位置，偏移量将不包括该字符串
     * @return void
     */
    public function limit($needle)
    {
        if($needle === 0) {
            $this->content = $this->bakContent;
            return 0;
        } elseif(is_int($needle)) {
            $this->content = mb_substr($this->content, 0, $needle);
            return $needle;
        }
        $limit = mb_strpos($this->content, $needle);
        $this->content = mb_substr($this->content, 0, $limit);
        return $limit;
    }

    /**
     * 
     * @param string $name
     * @param array $arguments
     * @return \Generator
     */
    public function __call(string $name, $arguments = [])
    {
        return $this->iterator($name, $arguments);
    }

    /**
     * 
     * @param string $name
     * @param array $args
     * @return \Generator
     * @throws Error
     */
    protected function iterator(string $name, $args)
    {
        $class = self::class;
        if($name[0] != 'g') {
            throw new Error("Call to undefined method $class::$name()", E_USER_ERROR);
        }
        $method = lcfirst(substr($name, 1));
        $funcList = [
            'back', 'next', 'nextSub', 'backRange', 'backSub', 'nextPair',
            'nextPairMatch', 'nextRange'
        ];
        if(!in_array($method, $funcList)) {
            throw new Error("Call to undefined method $class::$name()", E_USER_ERROR);
        }
        do {
            $ret = call_user_func_array([$this, $method], $args);
            if($ret === false) {
                return $ret;
            }
            yield $ret;
        } while(true);
    }

    private function trailPos(string $needle)
    {
        $this->trail = $this->offset + mb_strlen($needle);
    }

    /**
     * 从当前偏移量回退查找第一个出现的$needle
     *
     * @param string $needle
     * @param boolean $move
     * @return int
     */
    public function back(string $needle, bool $move = true)
    {
        $coffset = mb_strrpos($this->content, $needle, $this->offset - $this->contentLen);
        if($coffset !== false && $move) {
            $this->offset = $coffset;
            $this->trailPos($needle);
        }
        return $coffset;
    }

    /**
     * 设置尾偏移量为起始量，尾偏移量位于查看字符串末尾
     *
     * @return void
     */
    public function trailToOffset()
    {
        $this->trail = $this->offset;
    }

    /**
     * 从上一次的尾偏移量开始，查找下一个指定字符串
     * 注：偏移量位于查找字符串开头
     *
     * @param string $needle    需要查找的字符串
     * @param boolean $move     是否移动偏移量，默认移动
     * @return int              返回偏移量
     */
    public function next(string $needle, bool $move = true)
    {
        $coffset = mb_strpos($this->content, $needle, $this->trail);
        if($coffset !== false && $move) {
            $this->offset = $coffset;
            $this->trailPos($needle);
        }
        return $coffset;
    }

    /**
     * 判断当前偏移前方（文件末尾方向）是否紧随给定字符串
     *
     * @param string $needle
     * @param boolean $ignoreEmpty  忽略空白字符，空白字符为 trim() 函数默认的去除的字符
     * @return bool
     */
    public function afterNear(string $needle, $ignoreEmpty = false)
    {
        $current = $this->trail;
        $cur = $this->next($needle, false);
        if($cur === false) {
            return false;
        }
        if($cur === $current) {
            return true;
        }
        if($ignoreEmpty) {
            return empty(ltrim(mb_substr($this->content, $current, $cur - $current)));
        }
    }

    /**
     * 检测多个字符串
     *
     * @param array $needle
     * @param boolean $ignoreEmpty
     * @return bool
     */
    public function afterNearMatch(array $needle, $ignoreEmpty = false)
    {
        foreach($needle as $n) {
            if($this->afterNear($n, $ignoreEmpty)) {
                return true;
            }
        }
        return false;
    }

    public function beforeNearMatch(array $needle, $ignoreEmpty = false)
    {
        foreach($needle as $n) {
            if($this->beforeNear($n, $ignoreEmpty)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断当前偏移后方（文件头方向）是否紧随给定字符串
     *
     * @param string $needle
     * @param boolean $ignoreEmpty  忽略空白字符，空白字符为 ltrim() 函数默认的去除的字符
     * @return bool
     */
    public function beforeNear(string $needle, $ignoreEmpty = false)
    {
        $current = $this->offset;
        $cur = $this->back($needle, false);
        if($cur === false) {
            return false;
        }
        if($this->trail === $current) {
            return true;
        }
        if($ignoreEmpty) {
            return empty(ltrim(mb_substr($this->content, $cur, $current - $cur)));
        }
    }

    /**
     * 从上一次的尾偏移量开始，获取下一个子字符串，查找范围不包括起始与结束字符串，
     *
     * @param string $start         起始字符串
     * @param string|null $end      结束字符串
     * @param boolean $move         是否移动偏移量，偏移量位于开始字符串首
     * @return string
     */
    public function nextSub(string $start, string $end = null, $move = false)
    {
        $findPos = 0;
        if(!$end) {
            $findPos = $this->next($start);
            if(false === $findPos) {
                return false;
            }
            $str = $this->sub($findPos + mb_strlen($start));
        } else {
            $str = strFind($this->content, $start, $end, $this->trail, $findPos);
        }
        if($move && $str) {
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
        if($move && $str) {
            $this->offset = $findPos;
            $this->trailPos($str);
        }
        return $str;
    }

    /**
     * 获取子字符串
     *
     * @param int $start
     * @param int $length
     * @return string
     */
    public function sub($start, $length = null)
    {
        if($length === null) {
            return mb_substr($this->content, $start);
        }
        return mb_substr($this->content, $start, $length);
    }

    /**
     * 在偏移量后，获取子字符串的SmartString实例
     *  注：不包括起始与结束字符串
     * @param string $start     起始字符串
     * @param string $end       结束字符串
     * @return SmartString|bool
     */
    public function nextRange(string $start, string $end)
    {
        $str = $this->nextSub($start, $end, true);
        if($str) {
            return self::begin($str);
        }
        return false;
    }

    /**
     * 在偏移量前，获取子字符串SmartString实例
     *
     * @param string $start
     * @param string $end
     * @return SmartString|bool
     */
    public function backRange(string $start, string $end)
    {
        $str = $this->backSub($start, $end, true);
        if($str) {
            return self::begin($str);
        }
        return false;
    }

    /**
     * 多规则字符串查找
     *
     * @param string $str           查找字符开头部分
     * @param array $suffixArr      查找字符串尾列表
     * @param int $offset           查找偏移
     * @param int $len              匹配字符串长度
     * @return int
     */
    protected function match($str, $suffixArr, $offset, &$len)
    {
        $pos = false;
        $prepos = mb_strpos($this->content, $str, $offset);
        if($prepos === false) {
            return $pos;
        }
        foreach($suffixArr as $suffix) {
            $len = mb_strlen($str . $suffix);
            $pos = mb_strpos($this->content, $str . $suffix, $offset);
            if($pos !== false) {
                break;
            }
        }
        return $pos;
    }

    /**
     * 子字符串长度
     *
     * @param string $needle
     * @param boolean $start
     * @return void
     */
    public function count($needle, $start = false)
    {
        $offsetContent = $this->content;
        if($start && $this->offset) {
            $offsetContent = mb_substr($this->content, $this->offset);
        }
        return mb_substr_count($offsetContent, $needle);
    }

    /**
     * 在指定字符串后获取子字符串
     *
     * @param string $startPairFlag
     * @param string $startPair
     * @param string $endPair
     * @return SmartString
     */
    public function nextPair($startPairFlag, $startPair, $endPair)
    {
        return $this->nextPairMatch($startPairFlag, $startPair, null, $endPair, null);
    }

    /**
     * 在指定字符串后获取子字符串，多规则查找
     * 
     * <code>
     * $str = new SmartString('123abcd45-123efg45=123higk45');
     * $str->nextPairMatch('=','123',null, '45', null); // higk
     * </code>
     *
     * @param string $startPairFlag             该字符串后查找
     * @param string $startPair                 截取起始字符串前缀
     * @param array|null $startPairSuffix       截取起始字符串后缀列表
     * @param string $endPair                   截取结束字符串前缀
     * @param array|null $endPairSuffix         截取结束字符串后缀列表
     * @return SmartString
     */
    public function nextPairMatch(string $startPairFlag, string $startPair, ?array $startPairSuffix, string $endPair, ?array $endPairSuffix)
    {
        $startPos = $this->next($startPairFlag);

        if($startPos === false) {
            return false;
        }
        $needleLen = mb_strlen($startPairFlag);
        $endPosOffset = $pairPosOffset = $this->trail;

        $pairLen = mb_strlen($endPair);
        $endPairLen = mb_strlen($endPair);

        do {
            if($endPairSuffix) {
                $endPos = $this->match($endPair, $endPairSuffix, $endPosOffset, $endPairLen);
            } else {
                $endPos = mb_strpos($this->content, $endPair, $endPosOffset);
            }

            if(!$endPos) {
                return false;
            }
            if($startPairSuffix) {
                $pairPos = $this->match($startPair, $startPairSuffix, $pairPosOffset, $pairLen);
            } else {
                $pairPos = mb_strpos($this->content, $startPair, $pairPosOffset);
            }

            if($pairPos && $pairPos < $endPos) {
                $str = mb_substr($this->content, $startPos + $needleLen, $endPos - $startPos - $needleLen);
                $this->offset = $startPos;
                $this->trail = $endPos + mb_strlen($endPair);
                return self::begin($str);
            } elseif($pairPos === $endPos) {
                throw new RangeException('start and end pair name is ambiguous');
            }
            $endPosOffset = $endPos + $endPairLen;
            $pairPosOffset = $pairPos + $pairLen;
            if($endPosOffset > $this->contentLen || $pairPosOffset > $this->contentLen) {
                return false;
            }
        } while(true);
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
        return new static($content);
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __toString()
    {
        return $this->content;
    }

    public static function __set_state($properties)
    {
        $pos = new static($properties['content'], $properties['offset']);
        foreach($properties as $name => $v) {
            $pos->$name = $v;
        }
        return $pos;
    }
}
