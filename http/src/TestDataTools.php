<?php
/**
 * Created by PhpStorm.
 * User: workspace
 * Date: 2018/11/20
 * Time: 09:26
 */

namespace GuzzleTools;


class TestDataTools{
    //规则配置必须数组的顺序来配置否则无法保证nginx优先级，否则影响匹配顺序,
    //按数组顺序匹配，支持以下几种类型，如果最后没有匹配成功，http 404异常（数组中的项可以缺少，如果存在default,最后会落到default）
    /*
     * redis test data
     * 1：先匹配普通location，再匹配正则表达式
     * 2：普通location 最大前缀匹配 例如：location /prefix/mid/ {} 和location /prefix/ {} ，选的是location /prefix/mid/ {}
     * 3：正则按照编程顺序匹配
     * */
    private $_locationRule = [
        'http://api.qyds.com' => [
            //全等匹配
            'equal' => [
                '/newapi/user/account/info' => 'web_account',
                '/newapi/user/bank/info' => 'web_bank'
            ],
            //路由前缀匹配,匹配到之后不在继续匹配
            'prefix_equal' => [
                '/new/' => 'web',
                '/newapi' => 'web_new_api'
            ],
            //区分大小写的正则匹配
            'distinguish_regex' => [
                'name' => 'web',
                'bank' => 'web_bank',
            ],
            //不区分大小写的正则匹配
            'undistinguish_regex' => ['name' => 'web'],
            //路由前缀匹配，但在正则之后
            //'prefix_equal2' => ['/a' => 'web'],
            //通用匹配　
            'default' => ['/' => 'web'],
            /*
            * 如果轮训需要权重，weight权重值，effective_weight初始化与权重值等同，current_weight初始0 server 节点ip和端口
            * 'weight' => 4, // 实际权重
            * 'effective_weight' => 4, // 有效权重
            * 'current_weight' => 0,  // 当前权重
            * 'server' => '127.0.0.1:9001'
             */
            'web_account' => [
                ['server' => '127.0.0.1'],
                ['server' => '127.0.0.1'],
                ['server' => '127.0.0.1'],
            ],
            'web_bank' => [
                ['server' => '127.0.0.1:9001', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1:9002', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1:9003', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
            'web' => [
                ['server' => '127.0.0.1', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
            'web_new_api' => [
                ['server' => '127.0.0.1:9001', 'weight' => 4, 'effective_weight' => 4, 'current_weight' => 0],
                ['server' => '127.0.0.1:9002', 'weight' => 3, 'effective_weight' => 3, 'current_weight' => 0],
                ['server' => '127.0.0.1:9003', 'weight' => 1, 'effective_weight' => 1, 'current_weight' => 0],
            ],
        ]
    ];

    public function testInsertData()
    {
        $preds = new PredisClient();
        $preds->del('http://www.qyd.com');
        foreach ($this->_locationRule as $key => $value) {
            foreach ($this->_locationRule[$key] as $k => $v) {
                echo 'KEY=>' . $key . '|' . $k . PHP_EOL;
                $preds->hset($key, $k, json_encode($v));
            }
        }
    }

    public function getData(){
        $preds = new PredisClient();
        $data = $preds->hgetall('http://api.qyds.com');
        var_dump($data);
    }
}


