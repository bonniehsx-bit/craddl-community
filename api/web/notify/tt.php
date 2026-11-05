<?php
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/5/26
 * Time: 11:04
 * Note:
 */


//头条小程序的
use app\core\WebApplication;

$_GET['r'] = '/notify/tt/notify';
error_reporting(E_ALL);
// 注册 Composer 自动加载器
require(__DIR__ . '/../../vendor/autoload.php');
$config = require __DIR__ . '/../../config/web.php';
// 创建、运行一个应用
$application = new WebApplication($config);
$application->run();
