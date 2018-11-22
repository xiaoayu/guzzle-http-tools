<?php

namespace GuzzleTools;


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

}
