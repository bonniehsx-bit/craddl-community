<?php
// /web/notify/fastpay.php

use app\core\WebApplication;

$_GET['r'] = 'notify/fast/split';

error_reporting(E_ALL);

// 注册 Composer 自动加载器
require(__DIR__ . '/../../vendor/autoload.php');
$config = require __DIR__ . '/../../config/web.php';
// 创建、运行一个应用
$application = new WebApplication($config);
$application->run();