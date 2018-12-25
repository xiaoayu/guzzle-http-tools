<?php

namespace GuzzleTools;

use App\library\redis;

/**
 *  数据操作类
 */
class ServerData
{

    public function getServerData($host)
    {
        $prdsClient = redis::getInstance()->getDrive();
        $data = $prdsClient->hgetall($host);
        if (!empty($data)) {
            $ruleList = [];
            $serverList = [];
            foreach ($data as $key => $value) {
                if (in_array($key, ['equal', 'prefix_equal', 'distinguish_regex', 'undistinguish_regex', 'default'])) {
                    $ruleList[$key] = json_decode($value, true);
                } else {
                    $serverList[$key] = json_decode($value, true);
                }
            }
            return ['ruleList' => $ruleList, 'serverList' => $serverList];

        } else {
            throw new \Exception('502 bad gateway');
        }

    }
}
