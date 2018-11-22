<?php

namespace GuzzleTools;

use function React\Promise\reduce;

/**
 *  数据操作类
 */
class ServerData
{
    const CACHE_PATH = __DIR__ . '/cache/';

    /**
     * 获取路由规则 （查找缓存文件是否存在，如果不存在则读取redis）
     * @param $host
     * @return array|mixed
     * @throws \Exception
     */
    public function getRouteRule($host)
    {

        try {
            $ruleContent = file_exists(self::CACHE_PATH . md5($host) . '.php');
            if ($ruleContent == false) {
                $prdsClient = new PredisClient();
                $data = $prdsClient->hgetall($host);
                if (!empty($data)) {
                    $serviceList = [];
                    if (isset($data['equal'])) {
                        $serviceList['equal'] = json_decode($data['equal'], true);
                    }
                    if (isset($data['prefix_equal'])) {
                        $serviceList['prefix_equal'] = json_decode($data['prefix_equal'], true);
                    }
                    if (isset($data['distinguish_regex'])) {
                        $serviceList['distinguish_regex'] = json_decode($data['distinguish_regex'], true);
                    }
                    if (isset($data['undistinguish_regex'])) {
                        $serviceList['undistinguish_regex'] = json_decode($data['undistinguish_regex'], true);
                    }
                    if (isset($data['default'])) {
                        $serviceList['default'] = json_decode($data['default'], true);
                    }

                    $myfile = fopen(self::CACHE_PATH . md5($host) . '.php', "w");

                    fwrite($myfile, json_encode($serviceList));
                    return $serviceList;

                } else {
                    throw new \Exception('502 bad gateway');
                }
            } else {

                $content = file_get_contents(self::CACHE_PATH . md5($host) . '.php');

                return json_decode($content, true);
            }
        } catch (\Exception $e) {
            throw new \Exception('502 bad gateway');
        }
    }

    /**
     * 获取路由规则 （查找缓存文件是否存在，如果不存在则读取redis）
     * @param $host
     * @param $upstreamName
     * @return array|mixed
     * @throws \Exception
     */
    public function getServer($host, $upstreamName)
    {

        try {
            $fileName = self::CACHE_PATH . md5($host) . '_' . $upstreamName . '.php';
            $upstreamContent = file_exists($fileName);
            if ($upstreamContent == false) {
                $prdsClient = new PredisClient();
                $data = $prdsClient->hget($host, $upstreamName);
                if (!empty($data)) {

                    $myfile = fopen($fileName, "w");
                    flock($myfile, LOCK_SH);
                    fwrite($myfile, $data);
                    return ['serverList' => json_decode($data, true), 'fileHandle' => $myfile];

                } else {
                    throw new \Exception('504 gateway time-out');
                }
            } else {
                $fileSize = filesize($fileName);
                $myfile = fopen($fileName, "a+");
                flock($myfile, LOCK_SH);
                $content = json_decode(fread($myfile, $fileSize), true);
                return ['serverList' => (array)$content, 'fileHandle' => $myfile];
            }
        } catch (\Exception $e) {

            throw new \Exception($e);
        }
    }


    /**
     * 重置节点
     * @param $serverList
     * @param $fileHandle
     */
    public function resetServer($serverList, $fileHandle)
    {
        rewind($fileHandle);
        ftruncate($fileHandle, 0);
        fwrite($fileHandle, json_encode($serverList));
        fflush($fileHandle);            // flush output before releasing the lock
        flock($fileHandle, LOCK_UN);    // 释放锁定
        fclose($fileHandle);
    }
}
