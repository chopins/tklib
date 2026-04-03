<?php

namespace Toknot\Network;

use CurlMultiHandle;
use Generator;
use Toknot\Math\Byte;
use Toknot\Process\Console;

class MCurl
{
    private CurlMultiHandle $multi;
    public static int $maxHostConnect = 10;
    public static int $maxTotalConnect = 20;
    public static int $sleepTime = 2;
    public static int $throttle = 100;
    public static ?string $userAgent = null;
    private array $addUrls = [];
    private Generator $rangeGenerator;
    private Generator $generator;
    private bool $continue = true;
    private int $progressCount = 0;
    private int $totalUrl = 0;
    private $shellColumn = 0;
    private string $downloadTotalHumanSize = '0';
    private int $downloadContentLength = 0;
    private int $downloadStartTime = 0;
    private string $downloadUrl =  '';
    private string $downloadTmpFile = '';
    private string $downloadTraget = '';
    private string $downloadTragetByURLPath = '';
    private string $downloadAttachmentFilename = '';
    private int $downloadFileIndex = 1;
    public function __construct()
    {
        $this->multi = self::initCurlMulti();
        $this->shellColumn = shell_exec('tput cols 2>/dev/null');
        $this->continue = true;
    }

    protected static function initCurlMulti()
    {
        $multi = curl_multi_init();
        curl_multi_setopt($multi, \CURLMOPT_MAX_HOST_CONNECTIONS, self::$maxHostConnect);
        curl_multi_setopt($multi, \CURLMOPT_MAX_TOTAL_CONNECTIONS, self::$maxTotalConnect);
        return $multi;
    }

    public function randomUA()
    {
        return mt_rand(0, 1) ? $this->firefoxUA() : $this->chromeUA();
    }

    public function firefoxUA()
    {
        $ri = mt_rand(0, 2);
        $andver = mt_rand(8, 13);
        $win = [6.1, 6.2, 10.0];
        $winver = $win[array_rand($win)];
        $os = ['X11', "Windows NT $winver", "Android $andver"][$ri];
        $arch = ['Linux x86_64', 'Win64; x64', 'Mobile'][$ri];
        $ver = mt_rand(140, 148) . '.0';
        $geckoVer = $ri == 2 ? $ver : '20100101';
        return "Mozilla/5.0 ($os; $arch; rv:$ver) Gecko/$geckoVer Firefox/$ver";
    }

    public function chromeUA()
    {
        $ri = mt_rand(0, 2);
        $andver = mt_rand(8, 14);
        $iosverMap = ['11.0' => 4, '12.0' => 4, '12.4' => 4, '13.0' => 5, '14.0' => 5, '15.0' => 5, '16.4' => 6, '17.0' => 6, '18.6' => 6];
        $iosver = array_rand($iosverMap);
        $win = [6.1, 6.2, 10.0];
        $winver = $win[array_rand($win)];
        $os = ['X11', "Windows NT $winver", "Linux; Android $andver", "iPhone", "Macintosh"][$ri];
        $macVer = ['26_2_0', '15_7.2', '14_8_2', '12_7_8', '13_6_7'];
        $macVer = $macVer[array_rand($macVer)];
        $arch = ['Linux x86_64', 'Win64; x64', 'Andorid', "CPU iPhone OS $iosver like Mac OS X", "Intel Mac OS X $macVer"][$ri];
        $ver = '131.0.0.0';
        $mobile = $ri == 2 ? 'Mobile' : '';
        $chrome = 'Chrome';
        if ($ri == 3) {
            $mobile = 'Mobile/15E148';
            $chrome = 'CriOS';
        }
        $webkitVer = '537.36';
        if ($ri == 3) {
            $webkitVer = '60' . $iosverMap[$iosver] . '.1';
        }
        return "Mozilla/5.0 ($os; $arch) AppleWebKit/$webkitVer (KHTML, like Gecko) $chrome/$ver $mobile Safari/$webkitVer";
    }

    public function error($url, string $error)
    {
        echo "Url : $url Error: $error" . PHP_EOL;
    }

    public function changeState($action, $url, $code)
    {
        $p1 = "\r$action($code):$url";
        $p2 = "$this->progressCount/$this->totalUrl";
        $space = $this->shellColumn - strlen($p1);
        printf("%s%{$space}s", $p1, $p2);
    }

