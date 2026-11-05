<?php
$_GET['r'] = 'install/index';
error_reporting(E_ERROR | E_PARSE);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config/web.php';

(new \app\core\InstallApplication($config))->run();
