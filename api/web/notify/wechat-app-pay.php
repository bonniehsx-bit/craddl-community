<?php

/**
 * App 微信原生支付专门的支付回调入口 (V3 解耦架构)
 */

use app\core\WebApplication;

// 将入口路由转发到专用的 WechatAppPayController
$_GET['r'] = 'notify/wechat-app-pay/index';

error_reporting(E_ALL);

// 注册 Composer 自动加载器
require(__DIR__ . '/../../vendor/autoload.php');
$config = require __DIR__ . '/../../config/web.php';
// 创建、运行一个应用
$application = new WebApplication($config);
$application->run();
