<?php

namespace GuzzleTools;

/**
 * predis client
 */

class PredisClient extends \Predis\Client
{
    private $_coon = "tcp://192.168.212.114:26379?timeout=1|mymaster";

    public function getRedis($name = 'DEFAULT')
    {

        static $_instance = [];
        if (isset($_instance[$name])) {
            return $_instance[$name];
        }
        $masterName = 'mymaster';
        if (strpos($this->_coon, '|') !== false) {
            [$masterName, $this->_coon] = explode('|', $this->_coon);
        }

        $sentinels = explode(',', $this->_coon);

        $options = ['replication' => 'sentinel', 'service' => $masterName];
        $client = new \Predis\Client($sentinels, $options);
        return $client;
    }
}
