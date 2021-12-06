<?php

/**
 * Toknot (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2019 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

namespace Toknot\Process;

use Toknot\Math\Byte;
use Toknot\Path\Path;

/**
 * ShareMemory
 *
 * @author chopin
 */
class ShareMemory
{

    private $shm = null;
    private $project = null;
    private $projectSpace = '';
    private $workSpace = '';
    private $size = null;
    private $permissions = 0600;
    private $blocks = [];
    private $lastValue = [];
    public $enableJson = true;

    /**
     * key default store path
     * 
     * C:/Windows/Temp/php_share_memory_key_space
     * /tmp/php_share_memory_key_space
     * 
     * @var string
     */
    public static string $PHP_SHARE_MEMORY_KEY_SPACE = 'php-shared-memory';

    /**
     * if not set default store sys temp
     * 
     * @var ?string
     */
    public static ?string $PHP_SHARE_MEMORY_STORE_LOCATION = null;

    /**
     * 
     * @parma string $shmName
     * @param string $project    a one character
     * @throws RuntimeException
     */
    public function __construct($shmName, string $project, $size = null, int $permissions = 0600)
    {
        if(strlen($project) != 1) {
            throw new RuntimeException('project name only a one character');
        }

        $this->permissions = $permissions;
        $this->setSize($size);
        $this->project = $project;
        $location = self::$PHP_SHARE_MEMORY_STORE_LOCATION ?? sys_get_temp_dir();
        $this->projectSpace = $location . '/' . self::$PHP_SHARE_MEMORY_KEY_SPACE;

        if(!file_exists($this->projectSpace)) {
            mkdir($this->projectSpace, $this->permissions, true);
            chmod($this->projectSpace, $this->permissions|0700);
        } elseif(!is_dir($this->projectSpace)) {
            throw new RuntimeException("$this->projectSpace is not directory");
        }
        $this->enableJson = function_exists('json_encode');
        $this->attach($shmName);
    }
    
    protected function encode($v)
    {
        if($this->enableJson) {
            return json_encode($v);
        }
        return serialize($v);
    }
    
    protected function decode($v)
    {
        if($this->enableJson) {
            return json_decode($v, true);
        }
        return unserialize($v);
    }

    public function hasChange($varName)
    {
        $key = $this->varKey($varName);
        if(!shm_has_var($this->shm, $key)) {
            return false;
        }
        $v = shm_get_var($this->shm, $key);
        $v = $this->decode($v);
        if(empty($v[1]) || $v[1] > $this->lastValue[$key]) {
            return true;
        }
        return false;
    }

    protected function unConnect()
    {
        if(!$this->shm) {
            throw new RuntimeException('before must be creates or open a shared memory segment');
        }
    }

    protected function varKey(string $name)
    {
        $this->unConnect();
        $file = $this->workSpace . '/' . $name;
        touch($file);
        return ftok($file, $this->project);
    }

    protected function setSize($size)
    {
        if($size) {
            $this->size = Byte::toByte($size) * 8;
        } else {
            $iniVar = ini_get('sysvshm.init_mem');
            $this->size = empty($iniVar) ? 10000 : $iniVar;
        }
    }

    protected function attach($shmName)
    {
        $this->workSpace = $this->projectSpace . '/' . $this->project . '@' . $shmName;
        if(!file_exists($this->workSpace)) {
            mkdir($this->workSpace, $this->permissions);
            chmod($this->workSpace, $this->permissions|0700);
        } elseif(!is_dir($this->workSpace)) {
            throw new RuntimeException("$this->workSpace is not directory");
        }

        $key = ftok($this->workSpace, $this->project);
        $this->shm = shm_attach($key, $this->size, $this->permissions);
        return (bool) $this->shm;
    }

    public function detach()
    {
        $this->unConnect();
        return shm_detach($this->shm);
    }

    public function destroy()
    {
        $this->unConnect();
        $ret = shm_remove($this->shm);
        if($ret) {
            Path::rmdir($this->workSpace, true);
        }
        return $ret;
    }

    public function setBlocking($varName, bool $enable)
    {
        $key = $this->varKey($varName);
        $this->blocks[$key] = $enable;
    }

    public function get($varname)
    {
        $key = $this->varKey($varname);

        if(!empty($this->blocks[$key])) {
            while(!shm_has_var($this->shm, $key)) {
                usleep(100000);
            }
        }
        do {
            $v = shm_get_var($this->shm, $key);
            $v = $this->decode($v);
            if(empty($v[1]) || empty($this->lastValue[$key]) || $v[1] > $this->lastValue[$key]) {
                return $v[0];
            }
            usleep(100000);
        } while(!empty($this->blocks[$key]));
        return $v[0];
    }

    public function has($varname)
    {
        $key = $this->varKey($varname);
        return shm_has_var($this->shm, $key);
    }

    private function lastTime()
    {
        if(PHP_VERSION_ID > 70300) {
            $hrtime = hrtime();
            return (($hrtime[0] * 1000000000 + $hrtime[1]) / 1000000000);
        }
        return microtime(true);
    }

    public function put($varname, $value)
    {
        $key = $this->varKey($varname);
        $lt = $this->lastTime();
        $value = $this->encode([$value, $lt]);
        if(strlen($value) > $this->size) {
            throw new RuntimeException("put value length big than $this->size");
        }
        $ret = shm_put_var($this->shm, $key, $value);
        if($ret) {
            $this->lastValue[$key] = $lt;
        }
    }

    public function del($varname)
    {
        $key = $this->varKey($varname);
        $ret = shm_remove_var($this->shm, $key);
        if($ret) {
            unlink($this->projectSpace . '/' . $varname);
        }
        return $ret;
    }

}
