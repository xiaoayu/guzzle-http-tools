<?php
/**
 * Created by PhpStorm.
 * User: workspace
 * Date: 2018/12/3
 * Time: 10:03
 */

namespace GuzzleTools;


/**
 *  http Weight Round Round Class
 *  加权随机算法
 *  根据服务器信息获取当前ip和下一个节点ip和再一次轮训的服务器信息
 */
class HttpWeightRound
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
     * 正常加权随机处理
     * @return array
     */

    public function getServerNode()
    {
        //数组总和
        $total = 0;
        //权重总和
        $sum = 0;
        $serverListTmp = [];
        foreach ($this->_serverIpList as $k => $server) {
            $total += 1;
            $sum += $server['weight'];
            for ($i = 0; $i < $server['weight']; $i++) {
                $serverListTmp[] = $server['server'];
            }
        }
        $currentKey = rand(0, $sum - 1);
        $currentIp = $serverListTmp[$currentKey];
        foreach ($this->_serverIpList as $k => $server) {
            if ($server['server'] == $currentIp) {
                $tmpCurrentKey = $k;
                break;
            }
        }

        $nextKey = ($tmpCurrentKey + 1 == $total ? 0 : $tmpCurrentKey + 1);
        $lastKey = ($nextKey + 1 == $total ? 0 : $nextKey + 1);
        return ['currentIp' => $currentIp, 'nextIp' => $this->_serverIpList[$nextKey]['server'], 'lastIp' => $this->_serverIpList[$lastKey]['server']];

    }
}