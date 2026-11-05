<?php
/**
 * 快付「灵工下发」异步通知入口。
 *
 * FastPayFlexibleLaborFund::payout() 把本地址(/lg/fastpay.php)作为 notifyUrl 上送给快付。
 * 此前这个文件**不存在** —— 网关回调打过来是 404,下发结果永远收不到:
 * 失败的单不会置「打款失败」,只能等对账 cron 兜底,而快付查单响应的 status 并非打款结果
 * (代付实测 6 个月 2212 笔零失败),于是失败也会被判成「已到账」。
 *
 * 与 /fund/fastpay.php(代付)同构,只是路由到各自的 action。
 */

use app\core\WebApplication;

$_GET['r'] = 'notify/fast/lg';

error_reporting(E_ALL);

// 注册 Composer 自动加载器
require(__DIR__ . '/../../vendor/autoload.php');
$config = require __DIR__ . '/../../config/web.php';
// 创建、运行一个应用
$application = new WebApplication($config);
$application->run();
