<?php
/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Network;

use Toknot\Path\PathExistsException;

class Fetch
{
    protected static $curlAvailable = false;
    protected static $curlSafeOpt   = false;

    protected $returnRaw             = true;
    protected $cookie                = '';
    protected $agent                 = false;
    protected $url                   = '';
    protected $referer               = '';
    protected $user                  = '';
    protected $pwd                   = '';
    protected $port                  = 0;
    protected $autoFollow            = true;
    protected $maxRedirs             = 10;
    protected $curlOtherOpt          = [];
    protected $responseHeader        = [];
    protected $disableHeaderCallback = false;
    protected $headerCallback        = null;
    protected $lastErrno             = 0;
    protected $lastError             = '';
    public function __construct($url)
    {
        $this->url = $url;
        $this->checkCURL();
    }

    protected function checkCURL()
    {
        if (!self::$curlAvailable) {
            if (\extension_loaded('curl')) {
                self::$curlAvailable = true;
            }
        }
        if (self::$curlAvailable && !self::$curlSafeOpt && defined('CURLOPT_SAFE_UPLOAD')) {
            self::$curlSafeOpt = true;
        }
    }

    protected function open($op)
    {
        if (self::$curlAvailable) {
            $ch = \curl_init($this->url);
            \curl_setopt_array($ch, $op);
            $res             = \curl_exec($ch);
            $this->lastErrno = \curl_errno($ch);
            $this->lastError = \curl_error($ch);
            \curl_close($ch);
            return $res;
        } else {
            $cxt = \stream_context_create($opt);
            $res = \file_get_contents($url, $cxt);
            if (!$this->disableHeaderCallback) {
                $this->responseHeader = \get_headers();
            }
            return $res;
        }

    }

    protected function setPHPStramOpt()
    {
        $op = ['http' => ['header' => '']];
        if ($this->cookie) {
            if (\is_array($this->cookie)) {
                $cookie = \http_build_query($this->cookie, '', ';');
            } elseif (is_file($this->cookie)) {
                $cookie = \file_get_contents($this->cookie);
            }
            $cookie = trim($cookie, "\r\n");
            $op['http']['header'] .= "Cookie: $cookie\r\n";
        }
        if ($this->referer) {
            $op['http']['header'] .= "Referer: {$this->referer}\r\n";
        }
        if ($this->autoFollow) {
            $op['http']['follow_location'] = 1;
            $op['http']['max_redirects']   = $this->maxRedirs;
        } else {
            $op['http']['follow_location'] = 0;
        }
        if ($this->agent) {
            $op['http']['user_agent'] = $agent;
        }

        return $op;
    }

    protected function setCurlOpt()
    {
        $op = [];
        if (\is_array($this->cooke)) {
            $cookieStr          = http_build_query($this->cooke, '', ';');
            $op[CURLOPT_COOKIE] = $cookieStr;
        } elseif ($this->cookie && is_file($this->cooke)) {
            $op[CURLOPT_COOKIEFILE] = $this->cooke;
        }
        if ($this->returnRaw) {
            $op[CURLOPT_RETURNTRANSFER] = true;
        }
        if ($this->agent) {
            $op[CURLOPT_USERAGENT] = $this->agent;
        }
        if ($this->referer) {
            $op[CURLOPT_REFERER] = $this->referer;
        }
        if ($this->user) {
            $op[CURLOPT_USERPWD] = "{$this->user}:{$this->pwd}";
        }
        if ($this->port) {
            $op[CURLOPT_PORT] = $this->port;
        }
        $op[CURLOPT_AUTOREFERER] = true;
        if ($this->autoFollow) {
            $op[CURLOPT_FOLLOWLOCATION] = true;
            $op[CURLOPT_MAXREDIRS]      = $this->maxRedirs;
        }
        if (!$this->disableHeaderCallback) {
            $op[CURLOPT_HEADERFUNCTION] = $this->headerFunction();
        }
        foreach ($this->curlOtherOpt as $k => $v) {
            $op[$k] = $v;
        }
        return $op;
    }

    protected function setOpt()
    {
        if (!self::$curlAvailable) {
            return $this->setPHPStramOpt();
        } else {
            return $this->setCurlOpt();
        }
    }

    protected function disableHeaderCallback()
    {
        $this->disableHeaderCallback = true;
        return $this;
    }

    protected function headerFunction()
    {
        return function ($ch, $header) {
            $this->responseHeader[] = $header;
            if ($this->headerCallback) {
                $func = $this->headerCallback;
                $func($ch, $header);
            }
            return strlen($header);
        };
    }

