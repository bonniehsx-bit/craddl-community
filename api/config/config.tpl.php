<?php

/**
 * ============================================================================
 * 统一配置文件模板 (config.tpl.php)
 * ============================================================================
 * 部署脚本会自动读取 setup.env 并替换其中的 __变量__
 */

// 1. 定义 Yii 环境
defined('YII_ENV') or define('YII_ENV', '__YII_ENV__');
defined('YII_DEBUG') or define('YII_DEBUG', __YII_DEBUG__);

return [

    // ===================================
    // 数据库 (严格遵照你的格式)
    // ===================================
    'db' => [
        'host' => '__DB_HOST__',
        'port' => '__DB_PORT__',
        'username' => '__DB_USER__',
        'password' => '__DB_PASS__',
        'dbname' => '__DB_NAME__',
        'tablePrefix' => '__DB_PREFIX__',
        'charset' => 'utf8mb4',
        'attributes' => [
            PDO::ATTR_TIMEOUT => 10,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],

    // ===================================
    // Redis
    // ===================================
    'redis' => [
        'class' => 'yii\redis\Connection',
        'hostname' => '__REDIS_HOST__',
        'port' => '__REDIS_PORT__',
        'database' => __REDIS_DB__,
        'password' => '__REDIS_PASS__' ?: null,
    ],

    // ===================================
    // 安全设置
    // ===================================
    'security' => [
        'passwordHashCost' => __SECURITY_HASH_COST__,
    ],

    // ===================================
    // 开发工具
    // ===================================
    'dev' => [
        'debug_allowed_ips' => ['127.0.0.1', '::1'],
        'gii_allowed_ips' => ['127.0.0.1', '::1', '*'],
    ],

    // ===================================
    // 微信 & 字节跳动 (保留原始结构)
    // ===================================

    'wechat' => [
        'app_id' => 'YOUR_WECHAT_APP_ID',
        'secret' => 'YOUR_WECHAT_SECRET',
        'token' => 'YOUR_WECHAT_TOKEN',
        'aes_key' => 'YOUR_WECHAT_AES_KEY',
        'response_type' => 'array',
    ],

    'wechatPayment' => [
        'app_id' => 'YOUR_WECHAT_PAY_APP_ID',
        'mch_id' => 'YOUR_WECHAT_MCH_ID',
        'key' => 'YOUR_WECHAT_PAY_KEY',
        // 建议：证书路径可以使用 __PROJECT_ROOT__ 变量，或者你保持绝对路径
        'cert_path' => '/absolute/path/to/your/apiclient_cert.pem',
        'key_path' => '/absolute/path/to/your/apiclient_key.pem',
        'notify_url' => 'https://your.domain.com/path/to/notify',
    ],

    'wechatMiniProgram' => [
        // 'app_id' => 'YOUR_MINI_APP_ID',
        // 'secret' => 'YOUR_MINI_SECRET',
    ],

    'wechatOpenPlatform' => [],
    'wechatWork' => [],
    'wechatOpenWork' => [],
    'wechatMicroMerchant' => [],
];
