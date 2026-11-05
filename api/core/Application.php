<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:29
 * 官网：https://www.craddl.cn
 * The contents of this file are subject to the Common Public Attribution License Version 1.0 (the
 * “License”); you may not use this file except in compliance with the License. You may obtain a
 * copy of the License from LICENSE in the source package or
 * https://opensource.org/license/cpal-1.0. The License is based on the Mozilla Public License
 * Version 1.1 but Sections 14 and 15 have been added to cover use of software over a computer
 * network and provide for limited attribution for the Original Developer. In addition, Exhibit A
 * has been modified to be consistent with Exhibit B.
 * Software distributed under the License is distributed on an “AS IS” basis, WITHOUT WARRANTY OF
 * ANY KIND, either express or implied. See the License for the specific language governing rights
 * and limitations under the License.
 * The Original Code is Craddl Community Edition.
 * The Original Developer is the Initial Developer.
 * The Initial Developer of the Original Code is 佛山今日鼎盛信息科技有限公司. All portions of the code written
 * by 佛山今日鼎盛信息科技有限公司 are Copyright (c) 2026. All Rights Reserved.
 */

namespace app\core;

use app\components\Plugin;
use app\listeners\BaseListener;
use app\listeners\ListenerRegister;
use app\models\Mall;
// ────────────────────────────────────────────────────────────────────────────
// 架构说明（重要）：
//
// 下面 use 了 mch 插件的两个 model（Mch / MchClerk）。
// 这违背"核心-插件单向依赖"原则，但属于**经评估的合法豁免**：
//   1. docs/开发规范.md §21 明确将 mch 标记为「基础依赖插件」，全平台永久启用
//   2. Mch / MchClerk 是平台级"商户主体 / 员工身份"，所有跨插件流程都要用
//   3. 用强类型可换来 PHP 引擎层的运行时校验 + IDE 全程提示，价值显著
//
// 如未来要彻底解耦 Mch / MchClerk，应先把它们下沉到 app\core 或抽 ActorInterface，
// 影响面较大不在本次改造范围。
// ────────────────────────────────────────────────────────────────────────────
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Dotenv\Dotenv;
use yii\base\Module;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Trait Application
 * @package app\core
 *
 * @property Response $response
 * @property Mall $mall
 * @property string $platform
 * @property string $system
 * @property Plugin $plugin
 * @property bool $isAdmin
 * @property int $mchId
 * @property bool $isMch
 * @property int $mallId
 * @property bool $isPc
 * @property string $ios
 * @property string $android
 * @property string $mpwx
 * @property string $appVersion
 * @property Mch $mch
 * @property array|null $mchRole
 * @property MchClerk|null $mchClerk
 */
trait Application
{
    // ======================= [核心] 声明所有受保护的属性 =======================
    /** @var Mall 存储当前请求上下文的商城对象 */
    protected $mall;

    /** @var Mch 存储当前商户后台上下文的商户对象 */
    protected $mch;

    /** @var string 当前请求的平台标识 (e.g., 'MP-WEIXIN', 'H5') */
    public $platform;

    /** @var string 当前请求的操作系统 (e.g., 'ios', 'android') */
    protected $system;

    /** @var bool 当前请求是否来自PC端 */
    protected $isPc = false;

    /** @var bool 当前用户是否为平台管理员 */
    protected $isAdmin = false;

    /** @var bool 当前用户是否为商户 (通常指店长) */
    protected $isMch = false;

    /** @var int 当前商城的ID */
    protected $mallId;

    /** @var int 当前商户的ID */
    protected $mchId;

    /** @var array 存储当前请求上下文中，用户的商家角色信息 */
    protected $mchRole;

    /** @var MchClerk 存储当前请求上下文中，用户的商家成员关系对象 */
    protected $mchClerk;

    /** @var string|null 兼容旧属性 */
    protected $ios;

    /** @var string|null 兼容旧属性 */
    protected $android;

    /** @var string|null 兼容旧属性 */
    protected $mpwx;

    /** @var string|null 兼容旧属性 */
    protected $appVersion;
    // ======================= [声明结束] =======================

    public function getMchRole(): ?array
    {
        return $this->mchRole;
    }

