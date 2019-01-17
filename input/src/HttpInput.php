<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2018 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Input;

use ArrayAccess;
use ArrayObject;
use Countable;
use IteratorAggregate;

class HttpInput implements ArrayAccess, Countable, IteratorAggregate
{

    private $inputType   = false;
    private $arrayObject = null;

    final private function __construct($data)
    {
        $this->arrayObject = new ArrayObject($data);
    }

    public function offsetExists($offset): bool
    {
        return $this->arrayObject->offsetExists($offset);
    }
    public function offsetGet($offset)
    {
        return $this->arrayObject->offsetGet($offset);
    }
    public function offsetSet($offset, $value)
    {
    }
    public function offsetUnset($offset)
    {
    }

    public function getIterator()
    {
        return  new ArrayIterator($this->all());
    }

    public function count()
    {
        return $this->arrayObject->count();
    }

    public static function post()
    {
        $data           = filter_input_array(INPUT_POST);
        $obj            = new static($data);
        $obj->inputType = INPUT_POST;
        return $obj;
    }

    public static function body()
    {
        return file_get_contents('php://input');
    }

    public static function get()
    {
        $data           = filter_input_array(INPUT_GET);
        $obj            = new static($data);
        $obj->inputType = INPUT_GET;
        return $obj;
    }

    public static function cookie()
    {
        $data           = filter_input_array(INPUT_COOKIE);
        $obj            = new static($data);
        $obj->inputType = INPUT_COOKIE;
        return $obj;
    }

    public static function filterTable()
    {
        $table = [
            'isint'    => 'onInt', 'isfloat'     => 'onFloat', 'email'   => 'email', 'ip'        => 'ip',
            'ipv4'     => 'ipv4', 'ipv6'         => 'ipv6', 'cntel'      => 'cnMobile', 'yes'    => 'onYes',
            'username' => 'username', 'password' => 'password', 'length' => 'inputLen', 'letter' => 'letter',
            'numeric'  => 'numeric', 'no'        => 'onOff', 'set'       => 'onSet'];
        return $table;
    }

    public function __get($key)
    {
        return $this->offsetGet($key);
    }

    public function all()
    {
        return $this->arrayObject->getArrayCopy();
    }

    public function has($value)
    {
        return filter_has_var($this->inputType, $value);
    }

    public function val($name, $filter = FILTER_UNSAFE_RAW, $option = [])
    {
        return filter_input($this->inputType, $name, $filter, $option);
    }

    public function onYes($name)
    {
        return filter_input($this->inputType, $name, FILTER_VALIDATE_BOOLEAN);
    }

    public function onOff($name)
    {
        $res = filter_input($this->inputType, $name, FILTER_VALIDATE_BOOLEAN);
        return $res === false ? true : false;
    }

