<?php

namespace Toknot\Network;

use CurlMultiHandle;
use Generator;

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

    public function __construct()
    {
        $this->multi = curl_multi_init();
        curl_multi_setopt($this->multi, \CURLMOPT_MAX_HOST_CONNECTIONS, self::$maxHostConnect);
        curl_multi_setopt($this->multi, \CURLMOPT_MAX_TOTAL_CONNECTIONS, self::$maxTotalConnect);
        $this->shellColumn = shell_exec('tput cols 2>/dev/null');
        $this->continue = true;
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
