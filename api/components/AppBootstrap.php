<?php
/**
 * Copyright (c) 2026 佛山今日鼎盛信息科技有限公司
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

namespace app\components;

use app\helpers\CacheHelper;
use app\models\AccessToken;
use app\models\Mall;
use app\models\User;
use app\services\UserService;
use Yii;
use yii\base\BootstrapInterface;
use yii\web\Application as WebApplication;
use yii\console\Application as ConsoleApplication;

/**
 * Class AppBootstrap
 * @package app\components
 *
 * [最终架构版 v2] 统一的应用引导组件。
 *
 * 核心策略：
 * 1. 动静分离：身份字段走 Redis 缓存，余额/积分等动态字段每次直查 DB（清单见 User::DYNAMIC_CACHE_EXCLUDE_FIELDS）
 * 2. OpenID 跟随 Token：OpenID 动态注入 User 对象，解决多端 openid 隔离
 * 3. Token 运行时携带：将 Token 注入 User 内存对象，业务层可直接读 access_token
 *
 * 三段式职责分明：
 *   bootstrapMall($app)    —— 商城上下文（CLI/Web 共用）
 *   bootstrapDevice($app)  —— 平台 / 系统 / 版本（仅 Web）
 *   bootstrapUser($app)    —— 用户登录态（仅 Web）
 */
class AppBootstrap implements BootstrapInterface
{
    /**
     * Token 缓存兜底上限：7 天（实际 TTL 跟 DB.expire_at 同步）
     */
    private const TOKEN_CACHE_MAX_TTL = 7 * 24 * 60 * 60;

    /**
     * Identity 缓存 TTL：1 小时
     */
    private const IDENTITY_CACHE_TTL = 3600;

    /**
     * @param \yii\base\Application $app
     */
    public function bootstrap($app)
    {
        $this->bootstrapMall($app);

        if ($app instanceof WebApplication) {
            $this->bootstrapDevice($app);
            $this->bootstrapUser($app);
        }
    }

    /**
     * 装载当前请求的 Mall 上下文
     *
     * - CLI: 兜底取 mall_id=1（注意：聚象V2 是多商城平台，CLI 命令应优先用 Manager::run() 显式逐商城跑，
     *        本 mall_id=1 仅作历史兼容兜底，若 mall_id=1 不存在则 mall 为 null + log）
     * - Web: 从 x-mall-id header 或 mall_id 参数读取，严格校验整数
     */
    private function bootstrapMall($app): void
    {
        $mall = null;

        if ($app instanceof ConsoleApplication) {
            try {
                $mall = Mall::getOne(1);
            } catch (\Throwable $e) {
                Yii::error('AppBootstrap[CLI] 加载默认 mall(id=1) 失败：' . $e->getMessage(), 'bootstrap');
            }
        } elseif ($app instanceof WebApplication) {
            $request = $app->getRequest();
            $headers = $request->getHeaders();
            $mall_id = $headers->get('x-mall-id') ?: $request->get('mall_id');

            // 严格校验：必须是纯正整数（拒绝 "1.5" / "1e10" / "0x01" / 负数 等异常输入）
            if (ctype_digit((string)$mall_id) && (int)$mall_id > 0) {
                try {
                    $mall = Mall::getOne((int)$mall_id);
                } catch (\Throwable $e) {
                    Yii::error("AppBootstrap[Web] 加载 mall(id={$mall_id}) 失败：" . $e->getMessage(), 'bootstrap');
                }
            }
        }

        if ($mall) {
            $app->set('mall', $mall);
        }
    }

    /**
     * 装载设备/平台/版本上下文（来自 Header，仅 Web）
     *
     * 注意：platform / isPc / isMch 完全由前端 Header 决定，不做服务端校验。
     * 这些字段仅作"前端 UI hint"使用，权限判定走各自的 Bootstrap（AdminBootstrap / MchFilter 等）。
     */
    private function bootstrapDevice(WebApplication $app): void
    {
        $headers = $app->request->getHeaders();
        $userAgent = $headers->get('user-agent', '');

        $app->platform = $headers->get('x-platform', 'unknown');
        $app->isPc = $headers->get('is-pc') == 1 ? 1 : 0;
        $app->appVersion = $headers->get('x-version', '100');
        $app->isMch = $headers->get('is-mch') ? 1 : 0;

        // user-agent 不区分大小写嗅探（兼容 iPhone / iphone / IPhone 各种写法）
        if (stripos($userAgent, 'iphone') !== false) {
            $app->system = 'Iphone';
        } elseif (stripos($userAgent, 'android') !== false) {
            $app->system = 'Android';
        } else {
            $app->system = 'Unknown';
        }
    }

