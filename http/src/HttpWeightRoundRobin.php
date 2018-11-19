<?php

namespace GuzzleHttpTools;


/**
 *  http Weight Round Round Class
 *  加权轮训算法
 *  根据服务器信息获取当前ip和下一个节点ip和再一次轮训的服务器信息
 */
class HttpWeightRoundRobin
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
     * 'weight' => 4, // 实际权重
     * 'effective_weight' => 4, // 有效权重
     * 'current_weight' => 0,  // 当前权重
     * 'server' => '127.0.0.1:9001'
     * ],
     * [
     * 'weight' => 2,
     * 'effective_weight' => 2,
     * 'current_weight' => 0,
     * 'server' => '127.0.0.1:9002'
     * ],
     * [
     * 'weight' => 1,
     * 'effective_weight' => 1,
     * 'current_weight' => 0,
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
     * 权重不变的情况下，正常轮询处理
     * @return array
     */
    public function getServerNode()
    {

        // 计算总权重
        $total = 0;
        //数组数量
        $count = 0;
        foreach ($this->_serverIpList as $k => $server) {
            $total += $server['effective_weight'];
            $count +=1;
        }

        if ($total == 1) {
            $currentKey = 0;
            $nexKey = 0;
        } else {
            // 定义本次请求最大权重与对应的键值
            $currentKey = $maxWeight = 0;
            foreach ($this->_serverIpList as $key => $value) {
                //获取当前权重值
                $current_weight = $value['current_weight'] + $value['effective_weight'];
                // 赋值当前服务器的当前权重
                $this->_serverIpList[$key]['current_weight'] = $current_weight;
                // 定义current_weight最大的服务器
                if ($current_weight > $maxWeight) {
                    $currentKey = $key;
                    $maxWeight = $current_weight;
                }
            }

            // 更新当前权重
            $this->_serverIpList[$currentKey]['current_weight'] -= $total;

            //获取下个节点key
            $nexKey = ($currentKey == $count - 1) ? 0 : $currentKey + 1;
        }

        return ['currentIp' => $this->_serverIpList[$currentKey]['server'], 'nextIp' => $this->_serverIpList[$nexKey]['server'], 'serviceList' => $this->_serverIpList];
    }
}