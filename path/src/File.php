<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

class File extends \SplFileObject {

    private $writer = null;
    private $reader = null;
    private $readerLen = 1024;
    private $filename = '';
    private $isclose = true;

    public function __construct($filename, $mode = 'r', $useInclude = false, $context = null) {
        $context ? parent::__construct($filename, $mode, $useInclude, $context) : parent::__construct($filename, $mode, $useInclude);
        $this->filename = $filename;
        $this->gwrite();
        $this->greader();
    }

    private function gwrite() {
        $this->isclose = true;
        $this->writer = function () {
            while ($this->isclose) {
                $data = yield;
                $this->fwrite($data);
            }
        };
    }

    private function greader() {
        $this->reader = function () {
            while (!$this->eof()) {
                yield $this->fread($this->readerLen);
            }
        };
    }

    public function getReader() {
        return $this->reader;
    }

    public function getWriter() {
        return $this->writer;
    }

    /**
     * find string in between $start string and $end string 
     * 
     * @param string $start
     * @param string $end
     * @return string
     */
    public function findRange($start, $end) {
        $find = $res = '';
        $search = $start;
        while (!($this->eof())) {
            $char = $this->fread(1);
            $find .= $char;
            if ($search == $end) {
                $res .= $char;
            }

            if (strpos($search, $find) === false) {
                $find = $char;
            }

            if ($find == $end && $search == $end) {
                break;
            }
            if ($start == $find) {
                $find = '';
                $search = $end;
            }
        }

        return substr($res, 0, strlen($end) * -1);
    }

    /**
     * move seek to a string
     * 
     * @param string $start
     * @return boolean
     */
    public function seekPos($start) {
        while (!($this->eof())) {
            $char = $this->fread(1);
            $find .= $char;
            if (strpos($start, $find) === false) {
                $find = $char;
            }
            if ($find == $start) {
                return $this->ftell();
            }
        }
        return false;
    }

    /**
     * find offset to end string from current seek
     * 
     * @param string $end
     * @return string
     */
    public function findNextRange($end) {
        $find = $res = '';
        while (!($this->eof())) {
            $char = $this->fread(1);
            $find .= $char;
            $res .= $char;
            if (strpos($end, $find) === false) {
                $find = $char;
            }
            if ($find == $end) {
                break;
            }
        }
        return substr($res, 0, strlen($end) * -1);
    }

    public function substr($start, $len) {
        $this->fseek($start);
        $i = 0;
        $res = '';
        while (!($this->eof())) {
            if ($i >= $len) {
                break;
            }
            $i++;
            $res .= $this->fread(1);
        }
        return $res;
    }

    /**
     * find string offset
     * 
     * @param string $search
     * @return int
     */
    public function strpos($search) {
        $find = '';
        $sl = strlen($search);

        while (!($this->eof())) {
            $char = $this->fread(1);
            $find .= $char;

            if (strpos($search, $find) === false) {
                $find = $char;
            }
            if ($search == $find) {
                return $this->ftell() - $sl;
            }
        }
        return false;
    }

    /**
     * yield write string
     * 
     * @param string $str
     */
    public function write($str) {
        if ($this->writer) {
            $this->writer->send($str);
        } else {
            $this->fwrite($str);
        }
    }

    /**
     * yield read string
     * 
     * @param int $len
     * @return string
     */
    public function read($len = 1024) {
        if (!$this->reader) {
            return $this->fread($len);
        }
        $this->readerLen = $len;
        $res = $this->reader->current();
        $this->reader->next();
        return $res;
    }

    public function unlink() {
        unlink($this->filename);
    }
    
    public function close() {
       $this->isclose = false;
    }

}
