<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Input;

class Browser
{
    private $browserInfo = [];
    private $agent       = '';
    public function __construct($userAgent = '')
    {
        $this->agent       = $userAgent ? $userAgent : ServerInput::uAgent();
        $this->browserInfo = get_browser($this->agent);
    }
    public function platform()
    {
        return $this->browserInfo['platform'] ?? '';
    }
    public function type()
    {
        $type = $this->browserInfo['browser'] ?? '';
        if (empty($type)) {
            return $this->determineType();
        }
        return $type;
    }

    protected function determineType()
    {
        $key = ['Firefox' => 'Firefox', 'Android' => 'Chrome', 'Edge' => 'Edge', 'Chrome' => 'Chrome', 'Safari' => 'Safari', 'Trident' => 'IE', 'MSIE' => 'IE', 'Presto' => 'Opera'];
        foreach ($key as $k => $v) {
            if (stripos($this->agent, $k) !== false) {
                return $k;
            }
        }
        return 'other';
    }
}
