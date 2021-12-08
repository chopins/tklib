<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Network;

use Toknot\Type\Char;

/**
 * SimpleCurl
 *
 * @author chopin
 */
class SimpleCurl
{
     private string $data = '';
    private $retCode = 0;
    private string $error = '';
    private int $errCode = 0;
    public static ?string $CURLOPT_USERAGENT = null;
    public static ?string $CURLOPT_COOKIE = NULL;
    public static int $CURLOPT_CONNECTTIMEOUT = 10;
    public static bool $CURLOPT_FOLLOWLOCATION = true;
    public static int $CURLOPT_MAXREDIRS = 10;
    public static bool $autoLastReferer = false;
    public static string $lastUrl = '';
    public static bool $CURLOPT_DNS_USE_GLOBAL_CACHE = true;

    /**
     * 是否复用连接
     *
     * @var boolean
     */
    public static bool $CURL_CONNET_REUSE = true;
    public static $ch1 = null;

    public function __construct($url, $opt = [])
    {
        if(!self::$ch1 || !self::$CURL_CONNET_REUSE) {
            self::$ch1 = curl_init();
        }

        $defOpt = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::$CURLOPT_CONNECTTIMEOUT,
            CURLOPT_URL => $url,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_FOLLOWLOCATION => self::$CURLOPT_FOLLOWLOCATION,
            CURLOPT_MAXREDIRS => self::$CURLOPT_MAXREDIRS,
            CURLOPT_DNS_USE_GLOBAL_CACHE => self::$CURLOPT_DNS_USE_GLOBAL_CACHE,
        ];
        if(self::$autoLastReferer) {
            $defOpt[CURLOPT_REFERER] = self::$lastUrl;
        }

        self::$lastUrl = $url;
        if(isset($opt[CURLOPT_URL])) {
            self::$lastUrl = $opt[CURLOPT_URL];
        }

        if(self::$CURLOPT_USERAGENT !== null) {
            $defOpt[CURLOPT_USERAGENT] = self::$CURLOPT_USERAGENT;
        }
        if(self::$CURLOPT_COOKIE !== null) {
            if(is_array(self::$CURLOPT_COOKIE)) {
                $host = parse_url($url, PHP_URL_HOST);
                foreach(self::$CURLOPT_COOKIE as $cookeDomain => $cookie) {
                    if(Char::isUpDomain($host, $cookeDomain) >= 0) {
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
        curl_setopt_array(self::$ch1, $defOpt);
        $this->data = curl_exec(self::$ch1);
        $this->retCode = curl_getinfo(self::$ch1, CURLINFO_HTTP_CODE);
        $this->errCode = curl_errno(self::$ch1);
        $this->error = curl_error(self::$ch1);
        if(!self::$CURL_CONNET_REUSE) {
            curl_close(self::$ch1);
        }
    }

    public function getError()
    {
        return $this->error;
    }

    public function errorCode()
    {
        return $this->errCode;
    }

    public function returnCode()
    {
        return $this->retCode;
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __toString()
    {
        return (string) $this->data;
    }

}
