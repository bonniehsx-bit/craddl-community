<?php

use yii\log\Logger;

/**
 * IDE friendly
 * 此文件没有任何作用，只是为了IDE友好提示而已
 */
class Yii extends \yii\BaseYii
{
    /**
     * @var Application the application instance
     */
    public static $app;
}

spl_autoload_register(['Yii', 'autoload'], true, true);
Yii::$classMap = require __DIR__ . '/vendor/yiisoft/yii2/classes.php';
Yii::$container = new yii\di\Container();

/**
 * Class BaseApplication
 * Used for properties that are identical for both WebApplication and ConsoleApplication
 * @property \app\models\User $user
 * @property \yii\web\User $admin
 * @property \yii\queue\Queue $queue
 * @property \yii\queue\Queue $rqueue
 * @property \yii\queue\Queue $bqueue
 * @property \app\components\wechat\WechatComponent $wechat
 * @property \app\components\Plugin $plugin
 * @property \yii\redis\Connection $redis
 * @property \app\core\Connection $db
 * @property \app\models\Mall $mall
 * @property \app\components\JoinPay $joinPay
 * @property integer $mchId
 * @property integer $mallId
 * @property integer $isMch
 * @property string $platform
 * @property integer $isPc
 * @property string $appVersion
 */
abstract class Application extends yii\base\Application
{
    /**
     * @var false|mixed|object|null
     */
    public $isAdmin;

    /**
     * @return \app\models\User
     */
    abstract function getUser();

    /**
     * @return \app\models\Admin
     */
    abstract function getAdmin();
}