    /**
     * 装载用户登录态（核心认证逻辑）
     *
     * 分三阶段：
     *   A. 解析 Token → user_id + openid（Redis 命中 / DB 回源）
     *   B. 拉取用户身份（Redis identity 缓存 / DB 回源）
     *   C. 实例化 User 模型 + 注入 OpenID/Token 到内存对象 + 登录
     */
    private function bootstrapUser(WebApplication $app): void
    {
        $tokenString = $app->request->headers->get('x-access-token');
        if (!$tokenString) {
            return;
        }

        $requestIp = $app->request->userIP;

        // 阶段 A：解析 Token
        [$userId, $currentOpenId, $tokenMeta] = $this->resolveTokenToUserId($tokenString, $requestIp);
        if (!$userId) {
            return;
        }

        // 阶段 B：拉取用户身份
        $identityData = $this->loadUserIdentity($userId);
        if (!$identityData) {
            return;
        }

        // 活跃续期:用户还在用就把到期时间往后推,有效期按用户所属商城的「登录有效期」。
        // 续期时发现库里令牌已没了(退出/强制下线)→ 本次按未登录;续期本身出错只记日志,不影响本次请求
        try {
            $renewed = UserService::renewToken($tokenString, $tokenMeta, (int)($identityData['mall_id'] ?? 0), [
                'user_id' => $userId,
                'openid' => $currentOpenId,
                'ip' => $requestIp,
            ]);
            if ($renewed === false) {
                return;
            }
        } catch (\Throwable $e) {
            Yii::error('AppBootstrap 令牌续期失败 user=' . $userId . ':' . $e->getMessage(), 'bootstrap');
        }

        // 阶段 C：实例化 + 注入上下文 + 登录
        $this->loginUserWithContext($app, $identityData, $tokenString, $currentOpenId);
    }

    /**
     * 阶段 A：Token → user_id + openid
     *
     * @return array [int|null $userId, string|null $openid, array $tokenMeta]
     *         tokenMeta = ['expire_at'=>, 'ttl'=>],续期用;旧格式缓存没有就是空数组(续期时会回库查一次)
     */
    private function resolveTokenToUserId(string $tokenString, ?string $requestIp): array
    {
        $tokenCacheKey = User::CACHE_KEY_TOKEN_MAP . md5($tokenString);
        $tokenData = CacheHelper::get($tokenCacheKey);

        $userId = null;
        $currentOpenId = null;
        $tokenMeta = [];

        // A1. Redis 缓存命中
        if ($tokenData && is_array($tokenData)) {
            $userId = $tokenData['user_id'] ?? null;
            $currentOpenId = $tokenData['openid'] ?? null;
            $tokenMeta = array_intersect_key($tokenData, ['expire_at' => 1, 'ttl' => 1]);
            $cachedIp = $tokenData['ip'] ?? null;

            // 缓存记着的到期时间已过 → 回源 DB 判(正常情况下缓存 TTL 不会超过到期时间,这里兜底)
            if ($userId && isset($tokenMeta['expire_at']) && (int)$tokenMeta['expire_at'] <= time()) {
                CacheHelper::remove($tokenCacheKey);
                $userId = null;
                $currentOpenId = null;
                $tokenMeta = [];
            }

            // [缓存刷新] IP 漂移触发回源（注意：这不是安全机制，回源后会用新 IP 重写缓存）
            // 真要做"IP 绑定 token"防盗用，需在回源后比对 DB.ip 并拒绝服务。
            if ($userId && $cachedIp && $cachedIp !== $requestIp) {
                CacheHelper::remove($tokenCacheKey);
                $userId = null;
                $currentOpenId = null;
                $tokenMeta = [];
            }
        } elseif ($tokenData) {
            // 兼容旧格式（纯 userId 整数）
            $userId = $tokenData;
        }

        // A2. 强制登出拦截：Token缓存命中但用户已被踢出
        if ($userId && CacheHelper::get(User::CACHE_KEY_FORCE_LOGOUT . $userId)) {
            CacheHelper::remove($tokenCacheKey);
            $userId = null;
            $currentOpenId = null;
            $tokenMeta = [];
        }

        // A3. 回源 DB
        if (!$userId) {
            $accessTokenFromDb = AccessToken::find()
                ->where(['token' => hash('sha256', $tokenString), 'model' => User::class])
                ->andWhere(['>', 'expire_at', time()])
                ->one();

            if ($accessTokenFromDb) {
                $userId = (int)$accessTokenFromDb->model_id;
                $currentOpenId = $accessTokenFromDb->wx_openid;
                $tokenMeta = ['expire_at' => (int)$accessTokenFromDb->expire_at];

                // 缓存 TTL 与 DB.expire_at 同步：避免"DB 已过期但 Redis 还活着"导致 token 续命
                $cacheTtl = max(60, min(self::TOKEN_CACHE_MAX_TTL, (int)$accessTokenFromDb->expire_at - time()));

                CacheHelper::set($tokenCacheKey, [
                    'user_id' => $userId,
                    'openid' => $currentOpenId,
                    'ip' => $requestIp,
                    'expire_at' => $tokenMeta['expire_at'],
                ], $cacheTtl);

                // 同步更新 DB 中的 IP 记录(updateAttributes 不触发 afterSave,免得每次回源都多排一个过期清理任务)
                $accessTokenFromDb->updateAttributes(['ip' => $requestIp]);
            }
        }

        return [$userId, $currentOpenId, $tokenMeta];
    }

