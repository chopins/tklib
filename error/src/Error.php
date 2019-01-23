<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Error;

class Error
{
    protected static $errorExceptionInfo = [];
    protected static $errorHandler       = [];
    public function __construct()
    {
        $this->error2Exception();
    }

    public static function enableError2Exception($exception)
    {
        if (defined("{$exception}::PHP_ERROR_TYPE") && defined("{$exception}::PHP_ERROR_REPORT_MESSAGE_KEY")) {
            self::$errorExceptionInfo[] = $exception;
        }
        throw new \InvalidArgumentException("parameter #1 must be object or class name and has constant $exception::PHP_ERROR_TYPE and $exception::PHP_ERROR_REPORT_MESSAGE_KEY");
    }

    public static function setErrorHandler($handler)
    {
        self::$errorHandler = $handler;
    }

    public function getErrorException()
    {
        return self::$errorExceptionInfo;
    }

    public function getErrorHandler()
    {
        return self::$errorHandler;
    }

    public function unexception($callback)
    {
        \set_exception_handler($callback);
    }

    public function error2Exception()
    {
        \set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            foreach (self::$errorExceptionInfo as $info) {
                if ($errno == $info::PHP_ERROR_TYPE && strpos($errstr, $info::PHP_ERROR_REPORT_MESSAGE_KEY) !== false) {
                    throw new $info;
                }
            }
            $func = self::$errorHandler;
            $func($errno, $errstr, $errfile, $errline);
        });
    }
}
