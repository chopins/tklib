<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

/**
 * Csv
 *
 * @author chopin
 */
class Csv
{

    public static int $lineLength = 0;
    public static string $delimiter = ',';
    public static string $enclosure = '"';
    public static string $escape = '\\';
    private $fp = null;

    public function __construct(string $file)
    {
        $this->fp = fopen($file, 'rb');
    }

    public function read()
    {
        if($this->isEnd()) {
            return false;
        }
        return fgetcsv($this->fp, self::$lineLength, self::$delimiter, self::$enclosure, self::$escape);
    }

    public function readAll()
    {
        $this->reset();
        $ret = [];
        while(false !== ($line = $this->read())) {
            $ret[] = $line;
        }
        return $ret;
    }

    public function reset()
    {
        fseek($this->fp, 0, SEEK_SET);
    }

    public function xRead()
    {
        while(false !== ($line = $this->read())) {
            yield $line;
        }
    }

    public function isEnd()
    {
        return feof($this->fp);
    }

    public function close()
    {
        fclose($this->fp);
    }

    public function __destruct()
    {
        $this->close();
    }

}