    /**
     * 阶段 B：拉取用户身份（缓存优先 + DB 回源 + 动态字段过滤）
     *
     * @return array|null 身份字段数组，失败/被禁用返回 null
     */
    private function loadUserIdentity(int $userId): ?array
    {
        $cacheKey = User::CACHE_KEY_IDENTITY . $userId;
        $identityData = CacheHelper::get($cacheKey);

        if ($identityData) {
            return $identityData;
        }

        $userModel = User::findOne(['id' => $userId, 'is_delete' => 0]);
        if (!$userModel || $userModel->status != User::STATUS_NORMAL) {
            return null;
        }

        $identityData = $userModel->attributes;

        // 剔除动态字段 —— 维护清单见 User::DYNAMIC_CACHE_EXCLUDE_FIELDS
        // 这些字段（余额/积分/累计金额等）变化频繁，不能缓存 1 小时
        foreach (User::DYNAMIC_CACHE_EXCLUDE_FIELDS as $field) {
            unset($identityData[$field]);
        }

        CacheHelper::set($cacheKey, $identityData, self::IDENTITY_CACHE_TTL);
        return $identityData;
    }

    /**
     * 阶段 C：实例化 User 模型 + 注入 OpenID/Token + 登录
     */
    private function loginUserWithContext(WebApplication $app, array $identityData, string $tokenString, ?string $currentOpenId): void
    {
        $user = new User();
        $user->setAttributes($identityData, false);
        $user->setIsNewRecord(false);

        // OpenID 注入：通用 openid 字段 + 平台特定字段
        if ($currentOpenId) {
            $user->openid = $currentOpenId;
            $platform = strtolower($app->platform ?? '');
            if ($platform === 'mpwx' || $platform === 'wxapp') {
                $user->mpwx_openid = $currentOpenId;
            } elseif ($platform === 'wechat' || $platform === 'official') {
                $user->wechat_openid = $currentOpenId;
            }
        }

        // Token 注入：让 Yii::$app->user->identity->access_token 在本次请求中可用
        // 注意：这只是内存赋值，不会写回 Redis identity 缓存
        $user->access_token = $tokenString;

        // 因为是 API 环境 (无状态)，login() 可能尝试 session_start() 导致权限/会话报错
        // 降级为 setIdentity() 跳过 session 写入
        try {
            $app->user->login($user);
        } catch (\Throwable $e) {
            $app->user->setIdentity($user);
        }
    }
}