    public function setMchRole(?array $role): void
    {
        $this->mchRole = $role;
    }

    public function getMchClerk(): ?MchClerk
    {
        return $this->mchClerk;
    }

    public function setMchClerk(?MchClerk $clerk): void
    {
        $this->mchClerk = $clerk;
    }

    /**
     * @return $this
     */
    protected function loadDotEnv()
    {
        try {
            $dotenv = Dotenv::createImmutable(dirname(__DIR__));
            $list = $dotenv->load();
            foreach ($list as $k => $v) {
                if ($v === 'true' || $v === 'false' || $v === true || $v === false) {
                    if ($v === 'true' || $v === true) {
                        define_once($k, true);
                    } else {
                        define_once($k, false);
                    }
                } else {
                    define_once($k, $v);
                }
            }
        } catch (\Dotenv\Exception\InvalidPathException $ex) {
        }
        return $this;
    }

    /**
     * @return $this
     */
    protected function responseAsJson()
    {
        // 用具名 callable 替代匿名闭包：可以 off() 解绑，避免重复 attach 时无法清理
        $this->response->on(Response::EVENT_BEFORE_SEND, [$this, 'autoFormatResponseAsJson']);
        return $this;
    }

    /**
     * Response::EVENT_BEFORE_SEND 回调：如果 data 是数组/对象则自动序列化为 JSON
     */
    public function autoFormatResponseAsJson($event): void
    {
        /** @var \yii\web\Response $response */
        $response = $event->sender;
        if (is_array($response->data) || is_object($response->data)) {
            $response->format = Response::FORMAT_JSON;
        }
    }

    /**
     * @param string $route
     * @param array $params
     * @return mixed
     * @throws \yii\base\InvalidConfigException
     * @throws NotFoundHttpException
     */
    public function runAction($route, $params = [])
    {
        // BC 函数精度（每请求只会执行一次的微开销，bcscale 是 ext-bcmath 内部状态）
        bcscale(2);

        $route = ltrim($route, '/');

        // 仅对 plugin/* 路由走插件解析，其他直接交给 Yii 原生路由
        // strncmp 比正则 preg_match 快 ~5x（避免 PCRE 编译开销）
        if (strncmp($route, 'plugin/', 7) !== 0) {
            return parent::runAction($route, $params);
        }

        $resolved = $this->resolvePluginRoute($route);
        if ($resolved === null) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Page not found.'));
        }
        [$plugin, $pluginName, $controllerClass, $controllerId, $actionId] = $resolved;

