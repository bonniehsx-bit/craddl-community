<?php

/**
 * 公众号专门的支付回调入口 (V3 解耦架构)
 */

use app\core\WebApplication;

// 将入口路由转发到专用的 WechatPayController
$_GET['r'] = 'notify/wechat-pay/index';

error_reporting(E_ALL);

// 注册 Composer 自动加载器
require(__DIR__ . '/../../vendor/autoload.php');
$config = require __DIR__ . '/../../config/web.php';
// 创建、运行一个应用
$application = new WebApplication($config);
$application->run();
