<?php
/**
 * Created by PhpStorm.
 * User: workspace
 * Date: 2018/12/3
 * Time: 10:02
 */

namespace GuzzleTools;


/**
 *  http Weight Round Round Class
 *  随机算法
 *  根据服务器信息获取当前ip和下一个节点ip和再一次轮训的服务器信息
 */
class HttpRound
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
     * 正常随机处理
     * @return array
     */

    public function getServerNode()
    {

        $total = count($this->_serverIpList);

        $currentKey = rand(0, $total - 1);
        $nextKey = ($currentKey + 1 == $total ? 0 : $currentKey + 1);
        $lastKey = ($nextKey + 1 == $total ? 0 : $nextKey + 1);

        return ['currentIp' => $this->_serverIpList[$currentKey]['server'], 'nextIp' => $this->_serverIpList[$nextKey]['server'], 'lastIp' => $this->_serverIpList[$lastKey]['server']];
    }
}