<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Process;

use Toknot\Process\ShareMemory;

class ShareMemoryBig
{
    protected ?ShareMemory $shm  = null;
    protected string $vk =  '';
    protected string $pk = '';
    public function __construct($name, $size)
    {
        $this->pk = uniqid('bigvar-' . $name);
        $this->shm = new ShareMemory($this->pk, 'B', $size);
        $this->vk = uniqid($this->pk);
    }

    public function setValue($v)
    {
        $this->shm->put($this->vk, $v);
    }

    public function getValue()
    {
        return $this->shm->get($this->vk);
    }

    public function delete()
    {
        $this->shm->destroy();
    }
}
