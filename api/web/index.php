<?php
// [关键] 1. 定义 Yii 框架的核心常量
// 部署到服务器/生产环境时，请确保 YII_DEBUG 为 false, YII_ENV 为 'prod'
// 这将关闭 debug 模块，从而解决内存耗尽的问题。
//
// 【优化建议】: 我们将把下面这两行移到 .env 文件中去控制

// defined('YII_DEBUG') or define('YII_DEBUG', false);
// defined('YII_ENV') or define('YII_ENV', 'prod');

// 错误报告级别 (建议在生产环境使用)
error_reporting(E_ERROR | E_PARSE);

// [关键] 默认就关回显——必须在 autoload 之前，否则引导期/vendor 缺失等早期 fatal
// 的堆栈会被写进输出，在 Go 调度器 FastCGI 派发下污染响应头 → "malformed MIME header: #0 {main}"。
// 开发环境(YII_DEBUG=true)会在下方读取 .env 后再打开回显。
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');


require __DIR__ . '/../vendor/autoload.php';

// ==================== [ vlucas/phpdotenv ] ====================
// 1. 加载 .env 文件
// createImmutable 指明 .env 文件在上一级目录 (即项目根目录)
try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    // 使用 safeLoad()，如果 .env 文件不存在 (例如在生产环境)，它不会报错
    $dotenv->safeLoad();
} catch (Exception $e) {
    // 处理加载 .env 时的异常
    Yii::warning("Could not load .env file: " . $e->getMessage());
}
// =============================================================


// ==================== [ 优化 YII_DEBUG 定义 ] ====================
// 2. 现在从 .env 文件中读取 YII_DEBUG 和 YII_ENV
// 注意：dotenv 读出的是【字符串】，"false" 是非空字符串=truthy，直接 define 会让生产仍开调试！
// 必须用 filter_var 按布尔解析，才能让 YII_DEBUG=false 真正生效。
defined('YII_DEBUG') or define('YII_DEBUG', filter_var($_ENV['YII_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN));
defined('YII_ENV') or define('YII_ENV', $_ENV['YII_ENV'] ?? 'prod');

// 顶部已默认关闭回显（生产保持关闭，堆栈只进日志、不污染 FastCGI 响应）；
// 仅开发环境(YII_DEBUG=true)在此重新打开，便于本地调试。
if (YII_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
}
// =============================================================


// Gii 访问限制 (你的原始逻辑, 加上了环境判断)
if (isset($_GET['r']) && strpos($_GET['r'], 'gii') !== false) {
    // 只有在开发环境 (dev) 才允许访问 Gii
    if (YII_ENV !== 'dev') {
        die('Access Denied.');
    }
}

// 3. 在 .env 加载完毕后，才加载 Yii 的配置
$config = require __DIR__ . '/../config/web.php';
try {
    (new \app\core\WebApplication($config))->run();
} catch (\Throwable $e) {
    // \Throwable 含 \Error：之前只 catch \Exception，TypeError 等 \Error 会逃逸成未捕获致命错误。
    Yii::error('App uncaught: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    if (!headers_sent()) {
        http_response_code(500);
    }
}