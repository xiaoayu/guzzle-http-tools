<?php

namespace GuzzleTools;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\TransferStats;
use GuzzleTools\HttpWeightRoundRobin;

class HttpClientTools extends Client implements HttpClientToolsInterface
{
    /*
    * @var $_redis 连接
    */
    private $_redis = null;
    /*
     * @var $_config 配置项
    */

    /*
    * 组件配置选项
    * @var is_open_balance_tools  是否开启负载均衡组件，默认开启
    * @var balance_type 负载均衡算法，默认轮训 支持 IpHash RoundRobin:轮训（包括加权轮训，如果有权重值系统会自动判断是否加权轮训）
    * @var time_out     http超时时间
    * @var is_reply   是否开启重试机制，默认关闭[开启组件后有效]
    * @curl_error_code 错误码为 5，6，7(28设置的time_out超时)【添加连接失败或请求超时 404 502 504】开启重试机制 5 无法解析代理 无法解析代理。无法解析给定代理主机。6无法解析主机地址 无法解析主机。无法解析给定的远程主机。7 无法连接到主机。 对应http状态码 404[开启组件后有效]
    * @verify https证书验证
    */
    private $_config = [
        'is_open_balance_tools' => true,
        'balance_type' => 'RoundRobin',
        'time_out' => 3,
        'is_reply' => false,
        'reply_times' => 2,
        'curl_error_code' => [5, 6, 7],
        'http_code' => [404, 502, 504],
        'verify' => false
    ];


    /**
     * HttpClientTools constructor.
     * @param array 配置项
     */
    public function __construct(array $config = array())
    {
        $this->_config = array_merge($this->_config, $config);
    }


    /**
     * 构建http 请求
     * @param $url string 请求地址
     * @param $options array 参数
     * @param $method string 请求方式
     * @return bool|mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function httpRequire($url, $options, $method)
    {
        //是否抛异常
        $replyException = true;
        //是否抛异常
        $reReplyException = true;
        //如果开启路由寻址功能
        if ($this->_config['is_open_balance_tools'] === true) {

            $serverInfo = $this->routeToServer($url);

            $url = $serverInfo['requestUrl'];
            $nextUrl = $serverInfo['nextUrl'];
            $lastUrl = $serverInfo['lastUrl'];

            if ($this->_config['is_reply'] === true && $this->_config['reply_times'] >= 2 && $url != $nextUrl) {
                $replyException = false;
            }
            if ($this->_config['is_reply'] === true && $this->_config['reply_times'] > 2 && $lastUrl != $nextUrl) {
                $reReplyException = false;
            }
        }
        try {
            $response = $this->sendHttpRequest($url, $options, $method, $replyException);
            //如果开启了重试机制，并且重试次数大于2，并且当前请求的地址和下次请求的地址不同
            if ($response === false) {
                $response = $this->sendHttpRequest($nextUrl, $options, $method, $reReplyException);
            }
            if ($response === false) {
                $response = $this->sendHttpRequest($lastUrl, $options, $method, true);
            }
            return $response;
        } catch (\Exception $e) {
            throw new \Exception($e);
        }
    }

    /**
     * 构建http 请求
     * @param $url string 请求地址
     * @param $options array 参数
     * @param $method string 请求方式
     * @param  $replyException boolean 是否抛404异常
     * @return bool|mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    private function sendHttpRequest($url, $options, $method, $replyException = true)
    {

        $httpCode = 0;
        try {
            $client = new Client(['timeout' => $this->_config['time_out'], 'verify' => $this->_config['verify']]);
            $curlErrorCode = 0;
            $response = $client->request($method, $url, $options = array_merge(
                [
                    'on_stats' => function (TransferStats $stats) use ($url, &$curlErrorCode) {
                        $curlErrorCode = $stats->getHandlerErrorData();
                    },
                    'force_ip_resolve' => 'v4'
                ], $options));

        } catch (\Exception $e) {

            $httpCode = $e->getCode();
            $exception = $e;
        }
        $curlErrorCode = $httpCode > 0 ? $httpCode : $curlErrorCode;

        if ($curlErrorCode == 0) {
            return $response;
        }

        if ($replyException == false && in_array($curlErrorCode, array_merge($this->_config['curl_error_code'], $this->_config['http_code']))) {
            return false;
        }
        throw new \Exception($exception);
    }

    /**
     *
     * 根据请求url找到对应的后端服务[路由寻址]
     * @param $url
     * @return array
     * @throws \Exception
     */
    public function routeToServer($url)
    {
        $urlInfo = parse_url($url);

        $host = $urlInfo['scheme'] . '://' . $urlInfo['host'] . (isset($urlInfo['port']) ? ':' . $urlInfo['port'] : '');
        $path = (isset($urlInfo['path']) ? $urlInfo['path'] : '/');

        $locationRuleClass = new ServerData();
        $locationRuleServer = $locationRuleClass->getRouteRule($host);

        //根据路由获取对应的的后端upstream name
        $upstreamName = false;
        //全等匹配
        if (isset($locationRuleServer['equal'])) {

            //必须循环，需要拿到数组的key和value
            foreach ($locationRuleServer['equal'] as $key => $value) {
                if ($key == $path) {
                    $upstreamName = $value;
                    break;
                }
            }
        }
        //路由前缀匹配
        if ($upstreamName === false && isset($locationRuleServer['prefix_equal'])) {

            foreach ($locationRuleServer['prefix_equal'] as $key => $value) {
                if ($key == substr($path, 0, strlen($key))) {
                    $upstreamName = $value;
                    break;
                }
            }
        }
        //区分大小写的匹配
        if ($upstreamName === false && isset($locationRuleServer['distinguish_regex'])) {

            foreach ($locationRuleServer['distinguish_regex'] as $key => $value) {

                if (preg_match("/" . $key . "/", $path)) {
                    $upstreamName = $value;
                    break;
                }
            }
        }
        //不区分大小写的匹配
        if ($upstreamName === false && isset($locationRuleServer['undistinguish_regex'])) {

            foreach ($locationRuleServer['undistinguish_regex'] as $key => $value) {
                if (preg_match("/" . strtolower($key) . "/", strtolower($path))) {
                    $upstreamName = $value;
                    break;
                }
            }
        }
        //默认路由匹配
        if ($upstreamName === false && isset($locationRuleServer['default'])) {
            $upstreamName = $locationRuleServer['default']['/'];
        }


        if ($upstreamName == false) {
            throw new \Exception('502 bad gateway');
        }

        $serverInfo = $this->_getService($host, $upstreamName);

        return ['requestUrl' => $serverInfo['currentIp'] . $path, 'nextUrl' => $serverInfo['nextIp'] . $path, 'lastUrl' => $serverInfo['lastIp'] . $path];
    }

