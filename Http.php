<?php

declare(strict_types=1);
/**
 * Http Request by Curl (http://toknot.com)
 *
 * @copyright  Copyright (c) 2011 - 2024 Toknot.com
 * @license    http://toknot.com/LICENSE.txt New BSD License
 * @link       https://github.com/chopins/toknot
 */

/**
 * Http 请求 Body 数据类型
 */
enum HttpRequestBodyType: string
{
    case JSON = 'json';
    case XML = 'xml';
    case RAW = 'raw';
    case FORM_MULTIPART = 'form-multipart';
    case FILE = 'file';
    case FORM_URL = 'form-url';
}

function RUN()
{
    return HTTP::init()->run();
}
/**
 * @param string $path  请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $data  请求时发送 Body 数据
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function GET(string $path, string|array $query = '', string|array $data = '')
{
    $obj = HTTP::init();
    if ($data) {
        $obj->custom('GET', $path, $query, $data);
    } else {
        $obj->get($path, $query);
    }
    return $obj;
}
/**
 * @param string $path   请求的文件路径，不包括 scheme, host, port部分
 * @param string|array|CURLStringFile $file 请求时发送 Body 数据
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function PUT(string $path, string|array|CURLStringFile $file, string|array $query = '')
{
    $obj = HTTP::init();
    if ($file instanceof CURLStringFile) {
        $data = $file->data;
    } else if (is_string($file) && file_exists($file)) {
        $data = file_get_contents($file);
    } else {
        return $obj->custom('PUT', $path, $query, $file);
    }
    return $obj->put($path, $data, $query);
}

/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $data 请求时发送 Body 数据
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function POST(string $path, string|array $data = '', string|array $query = '')
{
    $obj = HTTP::init();
    $obj->post($path, $data, $query);
    return $obj;
}
/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function DELETE(string $path, string|array $query = '')
{
    $obj = HTTP::init();
    $obj->delete($path, $query);
    return $obj;
}
/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function HEAD(string $path, string|array $query = '')
{
    $obj = HTTP::init();
    $obj->head($path, $query);
    return $obj;
}

/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function OPTIONS(string $path, string|array $query = '')
{
    $obj = HTTP::init();
    $obj->options($path, $query);
    return $obj;
}

/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|array $query URL 查询参数
 *
 * @return HTTP
 */
function TRACE(string $path, string|array $query = '')
{
    $obj = HTTP::init();
    $obj->trace($path, $query);
    return $obj;
}
/**
 * Http Request By curl
 */
class HTTP
{
    /**
     * @var int 当前总执行次数
     */
    public static int $execCount = 0;
    /**
     * @var int 当前显示的请求数量
     */
    public static int $showCount = 0;
    /**
     * @var string API执行脚本文件，默认为 $_SERVER['SCRIPT_FILENAME']
     */
    public static string $scriptFile = '';
    /**
     * @var array 网页显示时 bootstrap CSS 库文件，例如 bootstrap.min.css
     */
    public static array $bootstrapCssLink = [];
    /**
     * @var array 网页显示时 bootstrap JS 库文件，例如 bootstrap.min.js
     */
    public static array $bootstrapJsSrc = [];
    /**
     * @var string 用户名
     */
    public static string $user = '';
    /**
     * @var string 用户密码
     */
    public static string $password = '';
    /**
     * @var string 请求协议
     */
    public static string $scheme = 'http';
    /**
     * @var string 主机域名或IP
     */
    public static string $host = '';
    /**
     * @var int 请求端口，默认将根据 $scheme 进行设置
     */
    public static int $port = 0;
    /**
     * @var array 请求头列表
     */
    public static array $requestHeader = [];

    /**
     * @var string|HttpRequestBodyType 请求时发送的body数据类型
     */
    public static string|HttpRequestBodyType $requestBodyType;
    /**
     * @var string 位于调用行时，激活执行的 token 值
     */
    private static string $runTag = '@';

