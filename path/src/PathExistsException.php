<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Path;

use Toknot\Error\ErrorReportException;

class PathExistsException extends ErrorReportException
{
    const PHP_ERROR_TYPE = E_WARNING;
    const PHP_ERROR_REPORT_MESSAGE_KEY = 'File exists';
}
