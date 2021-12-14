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
    private $workspace = '';
    private $projectSpace = '';
    private $size = null;
    private $permissions = 0600;
    private $blocks = [];
    private $lastValue = [];
    private $varSize = 1;

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
     * @param int    $varSize       value fixed size
     * @throws RuntimeException
     */
    public function __construct($shmName, string $project, $varSize, $size = null, int $permissions = 0600)
    {
        if(strlen($project) != 1) {
            throw new RuntimeException('project name only a one character');
        }
        $this->varSize = $size;
        $this->permissions = $permissions;
        $this->setSize($size);
        $this->project = $project;

        $this->workspace = self::getWorkspace();

        if(!file_exists($this->workspace)) {
            mkdir($this->workspace, $this->permissions, true);
            chmod($this->workspace, $this->permissions | 0700);
        } elseif(!is_dir($this->workspace)) {
            throw new RuntimeException("$this->workspace is not directory");
        }
        $this->attach($shmName);
    }

    public static function getWorkspace()
    {
        $location = self::$PHP_SHARE_MEMORY_STORE_LOCATION ?? sys_get_temp_dir();
        return $location . '/' . self::$PHP_SHARE_MEMORY_KEY_SPACE;
    }

    public function hasChange($varName)
    {
        $key = $this->varKey($varName);
        if(!shm_has_var($this->shm, $key)) {
            return false;
        }
        $v = shm_get_var($this->shm, $key);
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
        $file = $this->projectSpace . '/' . $name;
        if(!touch($file)) {
            throw new \RuntimeException("touch $file error");
        }
        return ftok($file, $this->project);
    }

    /**
     * chunk head size 8byte + sizeof(zend_long) * 4
     * 
     * @param int $size
     * @throws \RuntimeException
     */
    protected function setSize($size)
    {
        if($size) {
            $this->size = Byte::toByte($size);
        } else {
            $iniVar = ini_get('sysvshm.init_mem');
            $this->size = empty($iniVar) ? 10000 : $iniVar;
        }
        $shmhead = 8 + PHP_INT_SIZE * 4;
        if($this->size < 8 + $shmhead) {
            throw new \RuntimeException("share memory must greater then $shmhead");
        }
    }

    protected function attach($shmName)
    {
        $this->projectSpace = $this->workspace . '/' . $this->project . '@' . $shmName;

        if(!file_exists($this->projectSpace)) {
            mkdir($this->projectSpace, $this->permissions);
            chmod($this->projectSpace, $this->permissions | 0700);
        } elseif(!is_dir($this->projectSpace)) {
            throw new RuntimeException("$this->projectSpace is not directory");
        }

        $key = ftok($this->projectSpace, $this->project);
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
        Path::rmdir($this->projectSpace, true);

        return $ret;
    }

    public static function assessShmSize($varNum, $varMaxLen)
    {
        $hs = 8 + PHP_INT_SIZE * 4;
        $len = $varMaxLen + strlen(serialize(['', self::lastTime()]));
        $c1 = $len + (3 * PHP_INT_SIZE);
        $c2 = (int) ($c1 / PHP_INT_SIZE);
        $c3 = ($c2 + 1) * PHP_INT_SIZE + PHP_INT_SIZE;
        return $c3 * $varNum + $hs;
    }

    public static function assessVarSize($var)
    {
        $sv = serialize([$var, self::lastTime()]);
        $len = strlen($sv);
        $c1 = $len + (3 * PHP_INT_SIZE);
        $c2 = (int) ($c1 / PHP_INT_SIZE);
        return $c2+1 * PHP_INT_SIZE + PHP_INT_SIZE;
    }

    public function setBlocking($varName, bool $enable)
    {
        $key = $this->varKey($varName);
        $this->blocks[$key] = $enable;
    }

    public function get($varname)
    {
        $key = $this->varKey($varname);

        do {
            $has = shm_has_var($this->shm, $key);
            if(!$has && empty($this->blocks[$key])) {
                return null;
            } elseif(!$has) {
                usleep(100000);
                continue;
            }
            $v = shm_get_var($this->shm, $key);
            if(empty($this->lastValue[$key]) || empty($this->blocks[$key]) || $v[1] > $this->lastValue[$key]) {
                return trim($v[0]);
            }
            usleep(100000);
        } while(!empty($this->blocks[$key]));
        return trim($v[0]);
    }

    public function has($varname)
    {
        $key = $this->varKey($varname);
        return shm_has_var($this->shm, $key);
    }

    private static function lastTime()
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
        $lt = self::lastTime();
        if(strlen($value) < $this->varSize) {
            $value = str_pad($value, $this->varSize);
        }
        $value = [$value, $lt];
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
            unlink($this->workspace . '/' . $varname);
        }
        return $ret;
    }

    public static function destroyAll($p = null)
    {
        $workspace = self::getWorkspace();
        if(!is_dir($workspace)) {
            throw new RuntimeException("share memory workspace not exists");
        }
        Path::dirWalk($workspace, function ($path) {
            unlink($path);
        }, function ($dir) use ($p) {
                list($project, $shmName) = explode('@', basename($dir));
                if($p && $p != $project) {
                    return;
                }
                $key = ftok($dir, $project);
                $shm = shm_attach($key, null);
                $r = shm_remove($shm);
                rmdir($dir);
            }, true);
    }

}