    /**
     *
     * 获取请求节点和下一次请求节点（如果http请求状态码是超时或者网络不通重试一次）
     * @param $host
     * @param $upstreamName
     * @return array ['currentIp' => $currentIp, 'nextIp' => $nextIp]
     * @throws \Exception
     */
    private function _getService($host, $upstreamName)
    {

        try {
            $locationRuleClass = new ServerData();
            $serverTmpInfo = $locationRuleClass->getServer($host, $upstreamName);
            $serverList = $serverTmpInfo['serverList'];

            if (in_array($this->_config['balance_type'], ['RoundRobin', 'IpHash'])) {
                //轮训算法
                if ($this->_config['balance_type'] == 'RoundRobin') {
                    //如果节点中含有权重值则调用加权轮训算法，否则调用轮训算法
                    if (isset($serverList[0]['weight'])) {
                        $httpServer = new HttpWeightRoundRobin($serverList);
                    } else {
                        $httpServer = new HttpRoundRobin($serverList);
                    }

                    $serverInfo = $httpServer->getServerNode();

                    $locationRuleClass->resetServer($serverInfo['serviceList'], $serverTmpInfo['fileHandle']);

                    return ['currentIp' => $serverInfo['currentIp'], 'nextIp' => $serverInfo['nextIp'], 'lastIp' => $serverInfo['nextIp']];
                } //IP hash 算法
                elseif ($this->_config['balance_type'] == 'IpHash') {
                    $httpIpHashServer = new HttpIpHash($serverList);
                    return $httpIpHashServer->getServiceNode();
                }
            } else {
                throw new \Exception('504 bad gateway!');
            }
        } catch (\Exception $e) {
            throw new \Exception($e);
        }

    }

}
