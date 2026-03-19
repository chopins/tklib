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
enum HttpContentType: string
{
    case JSON = 'application/json';
    case XML = 'application/xml';
    case HTML = 'text/html';
    case CSS = 'text/css';
    case BIN = 'application/octet-stream';
    case TEXT = 'text/plain';
    case FORM_MULTIPART = 'multipart/form-data';
    case BYTE_MULTIPART = 'multipart/byteranges';
    case FILE = 'application/file';
    case FORM_URL = 'application/x-www-form-urlencoded';
}
class Response
{
    public function __construct(
        public int $httpCode,
        public string $body,
        public string $url,
        public HttpContentType $contentType,
    ) {}
}

function RUN()
{
    return HTTP::init()->run();
}
/**
 * @param callable():Response $call
 */
function SHOW(callable $call, ...$params)
{
    $obj = HTTP::init();
    $obj->show($call, $params);
    return $obj;
}
function SAVE(string $path, string $save = '', string |array $query = '')
{
    $obj = HTTP::init();
    $obj->save($path, $query, $save);
    return $obj;
}
/**
 * @param string $path  请求的文件路径，不包括 scheme, host, port部分
 * @param string|string[] $data  请求时发送 Body 数据
 * @param string|string[] $query URL 查询参数
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
 * @param string|string[]|CURLStringFile $file 请求时发送 Body 数据
 * @param string|string[] $query URL 查询参数
 *
 * @return HTTP
 */
function PUT(string $path, mixed $file, string|array $query = '')
{
    $obj = HTTP::init();
    $type = gettype($file);
    if (!in_array($type, ['string', 'array', 'resource'])) {
        throw new TypeError("Argument #2 (\$file) must be of type string|array|resource, $type given");
    }
    if (is_array($file)) {
        $obj->custom('PUT', $path, $query, $file);
    }
    return $obj->put($path, $file, $query);
}