    /**
     * @var string 位于调用行时，激活执行的并显示的 token 值
     */
    private static string $runTagShow = '@';
    /**
     * @var string 设置 User Agent
     */
    public static ?string $userAgent = '';
    /**
     * @var string 设置 oauth2 token
     */
    public static string $oauth2Token = '';
    /**
     * @var int 连接等待最上时间
     */
    public static int $connectTimeout = 10;
    /**
     * @var int curl 执行最长时间
     */
    public static int $execTimeout = 30;
    /**
     * @var bool 是否显示响应头
     */
    public static bool $showResponseHeader = true;
    /**
     * @var bool 是否显示响应的内容
     */
    public static bool $showResponseBody = true;
    /**
     * @var bool 是否显示请求头
     */
    public static bool $showRequestHeader = false;
    /**
     * @var bool 是否使用表格显示数组结果
     */
    public static bool $showArrayTable = false;
    /**
     * @var array 表格显示规则
     */
    public static array $arrayTableLayout = [];
    /**
     * @var array 添加的CURL选项,会覆盖默认选项
     */
    public static array $curlOptions = [];
    /**
     * @var array 需要发送的COOKIE
     */
    public static array $requestCookie = [];
    /**
     * @var bool 当前实例是否已经显示
     */
    public bool $isShow = false;
    /**
     * @var string http 请求方法
     */
    public string $method = 'GET';

    /**
     * @var string 发起 http 时的 url
     */
    public string $url = '';
    /**
     * @var int http 响应状态码
     */
    public int $httpCode = 0;
    /**
     * @var string http 响应状态信息
     */
    public string $httpMsg = '';
    /**
     * @var float http 响应 body 长度
     */
    public float $responseContentLength = 0;
    public string $responseType = '';
    /**
     * @var bool http 响应 body 是否 JSON
     */
    public bool $isJson = false;
    /**
     * @var bool http 响应 body 是否 XML
     */
    public bool $isXml = false;
    /**
     * @var bool http 响应 body 是否 HTML
     */
    public bool $isHtml = false;
    /**
     * @var bool http 响应 body 是否 Text
     */
    public bool $isText = false;
    /**
     * @var string http 响应的HTTP 版本
     */
    public string $httpVersion = 'HTTP/1.1';

    /**
     * @var array 实际发送的请求头
     */
    public array $realRequestHeader = [];
    /**
     * @var string 发送 http 请求的 body 内容
     */
    public string|array $requestBody = '';
    /**
     * @var array http 响应的头列表
     */
    public array $responseHeader = [];
    /**
     * @var string http 响应的 body 内容
     */
    public string|bool $responseBody = '';
    /**
     * @var array 当前请求使用的 curl 选项
     */
    public array $currentCurlOptions = [];
    /**
     * @var int 请求时 curl 错误码
     */
    public int $curlErrno = 0;
    /**
     * @var string 请求时 curl 错误信息
     */
    public string $curlError = '';
    /**
     * @var float 请求执行时间
     */
    public float $execTime = 0;
    /**
     * @var float 请求发起连接时间
     */
    public float $connectTime = 0;
    /**
     * @var float DNS解析时间
     */
    public float $nsLookupTime = 0;
    /**
     * @var int 重定向次数
     */
    public int $redirectCount = 0;
    /**
     * @var string 最后一次重定向URL
     */
    public string $locationUrl = '';
    /**
     * @var string 最后请求的URL
     */
    public string $lastUrl = '';
    /**
     * @var array 重定向URL列表
     */
    public array $redirectUrls = [];
    /**
     * @var string 连接IP
     */
    public string $connectIp = '';

    /**
     * @var int 连接端口
     */
    public int $connectPort = 0;

    /**
     * @var bool 是否自定义请求方法
     */
    private bool $isCustomMethod = false;

    /**
     * @var bool 是否调用请求
     */
    private bool $run = false;

    /**
     * @var bool
     */
    private bool $enableShow = false;
    /**
     * @var CurlHandle
     */
    private ?CurlHandle $curl = null;
    /**
     * @var HTTP
     */
    private static HTTP $obj;
    /**
     * @var array
     */
    private static array $colors = [];
    /**
     * @var bool
     */
    private static bool $isCLI = true;

    /**
     * @var bool
     */
    private static bool $forceRun = false;
    /**
     * @var array
     */
    private static array $runFlagLines = [];

    /**
     * @var array
     */
    private static array $runFlagShowLines = [];

    /**
     * @var array
     */
    private static array $defaultObjVars = [];

    private function __construct()
    {
        self::$isCLI = PHP_SAPI == 'cli';
        self::$requestBodyType = HttpRequestBodyType::RAW;
        $this->checkRun(false);
        self::$defaultObjVars = get_object_vars($this);
        $this->color();
    }