        try {
            /** @var Controller $controller */
            $controller = \Yii::createObject($controllerClass, [$controllerId, $this]);
            $controller->module = new Module($pluginName, $this);
            $this->controller = $controller;
            \Yii::$app->plugin->setCurrentPlugin($plugin); // 设置当前插件上下文
            return $controller->runAction($actionId, $params);
        } catch (\ReflectionException $e) {
            throw new NotFoundHttpException(\Yii::t('yii', 'Page not found.'), 0, $e);
        }
    }

    /**
     * 解析 plugin/* 路由 → 定位插件 + Controller 类名 + actionId
     *
     * 路由格式: plugin/{name}/{namespace}/{controller}/{action}
     * 例:      plugin/mch/admin/order/list → app\plugins\mch\controllers\admin\OrderController::actionList
     *
     * 性能优化点（相比旧实现）：
     *   1. explode 代替 mb_split（URL 无多字节场景，~30x）
     *   2. count 提出循环条件
     *   3. dash-to-CamelCase 用 ucwords 字符串技巧代替 preg_replace_callback（消除闭包+正则编译）
     *
     * @param string $route 已去掉前缀 / 的完整路由
     * @return array|null [Plugin $plugin, string $pluginName, string $controllerClass, string $controllerId, string $actionId]
     */
    private function resolvePluginRoute(string $route): ?array
    {
        $parts = explode('/', $route);
        $pluginName = $parts[1] ?? '';
        if ($pluginName === '') {
            return null;
        }

        $plugin = $this->plugin->getPlugin($pluginName);
        if (!$plugin) {
            return null; // 插件未安装
        }

        $total = count($parts);
        $controllerClass = '';
        $controllerId = 'index';
        $actionId = 'index';

        // 滑动窗口探测：尝试不同 (namespace, controller) 组合，找首个真实存在的 Controller 类
        for ($i = 2; $i < $total; $i++) {
            $nameSpace = $parts[$i] !== '' ? $parts[$i] : 'index';
            $controllerId = (isset($parts[$i + 1]) && $parts[$i + 1] !== '') ? $parts[$i + 1] : 'index';

            // dash-to-CamelCase: 'goods-list' → 'GoodsList'（不用正则，速度 5-10x）
            $controllerName = str_replace(' ', '', ucwords(str_replace('-', ' ', $controllerId))) . 'Controller';

            $controllerClass = "app\\plugins\\{$pluginName}\\controllers\\{$nameSpace}\\{$controllerName}";
            $actionId = (isset($parts[$i + 2]) && $parts[$i + 2] !== '') ? $parts[$i + 2] : 'index';

            if (class_exists($controllerClass)) {
                return [$plugin, $pluginName, $controllerClass, $controllerId, $actionId];
            }
        }

        return null;
    }

    public function getMch(): ?Mch
    {
        return $this->mch;
    }

    public function setMch(?Mch $mch): void
    {
        $this->mch = $mch;
    }

    public function getMall(): ?Mall
    {
        return $this->mall;
    }

    public function setMall(?Mall $mall): void
    {
        $this->mall = $mall;
    }

    public function getIsPc(): bool
    {
        return (bool)$this->isPc;
    }

    public function setIsPc($flag = false): void
    {
        $this->isPc = (bool)$flag;
    }

    public function getIos(): ?string
    {
        return $this->ios;
    }

    public function setIos(?string $ios): void
    {
        $this->ios = $ios;
    }

    public function getAndroid(): ?string
    {
        return $this->android;
    }

    public function setAndroid(?string $android): void
    {
        $this->android = $android;
    }

    public function getMpwx(): ?string
    {
        return $this->mpwx;
    }

    public function setMpwx(?string $mpwx): void
    {
        $this->mpwx = $mpwx;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $version): void
    {
        $this->appVersion = $version;
    }

    /**
     * @return int 当前商城 ID，未设置时返回 0
     */
    public function getMallId(): int
    {
        return (int)($this->mallId ?? 0);
    }

    /**
     * @param int|string|null $mall_id
     */
    public function setMallId($mall_id)
    {
        $this->mallId = $mall_id === null ? null : (int)$mall_id;
    }

    /**
     * @return int 当前商户 ID，未设置时返回 0
     */
    public function getMchId(): int
    {
        return (int)($this->mchId ?? 0);
    }

    /**
     * @param int|string|null $mch_id
     */
    public function setMchId($mch_id)
    {
        $this->mchId = $mch_id === null ? null : (int)$mch_id;
    }

    public function getIsMch(): bool
    {
        return (bool)$this->isMch;
    }

    public function setIsMch($is_mch): void
    {
        $this->isMch = (bool)$is_mch;
    }

    public function getIsAdmin(): bool
    {
        return (bool)$this->isAdmin;
    }

    public function setIsAdmin($flag = false): void
    {
        $this->isAdmin = (bool)$flag;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): void
    {
        $this->platform = $platform;
    }

    public function getSystem(): ?string
    {
        return $this->system;
    }

    public function setSystem(?string $system): void
    {
        $this->system = $system;
    }

    /**
     * @return $this
     */
    protected function registerListeners()
    {
        $register = new ListenerRegister();
        $listenerClasses = $register->getListeners();
        foreach ($listenerClasses as $listenerClass) {
            /** @var BaseListener $listener */
            $listener = new $listenerClass();
            if ($listener instanceof BaseListener) {
                $listener->register();
            }
        }
        // 注册插件事件
        // 注意：异常必须记录到日志（之前是静默吞掉，导致线上插件 listener 注册失败无法定位）
        try {
            \Yii::$app->plugin->registerListeners();
        } catch (\Throwable $exception) {
            \Yii::error(
                '插件 listener 注册失败：' . $exception->getMessage() . "\n" . $exception->getTraceAsString(),
                'plugin'
            );
        }
        return $this;
    }
}
