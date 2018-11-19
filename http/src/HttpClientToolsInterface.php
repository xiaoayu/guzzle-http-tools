<?php

namespace GuzzleHttpTools;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Promise;
use GuzzleHttp\Psr7;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


interface HttpClientToolsInterface
{
    /**
     * 构建http 请求
     * @param $url string 请求地址
     * @param $options array 参数
     * @param $method string 请求方式
     * @return bool|mixed|\Psr\Http\Message\ResponseInterface
     * @throws \Exception
     */
    public function httpRequire($url, $options, $method);

    /**
     *
     * 根据请求url找到对应的后端服务[路由寻址]
     * @param $url
     * @return array
     * @throws \Exception
     */
    public function routeToServer($url);

    /**
     * 添加服务节点
     * @param $server string 服务器
     * @param $upstreamName string 服务器upstreamName
     * @param $node  string 服务器节点
     * @param int $weight
     * @return int
     */
    public function addServerNode($server, $upstreamName, $node, $weight = 0);

    /**
     * 删除服务节点
     * @param $server string 服务器
     * @param $upstreamName string 服务器upstreamName
     * @param $node  string 服务器节点
     * @return int
     * @throws \Exception
     */
    public function delServerNode($server, $upstreamName, $node);

    /**
     * 添加服务器匹配规则
     * @param $server string 服务器
     * @param $type string  equal|prefix_equal|distinguish_regex|undistinguish_regex|default
     * @param $rule  string 规则
     * @return int
     * @throws \Exception
     */
    public function addServerRule($server, $type, $rule);

    /**
     * 删除服务器匹配规则
     * @param $server string 服务器
     * @param $type string  equal|prefix_equal|distinguish_regex|undistinguish_regex|default
     * @param $rule  string 规则
     * @return int
     * @throws \Exception
     */
    public function delServerRule($server, $type, $rule);
}
