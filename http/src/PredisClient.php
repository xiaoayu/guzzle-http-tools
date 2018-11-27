<?php

namespace GuzzleTools;

/**
 * predis client
 */

class PredisClient extends \Predis\Client
{
    private $_coon = "tcp://192.168.212.148:26379?timeout=1|mymaster";

    public function getRedis($name = 'DEFAULT')
    {
        try {
            static $_instance = [];
            if (isset($_instance[$name])) {
                return $_instance[$name];
            }

            $sentinels = explode(',', $_ENV['REDIS_DEFAULT_SENTINEL_LIST']);

            $options = ['replication' => 'sentinel', 'service' => $_ENV['REDIS_DEFAULT_SENTINEL_MASTERNAME']];

            $client = new \Predis\Client($sentinels, $options);
            return $client;
        } catch (\Exception $e) {
            throw new \Exception('No sentinel server available for autodiscovery.');
        }
    }
}