/**
 * @param string $path 请求的文件路径，不包括 scheme, host, port部分
 * @param string|string[] $data 请求时发送 Body 数据
 * @param string|string[] $query URL 查询参数
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
 * @param string|string[] $query URL 查询参数
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
 * @param string|string[] $query URL 查询参数
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
 * @param string|string[] $query URL 查询参数
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
 * @param string|string[] $query URL 查询参数
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
    public static bool $verbose = false;
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
    public static array $bootstrapCssLink = ['https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css'];
    /**
     * @var array 网页显示时 bootstrap JS 库文件，例如 bootstrap.min.js
     */
    public static array $bootstrapJsSrc = ['https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.min.js'];
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
     * @var array 默认最前查询参数
     */
    public static array $defalutQueryArgs = [];
    /**
     * @var array 默认最前POST数据
     */
    public static array $defaultPostArgs = [];

    /**
     * @var string|\HttpContentType 请求时发送的body数据类型
     */
    public static string|HttpContentType $requestBodyType;
    /**
     * @var string 位于调用行时，激活执行的 token 值， 必须以 # 开头的单行代码注释
     */
    private static string $runTag = '#run';

    /**
     * @var string 位于调用行时，激活执行的并显示的 token 值, 必须是以 # 开头的单行代码注释
     */
    private static string $runShowTag = '#runshow';
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
    public static bool $showRequestBody = false;
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
    public bool $isArray = false;
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
    private bool $isrun = false;
    private Response $response;
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
    private static array $runShowFlagLines = [];

    /**
     * @var array
     */
    private static array $defaultObjVars = [];
    private  static bool $isWebview = false;

    private function __construct()
    {
        self::$isCLI = PHP_SAPI == 'cli';
        if (self::$isCLI && class_exists('webview')) {
            self::$isWebview = true;
            $this->outputWebview();
        }
        self::$requestBodyType = HttpContentType::TEXT;
        $this->checkRun(false);
        self::$defaultObjVars = get_object_vars($this);
        $this->color();
    }
    private function outputWebview()
    {
        new webview('API', 'file://' . getcwd() . '/');
    }

    /**
     * @param string $host
     *
     * @return HTTP
     */
    public static function init(string $runTag = '', string $runShowTag = ''): HTTP
    {
        if ($runTag && str_starts_with($runTag, '#')) {
            self::$runTag = $runTag;
        }
        if ($runShowTag && str_starts_with($runShowTag, '#')) {
            self::$runShowTag = $runShowTag;
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
        self::$forceRun = false;
        foreach (self::$defaultObjVars as $k => $v) {
            $this->$k = $v;
        }
    }

    private function buildUrl(string $path = '/', string|array $queryData = ''): void
    {
        $query = '';
        $realArgs = self::$defalutQueryArgs;
        if ($queryData && is_string($queryData)) {
            parse_str($queryData, $queryData);
        } else if (!$queryData) {
            $queryData = [];
        }

        $realArgs = array_merge($realArgs, $queryData);

        if ($realArgs) {
            $query =  http_build_query($realArgs);
            $query = (str_ends_with($path, '?') === false ? '?' : '&') . $query;
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
            self::$requestBodyType = HttpContentType::from(self::$requestBodyType);
        }
        if (is_array($data)) {
            $data = array_merge(self::$defaultPostArgs, $data);
        }

        switch (self::$requestBodyType) {
            case HttpContentType::JSON:
                $this->requestBody = is_array($data) ? json_encode($data) : $data;
                return;
            case HttpContentType::XML:
                $this->requestBody = is_array($data) ? self::xmlEncode($data) : $data;
                return;
        }
        self::$requestHeader['Content-Type'] = self::$requestBodyType->value;

        foreach (self::$requestHeader as $i => $v) {
            if (strpos($v, 'Content-Type:') === 0) {
                unset(self::$requestHeader[$i]);
            }
        }
        $this->requestBody = $data;
    }
    protected static function xmlEncode(array $data): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        foreach ($data as $key => $v) {
            if (is_array($v)) {
                $v = self::xmlEncode($v);
            }
            $xml .= "<{$key}>{$v}</{$key}>";
        }
        return $xml;
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

    protected function getSaveFileInfo($ch, $h, &$save, &$notRange)
    {
        if (stripos($h, 'HTTP/') === 0) {
            list(, $httpCode) = explode(' ', $h);
            if ($httpCode == 200) {
                $notRange = true;
            } else if ($httpCode == 206) {
                $notRange = false;
            } else if ($ch && $httpCode == 416) {
                $notRange = true;
                curl_close($ch);
                throw new RuntimeException("请求范围错误");
            }
        } else if (stripos($h, 'Accept-Ranges:') === 0) {
            $notRange = false;
        } else if (!$save && stripos($h, 'Content-Disposition:') == 0) {
            strtok($h, ':');
            $name = '';
            do {
                $v = trim(strtok(';'));
                if (stripos($v, 'filename=') === 0) {
                    list(, $name) = explode('=', $v);
                    $name = trim($name, '"');
                } else if (stripos($v, 'filename*=') === 0) {
                    list(, $name) = explode('=', $v);
                    $name = rawurldecode(explode('\'', $name)[2]);
                    break;
                }
            } while ($v);
            if ($name) {
                $save = getcwd() . '/' . basename($name);
            }
        }
    }

    public function save(string $path, string|array $query = '', string $save = '')
    {
        if ($save && file_exists($save)) {
            $size = filesize($save);
            $this->currentCurlOptions[CURLOPT_RANGE] = "$size-";
        } else {
            $this->currentCurlOptions[CURLOPT_RANGE] = "0-";
        }
        $notRange = false;
        if (!$save) {
            $this->head($path, $query);
            $headStatus = ($this->httpCode > 100 && $this->httpCode < 300);
            if ($headStatus) {
                foreach ($this->responseHeader as $h) {
                    $this->getSaveFileInfo(null, $h, $save, $notRange);
                }
            }
            $this->responseHeader = [];
        }
        $this->buildUrl($path, $query);
        $this->method = 'SAVE';
        if (!$headStatus) {
            $this->currentCurlOptions[CURLOPT_HEADERFUNCTION] = function ($ch, $h) use (&$notRange, &$save) {
                $this->getSaveFileInfo($ch, $h, $save, $notRange);
                $this->responseHeader[] = $h;
                return strlen($h);
            };
        }

        try {
            $this->currentCurlOptions[CURLOPT_WRITEFUNCTION] = function ($ch, string $data) use ($save, $notRange) {
                static $fp = null;
                $mode = $notRange ? 'wb+' : 'ab+';
                if (!$save) {
                    $save = getcwd() . '/' . basename(parse_url($this->url, PHP_URL_PATH));
                }
                if (!$notRange && file_exists($save) && $this->currentCurlOptions[CURLOPT_RANGE] == '0-') {
                    $size = filesize($save);
                    if ($size > 0) {
                        curl_close($ch);
                        throw new RuntimeException($save, 200206);
                    }
                }
                if (!$fp) {
                    $fp = fopen(getcwd() . "/$save", $mode);
                }
                return fwrite($fp, $data);
            };
            return $this->request();
        } catch (RuntimeException $e) {
            if ($e->getCode() == 200206) {
                return $this->save($path, $query, $e->getMessage());
            }
        }
    }

    public function post(string $path, string|array $data, string|array $query = ''): HTTP
    {
        $this->buildUrl($path, $query);
        $this->method = 'POST';
        if (is_string($data)) {
            self::$requestBodyType = HttpContentType::FORM_URL;
        }
        $this->buildBody($data);
        $this->currentCurlOptions[CURLOPT_POSTFIELDS] = $this->requestBody;
        return $this->request();
    }

    public function put(string $path, mixed $file, string|array $query = ''): HTTP
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

    /**
     * @param Response $call
     */
    public function show(callable $call, $params = []): HTTP
    {
        $this->isrun = $this->checkRun();
        if (!$this->isrun) {
            return $this;
        }
        self::$execCount++;
        try {
            $this->response = $call(...$params);
        } catch (Throwable $e) {
            if ($e instanceof TypeError) {
                $type = explode(' ', $e->getMessage())[2];
                $message = "TypeError: SHOW() Argument #1 callable(): Return value must be of type Response, $type returned" . PHP_EOL;
            } else {
                $message = $e->getMessage();
            }
            $message .= $e->getTraceAsString();
            $this->response = new Response(
                500,
                $message,
                json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                HttpContentType::TEXT
            );
        }
        $this->method = 'SHOW';
        $this->httpCode = $this->response->httpCode;
        $this->url = $this->response->url;
        $this->responseBody = $this->response->body;
        $this->getResposeType($this->response->contentType);

        if ($this->enableShow) {
            return $this->view();
        }
        return $this;
    }

    protected function request(): HTTP
    {
        $this->isrun = $this->checkRun();
        if (!$this->isrun) {
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
        } else if ($this->method == 'POST' && self::$requestBodyType == HttpContentType::FORM_URL) {
            $this->currentCurlOptions[CURLOPT_POST] = true;
        } else if ($this->method == 'PUT') {
            $this->currentCurlOptions[CURLOPT_PUT] = true;
        } else if ($this->method == 'SAVE') {
            $this->currentCurlOptions[CURLOPT_HTTPGET] = true;
            $this->currentCurlOptions[CURLOPT_HEADER] = false;
        }
        if (self::$showRequestHeader) {
            $this->currentCurlOptions[CURLINFO_HEADER_OUT] = 1;
        }
        $this->currentCurlOptions[CURLOPT_FOLLOWLOCATION] = true;

        if ($this->method != 'SAVE') {
            $this->currentCurlOptions[CURLOPT_HEADERFUNCTION] = function ($ch, $h) {
                $this->responseHeader[] = $h;
                return strlen($h);
            };
        }
        self::$requestHeader = array_unique(self::$requestHeader);
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
            return $this->view();
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
                $ver[constant('CURL_HTTP_VERSION_3')] = 'HTTP/3';
                $ver[constant('CURL_HTTP_VERSION_3ONLY')] = 'HTTP/3';
            }
            $this->httpVersion = $ver[$info['http_version']];
        }
    }

    private function getNetworkError(): void
    {
        $this->curlErrno = curl_errno($this->curl);
        $this->curlError = curl_error($this->curl);
    }

    protected function getResposeType(string|HttpContentType $header): void
    {
        if (self::isHave($header, 'text/json') || self::isHave($header, HttpContentType::JSON)) {
            $this->isJson = true;
        } else if (
            self::isHave($header, HttpContentType::XML)
            || self::isHave($header, 'text/xml')
            || self::isHave($header, 'application/atom+xml')
        ) {
            $this->isXml = true;
        } else if (self::isHave($header, HttpContentType::TEXT)) {
            $this->isText = true;
        } else if (self::isHave($header, HttpContentType::HTML)) {
            $this->isHtml = true;
        } else if (self::isHave($header, HttpContentType::FORM_URL)) {
            $this->isArray = true;
            parse_str($this->responseBody, $result);
            $this->responseBody = var_export($result, true);
        }
    }

    public function getResposeJsonBody($associative = true)
    {
        if ($this->isJson) {
            return json_decode($this->responseBody, $associative);
        }
        return $this->responseBody;
    }

    public static function isHave(string|HttpContentType $hay, string|HttpContentType $needle): bool
    {
        if ($needle instanceof HttpContentType) {
            $needle = $needle->value;
        }
        if ($hay instanceof HttpContentType) {
            $hay = $hay->value;
        }
        return stripos($hay, $needle) !== false;
    }

    public function view(): HTTP
    {
        if ($this->isShow) {
            return $this;
        }
        if (!$this->isrun) {
            return $this;
        }

        if (self::$isCLI && !self::$isWebview) {
            $this->showConsole();
        } else {
            $this->showHTML();
        }
        self::$showCount++;
        $this->isShow = true;
        return $this;
    }

    /**
     * @param HTTP::callable $callable
     * @param mixed ...$args
     */
    public function then(callable $callable, ...$args): HTTP
    {
        if (!$this->isrun) {
            return $this;
        }
        $callable->call($this, ...$args);
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
        } else if (!self::$isCLI && $nl) {
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
        if (self::$showRequestBody) {
            echo $this->requestBody . PHP_EOL;
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
            } else if ($this->isArray) {
                echo $this->responseBody;
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

        if (!self::$isCLI || self::$isWebview) {
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
        $execNum = self::$execCount;
        echo <<<HTML
        <div class="accordion-item">
        <h2 class="accordion-header" id="commonConfigHeading-{$execNum}">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#commonConfig-{$execNum}" aria-expanded="true" aria-controls="commonConfig-{$execNum}">
        HTML;
        self::BLUE("{$this->method} {$this->url} ", true);
        echo <<<HTML
        </button></h2>
        <div id="commonConfig-{$execNum}" class="accordion-collapse collapse show" aria-labelledby="commonConfigHeading-{$execNum}" data-bs-parent="#mainAccordion">
        <div class="accordion-body d-grid gap-2">
        HTML;
        if (!$this->httpCode) {
            echo '<div class="alert alert-danger" role="alert">' . curl_error($this->curl) . '</div>';
        }
        if (self::$showRequestHeader) {
            $id = 'showRequestHeaderCollapse-' . self::$execCount;
            echo <<<HTML
            <a href="#a-{$id}" class="btn btn-outline-primary dropdown-toggle" role="button" data-bs-toggle="collapse" data-bs-target="#{$id}" aria-expanded="false" aria-controls="{$id}" id="a-{$id}">实际请求头</a>
            <div class="collapse" id="{$id}"><ul class="list-group">
            HTML;
            foreach ($this->realRequestHeader as $i => $header) {
                if (!$header) {
                    continue;
                }
                echo '<li class="list-group-item">';
                if (strpos($header, ':') === false && $header) {
                    self::GREEN($header);
                } else {
                    self::MAGENTA(str_replace(':', ':' . self::$colors['END'] . '<span>', $header));
                }
                echo '</li>';
            }
            echo '</ul></div>';
        }
        if (self::$showRequestBody) {
            if (self::$requestBodyType == HttpContentType::JSON) {
                echo "<script class=\"responseContent\" type=\"text/plain\" content-type=\"json\">{$this->requestBody}</script>";
            } else {
                echo '<code>' . (is_scalar($this->requestBody) ? $this->requestBody : print_r($this->requestBody, true)) . '</code>';
            }
        }
        if (self::$showResponseHeader) {
            $id = 'showResponseHeaderCollapse-' . self::$execCount;
            if ($this->httpCode >= 500) {
                $color = 'danger';
            } else if ($this->httpCode >= 400) {
                $color = 'warning';
            } else if ($this->httpCode < 300) {
                $color = 'success';
            } else {
                $color = 'primary';
            }

            echo <<<HTML
            <div class="d-grid gap-2 d-md-block"><a href="javascript:location.reload()" class="btn btn-info">刷新</a>
            <a href="#a-{$id}" class="btn btn-outline-{$color} dropdown-toggle" role="button" data-bs-toggle="collapse" data-bs-target="#{$id}" aria-expanded="false" aria-controls="{$id}" id="a-{$id}">响应头：{$this->httpCode}</a></div>
            <div class="collapse" id="{$id}"><ul class="list-group">
            HTML;
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
            echo '</div></div></div>';
            return;
        }
        if ($this->isArray) {
            highlight_string('<?php ' . $this->responseBody . ';?>');
            echo '</div></div></div>';
        } else {
            $contentType = $this->isJson ? 'json' : ($this->isXml ? 'xml' : 'html');
            $content = $this->isJson ? $this->responseBody : str_ireplace(['&', '</script'], ['&amp;', '&lt;/script'], $this->responseBody);
            echo "<script class=\"responseContent\" type=\"text/plain\" content-type=\"{$contentType}\">{$content}</script></div></div></div>";
        }
    }

    public function run(): HTTP
    {
        self::$forceRun = true;
        return $this;
    }

    protected function checkRun(bool $unparse = true): bool
    {
        return $this->checkRunNew($unparse);
        $funcName = ['GET', 'PUT', 'POST', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'SHOW', 'SAVE'];
        if ($unparse) {
            if (self::$forceRun) {
                return true;
            }
            try {
                throw new \Exception();
            } catch (\Exception $e) {
                $trace = $e->getTrace();
            }
            $callline = 0;
            foreach ($trace as $t) {
                if (isset($t['function']) && in_array($t['function'], $funcName)) {
                    $callline = $t['line'];
                    break;
                }
            }
            if (!$callline) {
                return false;
            }

            $this->enableShow = false;
            if (in_array($callline, self::$runShowFlagLines)) {
                $this->enableShow = true;
                return true;
            }
            if (in_array($callline, self::$runFlagLines)) {
                return true;
            }
            return false;
        }

        $files = get_included_files();
        foreach ($files as $f) {
            if ($f == __FILE__) {
                continue;
            }
            $tokens = token_get_all(file_get_contents($f, false));
            $tokensCount = count($tokens);
            for ($i = 0; $i < $tokensCount; $i++) {
                $token = $tokens[$i];
                $findRunTag = $findRunShowTag = false;
                if (is_array($token) && $token[0] === T_COMMENT && $token[1] === self::$runShowTag) {
                    $findRunShowTag = true;
                    $line = $token[2];
                } else if (is_array($token) && $token[0] == T_COMMENT && $token[1] == self::$runTag) {
                    $findRunTag = true;
                    $line = $token[2];
                }

                while ($findRunShowTag || $findRunTag) {
                    $i++;
                    $token = $tokens[$i];
                    if (is_array($token) && $token[0] === T_WHITESPACE && trim($token[1]) === '' &&  strpos($token[1], "\n") !== false) {
                        $line++;
                        continue;
                    } else if (is_array($token) && $token[0] === T_WHITESPACE) {
                        continue;
                    }

                    if (is_array($token) && $token[0] === T_STRING && in_array($token[1], $funcName) && $token[2] == $line) {
                        if ($findRunShowTag) {
                            self::$runShowFlagLines[] = $token[2];
                        } else if ($findRunTag) {
                            self::$runFlagLines[] = $token[2];
                        }
                        break;
                    } elseif (is_array($token) && $token[2] == $line) {
                        break;
                    }
                }
            }
        }

        return false;
    }

    protected function checkRunNew(bool $unparse = true): bool
    {
        $funcName = ['GET', 'PUT', 'POST', 'DELETE', 'HEAD', 'OPTIONS', 'TRACE', 'SHOW', 'SAVE'];
        if ($unparse) {
            if (self::$forceRun) {
                return true;
            }
            try {
                throw new \Exception();
            } catch (\Exception $e) {
                $trace = $e->getTrace();
            }
            $callline = 0;
            foreach ($trace as $t) {
                if (isset($t['function']) && in_array($t['function'], $funcName)) {
                    $callline = $t['line'];
                    break;
                }
            }
            if (!$callline) {
                return false;
            }

            $this->enableShow = false;
            if (isset(self::$runShowFlagLines[$callline])) {
                $this->enableShow = true;
                unset(self::$runShowFlagLines[$callline]);
                return true;
            }
            if (isset(self::$runFlagLines[$callline])) {
                unset(self::$runFlagLines[$callline]);
                return true;
            }
            return false;
        }
        $files = get_included_files();

        foreach ($files as $f) {
            if ($f == __FILE__) {
                continue;
            }
            $tokens = token_get_all(file_get_contents($f, false));
            $tokensCount = count($tokens);
            $runFlagLines = $runShowFlagLines = 0;
            for ($i = 0; $i < $tokensCount; $i++) {
                $token = $tokens[$i];
                if (!is_array($token)) {
                    continue;
                }
                if (is_array($token) && $token[0] === T_COMMENT && $token[1] === self::$runShowTag) {
                    $runShowFlagLines = $token[2];
                } else if (is_array($token) && $token[0] == T_COMMENT && $token[1] == self::$runTag) {
                    $runFlagLines = $token[2];
                } else if (is_array($token) && $token[0] === T_STRING && in_array($token[1], $funcName)) {
                    if (!$runFlagLines && !$runShowFlagLines) {
                        continue;
                    }
                    if ($runFlagLines + 1 != $token[2] && $runShowFlagLines + 1 != $token[2]) {
                        $runFlagLines = 0;
                        $runShowFlagLines = 0;
                        continue;
                    }
                    if ($runShowFlagLines) {
                        self::$runShowFlagLines[$token[2]] = $i;
                    } else if ($runFlagLines) {
                        self::$runFlagLines[$token[2]] = $i;
                    }
                    $runFlagLines = 0;
                    $runShowFlagLines = 0;
                }
            }
        }
        return false;
    }
    public function parseToken($tokens, $i) {}


    public function showArrayTable(Traversable|array $array): void
    {
        if (!self::$showArrayTable) {
            print_r($array);
            return;
        }

        $cols = (int)exec('tput cols');
        $lineSep = str_repeat('=', $cols) . PHP_EOL;
        echo $lineSep;
        foreach (self::$arrayTableLayout as $line) {
            $width = (int)floor($cols / count($line));
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

    protected function showList(array $array, int $indent): void
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
        $msg = 'All(' . HTTP::$execCount . ') requested and shown' . PHP_EOL;
        if (!HTTP::$showCount) {
            $msg =  'Exec ' . HTTP::$execCount . ' request  and no  output data' . PHP_EOL;
        } else if (HTTP::$showCount != HTTP::$execCount) {
            $msg = 'Exec ' . HTTP::$execCount . ' request  and ' . HTTP::$showCount . ' show output' . PHP_EOL;
        }
        if (!self::$isCLI || self::$isWebview) {
            echo <<<HTML
            </div><div class="alert alert-success" role="alert">$msg</div>
            <div class="modal" tabindex="-1" id="PageLoad">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">页面加载成功</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                </div>
            </div>
            </div>
            <script>document.addEventListener('DOMContentLoaded', function() {
                const loadModal = new bootstrap.Modal('#PageLoad', {keyboard: true});
                loadModal.show();
                setTimeout(() => loadModal.hide(), 500);
            });</script>
            </div></body></html>
            HTML;
        } else {
            self::GREEN($msg);
        }
    }

    public function htmlPage()
    {
        if (PHP_SAPI == 'cli' && !self::$isWebview) {
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

                u {
                    left: 1em;
                    position: absolute;
                    text-decoration: none;
                    color: #666;
                    font-size: 1em;
                    cursor: pointer;
                    background-color: #FFFFFF;
                    display: inline-block;
                    width: 2.6em;
                    text-align: right;
                }

                u:hover {
                    text-decoration: underline;
                }

                html {
                    width: 99%;
                    word-break: break-all;
                }

                hr {
                    padding: 1px;
                    color: yellow;
                }

                code>ul {
                    display: block;
                    padding-left: 2px;
                }

                code>ul>ul {
                    display: block;
                }

                code ul li i {
                    color: #999;
                }

                ul {
                    list-style: none;
                    display: none;
                }

                ul li {
                    white-space: normal;
                    word-break: break-all;
                    word-wrap: break-word;
                }

                x {
                    color: blue;
                    font-weight: bold;
                    margin: 0 2px;
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
                    margin-left: 2em;
                    overflow: auto;
                }

                code button {
                    display: block;
                }

                code b {
                    color: navy;
                    margin-right: 10px;
                }

                code div {
                    display: none;
                    margin-left: 20px;
                }

                code>div {
                    display: block;
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

                code>textarea {
                    opacity: 0;
                    width: 1px;
                    height: 1px;
                    float: right;
                }

                code>button {
                    float: right;
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
                var idx = 1;

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
                            return '[],';
                        }
                        for (let i in o) t += '<li><u>' + (idx++) + '</u><i>' + i + ':</i>' + jsonview(o[i]) + '</li>';
                        t = t.slice(0, -5) + '</li>';
                        return '[<ul>' + t + '</ul></li><li><u>' + (idx++) + '</u>],';
                    } else if (o instanceof Object) {
                        for (let i in o) t += '<li><u>' + (idx++) + '</u><b>"' + i + '":</b>' + jsonview(o[i]) + '</li>';
                        t = t.slice(0, -5) + '</li>';
                        return '{<ul>' + t + '</ul></li><li><u>' + (idx++) + '</u>},';
                    } else if (typeof o == 'string') {
                        o = o.replaceAll(/[\r\n\t&]/img, function(m) {
                            let a = {
                                "\r": '\\r',
                                "\n": '\\n',
                                "\t": '\\t'
                            };
                            if (m == '&') {
                                return '&amp;';
                            }
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

                function toggleJsonBody(e) {
                    let ul = e.target.parentElement.querySelector('ul');
                    let firstStatus = '';
                    ul.querySelectorAll('ul').forEach((ul, i) => {
                        if (i == 0) {
                            return;
                        } else if (i == 1) {
                            firstStatus = ul.style.display = ul.style.display == 'block' ? 'none' : 'block'
                        } else {
                            ul.style.display = firstStatus;
                        }
                    });
                }

                function copyResponse(e) {
                    let obj = e.target;
                    let btn = obj;
                    do {
                        find = false;
                        if (obj.parentElement.tagName == 'CODE') {
                            find = true;
                        } else if (obj.parentElement.tagName == 'BODY') {
                            find = true;
                        }
                        obj = obj.parentElement;
                    } while (!find);

                    let code = obj.previousElementSibling;
                    if (window.isSecureContext) {
                        navigator.clipboard.writeText(code.textContent);
                    } else {
                        btn.nextElementSibling.value = code.textContent;
                        btn.nextElementSibling.select();
                        document.execCommand('copy');
                    }
                    alert('已复制');
                }

                $(function() {
                    $('.responseContent').forEach(function(e) {
                        let type = e.getAttribute('content-type');
                        let v = e.innerHTML.trim();
                        let s = null;
                        if (type == 'json') {
                            s = d.createElement('code');
                            try {
                                s.innerHTML = '<button class="btn btn-primary">复制</button><textarea></textarea><button class="btn btn-success">显示/隐藏</button></div><ul><u>' + (idx++) + '</u>' + jsonview(JSON.parse(v)) + '</ul>';
                                idx = 1;
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
                        } else if (type == 'html') {
                            s = d.createElement('iframe');
                            s.width = "99%";
                            s.height = "900";

                            if (/^<!DOCTYPE|<html/i.test(v)) {
                                s.srcdoc = v.replaceAll('&lt;/script', '</script').replaceAll('&amp;', '&');
                            } else {
                                s.srcdoc = '<pre>' + v.replaceAll('&lt;/script', '</script').replaceAll('&amp;', '&') + '</pre>';
                            }
                        }
                        e.after(s);
                    });
                    $('code>.btn-primary').forEach((b) => b.addEventListener('click', copyResponse));
                    $('code>.btn-success').forEach((b) => b.addEventListener('click', toggleJsonBody));
                    $('u').forEach((c) => c.addEventListener('click', (e) => {
                        let ul = e.target.parentNode.querySelector('ul');
                        if (!ul) {
                            return;
                        }
                        ul.style.display = ul.style.display == 'block' ? 'none' : 'block';
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
