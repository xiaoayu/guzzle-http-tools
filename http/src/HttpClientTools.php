<?php

namespace GuzzleHttpTools;


use App\library\redis;
use GuzzleHttp\Exception\ConnectException;

class HttpClientTools implements HttpClientToolsInterface
{
    //1:配置信息从redis读取
    //2:轮训规则自己处理自己的 文件+lock，upname每个单独存放
    //广播修改配置信息 todo
    //3:根据http status code 重试

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
    * @curl_error_code 错误码为 5，6，7【添加连接失败或请求超时 404 502 504】开启重试机制 5 无法解析代理 无法解析代理。无法解析给定代理主机。6无法解析主机地址 无法解析主机。无法解析给定的远程主机。7 无法连接到主机。 对应http状态码 404[开启组件后有效]
    * @verify https证书验证
    */
    private $_config = [
        'is_open_balance_tools' => true,
        'balance_type' => 'RoundRobin',
        'time_out' => 3,
        'is_reply' => false,
        'curl_error_code' => [5, 6, 7],
        'verify' => false
    ];
    //规则配置必须数组的顺序来配置否则无法保证nginx优先级，否则影响匹配顺序,
    //按数组顺序匹配，支持以下几种类型，如果最后没有匹配成功，http 404异常（数组中的项可以缺少，如果存在default,最后会落到default）
    /*
     * redis test data
     * 1：先匹配普通location，再匹配正则表达式
     * 2：普通location 最大前缀匹配 例如：location /prefix/mid/ {} 和location /prefix/ {} ，选的是location /prefix/mid/ {}
     * 3：正则按照编程顺序匹配
     * */
    private $_locationRule = [
        'http://api.qyd.com' => [
            //全等匹配
            'equal' => [
                '/newapi/user/account/info' => 'web_account',
                '/newapi/user/bank/info' => 'web_bank'
            ],
            //路由前缀匹配,匹配到之后不在继续匹配
            'prefix_equal' => [
                '/new/' => 'web',
                '/newapi' => 'web_new_api'
            ],
            //区分大小写的正则匹配
            'distinguish_regex' => [
                'name' => 'web',
                'bank' => 'web_bank',
            ],
            //不区分大小写的正则匹配
            'undistinguish_regex' => ['name' => 'web'],
            //路由前缀匹配，但在正则之后
            //'prefix_equal2' => ['/a' => 'web'],
            //通用匹配　
            'default' => ['/' => 'web'],
            /*
            * 如果轮训需要权重，weight权重值，effective_weight初始化与权重值等同，current_weight初始0 server 节点ip和端口
            * 'weight' => 4, // 实际权重
            * 'effective_weight' => 4, // 有效权重
            * 'current_weight' => 0,  // 当前权重
            * 'server' => '127.0.0.1:9001'
             */
            'web_account' => [
                ['server' => '127.0.0.1'],
                ['server' => '127.0.0.1'],
                ['server' => '127.0.0.1'],
            ],
            'web_bank' => [
                ['server' => '127.0.0.1:9001', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1:9002', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1:9003', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
            'web' => [
                ['server' => '127.0.0.1', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
            'web_new_api' => [
                ['server' => '127.0.0.1:9001', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1:9002', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1:9003', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
        ]
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
        //是否抛404异常
        $replyException = true;
        //如果开启路由寻址功能
        if ($this->_config['is_open_balance_tools'] === true) {

            $serverInfo = $this->routeToServer($url);
            $url = $serverInfo['requestUrl'];
            $nextUrl = $serverInfo['requestUrl'];
            $replyException = false;
        }
        try {
            $response = $this->sendHttpRequest($url, $options, $method, $replyException);
            //如果是直接请求并且开启了重试机制并且当前请求的地址和下次请求的地址不同，则再重试一次
            if ($response === false && $this->_config['is_reply'] === true && $url != $nextUrl) {
                $response = $this->sendHttpRequest($url, $options, $method, true);
            }
            var_dump($response);
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
        } catch (ConnectException $e){
            echo 1;var_dump($e);exit;
        } catch (\Exception $e) {
           var_dump($e->getCode());
            $exception = $e;
        }
        if ($curlErrorCode == 0) {
            return $response;
        }
        var_dump($curlErrorCode);
        if ($replyException == false && in_array($curlErrorCode, $this->_config['curl_error_code'])) {
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


        $locationRuleServer = $this->_getServerRedisData($host);

        //根据路由获取对应的的后端upstream name
        $upstreamName = false;
        foreach ($locationRuleServer as $key => $serverRule) {
            $serverRule = json_decode($serverRule, true);

            //全等匹配
            if ($key == 'equal') {
                foreach ($serverRule as $key => $value) {
                    if ($key == $path) {
                        $upstreamName = $value;
                        break 2;
                    }
                }
            }
            // 路由前缀匹配
            if ($key == 'prefix_equal') {
                foreach ($serverRule as $key => $value) {
                    if ($key == substr($path, 0, strlen($key))) {
                        $upstreamName = $value;
                        break 2;
                    }
                }
            }
            //区分大小写的匹配
            if ($key == 'distinguish_regex') {
                foreach ($serverRule as $key => $value) {
                    if (preg_match("/" . $key . "/", $path)) {
                        $upstreamName = $value;
                        break 2;
                    }
                }
            }
            //不区分大小写的匹配
            if ($key == 'undistinguish_regex') {
                foreach ($serverRule as $key => $value) {
                    if (preg_match("/" . strtolower($key) . "/", strtolower($path))) {
                        $upstreamName = $value;
                        break 2;
                    }
                }
            }

            //默认路由
            if ($key == 'default') {
                $upstreamName = $serverRule['/'];
                break;
            }
        }

        if ($upstreamName == false) {
            throw new \Exception('502 bad gateway!');
        }

        if (!isset($locationRuleServer[$upstreamName])) {
            throw new \Exception('404 server not found!');
        }

        $serviceList = $locationRuleServer[$upstreamName];

        $serverInfo = $this->_getService(json_decode($serviceList, true), $host, $upstreamName);

        return ['requestUrl' => $serverInfo['currentIp'] . $path, 'nextUrl' => $serverInfo['nextIp'] . $path];
    }

    /**
     *
     * 获取请求节点和下一次请求节点（如果http请求状态码是超时或者网络不通重试一次）
     * @param $serverList array
     * @return array ['currentIp' => $currentIp, 'nextIp' => $nextIp]
     * @throws \Exception
     */
    private function _getService($serverList, $host, $upstreamName)
    {

        try {
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

                    $this->_redis->hset($host, $upstreamName, json_encode($serverInfo['serviceList']));

                    return ['currentIp' => $serverInfo['currentIp'], 'nextIp' => $serverInfo['nextIp']];
                } //IP hash 算法
                elseif ($this->_config['balance_type'] == 'IpHash') {
                    $httpIpHashServer = new HttpIpHash($serverList);
                    return $httpIpHashServer->getServiceNode();
                }
            } else {
                throw new \Exception('504 bad gateway!');
            }
        } catch (\Exception $e) {
            throw new \Exception('504 bad gateway!');
        }

    }

    /**
     * 添加服务节点
     * @param $server string 服务器
     * @param $upstreamName string 服务器upstreamName
     * @param $node  string 服务器节点
     * @param int $weight
     * @return int
     */
    public function addServerNode($server, $upstreamName, $node, $weight = 0)
    {
        $this->_redis = redis::getInstance()->getDrive();
        $nodeList = $this->_redis->hget($server, $upstreamName);
        if (empty($nodeList)) {
            $newNodeList = ($weight > 0 ? [['server' => $node, 'weight' => $weight, 'effective_weight' => $weight, 'current_weight' => 0]] : [['server' => $node]]);

        } else {
            $newNodeList = [];
            $nodeList = json_decode($nodeList, true);
            foreach ($nodeList as $nodeInfo) {
                $nodeInfoTmp = [];
                $nodeInfoTmp['server'] = $nodeInfo['server'];
                if (isset($nodeInfoTmp['weight'])) {
                    $nodeInfoTmp['weight'] = $nodeInfo['weight'];
                    $nodeInfoTmp['effective_weight'] = $nodeInfo['weight'];
                    $nodeInfoTmp['current_weight'] = 0;
                }
                $newNodeList[] = $nodeInfoTmp;
                $addNodeInfo = ($weight > 0 ? ['server' => $node, 'weight' => $weight, 'effective_weight' => $weight, 'current_weight' => 0] : ['server' => $node]);
                $newNodeList = array_merge($newNodeList, $addNodeInfo);
            }
        }
        return $this->_redis->hset($server, $upstreamName, json_encode($newNodeList));
    }


    /**
     * 删除服务节点
     * @param $server string 服务器
     * @param $upstreamName string 服务器upstreamName
     * @param $node  string 服务器节点
     * @return int
     * @throws \Exception
     */
    public function delServerNode($server, $upstreamName, $node)
    {
        $this->_redis = redis::getInstance()->getDrive();
        $nodeList = $this->_redis->hget($server, $upstreamName);
        if (empty($nodeList)) {
            throw new \Exception('服务节点不存在', 200);

        } else {
            $newNodeList = [];
            $nodeList = json_decode($nodeList, true);
            foreach ($nodeList as $nodeInfo) {
                $nodeInfoTmp = [];
                if ($node == $nodeInfo['server']) continue;

                $nodeInfoTmp['server'] = $nodeInfo['server'];
                if (isset($nodeInfoTmp['weight'])) {
                    $nodeInfoTmp['weight'] = $nodeInfo['weight'];
                    $nodeInfoTmp['effective_weight'] = $nodeInfo['weight'];
                    $nodeInfoTmp['current_weight'] = 0;
                }
                $newNodeList[] = $nodeInfoTmp;

            }
        }
        return $this->_redis->hset($server, $upstreamName, json_encode($newNodeList));
    }

    /**
     * 添加服务器匹配规则
     * @param $server string 服务器
     * @param $type string  equal|prefix_equal|distinguish_regex|undistinguish_regex|default
     * @param $rule  string 规则
     * @return int
     * @throws \Exception
     */
    public function addServerRule($server, $type, $rule)
    {
        //todo
    }

    /**
     * 删除服务器匹配规则
     * @param $server string 服务器
     * @param $type string  equal|prefix_equal|distinguish_regex|undistinguish_regex|default
     * @param $rule  string 规则
     * @return int
     * @throws \Exception
     */
    public function delServerRule($server, $type, $rule)
    {
        //todo
    }

    /**
     * 获取redis服务器信息
     * @param $host string 当前请求host
     * @return mixed
     * @throws \Exception
     */
    private function _getServerRedisData($host)
    {
        try {
            $this->_redis = redis::getInstance()->getDrive();

        } catch (\Exception $e) {
            throw new \Exception('该组件必须redis支持');
        }

        $serverData = $this->_redis->hgetall($host);
        if (empty($serverData)) {
            throw new \Exception('404 server not found!');
        }
        return $serverData;
    }

    public function testInsertData()
    {
        $this->_redis = redis::getInstance()->getDrive();
        $this->_redis->del('http://www.qyd.com');
        foreach ($this->_locationRule as $key => $value) {
            foreach ($this->_locationRule[$key] as $k => $v) {
                echo 'KEY=>' . $key . '|' . $k . PHP_EOL;
                $this->_redis->hset($key, $k, json_encode($v));
            }
        }
    }
}
