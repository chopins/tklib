<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Input;

class ServerInput
{
    public static function header($feild)
    {
        if (strpos('HTTP_') !== 0) {
            $feild = str_replace('-', '_', $feild);
            $feild = 'HTTP_' . ucwords($feild, '_');
        }
        return isset($_SERVER[$feild]) ? $_SERVER[$feild] : null;
    }

    public static function useIp()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    public static function port()
    {
        return $_SERVER['REMOTE_PORT'];
    }
    public static function https()
    {
        $res = filter_input(INPUT_SERVER, 'HTTPS', FILTER_NULL_ON_FAILURE);
        return $res === false ? false : true;
    }

    public static function uri()
    {
        return $_SERVER['REQUEST_URI'];
    }

    public static function host()
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            return $_SERVER['SERVER_NAME'];
        } else {
            return $_SERVER['HTTP_HOST'];
        }
    }

    public static function query()
    {
        return $_SERVER['QUERY_STRING'] ?? '';
    }

    public static function time()
    {
        return $_SERVER['REQUEST_TIME'];
    }

    public static function microtime()
    {
        return $_SERVER['REQUEST_TIME_FLOAT'];
    }

    public static function method()
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function referer()
    {
        return $_SERVER['HTTP_REFERER'] ?? '';
    }
    public static function uAgent()
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    public static function enterFile()
    {
        return $_SERVER['SCRIPT_FILENAME'];
    }

    public static function scheme()
    {
        if (empty($_SERVER['REQUEST_SCHEME'])) {
            list($scheme) = explode('/', $_SERVER['SERVER_PROTOCOL']);
            return $scheme;
        }
        return $_SERVER['REQUEST_SCHEME'];
    }
}
