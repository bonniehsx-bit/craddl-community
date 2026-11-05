<?php
$testConsole = defined('CRADDL_TEST_CONSOLE');
if ($testConsole && !in_array(YII_ENV, ['dev', 'test'], true)) {
    fwrite(STDERR, "测试命令仅允许在 dev 或 test 环境运行。\n");
    exit(1);
}

$config = require __DIR__ . '/common.php';
$config['controllerNamespace'] = $testConsole ? 'app\tests\console' : 'app\commands';
$config['components']['request'] = [
    'class' => \app\core\ConsoleRequest::class,
];
$config['components']['response'] = [
    'class' => \app\core\ConsoleResponse::class,
];
// 插件命令自动注册：
//   扫描 api/plugins/{plugin}/commands/{Xxx}Controller.php
//   注册为命令 ID "{plugin}-{kebab}"（详见 PluginConsoleHelper 注释）
//   零配置 — 新增插件命令丢文件就生效
//
// 注意：本配置在 Yii Application 实例化前加载，此时 Yii autoloader 尚未注册，
// 故直接 require helper 文件，不依赖 namespace 自动加载。
require_once dirname(__DIR__) . '/helpers/PluginConsoleHelper.php';

$config['controllerMap'] = array_merge(
    \app\helpers\PluginConsoleHelper::scanCommands(),
    [
        'migrate' => [
            'class' => 'app\commands\MigrateController'
        ],
    ]
);
$config['id'] = 'jxmall_console_app';
return $config;
