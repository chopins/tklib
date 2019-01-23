<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Error;

class ErrorReportException extends \RuntimeException
{
    public function __construct($msg, $code, $file, $line)
    {
        $this->file = $file;
        $this->line = $line;
        parent::__construct($msg, $code);
    }
}
