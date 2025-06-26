<?php
class DnsQuery
{

    public $transId = '';
    public $rrtype = self::RR_ANY;
    public $rropcode = 0;
    public $unsupport = false;
    public $queryName = [];
    public $queryData = '';
    public $dnsHost = 'udp://127.0.0.53:53';
    public $enableDOH = true;
    public $timeout = 10;
    public static $UDPSize = 65494;

    private static $logfp;
    public static $logs = [];
    public static $requestDatetime;
    public static $DNS_DOMAIN_MAP = [
        'CF' => [
            'github.com',
            'google.com',
            'gstatic.com',
            'elastic.co'
        ]
    ];
    public static $LOCAL_RR_LIST = [
        'host.godaddy.com' => [
            self::RR_A => ['35.154.51.163', '65.2.72.240']
        ],
    ];
    public static $DNS_HOSTS = [
        'Default' => 'udp://127.0.0.53:53',
        'CF' => 'https://1.1.1.1/dns-query',
        'TX' => 'https://doh.pub/dns-query',
    ];
    public static $BASE64_DNS_HOST = [];

    public function __construct()
    {
        header('cache-control: no-cache,no-store');
        if (!defined('RDIR')) {
            define('RDIR', dirname(realpath($_SERVER['SCRIPT_FILENAME'])));
        }
        if (isset(self::$DNS_HOSTS['Default'])) {
            $this->dnsHost = self::$DNS_HOSTS['Default'];
        }
        if (!is_dir(RDIR . '/logs')) {
            mkdir(RDIR . '/logs');
        }
        if (!is_dir(RDIR . '/data')) {
            mkdir(RDIR . '/data');
        }
        date_default_timezone_set('Asia/Shanghai');
        register_shutdown_function([$this, 'shutdown']);
        self::$requestDatetime = new DateTime();
        self::$logfp = fopen(RDIR . '/logs/dns.log-' . date('Y-m-d'), 'ab');
        if (!self::$logfp) {
            echo 'open log error:' . RDIR . '/logs/dns.log-' . date('Y-m-d');
        }

        if (PHP_SAPI != 'cli') {
            if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['dns'])) {
                $this->queryData = $this->base64url_decode($_GET['dns']);
            } else {
                $this->queryData = file_get_contents("php://input");
                $ctype = '';
                if (isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                    $ctype = $_SERVER['HTTP_CONTENT_TYPE'];
                } elseif (isset($_SERVER['CONTENT_TYPE'])) {
                    $ctype = $_SERVER['CONTENT_TYPE'];
                }
                if ($ctype == 'application/base64-dns-message') {
                    $this->queryData = base64_decode($this->queryData);
                }
            }
            if (empty($this->queryData)) {
                self::log("{$_SERVER['REQUEST_METHOD']}:query data empty");
                return;
            }