    /**
     * @param string $host
     *
     * @return HTTP
     */
    public static function init(string $runTag = '', string $runTagShow = ''): HTTP
    {
        if ($runTag) {
            self::$runTag = $runTag;
        }
        if ($runTagShow) {
            self::$runTagShow = $runTagShow;
        }
        if (!isset(self::$obj)) {
            self::$obj = new static();
            self::$obj->htmlPage();
        }
        self::$obj->reset();
        return self::$obj;
    }

    public function reset()
    {
        foreach (self::$defaultObjVars as $k => $v) {
            $this->$k = $v;
        }
    }

    private function buildUrl(string $path = '/', string|array $queryData = ''): void
    {
        $query = '';
        if ($queryData) {
            $query =  is_array($queryData) ? http_build_query($queryData) : $queryData;
            $query = (strpos($path, '?') === false ? '?' : '&') . $query;
        }
        if (strpos($path, '/') !== 0) {
            $path = "/$path";
        }
        $port = self::$port == 0 ? '' :  ':' . self::$port;
        $this->url = self::$scheme . "://" . self::$host . "{$port}{$path}{$query}";
    }

    private function buildBody(string|array $data): void
    {
        if (is_string(self::$requestBodyType)) {
            HttpRequestBodyType::from(self::$requestBodyType);
        }
        if (self::$requestBodyType == HttpRequestBodyType::JSON) {
            $this->requestBody = is_array($data) ? json_encode($data) : $data;
            self::$requestHeader[] = 'Content-Type: application/json';
        } else if (self::$requestBodyType->value == HttpRequestBodyType::XML) {
            $this->requestBody = is_array($data) ? self::xmlEncode($data) : $data;
            self::$requestHeader[] = 'Content-Type: application/xml';
        } else {
            $this->requestBody = $data;
        }
    }
    protected static function xmlEncode(array $data): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        foreach ($data as $key => $v) {
            if (is_array($v)) {
                $v = self::xmlEncode($v);
            }
            $xml .= "<{$key}>{$v}</{$key}>";
        }
    }
    /**
     * @param string $method
     * @param string $path
     * @param string $query
     * @param string $data
     *
     * @return HTTP
     */
    public function custom(string $method, string $path, string|array $query = '', string|array $data = ''): HTTP
    {
        $this->method = $method;
        $this->isCustomMethod = true;
        $this->buildUrl($path, $query);
        $this->buildBody($data);
        if ($data) {
            $this->currentCurlOptions[CURLOPT_POSTFIELDS] = $this->requestBody;
        }
        return $this->request();
    }
    public function get(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->method = 'GET';
        return $this->request();
    }

    public function post(string $path, string|array $data, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->method = 'POST';
        $this->buildBody($data);
        $this->currentCurlOptions[CURLOPT_POSTFIELDS] = $this->requestBody;
        return $this->request();
    }

    public function put(string $path, $file, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->method = 'PUT';
        if (is_string($file) && is_file($file)) {
            $this->currentCurlOptions[CURLOPT_INFILE] = fopen($file, 'rb');
            $this->currentCurlOptions[CURLOPT_INFILESIZE] = filesize($file);
        } else if (is_resource($file)) {
            $this->currentCurlOptions[CURLOPT_INFILE] = $file;
            $this->currentCurlOptions[CURLOPT_INFILESIZE] = fstat($file)['size'];
        }
        return $this->request();
    }

    public function delete(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->isCustomMethod = true;
        $this->method = 'DELETE';
        return $this->request();
    }

    public function head(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->isCustomMethod = true;
        $this->method = 'HEAD';
        return $this->request();
    }

    public function patch(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->isCustomMethod = true;
        $this->method = 'PATCH';
        return $this->request();
    }

    public function options(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->isCustomMethod = true;
        $this->method = 'OPTIONS';
        return $this->request();
    }

    public function trace(string $path, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->isCustomMethod = true;
        $this->method = 'TRACE';
        return $this->request();
    }

    protected function request(): HTTP
    {
        $this->run = $this->checkRun();
        if (!$this->run) {
            return $this;
        }
        self::$execCount++;
        if (self::$userAgent) {
            $this->currentCurlOptions[CURLOPT_USERAGENT] = self::$userAgent;
        } else if (self::$userAgent === null) {
            $this->currentCurlOptions[CURLOPT_USERAGENT] = '';
        }
        if (self::$oauth2Token) {
            $this->currentCurlOptions[CURLOPT_XOAUTH2_BEARER] = self::$oauth2Token;
        }
        if (self::$user) {
            $this->currentCurlOptions[CURLOPT_USERNAME] = self::$user;
            $this->currentCurlOptions[CURLOPT_PASSWORD] = self::$password;
            $this->currentCurlOptions[CURLOPT_HTTPAUTH] = CURLAUTH_ANY;
        }
        if ($this->isCustomMethod) {
            $this->currentCurlOptions[CURLOPT_CUSTOMREQUEST] = $this->method;
            $this->isCustomMethod = false;
        } else if ($this->method == 'GET') {
            $this->currentCurlOptions[CURLOPT_HTTPGET] = true;
        } else if ($this->method == 'POST' && self::$requestBodyType == HttpRequestBodyType::FORM_URL) {
            $this->currentCurlOptions[CURLOPT_POST] = true;
        } else if ($this->method == 'PUT') {
            $this->currentCurlOptions[CURLOPT_PUT] = true;
        }
        if (self::$showRequestHeader) {
            $this->currentCurlOptions[CURLINFO_HEADER_OUT] = 1;
        }
        $this->currentCurlOptions[CURLOPT_FOLLOWLOCATION] = true;
        $this->currentCurlOptions[CURLOPT_HEADERFUNCTION] = function ($ch, $h) {
            $this->responseHeader[] = $h;
            return strlen($h);
        };
        if (self::$requestHeader) {
            foreach (self::$requestHeader as $hk => $hv) {
                if (!is_numeric($hk)) {
                    self::$requestHeader[] = "$hk: $hv";
                    unset(self::$requestHeader[$hk]);
                }
            }
            $this->currentCurlOptions[CURLOPT_HTTPHEADER] = self::$requestHeader;
        }
        $this->currentCurlOptions[CURLOPT_RETURNTRANSFER] = 1;
        $this->currentCurlOptions[CURLOPT_CONNECTTIMEOUT] = self::$connectTimeout;
        $this->currentCurlOptions[CURLOPT_TIMEOUT] = self::$execTimeout;

        if (self::$requestCookie) {
            $this->currentCurlOptions[CURLOPT_COOKIE] = http_build_query(self::$requestCookie, '', ';');
        }

        $this->curl = curl_init($this->url);
        curl_setopt_array($this->curl, $this->currentCurlOptions);
        if (self::$curlOptions) {
            curl_setopt_array($this->curl, self::$curlOptions);
        }
        $this->responseBody = curl_exec($this->curl);

        $this->getCurlInfo();
        if ($this->httpCode === 0) {
            $this->getNetworkError();
        }

        if ($this->enableShow) {
            return $this->show();
        }
        return $this;
    }

    private function getCurlInfo(): void
    {
        $info = curl_getinfo($this->curl);
        $this->execTime = $info['total_time'];
        $this->connectTime = $info['connect_time'];
        $this->nsLookupTime = $info['namelookup_time'];

        $this->redirectUrls = [];
        $this->connectIp = $info['primary_ip'];
        $this->connectPort = $info['primary_port'];
        $this->responseContentLength = $info['size_download'];
        $this->lastUrl = $info['url'];

        $this->httpCode = $info['http_code'];

        if (!$this->httpCode) {
            return;
        }

        if (self::$showRequestHeader) {
            $this->realRequestHeader = explode("\r\n", $info['request_header']);
        }

        $this->redirectCount = $info['redirect_count'];
        if (isset($info['redirect_url'])) {
            $this->locationUrl = $info['redirect_url'];
        }

        if (isset($info['content_type'])) {
            $this->responseType = $info['content_type'];
            $this->getResposeType($info['content_type']);
        }
        if (isset($info['http_version'])) {
            $ver = [
                CURL_HTTP_VERSION_1_0 => 'HTTP/1.0',
                CURL_HTTP_VERSION_1_1 => 'HTTP/1.1',
                CURL_HTTP_VERSION_2 => 'HTTP/2',
                CURL_HTTP_VERSION_2_0 => 'HTTP/2',
                CURL_HTTP_VERSION_2TLS => 'HTTPS/2',
                CURL_HTTP_VERSION_2_PRIOR_KNOWLEDGE => 'HTTP/2'
            ];
            if (defined('CURL_HTTP_VERSION_3')) {
                $ver[CURL_HTTP_VERSION_3] = 'HTTP/3';
                $ver[CURL_HTTP_VERSION_3ONLY] = 'HTTP/3';
            }
            $this->httpVersion = $ver[$info['http_version']];
        }
    }

    private function getNetworkError(): void
    {
        $this->curlErrno = curl_errno($this->curl);
        $this->curlError = curl_error($this->curl);
    }

    protected function getResposeType(string $header): void
    {
        if (self::isHave($header, 'text/json') || self::isHave($header, 'application/json')) {
            $this->isJson = true;
        } else if (
            self::isHave($header, 'application/xml')
            || self::isHave($header, 'text/xml')
            || self::isHave($header, 'application/atom+xml')
        ) {
            $this->isXml = true;
        } else if (self::isHave($header, 'text/plain')) {
            $this->isText = true;
        } else if (self::isHave($header, 'text/html')) {
            $this->isHtml = true;
        }
    }
    public static function isHave(string $hay, string $needle): bool
    {
        return stripos($hay, $needle) !== false;
    }

    public function show(): HTTP
    {
        if ($this->isShow) {
            return $this;
        }
        if (!$this->run) {
            return $this;
        }
        if (self::$isCLI) {
            $this->showConsole();
        } else {
            $this->showHTML();
        }
        self::$showCount++;
        $this->isShow = true;
        return $this;
    }

    public function then(callable $callable): HTTP
    {
        if (!$this->run) {
            return $this;
        }
        $callable($this);
        return $this;
    }

    public static function __callStatic(string $name, array $arguments = []): void
    {
        if (isset(self::$colors[$name])) {
            self::out($name, ...$arguments);
        }
    }

    protected static function out(string $color = 'PRESET', string $str = '', bool $nl = false): void
    {
        if (isset(self::$colors[$color])) {
            echo self::$colors[$color] . $str . self::$colors['END'];
        } else {
            echo $str;
        }
        if ($nl && self::$isCLI) {
            echo PHP_EOL;
        } else if (!self::$isCLI) {
            echo '<br />';
        }
    }

    protected function showConsole(): void
    {
        $cols = (int)exec('tput cols');
        self::YELLOW(str_repeat('-', $cols), true);

        if (!$this->httpCode) {
            self::BLUE("{$this->method} {$this->url} ", true);
            self::RED(curl_error($this->curl), true);
            return;
        }
        if (self::$showRequestHeader) {
            foreach ($this->realRequestHeader as $i => $header) {
                if (strpos($header, ':') === false) {
                    self::GREEN($header, true);
                } else {
                    self::MAGENTA(str_replace(':', ':' . self::$colors['END'], $header), true);
                }
            }
        } else {
            self::BLUE("{$this->method} {$this->url} ", true);
        }
        if (self::$showResponseHeader) {
            foreach ($this->responseHeader as $i => $header) {
                if (strpos($header, ':') === false) {
                    self::GREEN($header);
                } else {
                    self::MAGENTA(str_replace(':', ':' . self::$colors['END'] . self::$colors['PRESET'], $header));
                }
            }
        }
        if (self::$showResponseBody) {
            if ($this->isJson) {
                $json = json_decode($this->responseBody, true);
                $json ? $this->showArrayTable($json) : print($this->responseBody);
            } else if ($this->isXml) {
                $xml = simplexml_load_string($this->responseBody);
                $xml ? $this->showArrayTable($xml) : print($this->responseBody);
            } else if ($this->responseContentLength <= 500 && $this->httpCode == 200) {
                echo $this->responseBody;
            } else {
                echo 'save to: file://' . realpath('./output.html');
                file_put_contents('./output.html', $this->responseBody);
            }
            echo PHP_EOL;
        }
    }

    protected function color(): void
    {
        $ansi = isset($_SERVER['ComSpec']) && $_SERVER['ComSpec'] == 'C:\Windows\system32\cmd.exe' ? "\x1b" : "\033";

        if (PHP_SAPI != 'cli') {
            $code = ['BLUE' => 'blue', 'GREEN' => 'green', 'MAGENTA' => 'magenta', 'RED' => 'red', 'YELLOW' => 'yellow', 'PRESET' => 'unset'];
            self::$colors['END'] = "</span>";
            foreach ($code as $k => $n) {
                self::$colors[$k] = "<span style='color:$n'>";
            }
        } else {
            $code = ['RED' => 31, 'GREEN' => 32, 'YELLOW' => 33, 'BLUE' => 34, 'MAGENTA' => 35, 'PRESET' => 0];
            self::$colors['END'] = "{$ansi}[0m";
            foreach ($code as $k => $n) {
                self::$colors[$k] = "{$ansi}[0;{$n}m";
            }
        }
    }

    protected function showHTML(): void
    {
        echo '<div class="accordion-item"><h2 class="accordion-header" id="commonConfigHeading"><button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#commonConfig" aria-expanded="true" aria-controls="commonConfig">';
        self::BLUE("{$this->method} {$this->url} ", true);
        echo '</button></h2><div id="commonConfig" class="accordion-collapse collapse show" aria-labelledby="commonConfigHeading" data-bs-parent="#mainAccordion"><div class="accordion-body d-grid gap-2">';
        if (!$this->httpCode) {
            '<div class="alert alert-danger" role="alert">'. curl_error($this->curl) . '</div>';
        }
        if (self::$showRequestHeader) {
            $id = 'showRequestHeaderCollapse-' . self::$execCount;
            echo '<a href="#a-'.$id.'" class="btn btn-outline-primary dropdown-toggle" role="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '" id="a-'.$id.'">实际请求头</a><div class="collapse" id="' . $id . '"><ul class="list-group">';
            foreach ($this->realRequestHeader as $i => $header) {
                if (!$header) {
                    continue;
                }
                echo '<li class="list-group-item">';
                if (strpos($header, ':') === false && $header) {
                    self::GREEN($header, true);
                } else {
                    self::MAGENTA(str_replace(':', ':' . self::$colors['END'] . '<span>', $header), true);
                }
                echo '</li>';
            }
            echo '</ul></div>';
        }
        if (self::$showResponseHeader) {
            $id = 'showResponseHeaderCollapse-' . self::$execCount;
            echo '<a href="#a-'.$id.'" class="btn btn-outline-primary dropdown-toggle" role="button" data-bs-toggle="collapse" data-bs-target="#' . $id . '" aria-expanded="false" aria-controls="' . $id . '" id="a-'.$id.'">响应头：'.$this->httpCode .'</a><div class="collapse" id="' . $id . '"><ul class="list-group">';
            foreach ($this->responseHeader as $i => $header) {
                echo '<li class="list-group-item">';
                $header = trim($header);
                if ($i == 0 && $header) {
                    self::GREEN($header);
                } else {
                    self::MAGENTA(str_replace(':', ':' . self::$colors['END'] . '<span>', $header));
                }
                echo '</li>';
            }
            echo '</ul></div>';
        }
        if (!$this->responseBody) {
            return;
        }
        $contentType = $this->isJson ? 'json' : ($this->isXml ? 'xml' : 'html');
        $content = $this->isJson ? $this->responseBody : str_ireplace(['&', '</script'], ['&amp;', '&lt;/script'], $this->responseBody);
        echo <<<HTML
        <script class="responseContent" type="text/plain" content-type="{$contentType}">{$content}</script>
        </div></div></div>
        HTML;
    }

    public function run(): HTTP
    {
        self::$forceRun = true;
        return $this;
    }

    protected function checkRun(bool $parse = true): bool
    {
        if ($parse) {
            if (self::$forceRun) {
                return true;
            }
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
            $callline = $trace[3]['line'];
            $this->enableShow = false;
            if (in_array($callline, self::$runFlagShowLines)) {
                $this->enableShow = true;
                return true;
            }
            if (in_array($callline, self::$runFlagLines)) {
                return true;
            }
            return false;
        }

        $file = self::$scriptFile ? self::$scriptFile : $_SERVER['SCRIPT_FILENAME'];
        $all = token_get_all(file_get_contents($file, false));
        $cnt = count($all);
        self::$runFlagLines = [];

        for ($i = 0; $i < $cnt; $i++) {
            $token = $all[$i];
            $findTag = $findTagShow = false;
            if ($token == self::$runTag || (is_array($token) && $token[1] == self::$runTag)) {
                $findTag = true;
            } elseif ($token == self::$runTagShow || (is_array($token) && $token[1] == self::$runTagShow)) {
                $findTagShow = true;
            }
            $next = $i;
            while ($findTag || $findTagShow) {
                $next++;
                if ($next >= $cnt) {
                    break;
                }
                if (!is_array($all[$next]) && $all[$next] != '=') {
                    break;
                }
                if ($all[$next][0] == T_STRING) {
                    $findTag && self::$runFlagLines[] = $all[$next][2];
                    $findTagShow && self::$runFlagShowLines[] = $all[$next][2];
                    break;
                } else if ($all[$next][0] != T_WHITESPACE) {
                    break;
                }
            }
        }

        return true;
    }

    public function showArrayTable(array $array): void
    {
        if (!self::$showArrayTable) {
            print_r($array);
            return;
        }

        $cols = exec('tput cols');
        $lineSep = str_repeat('=', $cols) . PHP_EOL;
        echo $lineSep;
        foreach (self::$arrayTableLayout as $line) {
            $width = floor($cols / count($line));
            foreach ($line as $k => $n) {
                if ($n == 'string') {
                    echo str_pad(self::$colors['BLUE'] . $k . self::$colors['END'] . " => $array[$k]", $width);
                } else {
                    $field =  $k . ' => ';
                    echo self::$colors['BLUE'] . $k . self::$colors['END'] . ' => ';
                    $this->showList($array[$k], strlen($field));
                }
            }
            echo PHP_EOL;
            echo $lineSep;
        }
    }

    protected function showList(array $array, string $indent): void
    {
        $indentStr = str_repeat(' ', $indent);
        $i = 0;
        foreach ($array as $k => $v) {
            $field = $k . ' => ';
            $i > 0 && print($indentStr);
            $i++;
            echo self::$colors['BLUE'] . $k . self::$colors['END'] . ' => ';
            if (is_array($v)) {
                $this->showList($v, $indent + strlen($field));
            } else {
                echo $v . PHP_EOL;
            }
        }
    }

    public static function file($filename, $filemime = null): CURLFile
    {
        $mime = mime_content_type($filename);
        if (!$mime && !$filemime) {
            $mime = 'application/octet-stream';
        } elseif (!$mime) {
            $mime = $filemime;
        }
        return new \CURLFile($filename, $mime);
    }

    public function __destruct()
    {
        if ($this->curl instanceof CurlHandle) {
            curl_close($this->curl);
        }
        $msg = 'All requested and show' . PHP_EOL;
        if (!HTTP::$showCount) {
            $msg =  'Exec ' . HTTP::$execCount . ' request  and no  output data' . PHP_EOL;
        } else if (HTTP::$showCount != HTTP::$execCount) {
            $msg = 'Exec ' . HTTP::$execCount . ' request  and ' . HTTP::$showCount . ' show output' . PHP_EOL;
        }
        if (!self::$isCLI) {
            echo "</div><div class=\"alert alert-success\" role=\"alert\">$msg</div>";
            echo '</div></body></html>';
        } else {
            self::GREEN($msg);
        }
    }

    public function htmlPage()
    {
        if (PHP_SAPI == 'cli') {
            return;
        }
?>
        <!DOCTYPE html>
        <html>

        <head>
            <title>API Request</title>
            <style>
                * {
                    font-size: 14px;
                }

                :root {
                    --pseudo-display: block;
                }

                html {
                    width: 99%;
                    word-break: break-all;
                }

                hr {
                    padding: 1px;
                    color: yellow;
                }

                code p {
                    margin: 0;
                }

                m,
                x {
                    color: blue;
                    font-weight: bold;
                }

                t {
                    color: green;
                }

                n {
                    color: darkorchid;
                }

                .responseContent {
                    display: none;
                }

                code {
                    display: block;
                    background-color: #FAFAFA;
                    border: 1px solid #CCC;
                    padding: 5px;
                    font-family: "DejaVu Sans Mono", "Consolas", ui-monospace, monospace;
                    font-size-adjust: 0.5;
                    letter-spacing: 1px;
                    white-space: pre;
                }

                code button {
                    display: block;
                }

                code b {
                    color: navy;
                    margin-right: 10px;
                }

                code>div>div {
                    display: none;
                }

                code>div>div+m::before {
                    content: '......';
                    display: var(--pseudo-display);
                    margin-left: 20px;
                }

                code div {
                    margin-left: 20px;
                }

                body {
                    margin-top: 50px;
                }

                body>h3 {
                    position: fixed;
                    top: 1px;
                    background-color: #EEE;
                    border: #000 solid 1px;
                    padding: 4px;
                    color: green;
                }

                sub {
                    font-size: 10px;
                    color: #666;
                }

                x {
                    font-weight: bold;
                    margin: 0 2px;
                }
            </style>
            <?php if (self::$bootstrapCssLink) {
                foreach (self::$bootstrapCssLink as $link) {
                    echo '<link href="' . $link . '" type="text/css" rel="stylesheet" />';
                }
            }
            if (self::$bootstrapJsSrc) {
                foreach (self::$bootstrapJsSrc as $src) {
                    echo '<script src="' . $src . '" type="application/javascript"></script>';
                }
            }
            ?>
            <script>
                const d = document;

                function $(e) {
                    if (typeof e == 'function') {
                        d.addEventListener('DOMContentLoaded', e);
                    } else if (typeof e == 'string') {
                        return d.querySelectorAll(e);
                    } else {
                        return e;
                    }
                }

                function jsonview(o) {
                    let t = '';
                    if (o instanceof Array) {
                        if (o.length == 0) {
                            return '<m>[]</m>,';
                        }
                        for (let i in o) t += '<p><b>' + i + ':</b>' + jsonview(o[i]) + '</p>';
                        t = t.slice(0, -5) + '</p>';
                        return '<m>[</m><div>' + t + '</div><m>]</m>,';
                    } else if (o instanceof Object) {
                        for (let i in o) t += '<p><b>"' + i + '":</b>' + jsonview(o[i]) + '</p>';
                        t = t.slice(0, -5) + '</p>';
                        return '<m>{</m><div>' + t + '</div><m>}</m>,';
                    } else if (typeof o == 'string') {
                        o = o.replaceAll(/[\r\n\t]/img, function(m) {
                            let a = {
                                "\r": '\\r',
                                "\n": '\\n',
                                "\t": '\\t'
                            };
                            return '<sub>' + a[m] + '</sub>';
                        });
                        return '<t>"' + o + '"</t>,';
                    } else {
                        return '<n>' + o + '</n>,';
                    }
                }

                function xmlview(o) {
                    let t = '';
                    for (let e of o.children) {
                        t += '<p><x>&lt;</x><m>' + e.tagName + '</m><x>&gt;</x>';
                        if (e.children.length > 0) {
                            t += '<div>' + xmlview(e) + '</div>';
                        } else {
                            t += '<t>' + e.innerHTML + '</t>';
                        }
                        t += '<x>&lt;/</x><m>' + e.tagName + '</m><x>&gt;</x></p>';
                    }
                    return t;
                }


                $(function() {
                    $('.responseContent').forEach(function(e) {
                        let type = e.getAttribute('content-type');
                        let v = e.innerHTML;
                        let s = null;
                        if (type == 'json') {
                            s = d.createElement('code');
                            try {
                                s.innerHTML = '<button class="btn btn-success dropdown-toggle">显示/隐藏</button><br />' + jsonview(JSON.parse(v)).slice(0, -1);
                            } catch (e) {
                                s.innerHTML = v;
                            }
                        } else if (type == 'xml') {
                            s = d.createElement('code');
                            let xml = v.replaceAll('&lt;/script', '</script').replaceAll('&amp;', '&');
                            try {
                                let dom = new DOMParser().parseFromString(xml, 'application/xml');
                                s.innerHTML = '<button>显示/隐藏</button>' + xmlview(dom);
                            } catch (e) {
                                s.innerHTML = v;
                            }
                        } else {
                            s = d.createElement('iframe');
                            s.width = "99%";
                            s.height = "900";
                            s.srcdoc = v.replaceAll('&lt;/script', '</script').replaceAll('&amp;', '&');
                        }
                        e.after(s);
                    });
                    $('code>button').forEach((c) => c.addEventListener('click', (e) => {
                        let k = e.target.parentNode.querySelector('div>div');
                        d.documentElement.style.setProperty('--pseudo-display', getComputedStyle(k).display);
                        k.style.display = k.style.display == 'block' ? 'none' : 'block';
                    }));
                });
            </script>
        </head>

        <body>
            <div class="container-fluid">
                <div class="accordion" id="mainAccordion">
            <?php
        }
    }
