<?php

namespace Toknot\Network;

use CurlMultiHandle;
use Generator;
use Toknot\Math\Byte;

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
    private string $downloadTotalSize = '0';
    private int $downloadContentLength = 0;
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

    protected function headCheckRange($url)
    {
        $acceptRanges = -1;
        $headOk = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent,
            \CURLOPT_NOBODY => true,
            \CURLOPT_RETURNTRANSFER => true,
            \CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$acceptRanges, &$headOk) {
                if (stripos($header, 'HTTP/') === 0 && strpos($header, '200') > 0) {
                    $headOk = 1;
                }
                if ($headOk && stripos($header, 'Accept-Ranges:') === 0) {
                    if (stripos($header, 'none') > 0) {
                        $acceptRanges = 0;
                    } elseif (strpos($header, 'bytes') > 0) {
                        $acceptRanges = 1;
                    } else {
                        throw new \RuntimeException("unknown http range unit in header: $header");
                    }
                }
                if ($headOk && stripos($header, 'Content-Length:') === 0) {
                    $this->downloadContentLength = trim(substr($headOk, strlen('Content-Length:')));
                }
            }
        ]);
        curl_exec($ch);
        return $acceptRanges;
    }

    protected function progress($url, $writeSize, &$pretime)
    {
        $ntime = time();
        $prec = round($writeSize / $this->downloadContentLength, 2) * 100;
        $speed = Byte::toUnit(($writeSize / ($ntime - $pretime)) / 1024, 2, true);
        $pretime = $ntime;
        $downloadSize = Byte::toUnit($writeSize);
        $this->downloadTotalSize;
    }

    protected function getCheckRange($url, $fp, &$contentLength = 0)
    {
        $ch = curl_init($url);
        $acceptRanges = 0;
        $currentDownload = 0;
        $time = time();
        $writeSize = 0;
        curl_setopt_array($ch, [
            \CURLOPT_USERAGENT => self::$userAgent,
            \CURLOPT_RANGE => '0-0',
            \CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$currentDownload, &$fp, $url, &$writeSize, &$time) {
                if ($currentDownload) {
                    $size = fwrite($fp, $data);
                    $writeSize += $size;
                    $this->progress($url, $writeSize, $time);
                    return $size;
                }
                return strlen($data);
            },
            \CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$acceptRanges, &$currentDownload) {
                if (stripos($header, 'HTTP/') === 0) {
                    if (strpos($header, '206')) {
                        $acceptRanges = 1;
                    } else if (strpos($header, '200')) {
                        $currentDownload = 1;
                        $acceptRanges = 0;
                    }
                }
                if ($acceptRanges && stripos($header, 'Content-Range:') === 0) {
                    strtok($header, ' ');
                    $unit = strtok(' ');
                    if (strcasecmp($unit, 'bytes') !== 0) {
                        throw new \RuntimeException("unknown http range unit in header: $header");
                    }
                    $rse =  strtok('/');
                    if ($rse == '*') {
                        throw new \RuntimeException("unknown http range offset in header: $header");
                    }
                    $size = strtok('/');
                    if (is_numeric($size)) {
                        $this->downloadContentLength = $size;
                        $this->downloadTotalSize = Byte::toUnit($this->downloadContentLength);
                    } else {
                        throw new \RuntimeException("unknown http total range in header: $header");
                    }
                }
                if (!$acceptRanges && stripos($header, 'Content-Length:') === 0) {
                    $this->downloadContentLength = trim(substr($header, strlen('Content-Length:')));
                    $this->downloadTotalSize = Byte::toUnit($this->downloadContentLength);
                }
            }
        ]);
        curl_exec($ch);
        return $acceptRanges;
    }

    public function download($url, $outfile)
    {
        if ($outfile) {
        }
        $tmpfile = "$outfile." . md5(microtime());
        $fp = fopen($tmpfile, 'wb+');

        self::$userAgent = self::$userAgent ?? $this->randomUA();
        $acceptRanges = $this->headCheckRange($url);
        if ($acceptRanges != 1) {
            $acceptRanges = $this->getCheckRange($url, $fp);
        }
        if (!$acceptRanges) {
            return;
        }
        if (!$this->downloadContentLength) {
            throw new \RuntimeException("unknown file size");
        }
        ftruncate($fp, $this->downloadContentLength);
        $partLength = ceil($this->downloadContentLength / self::$maxHostConnect);
        $downloadInfo = [];

        for ($i = 0; $i < self::$maxHostConnect; $i++) {
            $ch = curl_init($url);
            $fp = fopen($tmpfile, 'rb+');
            $startRange = $partLength * $i;
            fseek($fp, $startRange, SEEK_SET);
            if ($i === self::$maxHostConnect - 1) {
                $endRnage = '';
                $partLength = $this->downloadContentLength - $startRange;
            } else {
                $endRnage = $partLength * ($i + 1) - 1;
            }
            $downloadInfo[$i] = ['writeSize' => 0, 'partTotal' => $partLength, 'time' => time()];
            curl_setopt_array($ch, [
                \CURLOPT_USERAGENT => self::$userAgent,
                \CURLOPT_RANGE => $startRange . '-' . $endRnage,
                \CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($fp, &$downloadInfo, $i, $url) {
                    $size =  fwrite($fp, $data);
                    $downloadInfo[$i]['writeSize'] += $size;
                    $this->progress($url, $downloadInfo[$i]['writeSize'], $downloadInfo[$i]['time']);
                    return $size;
                }
            ]);
            if (($mr = curl_multi_add_handle($this->multi, $ch)) !== \CURLM_OK) {
                $this->error($url, curl_multi_strerror($mr));
                return false;
            }
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
                    if ($info['result'] == \CURLE_OK) {
                        if ($requestInfo['http_code'] == 200) {
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
        } while ($running);
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
