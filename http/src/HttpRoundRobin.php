<?php

namespace GuzzleTools;


/**
 *  http Weight Round Round Class
 *  轮训算法
 *  根据服务器信息获取当前ip和下一个节点ip和再一次轮训的服务器信息
 */
class HttpRoundRobin
{
    const EXCEPTION_NOT_SERVER_IP_LIST = '服务器容器ip列表不能为空';

    /* @var $_serverIpList array 服务端port节点列表 */
    private $_serverIpList = [];

    /**
     * HttpIpHash constructor.
     * @param array $serverIpList
     * e.g.
     * $serverIpList = [
     * [
     * 'server' => '127.0.0.1:9001'
     * ],
     * [
     * 'weight' => 2,
     * 'current'=>false
     * 'server' => '127.0.0.1:9002'
     * ],
     * [
     * 'weight' => 1,
     * 'current'=>false
     * 'server' => '127.0.0.1:9003'
     * ]
     * ];
     * @throws \Exception
     */
    public function __construct(array $serverIpList)
    {
        if (empty($serverIpList)) {
            throw new \Exception('EXCEPTION_NOT_SERVER_IP_LIST');
        }
        $this->_serverIpList = $serverIpList;
    }

    /**
     * 正常轮询处理
     * @return array
     */
    public function getServerNode()
    {

        $total = 0;
        $lastKey = -1;
        foreach ($this->_serverIpList as $k => $server) {
            if(isset($server['current']) &&  $server['current'] === true){
                $lastKey = $k;
            }
            $total += 1;
        }

        $lastKey = ($lastKey == -1 ? $total - 1 : $lastKey);

        $currentKey = ($lastKey == $total - 1 ? 0 : $lastKey + 1);

        $nextKey = ($currentKey == $total - 1 ? 0 : $currentKey + 1);

        foreach ($this->_serverIpList as $key => &$server) {
            $server['current'] = false;
            if ($key == $currentKey) {
                $server['current'] = true;
            }
        }
        return ['currentIp' => $this->_serverIpList[$currentKey]['server'], 'nextIp' => $this->_serverIpList[$nextKey]['server'], 'serviceList' => $this->_serverIpList];
    }
}