            header('Content-Type: application/dns-message', true);
            $this->dnsClient();
        }
    }

    public static function log(...$datas)
    {
        $msg = '';
        foreach ($datas as $d) {
            $msg .= is_string($d) ? $d : json_encode($d, JSON_PRETTY_PRINT);
        }
        self::$logs[] = self::$requestDatetime->format('[H:i:s-u]') . $msg . PHP_EOL;
    }
    public function saveData($t, $data)
    {
        file_put_contents(RDIR . '/data/dns.' . $t, $data);
    }

    public function querySelf($type, $name)
    {
        $flags = self::initHeadFlags();
        $flags[self::P_H_FLAG_QR] = self::QR_QUERY;
        $flags[self::P_H_FLAG_RD] = 1;
        $packet = self::initPacketArray();
        $packet[self::P_QUERIES] = [
            [
                self::P_RR_NAME => $name,
                self::P_RR_TYPE => $type,
                self::P_RR_CLASS => self::CLASS_IN,
            ]
        ];
        $this->queryData = $this->buildDNSResponse($flags, $packet, self::$UDPSize);
        $this->dnsClient();
    }

    public function dnsClient()
    {
        $packet = $this->parseDNSPackage($this->queryData, $qstate);
        if ($packet[self::P_H_FLAG][self::P_H_FLAG_OPCODE] != self::OP_QUERY) {
            echo $this->buildNotImplementedData();
            return;
        }
        $this->transId = $packet[self::P_H_ID];
        $this->queryName = $packet[self::P_QUERIES];
        // self::log('Query:', $this->queryName);
        $ret = $this->getDNSCache($packet);

        if ($ret) {
            header("Content-Length: " . strlen($ret));
            echo $ret;
            return;
        }

        $this->switchDns();

        do {
            if ($this->enableDOH) {
                $ret = $this->DOHClient($body, $responseSize, $responseInfo);
                self::saveData('A', $body);
            } else {
                $ret = $this->TcpUdpClient($body, $responseSize);
            }
            if (!$ret) {
                self::log("Query From $this->dnsHost Error, Switch " . self::$DNS_HOSTS['Default']);
                $this->dnsHost = self::$DNS_HOSTS['Default'];
                $this->enableDOH = false;
            }
        } while (!$ret);

        if (!$ret) {
            $body = $this->buildServerErrorData();
        } else {
            if (count($this->queryName) == 1) {
                $this->cacheDNSRecord($body);
            }
            //$packet = $this->parseDNSPackage($body, $astate);
        }

        header("Content-Length: " . $responseSize);
        echo $body;
    }

    public function getMinTTL($packet)
    {
        $min = 0;
        foreach ($packet[self::P_ANSWERS] as $answer) {
            if ($answer[self::P_RR_TYPE] == self::RR_A || $answer[self::P_RR_TYPE] == self::RR_AAAA) {
                if ($answer[self::P_RR_TTL] < $min) {
                    $min = $answer[self::P_RR_TTL];
                }
            }
        }
        return $min;
    }

    public function cacheDNSRecord($body)
    {
        $type = $this->queryName[0][self::P_RR_TYPE] . '-' . $this->queryName[0][self::P_RR_NAME];
        $this->saveData($type, $body);
    }

    public function getDNSCache($queryPacket)
    {
        $localRecord = '';
        if ($this->localServer($localRecord, $queryPacket)) {
            return $localRecord;
        }
        $type = $this->queryName[0][self::P_RR_TYPE] . '-' . $this->queryName[0][self::P_RR_NAME];
        $file = RDIR . '/data/dns.' . $type;
        if (file_exists($file)) {
            $data = file_get_contents($file);
            if (strlen($data) == 0) {
                unlink($file);
                return false;
            }

            $packet = $this->parseDNSPackage($data);
            $min = $this->getMinTTL($packet);
            if (filemtime($file) + $min < time()) {
                unlink($file);
                self::log($this->queryName[0][self::P_RR_NAME] . " cache expired");
                return false;
            }
            self::log($this->queryName[0][self::P_RR_NAME] . ' use cache');
            return pack('n', $this->transId) . substr($data, 2);
        }
        return false;
    }
    public static function initPacketArray()
    {
        return [
            self::P_QUERIES => [],
            self::P_ANSWERS => [],
            self::P_AUTHORITY => [],
            self::P_ADDITIONAL => [],
        ];
    }
    public static function initHeadFlags()
    {
        return [
            self::P_H_FLAG_QR => self::QR_QUERY,
            self::P_H_FLAG_OPCODE => self::OP_QUERY,
            self::P_H_FLAG_AA => 0,
            self::P_H_FLAG_TC => 0,
            self::P_H_FLAG_RD => 0,
            self::P_H_FLAG_RA => 0,
            self::P_H_FLAG_Z => 0,
            self::P_H_FLAG_RCODE => 0
        ];
    }
    public function localServer(&$recordData, $queryPacket)
    {
        $type = $this->queryName[0][self::P_RR_TYPE];
        $name = $this->queryName[0][self::P_RR_NAME];
        if($queryPacket[self::P_ADDITIONAL]) {
            $UDPSize = $queryPacket[self::P_ADDITIONAL][0][self::P_RR_OPT_UDP_SIZE];
        } else {
            $UDPSize = self::$UDPSize;
        }
        if (!isset(self::$LOCAL_RR_LIST[$name][$type])) {
            return false;
        }

        $packet = self::initPacketArray();
        $packet[self::P_QUERIES] = $queryPacket[self::P_QUERIES];
        foreach ($this->queryName as $query) {
            $name = $query[self::P_RR_NAME];
            $type = $query[self::P_RR_TYPE];
            foreach (self::$LOCAL_RR_LIST[$name][$type] as $r) {
                $packet[self::P_ANSWERS][] = [
                    self::P_RR_NAME => $name,
                    self::P_RR_TYPE => $type,
                    self::P_RR_CLASS => self::CLASS_IN,
                    self::P_RR_TTL => 3600,
                    self::P_RR_DATA => $r
                ];
            }
        }
        if (empty($packet[self::P_ANSWERS])) {
            return false;
        }

        $packet[self::P_ADDITIONAL][] = [
            self::P_RR_NAME => self::S_NAME_END,
            self::P_RR_TYPE => self::RR_OPT,
            self::P_RR_OPT_UDP_SIZE => self::$UDPSize,
            self::P_RR_OPT_RCODE => 0,
            self::P_RR_OPT_E_V => 0,
            self::P_RR_OPT_DO => 0,
            self::P_RR_OPT_Z => 0,
            self::P_RR_OPT_OPTION => [],
        ];

        $flags = self::initHeadFlags();
        $flags[self::P_H_FLAG_QR] = self::QR_RESPONSE;
        $flags[self::P_H_FLAG_OPCODE] = $queryPacket[self::P_H_FLAG][self::P_H_FLAG_OPCODE];
        $flags[self::P_H_FLAG_RD] = $queryPacket[self::P_H_FLAG][self::P_H_FLAG_RD];
        $flags[self::P_H_FLAG_RA] = 1;

        $recordData = $this->buildDNSResponse($flags, $packet, $UDPSize);
        return true;
    }

    public function buildName($labelist, $name, &$rLabels = null, $unend = false)
    {
        if ($name == self::S_NAME_END) {
            return $name;
        }
        $labels = explode('.', $name);
        $rLabels = array_reverse($labels, true);
        $ptrMaxLen = 0;
        $ptrName = '';
        foreach ($labelist as $name => $ls) {
            $ptrLen = 0;
            $findNum = 0;
            foreach ($rLabels as $i => $label) {
                if (current($ls[0]) === $label) {
                    $ptrLen += strlen($label) + 1;
                } else {
                    break;
                }
                if (next($ls[0]) === false) {
                    break;
                }
            }
            if ($ptrLen > $ptrMaxLen) {
                $ptrMaxLen = $ptrLen;
                $ptrName = $name;
            }
        }

        $binary = '';
        if ($ptrName) {
            $ptrAdd = substr($ptrName, 0, -1 * $ptrMaxLen);
            $preLen = $ptrAdd ? strlen($ptrAdd) + 1 : 0;
            $ptrPos = $labelist[$ptrName][1] + $preLen;

            $realLabels = explode('.', substr($name, 0, -1 * $ptrMaxLen));

            foreach ($realLabels as $l) {
                if (!empty($l)) {
                    $binary .= chr(strlen($l)) . $l;
                }
            }
            $binary .= self::S_PTR . chr($ptrPos);
        } else { //没有指针
            foreach ($labels as $l) {
                $binary .= chr(strlen($l)) . $l;
            }
            if (!$unend) {
                $binary .= self::S_NAME_END;
            }
        }
        return $binary;
    }

    public function buildDNSResponse($flagArray, $packet, $UDPSize)
    {
        $labelist = [];
        $offset = 12;
        $binary = '';
        foreach ($packet as $zone => $answer) {
            foreach ($answer as $a) {
                $rbinary = $this->buildName($labelist, $a[self::P_RR_NAME], $rLabels);

                $rbinary .= pack('n2', $a[self::P_RR_TYPE], $a[self::P_RR_CLASS]);
                if ($zone != self::P_QUERIES) {
                    $rbinary .= $this->buildRRData($labelist, $a, $offset + strlen($rbinary));
                }
                if (in_array($a[self::P_RR_TYPE], self::G_CACHE_NAME_TYPE)) {
                    $labelist[$a[self::P_RR_NAME]] = [$rLabels, $offset];
                }
                $offset += strlen($rbinary);
                $binary .= $rbinary;
            }
        }

        if (strlen($binary) + 12 > $UDPSize) {
            $flagArray[self::P_H_FLAG_TC] = 1;
        }

        $flag = $this->setFlags($flagArray);
        $headers = [
            $this->transId,
            $flag,
            count($packet[self::P_QUERIES]),
            count($packet[self::P_ANSWERS]),
            count($packet[self::P_AUTHORITY]),
            count($packet[self::P_ADDITIONAL])
        ];

        return pack("n*", ...$headers) . $binary;
    }

    public function buildRRData(&$labelist, $a, $offset)
    {
        $rType = $a[self::P_RR_TYPE];
        $binary = '';

        $binary .= pack('N', $a[self::P_RR_TTL]);

        if ($rType == self::RR_A) {
            $binary .= pack('n', 4);
            $binary .= pack('N', ip2long($a[self::P_RR_DATA]));
        } else if ($rType == self::RR_AAAA) {
            $binary .= pack('n', 8);
            $binary .= self::ipv6long($a[self::P_RR_DATA]);
        } else if (in_array($rType, self::G_RR_DATA_NAME)) {
            $name = $this->buildName($labelist, $a[self::P_RR_DATA], $rLabels, true);
            $binary .= pack('n', strlen($name));
            $binary .= $name;
            $labelist[$a[self::P_RR_DATA]] = [$rLabels, $offset + 6];
        } else if ($rType == self::RR_OPT) {
            $binary = pack('C2', $a[self::P_RR_OPT_RCODE], $a[self::P_RR_OPT_E_V]);
            $binary .= pack('n', ($a[self::P_RR_OPT_DO] << 15) | $a[self::P_RR_OPT_Z]);
            if (empty($a[self::P_RR_OPT_OPTION])) {
                $binary .= pack('n', 0);
            } else {
                $optionBinary = '';
                foreach ($a[self::P_RR_OPT_OPTION] as $option) {
                    $optionBinary .= pack('n2', $option[self::P_RR_OPT_OPTION_CODE], strlen($option[self::P_RR_OPT_OPTION_DATA]));
                    $optionBinary .= $option[self::P_RR_OPT_OPTION_DATA];
                }
                $binary .= pack('n', strlen($optionBinary)) . $optionBinary;
            }
        } else if ($rType == self::RR_HTTPS) {
            $hv = $a[self::P_RR_DATA];
            $svcbHttpsBinary = pack('n', $hv[self::P_RR_HTTPS_PRIORITY]);
            $rl = null;

            $svcbHttpsBinary .= $this->buildName($labelist, $hv[self::P_RR_HTTPS_TARGET_NAME], $_rl, true);
            foreach ($hv[self::P_RR_HTTPS_PARAMS] as $param) {
                $svcbHttpsBinary .= pack('n', $param[self::P_RR_HTTPS_PARAMS_KEY]);
                if ($param[self::P_RR_HTTPS_PARAMS_KEY] ==  self::RR_HTTPS_NO_DEFAULT_ALPN) {
                    $svcbHttpsBinary .= pack('n', 0);
                    continue;
                } else if (
                    $param[self::P_RR_HTTPS_PARAMS_KEY] == self::RR_HTTPS_MANDATORY
                    || $param[self::P_RR_HTTPS_PARAMS_KEY] == self::RR_HTTPS_PORT
                ) {
                    $paramValue = pack('n*', ...$param[self::P_RR_HTTPS_PARAMS_KEY]);
                    $svcbHttpsBinary .= pack('n', strlen($paramValue)) . $paramValue;
                    continue;
                } else if ($param[self::P_RR_HTTPS_PARAMS_KEY] == self::RR_HTTPS_ECH) {
                    $echconfig = $this->buildECHConfig($param[self::P_RR_HTTPS_PARAMS_VALUE]);
                    $svcbHttpsBinary .= pack("n", strlen($echconfig)) . $echconfig;
                    continue;
                }
                $paramValue = '';
                foreach ($param[self::P_RR_HTTPS_PARAMS_VALUE] as $v) {
                    switch ($param[self::P_RR_HTTPS_PARAMS_KEY]) {
                        case self::RR_HTTPS_ALPN:
                            $paramValue .= chr(strlen($v)) . $v;
                            break;
                        case self::RR_HTTPS_IPV4HINT:
                            $paramValue .= pack('N', ip2long($v));
                            break;
                        case self::RR_HTTPS_IPV6HINT:
                            $paramValue .= self::ipv6long($v);
                            break;
                    }
                }
                $svcbHttpsBinary .= pack('n', strlen($paramValue)) . $paramValue;
            }
            $binary .= pack('n', strlen($svcbHttpsBinary)) . $svcbHttpsBinary;
        }

        return $binary;
    }

    /**
     * https://datatracker.ietf.org/doc/draft-ietf-tls-esni/
     */
    public function buildECHConfig($value)
    {
        self::genHpke($value);
        $id = pack('n', 0xfe0d); //version
        $binary = chr($value['configId']) . pack('n2', $value['kemId'], strlen($value['pubKey'])) . $value['pubKey'];
        $binary .= pack('n', count($value['ciphers']) * 4);
        foreach ($value['ciphers'] as $cipher) {
            $binary .= pack('n2', $cipher['kdfId'], $cipher['aeadId']);
        }
        $binary .= chr($value['maxNameLen']) . chr(strlen($value['pubName'])) . $value['pubName'];
        if ($value['extensions']) {
            foreach ($value['extensions'] as $ext) {
                $binary .= pack('n', $ext['type'], strlen($ext['data']));
                $binary .= $ext['data'];
            }
        } else {
            $binary .= pack('n', 0);
        }
        $data = $id . pack('n', strlen($binary)) . $binary;
        return pack('n', strlen($data)) . $data;
    }

    public static function genHpke(&$value)
    {
        if (!function_exists('sodium_crypto_box_keypair')) {
            $kp = sodium_crypto_kx_keypair();
            $kp_pubkey = sodium_crypto_kx_publickey($kp);

            $value['kemId'] = self::ECC_NAME_ID['x25519'][0];
            $value['pubKey'] = $kp_pubkey;
            $value['ciphers'][] = ['kdfId' => self::ECC_NAME_ID['x25519'][1], 'aeadId' => 0x0002];
            return true;
        }
        $names = openssl_get_curve_names();
        foreach (self::ECC_NAME_ID as $n => $ids) {
            if (in_array($n, $names)) {
                $key = openssl_pkey_new([
                    'private_key_type' => OPENSSL_KEYTYPE_EC,
                    'curve_name' => $n,
                ]);

                $value['kemId'] = self::ECC_NAME_ID[$n][0];
                $value['pubKey'] = openssl_pkey_get_details($key)['ec']['x'];
                $value['ciphers'][] = ['kdfId' => self::ECC_NAME_ID[$n][1], 'aeadId' => 0x0002];
                return true;
            }
        }
        return false;
    }

    public static function bset($bit, $size)
    {
        return ($bit & (1 << $size)) > 0 ? 1 : 0;
    }

    public function setFlags($flags)
    {
        $flag = 0;
        if ($flags[self::P_H_FLAG_QR]) {
            $flag = 1 << 15;
        }
        if ($flags[self::P_H_FLAG_OPCODE]) {
            $flag |= $flags[self::P_H_FLAG_OPCODE] << 11;
        }
        if ($flags[self::P_H_FLAG_AA]) {
            $flag |= 1 << 10;
        }
        if ($flags[self::P_H_FLAG_TC]) {
            $flag |= 1 << 9;
        }
        if ($flags[self::P_H_FLAG_RD]) {
            $flag |= 1 << 8;
        }
        if ($flags[self::P_H_FLAG_RA]) {
            $flag |= 1 << 7;
        }
        if ($flags[self::P_H_FLAG_RCODE]) {
            $flag |= $flags[self::P_H_FLAG_RCODE];
        }
        return $flag;
    }

    public function parseFlags($bit)
    {
        $flags = self::initHeadFlags();
        $flags[self::P_H_FLAG_QR] = self::bset($bit, 15);
        $flags[self::P_H_FLAG_OPCODE] = $flags[self::P_H_FLAG_QR] ? (($bit >> 11) ^ 16) : ($bit >> 11);
        $flags[self::P_H_FLAG_AA] = self::bset($bit, 10);
        $flags[self::P_H_FLAG_TC] = self::bset($bit, 9);
        $flags[self::P_H_FLAG_RD] = self::bset($bit, 8);
        $flags[self::P_H_FLAG_RA] = self::bset($bit, 7);
        $flags[self::P_H_FLAG_RCODE] = $bit & 15;
        return $flags;
    }

    public static function getDomainFromOffset($queryData, &$i, $maxLen = 254)
    {
        $start = $i;
        $labels = [];
        do {
            if ($queryData[$i] == self::S_NAME_END) {
                $i++;
                break;
            }
            if ($queryData[$i] == self::S_PTR) {
                $i++;
                $ptrOffset = ord($queryData[$i]);
                $labels[] = self::getDomainFromOffset($queryData, $ptrOffset);
                $i++;
                break;
            } else {
                $byteInt = ord($queryData[$i]);
                $i++;
                $labels[] = substr($queryData, $i, $byteInt);
                $i += $byteInt;
                continue;
            }
            $i++;
        } while (($i - $start) < $maxLen && isset($queryData[$i]));
        return join('.', $labels);
    }
    public static function ipv6long($ipv6)
    {
        return pack('n8', hexdec(str_replace(':', $ipv6)));
    }
    public static function long2ipv6($data, &$i)
    {
        return  join(':', array_map('dechex', self::unpack('n8', $data, $i)));
    }
    public static function unpack($format, $string, &$offset = 0)
    {
        $bitSize = ['n' => 2, 'N' => 4, 'C' => 1, 'H' => 1];
        try {
            $ret = unpack($format, $string, $offset);
            if (!$ret) {
                throw new ValueError(error_get_last());
            }
        } catch (ValueError $e) {
            self::log("Offset:$offset", $e->__toString());
        }
        $len  = $bitSize[$format[0]];
        if (strlen($format) > 1) {
            $len = $len * substr($format, 1);
        }
        $offset += $len;
        return $ret;
    }

    public function parseDNSPackage($queryData, &$parseStatus = true)
    {
        $parseStatus = true;
        $headers = self::unpack('n6', $queryData);
        $packet = self::initPacketArray();
        $packet[self::P_H_ID] = $headers[1];
        $packet[self::P_H_FLAG] = $this->parseFlags($headers[2]);
        $packet[self::P_H_QUESTION] = $headers[3];
        $packet[self::P_H_ANSWER] = $headers[4];
        $packet[self::P_H_AUTHORITY] = $headers[5];
        $packet[self::P_H_ADDITIONAL] = $headers[6];

        $queryDataLen = strlen($queryData);
        $count = $packet[self::P_H_QUESTION] + $packet[self::P_H_ANSWER] + $packet[self::P_H_AUTHORITY] + $packet[self::P_H_ADDITIONAL];
        $authorityOffset = $packet[self::P_H_QUESTION] + $packet[self::P_H_ANSWER];
        $additionalOffset = $count - $packet[self::P_H_ADDITIONAL];

        $pos = 12;
        for ($rrs = 0; $rrs < $count; $rrs++) {

            if ($pos > $queryDataLen) {
                $parseStatus = false;
                self::log("OutRangeCheck:read pos $pos >= data len $queryDataLen ");
                break;
            }
            $name = self::getDomainFromOffset($queryData, $pos);

            $rrtype = self::unpack('n2', $queryData, $pos);
            $queryName = [self::P_RR_NAME => $name, self::P_RR_TYPE => $rrtype[1], self::P_RR_CLASS => $rrtype[2]];
            $typeName = $queryName[self::P_RR_TYPE];

            if ($rrs >= $packet[self::P_H_QUESTION]) {
                $ttl = self::unpack('N', $queryData, $pos)[1];
                $queryName[self::P_RR_TTL] = $ttl;
                $RDLen = self::unpack('n', $queryData, $pos)[1];

                if ($typeName == self::RR_OPT) {
                    $queryName[self::P_RR_OPT_UDP_SIZE] = $queryName[self::P_RR_CLASS];
                    $queryName[self::P_RR_OPT_RCODE] = $queryName[self::P_RR_TTL];
                }
                $queryName[self::P_RR_DATA_LEN] = $RDLen;

                if (in_array($typeName, self::G_RR_DATA_NAME)) {
                    $data = self::getDomainFromOffset($queryData, $pos, $RDLen);
                } else if ($typeName == self::RR_A) {
                    $data = long2ip(self::unpack('N', $queryData, $pos)[1]);
                } else if ($typeName == self::RR_AAAA) {
                    $data = self::long2ipv6($queryData, $pos);
                } else {
                    $data = self::unpack("H{$RDLen}", $queryData, $pos);
                }
                $queryName[self::P_RR_DATA] = $data;
            }

            if ($packet[self::P_H_ADDITIONAL] && $rrs >= $additionalOffset) {
                $packet[self::P_ADDITIONAL][] = $queryName;
            } else if ($packet[self::P_H_AUTHORITY] && $rrs >= $authorityOffset) {
                $packet[self::P_AUTHORITY][] = $queryName;
            } else if ($packet[self::P_H_ANSWER] && $rrs >= $packet[self::P_H_QUESTION]) {
                $packet[self::P_ANSWERS][] = $queryName;
            } else {
                $packet[self::P_QUERIES][] = $queryName;
            }
        }
        if ($queryDataLen != $pos) {
            self::log("EndCheck, Last pos $pos != data len $queryDataLen ");
            $parseStatus = false;
        }
        return $packet;
    }

    public function switchDns()
    {
        foreach (self::$DNS_DOMAIN_MAP as $dns => $domain) {
            foreach ($domain as $name) {
                if (str_ends_with($this->queryName[0][self::P_RR_NAME], $name)) {
                    $this->dnsHost = self::$DNS_HOSTS[$dns];
                    self::log('Switch Dns:', $this->dnsHost);
                    return true;
                }
            }
        }
        $this->enableDOH = str_starts_with($this->dnsHost, 'https://');
        return true;
    }


    public function TcpUdpClient(&$response, &$responseSize)
    {
        $fp = stream_socket_client($this->dnsHost, $errno, $error, $this->timeout);
        if (!$fp) {
            self::log("Network Error: {$this->dnsHost} $error($errno)");
            return false;
        }
        $response = '';
        if (stream_socket_sendto($fp, $this->queryData)) {
            do {
                $response .=  stream_socket_recvfrom($fp, 512);
                if (strlen($response) < 512) {
                    break;
                }
                $this->parseDNSPackage($response, $parseStatus);
                if (!$parseStatus) {
                    continue;
                }
            } while (false);
            $responseSize = strlen($response);
            fclose($fp);
            return true;
        }
        fclose($fp);
        return false;
    }

    public function DOHClient(&$response, &$responseSize, &$responseInfo)
    {
        $isbas64 = in_array($this->dnsHost, self::$BASE64_DNS_HOST);
        $ch = curl_init($this->dnsHost);
        curl_setopt_array($ch, [
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_POSTFIELDS => $isbas64 ? base64_encode($this->queryData) : $this->queryData,
            CURLOPT_HTTPHEADER => [
                $isbas64 ? 'content-type: application/base64-dns-message' : 'content-type: application/dns-message',
                'accept: application/dns-message'
            ],
        ]);

        if (function_exists('curl_share_init_persistent')) {
            $sh = curl_share_init_persistent([CURL_LOCK_DATA_DNS, CURL_LOCK_DATA_CONNECT, CURL_LOCK_DATA_SSL_SESSION]);
            curl_setopt($ch, CURLOPT_SHARE, $sh);
        }

        $response = curl_exec($ch);

        $responseInfo = curl_getinfo($ch);
        $responseSize = $responseInfo['size_download'];
        if ($responseInfo['http_code'] == 0) {
            $error = curl_error($ch) . '(' . curl_errno($ch) . ')';
            self::log("Network Error: {$this->dnsHost} $error");
            return false;
        } else if ($responseInfo['http_code'] != 200) {
            self::log("Connect {$this->dnsHost} Error: HTTP {$responseInfo['http_code']}");
            return false;
        }
        if ($responseSize < 12) {
            self::log("Query {$this->dnsHost} Packet Size Error $responseSize");
            return false;
        }
        if ($responseInfo['http_code'] == 200) {
            return true;
        }
        self::log("Connect {$this->dnsHost}  Unknow Error");
        return false;
    }


    public function buildServerErrorData()
    {
        $flag  = (1 < 15) | 2;
        $h = [$this->transId, $flag, 0, 0, 0, 0];
        $result .= pack('n*', ...$h);
        return $result;
    }
    public function buildNotImplementedData()
    {
        $flag  = (1 < 15) | 5;
        $h = [$this->transId, $flag, 0, 0, 0, 0];
        $result .= pack('n*', ...$h);
        return $result;
    }
    public function base64url_encode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public function base64url_decode($data)
    {
        $data = strtr($data, '-_', '+/');
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode($data);
    }

    public function shutdown()
    {
        if (count(self::$logs) > 0) {
            fwrite(self::$logfp, implode('', self::$logs));
            fclose(self::$logfp);
        }
    }

    /**
     * https://www.rfc-editor.org/rfc/rfc9180.html#name-kem-ids
     * KEM IDs:
     * 0x0000 	Reserved
     * 0x0010 	DHKEM(P-256, HKDF-SHA256)
     * 0x0011 	DHKEM(P-384, HKDF-SHA384)
     * 0x0012 	DHKEM(P-521, HKDF-SHA512)
     * 0x0020 	DHKEM(X25519, HKDF-SHA256
     * 0x0021 	DHKEM(X448, HKDF-SHA512)
     *
     * https://www.rfc-editor.org/rfc/rfc9180.html#name-kdf-ids
     * KDF IDs:
     * 0x0000 	Reserved
     * 0x0001 	HKDF-SHA256
     * 0x0002 	HKDF-SHA384
     * 0x0003 	HKDF-SHA512
     *
     * https://www.rfc-editor.org/rfc/rfc9180.html#name-aead-ids
     * AEAD IDs:
     * 0x0000 	Reserved
     * 0x0001 	AES-128-GCM
     * 0x0002 	AES-256-GCM
     * 0x0003 	ChaCha20Poly1305
     * 0xFFFF 	Export-only
     */
    const ECC_NAME_ID = [
        'prime256v1' => [0x0010, 0x0001],
        'secp384r1' => [0x0011, 0x0002],
        'secp521r1' => [0x0012, 0x0003],
        'x25519' => [0x0020, 0x0001],
        'X448' => [0x0021, 0x0003],
    ];
    const ECH_TPL = [
        'configId' => 1, //random
        'kemId' => 32,
        'pubKey' => '',
        'ciphers' => [
            ['kdfId' => 1, 'aeadId' => 1],
        ],
        'maxNameLen' => 0,
        'pubName' => '', //签发服务器公用域名
        'extensions' => [], // ['type'=> '', 'data' => ]
    ];
    const S_NAME_END = "\0";
    const S_PTR = "\xc0";
    const P_H_ID = 0;
    const P_H_FLAG = self::P_H_ID + 1;
    const P_H_FLAG_QR = 0;
    const P_H_FLAG_OPCODE = self::P_H_FLAG_QR + 1;
    const P_H_FLAG_AA = self::P_H_FLAG_OPCODE + 1;
    const P_H_FLAG_TC = self::P_H_FLAG_AA + 1;
    const P_H_FLAG_RD = self::P_H_FLAG_TC + 1;
    const P_H_FLAG_RA = self::P_H_FLAG_RD + 1;
    const P_H_FLAG_Z = self::P_H_FLAG_RA + 1;
    const P_H_FLAG_RCODE = self::P_H_FLAG_Z + 1;
    const P_H_QUESTION = self::P_H_FLAG + 1;
    const P_H_ANSWER = self::P_H_QUESTION + 1;
    const P_H_AUTHORITY = self::P_H_ANSWER + 1;
    const P_H_ADDITIONAL = self::P_H_AUTHORITY + 1;
    const P_QUERIES = self::P_H_ADDITIONAL + 1;
    const P_ANSWERS = self::P_QUERIES + 1;
    const P_AUTHORITY = self::P_ANSWERS + 1;
    const P_ADDITIONAL = self::P_AUTHORITY + 1;
    const P_RR_NAME = self::P_ADDITIONAL + 1;
    const P_RR_TYPE = self::P_RR_NAME + 1;
    const P_RR_CLASS = self::P_RR_TYPE + 1;
    const P_RR_TTL = self::P_RR_CLASS + 1;
    const P_RR_DATA_LEN = self::P_RR_TTL + 1;
    const P_RR_DATA = self::P_RR_DATA_LEN + 1;

    const P_RR_OPT_UDP_SIZE = self::P_RR_CLASS;
    const P_RR_OPT_RCODE = self::P_RR_TTL;
    const P_RR_OPT_E_V = self::P_RR_OPT_RCODE + 1;
    const P_RR_OPT_DO = self::P_RR_OPT_E_V + 1;
    const P_RR_OPT_Z = self::P_RR_OPT_DO + 1;
    const P_RR_OPT_OPTION = self::P_RR_OPT_Z + 1;
    const P_RR_OPT_OPTION_CODE = 0;
    const P_RR_OPT_OPTION_DATA = self::P_RR_OPT_OPTION_CODE + 1;

    const P_RR_HTTPS_PRIORITY = self::P_RR_TTL + 1;
    const P_RR_HTTPS_TARGET_NAME = self::P_RR_HTTPS_PRIORITY + 1;
    const P_RR_HTTPS_PARAMS = self::P_RR_HTTPS_TARGET_NAME + 1;
    const P_RR_HTTPS_PARAMS_KEY = 0;
    const P_RR_HTTPS_PARAMS_VALUE = 1;

    const RR_HTTPS_MANDATORY = 0;
    const RR_HTTPS_ALPN = 1;
    const RR_HTTPS_NO_DEFAULT_ALPN = 2;
    const RR_HTTPS_PORT = 3;
    const RR_HTTPS_IPV4HINT = 4;
    const RR_HTTPS_ECH = 5;
    const RR_HTTPS_IPV6HINT = 6;
    /**
     * see https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml
     */
    const CLASS_IN = 1;
    const CLASS_Unassigned = 2;
    const CLASS_CH = 3;
    const CLASS_HS = 4;
    const CLASS_NONE = 254;
    const CLASS_ANY = 255;

    const QR_QUERY = 0;
    const QR_RESPONSE = 1;

    const OP_QUERY = 0;
    const OP_IQUERY = 1;
    const OP_STATUS = 2;
    const OP_Unassigned = 3;
    const OP_NOTIFY = 4;
    const OP_UPDATE = 5;
    const OP_DSO = 5;
    const OP_Unassigned_1 = 7;
    const OP_Unassigned_2 = 15;

    const RR_Reserved_0 = 0;
    const RR_A = 1;
    const RR_NS = 2;
    const RR_MD = 3;
    const RR_MF = 4;
    const RR_CNAME = 5;
    const RR_SOV = 6;
    const RR_MB = 7;
    const RR_MG = 8;
    const RR_MR = 9;
    const RR_NULL = 10;
    const RR_WKS = 11;
    const RR_PTR = 12;
    const RR_HINFO = 13;
    const RR_MINFO = 14;
    const RR_MX = 15;
    const RR_TXT = 16;
    const RR_RP = 17;
    const RR_AFSDB = 18;
    const RR_X25 = 19;
    const RR_ISDN = 20;
    const RR_RT = 21;
    const RR_NSAP = 22;
    const RR_NSAP_PTR = 23;
    const RR_SIG = 24;
    const RR_KEY = 25;
    const RR_PX = 26;
    const RR_GPOS = 27;
    const RR_AAAA = 28;
    const RR_LOC = 29;
    const RR_NXT = 30;
    const RR_EID = 31;
    const RR_NIMLOC = 32;
    const RR_SRV = 33;
    const RR_ATMA = 34;
    const RR_MAPTR = 35;
    const RR_KX = 36;
    const RR_CERT = 37;
    const RR_A6 = 38;
    const RR_DNAME = 39;
    const RR_SINK = 40;
    const RR_OPT = 41;
    const RR_APL = 42;
    const RR_DS = 43;
    const RR_SSHFP = 44;
    const RR_IPSECKEY = 45;
    const RR_RRSIG = 46;
    const RR_NSEC = 47;
    const RR_DNSKEY = 48;
    const RR_DHCID = 49;
    const RR_NSEC3 = 50;
    const RR_NSEC3PARAM = 51;
    const RR_TLSA = 52;
    const RR_SMIMEA = 53;
    const RR_Unassigned_1 = 54;
    const RR_HIP = 55;
    const RR_NINFO = 56;
    const RR_RKEY = 57;
    const RR_TALINK = 58;
    const RR_CDS = 59;
    const RR_CDNSKEY = 60;
    const RR_OPENPGPKEY = 61;
    const RR_CSYNC = 62;
    const RR_ZONEMD = 63;
    const RR_SVCB = 64;
    const RR_HTTPS = 65;
    const RR_DSYNC = 66;
    const RR_Unassigned_2 = 67;
    const RR_Unassigned_3 = 98;
    const RR_SPF = 99;
    const RR_UINFO = 100;
    const RR_UID = 101;
    const RR_GID = 102;
    const RR_UNSPEC = 103;
    const RR_NID = 104;
    const RR_L32 = 105;
    const RR_L64 = 106;
    const RR_LP = 107;
    const RR_EUI48 = 108;
    const RR_EUI64 = 109;
    const RR_Unassigned_4 = 110;
    const RR_Unassigned_5 = 127;
    const RR_NXNAME = 128;
    const RR_Unassigned_6 = 129;
    const RR_Unassigned_7 = 248;
    const RR_TKEY = 249;
    const RR_TSIG = 250;
    const RR_IXFR = 251;
    const RR_AXFR = 252;
    const RR_MAILB = 253;
    const RR_MAILA = 254;
    const RR_ANY = 255; // * record, A request for some or all records the server has available
    const RR_URI = 256;
    const RR_CAA = 257;
    const RR_AVC = 258;
    const RR_DOA = 259;
    const RR_AMTRELAY = 260;
    const RR_RESINFO = 261;
    const RR_WALLET = 262;
    const RR_CLA = 263;
    const RR_IPN = 264;
    const RR_Unassigned_8 = 265;
    const RR_Unassigned_9 = 32767;
    const RR_TA = 32768;
    const RR_DLV = 32769;
    const RR_Unassigned_10 = 32770;
    const RR_Unassigned_11 = 65279;
    const RR_Private_0 = 65280;
    const RR_Private_1 = 65534;
    const RR_Reserved_1 = 65535;


    public const G_CACHE_NAME_TYPE = [
        self::RR_A,
        self::RR_NS,
        self::RR_CNAME,
        self::RR_MX,
        self::RR_AAAA,
        self::RR_HTTPS,
    ];
    const G_RR_DATA_NAME = [self::RR_CNAME, self::RR_MX, self::RR_NS];
}
