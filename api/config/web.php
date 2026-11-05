<?php

$config = require __DIR__ . '/common.php';

$config['components']['request'] = [
    'cookieValidationKey' => $_ENV['COOKIE_VALIDATION_KEY'] ?? 'dfsf132sda1f32ds1af',
    'parsers' => [
        'application/json' => 'yii\web\JsonParser',
    ]
];


$config['components']['errorHandler'] = [
    'errorAction' => 'site/error',
];
$config['id'] = 'jxmall';
return $config;
