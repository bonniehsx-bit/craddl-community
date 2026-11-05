<?php

// 加载唯一的配置文件，替换 db.php, redis.php, params.php
$cfg = require __DIR__ . '/config.php';


// [辅助步骤] 3. 从 $cfg 构建 params 数组
$params = [
    'wechatConfig' => $cfg['wechat'],
    'wechatPaymentConfig' => $cfg['wechatPayment'],
    'wechatMiniProgramConfig' => $cfg['wechatMiniProgram'],
    'wechatOpenPlatformConfig' => $cfg['wechatOpenPlatform'],
    'wechatWorkConfig' => $cfg['wechatWork'],
    'wechatOpenWorkConfig' => $cfg['wechatOpenWork'],
    'wechatMicroMerchantConfig' => $cfg['wechatMicroMerchant']
];

// [辅助步骤] 4. 从 $cfg 构建 db 组件配置
$dbConfig = [
    'class' => \app\core\Connection::class,
    'dsn' => 'mysql:host=' . $cfg['db']['host'] . ';port=' . $cfg['db']['port'] . ';dbname=' . $cfg['db']['dbname'],
    'dbName' => $cfg['db']['dbname'],
    'tablePrefix' => $cfg['db']['tablePrefix'],
    'charset' => $cfg['db']['charset'],
    'attributes' => $cfg['db']['attributes'],
    // 只有在非开发环境才开启，或者你可以强制设为 true
    'enableSchemaCache' => YII_ENV !== 'dev',

    // 缓存时间：3600秒 (1小时)
    'schemaCacheDuration' => 3600,

    // 指定用哪个缓存组件存结构:必须是缓存组件 cache(yii\redis\Cache,数据仍在 redis 里)。
    // ⚠ 原来写的是 'redis' —— 那是连接组件不是缓存,Yii 判定后直接不缓存,线上每个请求都现查一遍
    //   用到的表结构(实测首页 18 条、下单预览 22 条 SQL 都花在这)。
    // 迁移改表后由 MigrateController 自动刷新;手工刷新用 php yii cache/flush-schema
    // (⛔ 别用 cache/flush-all:会清空整个 redis 库,连延时任务队列一起没了)
    'schemaCache' => 'cache',
    // ============================================================
    'masterConfig' => [
        'username' => $cfg['db']['username'],
        'password' => $cfg['db']['password'],
        'charset' => $cfg['db']['charset'],
        'tablePrefix' => $cfg['db']['tablePrefix'],
        'attributes' => $cfg['db']['attributes'],
    ],
    'masters' => [
        ['dsn' => 'mysql:host=' . $cfg['db']['host'] . ';port=' . $cfg['db']['port'] . ';dbname=' . $cfg['db']['dbname'],]
    ],
];


// [主配置] 5. 构建主配置数组
$config = [
    'name' => 'JXMALL',
    'language' => 'zh-CN',
    'timeZone' => 'Asia/Shanghai',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log', 'app\components\AppBootstrap', 'app\components\AdminBootstrap'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm' => '@vendor/npm-asset',
        '@webroot' => dirname(__DIR__) . '/web', // web 根目录 = api/web（与入口 web/index.php 同级；Web 上下文 Yii 会自动校正，此值主要供 CLI 使用）
        '@web' => '/',
    ],
    'components' => [
        'mall' => null,
        'platform' => null,
        'plugin' => [
            'class' => '\app\components\Plugin',
        ],
        'security' => [
            'class' => 'yii\base\Security',
            'passwordHashCost' => isset($cfg['security']['passwordHashCost']) ? (int)$cfg['security']['passwordHashCost'] : 13,
        ],
        'joinPay' => [
            'class' => '\app\components\JoinPay',
        ],
        'wechat' => [
            'class' => 'app\components\wechat\WechatComponent',
            'userConfig' => $cfg['wechat'] ?? [],
            'miniProgramConfig' => $cfg['wechatMiniProgram'] ?? [],
            'paymentConfig' => $cfg['wechatPayment'] ?? [],
            'openPlatformConfig' => $cfg['wechatOpenPlatform'] ?? [],
            'workConfig' => $cfg['wechatWork'] ?? [],
        ],

        // [修改] 假定你总是使用 redis, 如果不是, 你需要在这里添加逻辑
        'cache' => [
            'class' => 'yii\redis\Cache',
            'redis' => 'redis' // 指向下面的 'redis' 组件
        ],

        // [修改] 从 $cfg 加载 redis 连接
        'redis' => $cfg['redis'],

        'admin' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\models\Admin',
            'enableAutoLogin' => false,  // 纯 token API，不需要 cookie 自动登录
            'enableSession'   => false,  // 关闭 session：admin 认证由 AdminBootstrap 每次解 token 完成，
                                         // 无需 session 持久化；避免文件 session 锁导致并发请求串行等待
            'idParam' => '__admin_id',
        ],
        //前台登录标识
        'user' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => false,
            'enableSession'   => false,  // 纯 token API，认证由 AppBootstrap 每次解 token 完成，无需 session
        ],
        'mailer' => [
            'class' => 'yii\swiftmailer\Mailer',
            'useFileTransport' => true,
        ],
        'log' => [
            'traceLevel' => 1,
            'targets' => [
                [
                    'class' => \app\core\CoreLogTarget::class,
                    'levels' => ['error'],
                    'logFile' => '@runtime/logs/errors/' . date('Y-m-d') . '.log',
                    'maxLogFiles' => 30
                ],
                [
                    'class' => \app\core\CoreLogTarget::class,
                    'logVars' => [],
                    'levels' => ['warning', 'error',],
                    'logFile' => '@runtime/logs/' . date('Y-m-d') . '.log',
                    'maxLogFiles' => 30
                ],
                [
                    'class' => \app\core\CoreLogTarget::class,
                    'levels' => ['info'],
                    'logFile' => '@runtime/logs/info/' . date('Y-m-d') . '.log',
                    'maxLogFiles' => 30
                ],
                // 异步监控：error/warning 推送 Redis，由 Go 消费落库，不影响文件日志
                [
                    'class'  => \app\core\RedisLogTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ]
        ],

        // [修改] 从 $dbConfig 辅助变量加载
        'db' => $dbConfig,

        'urlManager' => [
            'enablePrettyUrl' => false,
            'showScriptName' => false,
            'rules' => [],
        ]
    ],

    // [修改] 从 $params 辅助变量加载
    'params' => $params,
];


// [重要修改] 6. 开发环境配置
// (使用 YII_ENV 和 $cfg 中的IP)
if (YII_ENV === 'dev') {
    // 仅在真实 HTTP 请求环境中挂载 Debug，防止 Go FastCGI/Cron 无 REQUEST_URI 时导致 URI 解析崩溃
    if (isset($_SERVER['REQUEST_URI'])) {
        $config['bootstrap'][] = 'debug';
        $config['modules']['debug'] = [
            'class' => 'yii\debug\Module',
            'allowedIPs' => $cfg['dev']['debug_allowed_ips'], // 从 config.php 读取
        ];
    }

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
        'allowedIPs' => $cfg['dev']['gii_allowed_ips'], // 从 config.php 读取
        'generators' => [
            'migrik' => [
                'class' => \insolita\migrik\gii\StructureGenerator::class,
                'templates' => [
                    'custom' => '@app/gii/default_structure',
                ],
            ],
            'migrikdata' => [
                'class' => \insolita\migrik\gii\DataGenerator::class,
                'templates' => [
                    'custom' => '@app/gii/migrator_data',
                ],
            ],
        ],
    ];
}


return $config;
