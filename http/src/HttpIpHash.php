<?php

namespace GuzzleTools;


/**
 * IP HASH Class
 *  客户端ip直接转成10进制或者ip获取hash code值
 *  根据服务器信息获取
 */
class HttpIpHash
{

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
     * 'server' => '127.0.0.1:9002'
     * ],
     * [
     * 'server' => '127.0.0.1:9003'
     * ]
     * ];
     * @throws \Exception
     */
    public function __construct(array $serverIpList)
    {
        if (empty($serverIpList)) {
            throw new \Exception('服务器容器ip列表不能为空');
        }
        $this->_serverIpList = $serverIpList;
    }

    /**
     * 获取服务器节点信息
     * @return array 当前节点和下一个节点 如果只有一个节点则两个节点一样
     */
    public function getServiceNode()
    {
        $clientIp = $this->get_client_ip();
        $ipHashValue = ip2long($clientIp);

        $total = count($this->_serverIpList);
        $currentIp = $this->_serverIpList[$ipHashValue % $total]['server'];
        $currentKey = $ipHashValue % $total;
        $nextKey = (($currentKey == $total - 1)?0:$currentKey+1);
        $lastKey = (($nextKey == $total - 1)?0:$nextKey+1);
        //如果已经是最大key 则取数组第一个元素
        $nextIp =  $this->_serverIpList[$nextKey]['server'];
        $lastIp =  $this->_serverIpList[$lastKey]['server'];
        return ['currentIp' => $currentIp, 'nextIp' => $nextIp, 'lastIp' => $lastIp];

    }


    /**
     * 获取客户端IP地址
     * @param integer $type
     *            返回类型 0 返回IP地址 1 返回IPV4地址数字
     * @param boolean $adv
     *            是否进行高级模式获取（有可能被伪装）
     * @return mixed
     */
    public function get_client_ip($type = 0, $adv = false)
    {
        $type = $type ? 1 : 0;
        static $ip = null;
        if ($ip !== null) {
            return $ip[$type];
        }

        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos) {
                    unset($arr[$pos]);
                }
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array(
            $ip,
            $long
        ) : array(
            '0.0.0.0',
            0
        );
        return $ip[$type];
    }
}