    public function add($url)
    {
        $this->addUrls[] = $url;
        $this->totalUrl++;
    }

    public function range(string $format, int|float|string $start, int|float|string $end, int|float $step = 1)
    {
        $this->rangeGenerator = $this->setRangeGenerator($format, $start, $end, $step);
    }

    protected function setRangeGenerator(string $format, int|float|string $start, int|float|string $end, int|float $step = 1)
    {
        $isString = false;
        if (is_string($start) && is_string($end)) {
            $start = ord($start);
            $end = ord($end);
            $isString = true;
        }
        if ($end < $start) {
            $this->totalUrl += floor(($start - $end) / $step);
            for ($i = $start; $i >= $end; $i--) {
                $char = $isString ?  chr($i) : $i;
                yield sprintf($format, $char);
            }
        } else {
            $this->totalUrl += floor(($end - $start) / $step);
            for ($i = $start; $i <= $end; $i++) {
                $char = $isString ?  chr($i) : $i;
                yield sprintf($format, $char);
            }
        }
    }

    private function addCurlHandle($url)
    {
        $this->changeState('add', $url, 0);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent ?? $this->randomUA(),
            \CURLOPT_RETURNTRANSFER => true,
        ]);
        if (($mr = curl_multi_add_handle($this->multi, $ch)) !== \CURLM_OK) {
            $this->error($url, curl_multi_strerror($mr));
            return false;
        }
        return true;
    }

    public function getGenerator()
    {
        yield from $this->addUrls;
        yield from $this->rangeGenerator;
    }

    private  function initConnect()
    {
        $this->continue = true;
        $this->generator = $this->getGenerator();
        for ($i = 0; $i < self::$maxTotalConnect; $i++) {
            $this->pushConnect();
        }
    }

    private  function pushConnect()
    {
        if (!$this->continue) {
            return;
        }
        $url = $this->generator->current();
        $this->generator->next();
        if (!$this->addCurlHandle($url)) {
            $this->generator->send($url);
        }
        $this->continue = $this->generator->valid();
        return $this->continue;
    }

    protected function httpHeadCheckRange()
    {
        $acceptRanges = -1;
        $headOk = 0;
        $ch = curl_init($this->downloadUrl);
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent,
            \CURLOPT_NOBODY => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$acceptRanges, &$headOk) {
                if (stripos($header, 'HTTP/') === 0 && strpos($header, '200') > 0) {
                    $headOk = 1;
                } elseif ($headOk && stripos($header, 'Accept-Ranges:') === 0) {
                    if (stripos($header, 'none') > 0) {
                        $acceptRanges = 0;
                    } elseif (strpos($header, 'bytes') > 0) {
                        $acceptRanges = 1;
                    } else {
                        throw new \RuntimeException("unknown http range unit in header: $header");
                    }
                } elseif ($headOk && stripos($header, 'Content-Length:') === 0) {
                    $this->downloadContentLength = trim(substr($headOk, strlen('Content-Length:')));
                    $this->downloadTotalHumanSize = Byte::toUnit($this->downloadContentLength);
                } elseif ($headOk && stripos($header, 'Content-Disposition:') === 0 && stripos($header, 'attachment;') === 0) {
                    $this->parseHttpAttachementFilename($header);
                }
                return strlen($header);
            }
        ]);
        curl_exec($ch);
        if (!$headOk) {
            $this->error($this->downloadUrl, curl_getinfo($ch, CURLINFO_HTTP_CODE));
        }
        curl_close($ch);
        return $acceptRanges;
    }

    public function parseHttpAttachementFilename($header)
    {
        strtok($header, ' ');
        strtok(';');
        $fnkey = strtok('=');
        $filename = '';
        if ($fnkey && strcasecmp($fnkey, 'filename') === 0) {
            $filename =  rawurldecode(trim(strtok('='), "\n\r\s\t\v\0'\""));
        } else if ($fnkey && strcasecmp($fnkey, 'filename*') === 0) {
            $extvalue = trim(strtok('='), "\n\r\s\t\v\0'\"");
            $exts = explode('\'', $extvalue, 3);
            $filename = rawurldecode($exts[2]);
            if (strcasecmp($exts[0], 'UTF-8') !== 0) {
                $filename = mb_convert_encoding($filename, 'UTF-8', $exts[0]);
            }
        }
        $this->downloadAttachmentFilename = $filename;
    }

    protected function progress(int $index, array &$downloadInfo)
    {
        static $pretime = 0;
        $ntime = time();
        if($pretime + 1 >= $ntime) {
            return;
        }
        $pretime = $ntime;
        $duration = $ntime - $downloadInfo[$index]['time'];
        if ($duration <= 0) {
            $duration = 1;
        }

        $downloadInfo[$index]['percent'] = round($downloadInfo[$index]['writeSize'] / $downloadInfo[$index]['chunkLen'], 1);
        $downloadInfo[$index]['speed'] = $downloadInfo[$index]['writeSize'] / $duration;

        $downloadInfo[$index]['time'] = $ntime;
        //$humanSize = Byte::toUnit($downloadInfo[$index]['writeSize']);
        $downTotalSize = array_sum(array_column($downloadInfo, 'writeSize'));
        $totalPercent = round($downTotalSize / $this->downloadContentLength, 1);
        $downTotal = Byte::toUnit($downTotalSize, false);
        $speed = Byte::toUnit(array_sum(array_column($downloadInfo, 'speed')), false);
        $totalDuration = $ntime - $this->downloadStartTime;
        $suffix = ($totalPercent * 100) ."%|$downTotal/$this->downloadTotalHumanSize $speed/s";
        $progresslen = $this->shellColumn - strlen($suffix) - self::$maxHostConnect * 2;
        $blockLen = floor($progresslen / self::$maxHostConnect);
        $mod = $progresslen % self::$maxHostConnect;
        $msg = "\r";
        for ($i = 0; $i < self::$maxHostConnect; $i++) {
            $completedLen = floor($blockLen * $downloadInfo[$index]['percent']);
            $completed = str_repeat(' ', $completedLen);
            $msg .= '[' . Console::colorString($completed, Console::STYLE_BG_COLOR_GREEN);
            $pending = str_repeat('-', $blockLen - $completedLen);
            $msg .= Console::colorString($pending, Console::STYLE_COLOR_YELLOW) . ']';
        }
        $msg .= str_repeat(' ', $mod) . $suffix;
        echo $msg;
    }

    protected function httpGetCheckRange($fp, $targetFp)
    {
        $ch = curl_init($this->downloadUrl);
        $acceptRanges = 0;
        $startDownload = 0;
        $downloadInfo = [['writeSize' => 1, 'chunkLen' => &$this->downloadContentLength, 'time' => time(), 'endRange' => '', 'startRange' => 0, 'speed' => 0, 'percent' => 0]];
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent,
            \CURLOPT_FOLLOWLOCATION => 1,
            \CURLOPT_RANGE => '0-0',
            \CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$startDownload, $fp, &$downloadInfo) {
                if ($startDownload) {
                    $size = fwrite($fp, $data);
                    $downloadInfo[0]['writeSize'] += $size;
                    $this->progress(0, $downloadInfo);
                    return $size;
                }
                return strlen($data);
            },
            \CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$acceptRanges, &$startDownload) {
                if (stripos($header, 'HTTP/') === 0) {
                    if (strpos($header, '206')) {
                        $acceptRanges = 1;
                    } else if (strpos($header, '200')) {
                        $startDownload = 1;
                        $acceptRanges = 0;
                        self::$maxHostConnect = 1;
                        $this->changeState("Download", $this->downloadUrl, 200);
                    }
                } elseif (
                    !$this->downloadAttachmentFilename
                    && ($startDownload || $acceptRanges)
                    && stripos($header, 'Content-Disposition:') === 0
                    && stripos($header, 'attachment;') === 0
                ) {
                    $this->parseHttpAttachementFilename($header);
                } elseif ($acceptRanges && stripos($header, 'Content-Range:') === 0) {
                    strtok($header, ' ');
                    if (strcasecmp(strtok(' '), 'bytes') !== 0) {
                        throw new \RuntimeException("unknown http range unit in header: $header");
                    }
                    if (strtok('/') == '*') {
                        throw new \RuntimeException("unknown http range offset in header: $header");
                    }
                    $size = strtok('/');
                    if (is_numeric($size)) {
                        $this->downloadContentLength = $size;
                        $this->downloadTotalHumanSize = Byte::toUnit($this->downloadContentLength);
                    } else {
                        throw new \RuntimeException("unknown http total range in header: $header");
                    }
                } elseif (!$acceptRanges && stripos($header, 'Content-Length:') === 0) {
                    $this->downloadContentLength = trim(substr($header, strlen('Content-Length:')));
                    $this->downloadTotalHumanSize = Byte::toUnit($this->downloadContentLength);
                }
                return strlen($header);
            }
        ]);
        curl_exec($ch);
        if ($startDownload) {
            $this->setTargetPath($targetFp);
            $this->copyTmpfile($fp, $targetFp);
        }
        return $acceptRanges;
    }
    protected function addHttpRangeDownload($index, &$downloadInfo)
    {
        $fp = fopen($this->downloadTmpFile, 'rb+');
        $ch = curl_init($this->downloadUrl);
        fseek($fp, $downloadInfo[$index]['startRange'], SEEK_SET);
        $startDownload = 0;
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent,
            \CURLOPT_FOLLOWLOCATION => 1,
            \CURLOPT_RANGE => $downloadInfo[$index]['startRange'] . '-' . $downloadInfo[$index]['endRange'],
            \CURLOPT_PRIVATE => $index,
            \CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$startDownload) {
                if (stripos($header, 'HTTP/') === 0 && stripos($header, '206')) {
                    strtok($header, ' ');
                    $code = trim(strtok(' '));
                    if ($code == 206) {
                        $startDownload = 1;
                    } else if ($code >= 400) {
                        $this->error($this->downloadUrl, $header);
                    }
                }
                return strlen($header);
            },
            \CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($fp, &$downloadInfo, $index, &$startDownload) {
                if (!$startDownload) {
                    return strlen($data);
                }
                $size =  fwrite($fp, $data);
                $downloadInfo[$index]['writeSize'] += $size;
                $this->progress($index, $downloadInfo);
                return $size;
            }
        ]);
        if (($mr = curl_multi_add_handle($this->multi, $ch)) !== \CURLM_OK) {
            $this->error("add part $index", curl_multi_strerror($mr));
            return false;
        }
    }

    public function openLockEx(&$file)
    {
        while (file_exists($file)) {
            $file .= '.' . $this->downloadFileIndex;
            $this->downloadFileIndex++;
        }
        $fp = fopen($file, 'x');
        if (!$fp) {
            throw new \RuntimeException("$file open error");
        }
        flock($fp, LOCK_EX);
        return $fp;
    }

    protected function copyTmpfile($tmpFp, $targetFp)
    {
        fclose($tmpFp);
        fclose($targetFp);
        unlink($this->downloadTraget);
        if (rename($this->downloadTmpFile, $this->downloadTraget)) {
            $this->changeState("Save", $this->downloadTraget, 0);
        }
        return false;
    }

    protected function setTargetPath($targetFp)
    {
        if (!$this->downloadTraget && $this->downloadAttachmentFilename) {
            $this->downloadTraget = getcwd() . '/' . $this->downloadAttachmentFilename;
        }
        if (!$this->downloadTraget) {
            $this->downloadTraget = $this->downloadTragetByURLPath;
        }
        if (!$targetFp) {
            $this->openLockEx($this->downloadTraget);
        }
    }

    protected function registerCleanTmpFile()
    {
        register_shutdown_function(function() {
            if ($this->downloadTmpFile && file_exists($this->downloadTmpFile)) {
                unlink($this->downloadTmpFile);
            }
        });
    }

    public function download(string $url, string $targetFile = '')
    {

        $this->downloadTotalHumanSize = '';
        $this->downloadContentLength = 0;
        $this->downloadAttachmentFilename = '';
        $this->downloadTraget = $targetFile;
        $this->downloadUrl = $url;
        $this->downloadStartTime = time();
        $tmpsuffix = md5(md5($url) . md5(microtime()));
        $targetFp = null;
        if (!$this->downloadTraget) {
            $this->downloadTragetByURLPath = getcwd() . '/' . basename(parse_url($url, PHP_URL_PATH));
            $this->downloadTmpFile = "$this->downloadTragetByURLPath.$tmpsuffix";
        } else {
            $this->downloadTmpFile = "{$this->downloadTraget}.$tmpsuffix";
            $targetFp = $this->openLockEx($this->downloadTraget);
        }
        $this->registerCleanTmpFile();
        $tmpfileFp = $this->openLockEx($this->downloadTmpFile);

        self::$userAgent = self::$userAgent ?? $this->randomUA();
        $acceptRanges = $this->httpHeadCheckRange();
        if ($acceptRanges != 1) {
            $acceptRanges = $this->httpGetCheckRange($tmpfileFp, $targetFp);
        }
        if (!$acceptRanges) {
            return;
        }
        if (!$this->downloadContentLength) {
            throw new \RuntimeException("unknown file size");
        }
        ftruncate($tmpfileFp, $this->downloadContentLength);
        $this->setTargetPath($targetFp);

        $chunkLength = ceil($this->downloadContentLength / self::$maxHostConnect);
        $downloadInfo = [];

        for ($i = 0; $i < self::$maxHostConnect; $i++) {
            $downloadInfo[$i] = ['writeSize' => 1, 'chunkLen' => &$chunkLength, 'time' => time(), 'speed' => 0, 'percent' => 0];
            $downloadInfo[$i]['startRange'] = $chunkLength * $i;
            if ($i === self::$maxHostConnect - 1) {
                $downloadInfo[$i]['endRange'] = '';
                $chunkLength = $this->downloadContentLength - $downloadInfo[$i]['startRange'];
            } else {
                $downloadInfo[$i]['endRange'] = $chunkLength * ($i + 1) - 1;
            }
            $this->addHttpRangeDownload($i, $downloadInfo);
        }
        do {
            $status = curl_multi_exec($this->multi, $running);
            if ($status != \CURLM_OK) {
                throw new \Exception(curl_multi_strerror(curl_multi_errno($this->multi)));
            }
            while (($info = curl_multi_info_read($this->multi)) !== false) {
                if ($info['msg'] === \CURLMSG_DONE) {
                    curl_multi_remove_handle($this->multi, $info['handle']);
                    curl_close($info['handle']);
                    $requestInfo = curl_getinfo($info['handle']);
                    $doneCurlIndex = curl_getinfo($info['handle'], CURLINFO_PRIVATE);
                    if ($info['result'] == \CURLE_OK) {
                        if ($requestInfo['http_code'] == 206) {
                            continue;
                        }
                    }
                    $downloadInfo[$doneCurlIndex]['writeSize'] = 0;
                    $downloadInfo[$doneCurlIndex]['time'] = time();
                    $this->addHttpRangeDownload($doneCurlIndex, $downloadInfo);
                    $this->error("Part:$doneCurlIndex", $requestInfo['http_code']);
                }
            }
            if ($running) {
                if (curl_multi_select($this->multi, 0.1) === -1) {
                    throw new \Exception(curl_multi_strerror(curl_multi_errno($this->multi)));
                }
            }
        } while ($running);
        return $this->copyTmpfile($tmpfileFp, $targetFp);
    }

    public function run(callable $contentCall, ...$argv)
    {
        $this->initConnect();
        do {
            $status = curl_multi_exec($this->multi, $running);
            if ($status != \CURLM_OK) {
                throw new \Exception(curl_multi_strerror(curl_multi_errno($this->multi)));
            }
            while (($info = curl_multi_info_read($this->multi)) !== false) {
                if ($info['msg'] === \CURLMSG_DONE) {
                    $this->progressCount++;
                    curl_multi_remove_handle($this->multi, $info['handle']);
                    curl_close($info['handle']);
                    if ($this->progressCount % self::$throttle === 0) {
                        sleep(self::$sleepTime);
                    }
                    $this->pushConnect();
                    $requestInfo = curl_getinfo($info['handle']);
                    if ($info['result'] == \CURLE_OK) {
                        $this->changeState('complete', $requestInfo['url'], $requestInfo['http_code']);
                        if ($requestInfo['http_code'] == 200) {
                            $content = curl_multi_getcontent($info['handle']);
                            $contentCall($content, ...$argv);
                            continue;
                        }
                    }
                    $this->error($requestInfo['url'], curl_strerror($info['result']));
                }
            }
            if ($running) {
                if (curl_multi_select($this->multi, 0.1) === -1) {
                    throw new \Exception(curl_multi_strerror(curl_multi_errno($this->multi)));
                }
            }
            if ($this->generator->valid()) {
                continue;
            }
        } while ($running);
    }

    public function __destruct()
    {
        curl_multi_close($this->multi);
    }
}