    public function onInt($name, $otherBase = false)
    {
        $op = $otherBase ? (FILTER_FLAG_ALLOW_OCTAL | FILTER_FLAG_ALLOW_HEX) : 0;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_INT, $op);
    }

    public function onSet($name)
    {
        $res = filter_input($this->inputType, $name, FILTER_VALIDATE_BOOLEAN);
        return $res === false ? false : true;
    }

    public function onFloat($name, $allowThousand = false)
    {
        $op = $allowThousand ? FILTER_FLAG_ALLOW_THOUSAND : 0;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_FLOAT);
    }

    public function email($name, $allowUnicode = false)
    {
        $op = $allowUnicode ? FILTER_FLAG_EMAIL_UNICODE : 0;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_EMAIL, $op);
    }

    public function ip($name, $allowPrivRes = false)
    {
        $op = $allowPrivRes ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : 0;
        $op = $op | FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_IP, $op);
    }
    public function ipv4($name, $allowPrivRes = false)
    {
        $op = $allowPrivRes ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : 0;
        $op = $op | FILTER_FLAG_IPV4;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_IP, $op);
    }

    public function ipv6($name, $allowPrivRes = false)
    {
        $op = $allowPrivRes ? FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE : 0;
        $op = $op | FILTER_FLAG_IPV6;
        return filter_input($this->inputType, $name, FILTER_VALIDATE_IP, $op);
    }

    public function cnMobile($name, $iic = false)
    {
        return filter_input($this->inputType, $name, FILTER_CALLBACK, function ($data) use ($iic) {
            $reg = $iic ? '/^(+|00)\d+{1,4}1[13578][0-9]{9}$/i' : '/^{1,4}1[13578][0-9]{9}$/';
            if (preg_match($re, $data)) {
                return $data;
            }
            return false;
        });
    }

    public function letter($name, $op = [])
    {
        return filter_input($this->inputType, $name, FILTER_CALLBACK, function ($data) use ($op) {
            if ($this->checkLen($data, $op) === false) {
                return false;
            }
            if (preg_match('/^[a-z]+$/i', $data)) {
                return $data;
            }
            return false;
        });
    }

    public function numeric($name, $op = [])
    {
        return filter_input($this->inputType, $name, FILTER_CALLBACK, function ($data) use ($op) {
            if ($this->checkLen($data, $op) === false) {
                return false;
            }
            return is_numeric($data) ? $data : false;
        });
    }

    public function usrname($name, $op = [])
    {
        return filter_input($this->inputType, $name, FILTER_CALLBACK, function ($data) use ($op) {
            $unicode = isset($op['unicode']) ? settype($op['unicode'], 'bool') : false;
            if ($this->checkLen($data, $op) === false) {
                return false;
            }
            $reg = $unicode ? '/^[\w^_][\w]*[\w^_]$/iu' : '/^[a-z][0-9a-z_][a-z]/i';
            if (preg_match($reg, $data)) {
                return $data;
            }
            return false;
        });
    }

    public function password($password, $op = [])
    {
        return filter_input($this->inputType, $password, FILTER_CALLBACK, function ($data) use ($op) {
            if ($this->checkLen($data, $op) === false) {
                return false;
            }
            $unicode = isset($op['unicode']) ? settype($op['unicode'], 'bool') : false;
            $reg     = $op['unicode'] ? '/^[\w^_][\w]*[\w^_]$/iu' : '/^[a-z][0-9a-z_][a-z]/i';
            if (preg_match('/[a-z]+[0-9]+[A-Z]+[~!@#$%\^&*()_+-=]+/', $data)) {
                return $data;
            }
            return false;
        });
    }

    public function inputLen($data, $op)
    {
        return filter_input($this->inputType, $data, FILTER_CALLBACK, function ($data) use ($op) {
            return $this->checkLen($data, $op);
        });
    }

    protected function checkLen($data, $op)
    {
        $len = strlen($data);
        if (isset($op['length_max']) && $len > $op['length_max']) {
            return false;
        }
        if (isset($op['length_min']) && $len < $op['length_min']) {
            return false;
        }
        return $data;
    }

    public function filterAll($op)
    {
        $table     = self::filterTable();
        $phpFilter = [];
        $localRes  = [];
        foreach ($op as $key => $filter) {
            if (is_array($filter) && !isset($filter['filter'])) {
                throw new \InvalidArgumentException('must have filter');
            } elseif (is_array($filter) && isset($table[$filter['filter']])) {
                $call = $table[$filter['filter']];
                if (isset($filter['option'])) {
                    $localRes[$key] = $this->$call($key, $filter['option']);
                } else {
                    $localRes[$key] = $this->$call($key);
                }
            } elseif (isset($table[$filter])) {
                $call           = $table[$filter];
                $localRes[$key] = $this->$call($key);
            } else {
                $phpFilter[$key] = $filter;
            }
        }
        $phpFilter = filter_input_array($this->inputType, $phpFilter);
        return array_merge($localRes, $phpFilter);
    }
}