    protected function upload($file, &$op)
    {
        if (\is_null($file)) {
            return [];
        }
        if (is_array($file)) {
            $op = [];
            foreach ($file as $key => $file) {
                if (\is_file($file)) {
                    $op[$key] = new CURLFile($file);
                }
            }
            return $op;
        }
        throw new \InvalidArgumentException('upload file type error, only array within key/filepath');
    }

    public function disableAutoFollow()
    {
        $this->autoFollow = false;
        return $this;
    }

    public function getError()
    {
        return $this->lastError;
    }

    public function getErrno()
    {
        return $this->lastErrno;
    }

    public function setMaxRedirs($num)
    {
        $this->maxRedirs = $num;
        return $this;
    }

    public function setCookie($cookie)
    {
        $this->cookie = $cookie;
        return $this;
    }

    public function setReferer($referer)
    {
        $this->referer = $referer;
        return $this;
    }

    public function setUser($user, $pwd = null)
    {
        $this->user = $user;
        if ($pwd) {
            $this->pwd = $pwd;
        }
        return $this;
    }

    public function setPwd($pwd)
    {
        $this->pwd = $pwd;
        return $this;
    }

    public function setPort($port)
    {
        if (!\is_numeric($port)) {
            throw \InvalidArgumentException("host port only numeric");
        } elseif ($port < 1 || $port > 65535) {
            throw \OutOfRangeException("host port must between 1 - 65535");
        }
        $this->port = $port;
        return $this;
    }
    public function curlOpt($opt, $value = null)
    {
        if (\is_array($opt)) {
            $this->curlOtherOpt = $opt;
        } else {
            $this->curlOtherOpt[$opt] = $value;
        }
        return $this;
    }
    public function outputContet()
    {
        $this->returnRaw = false;
        return $this;
    }

    public function setUserAgent($agent = false)
    {
        $this->agent = $agent;
        return $this;
    }

    /**
     * http get request
     *
     * @return string
     */
    public function get()
    {
        $op = $this->setOpt();
        if (self::$curlAvailable) {
            $op[CURLOPT_HTTPGET] = true;
        } else {
            $op['http']['method'] = 'GET';
        }
        return $this->open($op);
    }

    /**
     * @return array
     */
    public function getResponseHeader()
    {
        return $this->responseHeader;
    }

    /**
     * use curl request url
     *
     * @return mixed    string or bool false
     */
    public function request()
    {
        $op = $this->setOpt();
        return $this->open($op);
    }

    /**
     * http post request
     *
     * @param  mixed $data      form feilds array, without upload file
     * @param  array $file      form upload file feilds
     * @param  array $form      whether is form
     * @return string
     * @throws \InvalidArgumentException    when $data not scalar or array
     *                                      when $file is set and not array
     */
    public function post($data, array $file = [], $form = true)
    {
        if (!\is_scalar($data) && !\is_array($data)) {
            throw new \InvalidArgumentException('paramter #1 must be string or array');
        }
        $op = $this->setOpt();
        if (self::$curlAvailable) {
            $op[CURLOPT_POST] = 1;
            if ($form && is_array($data) && $file) {
                if (self::$curlSafeOpt) {
                    $op[CURLOPT_SAFE_UPLOAD] = true;
                }
                $fileopt                = $this->upload($file, $data);
                $op[CURLOPT_POSTFIELDS] = $data;
            } elseif ($form && \is_array($data)) {
                $op[CURLOPT_POSTFIELDS] = \http_build_query($data);
            } else {
                $op[CURLOPT_POSTFIELDS] = $data;
            }
        } else {
            if ($file) {
                throw new \RuntimeException("php stream mode not support post file");
            }
            $op['http']['method']  = 'POST';
            $op['http']['content'] = is_array($data) ? \http_build_query($data) : $data;
        }
        return $this->open($op);
    }

    /**
     * download file
     *
     * @param string $file      save to file
     * @param bool $override    whether override exists file
     * @return bool
     * @throws \Toknot\Path\PathExistsException    when $override set false and give $file exist
     */
    public function download($file, $override = false)
    {
        if(!self::$curlAvailable) {
            throw new \RuntimeException('\Toknot\Network\Fetch::download() method need curl');
        }
        if (!$override && \file_exists($file)) {
            throw new PathExistsException("$file is exists");
        }
        $fp                         = \fopen($file, 'w+');
        $op                         = $this->setOpt();
        $op[CURLOPT_FILE]           = $fp;
        $op[CURLOPT_RETURNTRANSFER] = false;
        return $this->open($op);
    }

}
