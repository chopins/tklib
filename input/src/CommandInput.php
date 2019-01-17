<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Input;

class CommandInput
{
    private $argv         = [];
    private $argc         = 0;
    private $options      = [];
    private $optionsCount = 0;
    private $enterFile    = '';
    private $cwd          = '';
    private $startTime    = 0;
    public static $shortBool = true;
    const OPT_START_CHAR  = '-';
    const OPT_LONG_CHAR   = '--';
    const OPT_REQUIRED    = 1;
    const OPT_NO_VALUE    = 0;
    const OPT_OPTIONAL    = -1;
    public function __construct(array $argv = [], int $argc = 0)
    {
        if ($argv) {
            $this->argv = $argv;
            $this->argc = $argc ? $argc : count($argc);
        } else {
            $argc = filter_input(INPUT_SERVER, 'argc');
            $argv = filter_input(INPUT_SERVER, 'argv');
            if ($argv) {
                $this->argv = $argv;
                $this->argc = $argc ? $argc : count($argc);
            }
        }
        $this->cmdInfo();
        $this->parseArg();
    }

    /**
     * Get command line input string
     *
     * @param string $prompt
     * @return string
     */
    public static function raw(): string
    {
        return trim(fgets(STDIN));
    }

    public static function rawOnYes($raw)
    {
        if(self::$shortBool && strtolower($raw) == 'y') {
            return;
        }
        return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
    }

    public static function rawOnOff($raw)
    {
        if(self::$shortBool && strtolower($raw) == 'n') {
            return;
        }
        $f = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $f === false ? true : false;
    }

    /**
     * specify optional whether be int number, is int returned value,or returned false
     *
     * @param string $key
     * @return number|boolean
     */
    public static function rawOnInt($raw)
    {
        return filter_var($raw, FILTER_VALIDATE_INT);
    }

    /**
     * get one optional
     *
     * @param string $key
     * @param int$option
     * @return string|boolean
     */
    public static function oneOpt(string $key, int $option = self::OPT_NO_VALUE)
    {
        $op = ltrim($key, self::OPT_START_CHAR);
        $op = $option === [] ? '' : ($option > self::OPT_NO_VALUE ? ':' : '::');
        if (strpos($key, self::OPT_LONG_CHAR) === 0) {
            return getopt('', [$op]);
        }
        $res = getopt($op);
        if (!$res) {
            return false;
        }
        return $res[$op];
    }

    protected function parseArg()
    {
        $prev  = false;
        $cArgv = $this->argv;
        array_shift($cArgv);
        foreach ($cArgv as $key => $value) {
            $chunk = explode('=', $value, 2);
            $hl    = strpos($value, self::OPT_START_CHAR);
            if (count($chunk) == 1) {
                if ($hl !== 0 && $prev !== false) {
                    $this->options[$prev] = trim($value, '\'"');
                    $prev                 = false;
                } elseif ($hl === 0) {
                    $this->options[$value] = '';
                    $prev                  = $value;
                } else {
                    $this->options[$value] = '';
                }
            } elseif ($hl === 0) {
                $this->options[$chunk[0]] = trim($chunk[1], '\'"');
            } else {
                $this->options[$value] = '';
            }
        }
        $this->optionsCount = count($this->options);
    }

    protected function cmdInfo()
    {
        if (isset($this->argv[0])) {
            $this->enterFile = realpath($argv[0]);
        }
        $this->cwd       = getcwd();
        $this->startTime = $_SERVER['REQUEST_TIME'];
    }

    public function getCwd(): string
    {
        return $this->cwd();
    }

    public function getStartTime(): int
    {
        return $this->startTime;
    }

    public function getEnterFile(): string
    {
        return $this->enterFile;
    }

    public function optCount(): int
    {
        return $this->optionsCount;
    }

    /**
     * get opt, the function paramter like
     *
     * @param array $options  short and long paramters use '-' and '--' start and suffix is ':' or '::' similar getopt of php
     * @return array
     */
    public function getopt(array $options): array
    {
        $res = [];
        foreach ($options as $opt) {
            $type = substr_count($op, self::OPT_START_CHAR, 0, 2);
            $op   = substr_count($op, ':', -2, 2);
            $key  = rtrim($opt, ':');
            if ($type === 0 && isset($this->options[$key])) {
                $res[$key] = $this->options[$key];
                continue;
            } elseif ($type > 2 || !isset($this->options[$key])) {
                $res[$key] = false;
                continue;
            }
            if ($opt === 0) {
                $res[$key] = $this->options[$key] === '' ? true : false;
                continue;
            } elseif ($opt === 1) {
                $res[$key] = $this->options[$key] === '' ? false : $this->options[$key];
                continue;
            } else {
                $res[$key] = $this->options[$key];
                continue;
            }
        }
        return $res;
    }

    /**
     * get specify option value or all optionals
     *
     * @param string $key
     * @return array|string|boolean
     */
    public function op(string $key = '')
    {
        if (empty($key)) {
            return $this->options;
        }

        return isset($this->options[$key]) ? $this->options($key) : false;
    }

    public function required(string $key)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key] === '' ? false : $this->options[$key];
        }
        return false;
    }

    /**
     * ony specify option is gived and no value returned true, return false otherwise
     *
     * @param string $key
     * @return boolean
     */
    public function unaccept(string $key): bool
    {
        if (isset($this->options[$key])) {
            return $this->options[$key] === '' ? true : false;
        }
        return false;
    }

    /**
     * specify optional be set true and equivalence will returned true, returns false  otherwise
     *
     * @param string $key
     * @return string|boolean
     */
    public function onYes(string $key)
    {
        if (isset($this->options[$key])) {
            return self::rawOnYes($this->options[$key]);
        }
        return false;
    }

    /**
     * check specify option whether be set and not false or equivalence
     *
     * @param string $key
     * @return boolean
     */
    public function onSet(string $key): bool
    {
        if (isset($this->options[$key])) {
            $res = filter_var($this->options[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            return $res === false ? false : true;
        }
        return false;
    }

    /**
     * specify optional whether be int number, is int returned value,or returned false
     *
     * @param string $key
     * @return number|boolean
     */
    public function onInt(string $key)
    {
        if (!isset($this->options[$key])) {
            return false;
        }
        return filter_var($this->options[$key], FILTER_VALIDATE_INT);
    }
}
