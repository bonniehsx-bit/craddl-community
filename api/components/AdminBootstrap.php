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
use app\models\Admin;
use Yii;
use yii\base\BootstrapInterface;
use yii\web\Application as WebApplication;

/**
 * Class AdminBootstrap
 * @package app\components
 *
 * [后台认证引导]
 * 负责解析 x-admin-token，实现 Redis 加速认证 + UA/IP 缓存键安全校验。
 *
 * 与 AppBootstrap (C 端) 的差异：
 *   - Admin 没有大量动态字段，只需脱敏 password/auth_key/access_token
 *   - 多一层 UA 校验：PC 端 UA 稳定，UA 变化触发缓存刷新
 *
 * 三段式职责分明：
 *   resolveTokenToAdminId    —— Token → admin_id（Redis 命中 / DB 回源）
 *   loadAdminIdentity        —— admin_id → 身份字段（脱敏）
 *   loginAdminWithContext    —— 实例化 + Token 注入 + 登录
 */
class AdminBootstrap implements BootstrapInterface
{
    /**
     * Token 缓存兜底上限：7 天（实际 TTL 跟 DB.expire_at 同步）
     */
    private const TOKEN_CACHE_MAX_TTL = 7 * 24 * 60 * 60;

    /**
     * Identity 缓存 TTL：2 小时（Admin 改资料频率低于 User，可缓存更久）
     */
    private const IDENTITY_CACHE_TTL = 7200;

    /**
     * 敏感字段：身份缓存中**必须剔除**这些字段
     */
    private const SENSITIVE_FIELDS = ['password', 'auth_key', 'access_token'];

    public function bootstrap($app)
    {
        if (!($app instanceof WebApplication)) {
            return;
        }

        $tokenString = $app->request->headers->get('x-admin-token');
        if (!$tokenString || !$app->admin->isGuest) {
            return;
        }

        $requestIp = $app->request->userIP;
        $currentUaHash = md5((string)$app->request->userAgent);

        // 阶段 A：解析 Token
        $adminId = $this->resolveTokenToAdminId($tokenString, $requestIp, $currentUaHash);
        if (!$adminId) {
            return;
        }

        // 阶段 B：拉取身份
        $identityData = $this->loadAdminIdentity($adminId);
        if (!$identityData) {
            return;
        }

        // 阶段 C：实例化 + 登录
        $this->loginAdminWithContext($app, $identityData, $tokenString);
    }

    /**
     * 阶段 A：Token → admin_id
     *
     * @return int|null 解析成功返回 admin_id，失败返回 null
     */
    private function resolveTokenToAdminId(string $tokenString, ?string $requestIp, string $currentUaHash): ?int
    {
        $tokenCacheKey = Admin::CACHE_KEY_TOKEN_MAP . md5($tokenString);
        $tokenData = CacheHelper::get($tokenCacheKey);

        $adminId = null;

        // A1. Redis 缓存命中分支
        if ($tokenData && is_array($tokenData)) {
            $savedId     = $tokenData['id'] ?? null;
            $savedUaHash = $tokenData['ua_hash'] ?? null;
            $cachedIp    = $tokenData['ip'] ?? null;

            // [缓存刷新] UA / IP 变化让缓存失效，强制回源
            // 注意：这不是真正的"安全机制" —— 回源后会用新 UA/IP 重写缓存，token 仍然有效。
            // 真要做"UA/IP 绑定 token"防盗用，需要 DB 也存 UA/IP 并在回源后比对拒绝服务。
            if ($savedUaHash && $savedUaHash !== $currentUaHash) {
                CacheHelper::remove($tokenCacheKey);
            } elseif ($cachedIp && $cachedIp !== $requestIp) {
                CacheHelper::remove($tokenCacheKey);
            } else {
                $adminId = $savedId;
            }
        } elseif ($tokenData) {
            // 兼容旧缓存（只存 admin_id 整数）
            $adminId = $tokenData;
        }

        // A2. 强制登出拦截
        if ($adminId && CacheHelper::get(Admin::CACHE_KEY_FORCE_LOGOUT . $adminId)) {
            CacheHelper::remove($tokenCacheKey);
            $adminId = null;
        }

        // A3. 回源 DB
        if (!$adminId) {
            try {
                $accessToken = AccessToken::find()
                    ->where([
                        'token' => hash('sha256', $tokenString),
                        'model' => Admin::class,
                    ])
                    ->andWhere(['>', 'expire_at', time()])
                    ->one();
            } catch (\Throwable $e) {
                Yii::error('AdminBootstrap 查 AccessToken 失败：' . $e->getMessage(), 'bootstrap');
                return null;
            }

            if ($accessToken) {
                $adminId = (int)$accessToken->model_id;

                // 缓存 TTL 与 DB.expire_at 同步：避免"DB 已过期但 Redis 还活着"导致 token 续命
                // 兜底下限 60 秒、上限 7 天
                $cacheTtl = max(60, min(self::TOKEN_CACHE_MAX_TTL, (int)$accessToken->expire_at - time()));

                CacheHelper::set($tokenCacheKey, [
                    'id'      => $adminId,
                    'ua_hash' => $currentUaHash,
                    'ip'      => $requestIp,
                ], $cacheTtl);

                // 同步更新 DB.ip
                $accessToken->ip = $requestIp;
                try {
                    $accessToken->save(false, ['ip']);
                } catch (\Throwable $e) {
                    Yii::error('AdminBootstrap 更新 AccessToken.ip 失败：' . $e->getMessage(), 'bootstrap');
                }
            }
        }

        return $adminId ? (int)$adminId : null;
    }

    /**
     * 阶段 B：拉取 Admin 身份字段（脱敏）
     *
     * @return array|null
     */
    private function loadAdminIdentity(int $adminId): ?array
    {
        $cacheKey = Admin::CACHE_KEY_IDENTITY . $adminId;
        $identityData = CacheHelper::get($cacheKey);

        if ($identityData) {
            return $identityData;
        }

        try {
            $adminModel = Admin::findOne(['id' => $adminId, 'is_delete' => 0]);
        } catch (\Throwable $e) {
            Yii::error("AdminBootstrap 查 Admin(id={$adminId}) 失败：" . $e->getMessage(), 'bootstrap');
            return null;
        }

        if (!$adminModel) {
            return null;
        }

        $identityData = $adminModel->attributes;
        // 脱敏：剔除敏感字段，避免泄漏到 Redis
        foreach (self::SENSITIVE_FIELDS as $field) {
            unset($identityData[$field]);
        }

        CacheHelper::set($cacheKey, $identityData, self::IDENTITY_CACHE_TTL);
        return $identityData;
    }

    /**
     * 阶段 C：实例化 Admin + 注入 Token + 登录
     */
    private function loginAdminWithContext(WebApplication $app, array $identityData, string $tokenString): void
    {
        $admin = new Admin();
        $admin->setAttributes($identityData, false);
        $admin->setIsNewRecord(false);

        // Token 注入：让 logout 等流程能取到当前 token
        $admin->access_token = $tokenString;

        $app->admin->login($admin);
    }
}
