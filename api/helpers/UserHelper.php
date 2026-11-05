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
/**
 * Created by PhpStorm.
 * User: ganxi
 * Date: 2022/4/22
 * Time: 14:05
 * Note: 用户辅助类 [最终优化版]
 */

namespace app\helpers;

use app\models\User;
use Yii;
use yii\db\Exception;

class UserHelper
{
    /**
     * 获取当前登录用户 ID
     * 来源：内存 (Redis 身份缓存)
     * 耗时：~0ms
     */
    public static function getMyId()
    {
        if (Yii::$app->user->isGuest) {
            return 0;
        }
        // 直接拿内存中的 ID，速度极快
        return Yii::$app->user->identity->getId();
    }

    /**
     * 获取当前用户的完整模型（强制查库）
     * 场景：需要获取余额、积分、优惠券数量等【实时变动】的数据时使用
     * @return User|null
     */
    public static function getUser()
    {
        $id = self::getMyId();
        if (!$id) {
            return null;
        }
        // findOne 走主键查询，性能很好 (1-3ms)
        return User::findOne($id);
    }


    /**
     * [最终完整版] 获取用户 OpenID
     * 策略：
     * 1. 优先利用内存中与当前 Token 绑定的 OpenID (精准、极速)。
     * 2. 仅在跨平台查询时，回溯 AccessToken 表的历史记录。
     *
     * @param string|null $targetPlatform 目标平台 (mpwx, wechat)，留空则返回当前会话的
     * @return string|null
     */
    public static function getOpenId($targetPlatform = null)
    {
        /** @var User $user */
        $user = Yii::$app->user->identity;
        if (!$user) {
            return null;
        }

        // 获取当前 Token 所处的平台 (由 AppBootstrap 注入的全局环境)
        // 假设你在 AppBootstrap 里定义了 Yii::$app->platform
        $currentAppPlatform = strtolower(Yii::$app->platform ?? '');

        // =========================================================
        // 1. 极速模式：直接使用当前 Token 携带的 OpenID
        // =========================================================

        // 场景 A: 没有指定目标平台 -> 就是要当前会话的 OpenID
        if (empty($targetPlatform)) {
            return $user->openid;
        }

        $targetPlatform = strtolower($targetPlatform);
        // 别名标准化
        if ($targetPlatform === 'wxapp') $targetPlatform = 'mpwx';
        if ($targetPlatform === 'official') $targetPlatform = 'wechat';

        // 场景 B: 指定了目标平台，且目标平台 == 当前 APP 平台
        // 既然平台一致，那么当前 Token 对应的 OpenID 就是精准结果
        if ($targetPlatform === $currentAppPlatform && !empty($user->openid)) {
            return $user->openid;
        }

        // =========================================================
        // 2. 兜底模式：跨平台查询 (Token 帮不上忙了，查历史表)
        // 场景：当前在公众号(wechat)登录，但业务要发小程序(mpwx)订阅消息
        // =========================================================

        // 去 access_token 表，找该用户在目标平台最后一次登录留下的 OpenID
        $latestToken = \app\models\AccessToken::find()
            ->select(['wx_openid'])
            ->where([
                'model_id' => $user->id,
                'model' => User::class,
                'platform' => $targetPlatform // 严格匹配目标平台
            ])
            ->andWhere(['IS NOT', 'wx_openid', null])
            ->andWhere(['!=', 'wx_openid', ''])
            ->orderBy(['id' => SORT_DESC]) // 取最新的
            ->limit(1)
            ->asArray()
            ->one();

        if ($latestToken) {
            return $latestToken['wx_openid'];
        }

        return null;
    }

    /**
     * 修改用户单个属性
     * 机制：修改后保存 -> 触发 User::afterSave -> 自动刷新 Redis 身份缓存
     */
    /**
     * C 端可由用户自行修改的字段白名单。
     *
     * 【安全红线】本方法被 /api/user/set-user-info 以「任意 key-value」形式调用,
     * 若不限制字段,用户可直接改 integral/money/price/level_id/parent_id 等资产与身份字段
     * (它们都在 User::rules() 的 number/integer 规则内,能通过校验)→ 直接资损。
     * 新增可改字段请务必确认其不涉及资产、等级、关系链、权限。
     */
    const USER_EDITABLE_ATTRIBUTES = [
        'nickname',
        'avatar_url',
        'gender',
        'birthday',
        'mobile_area',
        'email',
        'province_id',
        'city_id',
        'district_id',
    ];

    /**
     * 后台(/mall/user/set-user-info,用户详情抽屉)可改的字段:C 端那份 + 手机号、姓名、个人简介和三个开关。
     *
     * 后台同样**不能**从这里改资产(money/integral/price 及 total_*)、等级、上级、状态 ——
     * 这条路就是一句 UPDATE 整值覆盖:不写流水、会冲掉同一时刻的入账,改等级/上级也不发各自的事件。
     * 它们各有专门入口:余额/积分/佣金走 ChargeForm(带流水),等级走 UserLevelChangeForm,
     * 上级走 ParentChangeForm,状态走封禁/解封。
     * 2026-09-23 前这里是 false 直接放行任意字段,实测后台接口能把余额从 0 改成 88888、一条流水都没有。
     * 抽屉新增可编辑字段时加到这里,并先确认不属于上面几类。
     */
    const ADMIN_EDITABLE_ATTRIBUTES = [
        'nickname',
        'avatar_url',
        'gender',
        'birthday',
        'mobile_area',
        'email',
        'province_id',
        'city_id',
        'district_id',
        'mobile',
        'name',
        'detail',
        'is_inviter',
        'order_always',
        'disable_auto_upgrade',
    ];

    /**
     * 「完善资料」字段(生日/性别)采集后,是否允许用户【自行修改】(per-mall 商城设置)。
     * 默认值保持各自历史行为:生日填写即锁定(0)、性别可随时改(1)。
     * 锁定时 C 端弹窗对已填字段直接隐藏,后端 setAttribute 同步拦截;后台/客服改不受此限。
     *
     * @param string $key    'birthday' | 'gender'
     * @param int    $mallId 用户所属商城
     */
    public static function profileFieldEditable(string $key, int $mallId): bool
    {
        static $defaults = ['birthday' => '0', 'gender' => '1'];
        if (!isset($defaults[$key])) {
            return true; // 非受控字段,不限制
        }
        $setting = OptionHelper::get('mall_setting', $mallId, []);
        $val = (is_array($setting) && isset($setting[$key . '_editable']))
            ? $setting[$key . '_editable']
            : $defaults[$key];
        return (string)$val === '1';
    }

    /**
     * @param bool $restricted 按哪份白名单限制字段。
     *   **默认 true(安全优先)**:C 端自助入口(/api/user/set-user-info),只能改 USER_EDITABLE_ATTRIBUTES。
     *   后台传 false:改用 ADMIN_EDITABLE_ATTRIBUTES(多了手机号等资料字段),**不是**不限制 ——
     *   两份都不含资产、等级、上级。
     */
    public static function setAttribute($user_id, $key, $value, $restricted = true)
    {
        // 白名单校验:非允许字段一律拒绝(防越权改资产/等级/关系链),C 端和后台各一份
        $allowed = $restricted ? self::USER_EDITABLE_ATTRIBUTES : self::ADMIN_EDITABLE_ATTRIBUTES;
        if (!in_array($key, $allowed, true)) {
            $side = $restricted ? 'C端' : '后台';
            \Yii::warning("[UserHelper::setAttribute] 拒绝非白名单字段修改({$side}): user={$user_id} key={$key}", 'security');
            return false;
        }
        $user = User::findOne($user_id);
        if ($user) {
            try {
                // 生日/性别一经填写后能否由用户自行修改,由商城设置控制(per-mall:birthday_editable /
                // gender_editable,0=锁定仅后台改)。后台/客服($restricted=false)始终不受限。
                if ($restricted && in_array($key, ['birthday', 'gender'], true) && !empty($user->$key)) {
                    if (!self::profileFieldEditable($key, (int)$user->mall_id)) {
                        \Yii::warning("[UserHelper::setAttribute] {$key} 已锁定(商城设置不允许自行修改),拒绝前端修改: user={$user_id}", 'security');
                        return false;
                    }
                }
                $user->setAttribute($key, $value);
                // 这里的逻辑保留：修改头像或昵称时标记 is_avatar
                if (in_array($key, ['avatar_url', 'nickname'])) {
                    $user->is_avatar = 1;
                }

                // save() 会触发我们在 User 模型中写好的 afterSave 钩子
                // 如果修改的是 nickname/avatar 等身份字段，Redis 会自动刷新
                if (!$user->save()) {
                    return false;
                }
                return true;
            } catch (Exception $exception) {
                return false;
            }
        }
        return false;
    }

    /**
     * 获取用户地理位置
     * 优先取 Header，其次取缓存，最后取默认值
     */
    public static function getLocation($user_id = 0)
    {
        $headers = Yii::$app->request->headers;

        // 1. 优先取 Header 里的经纬度 (前端传过来的实时定位)
        if (isset($headers['lat']) && isset($headers['lon']) && $headers['lat'] && $headers['lon']) {
            return ['lon' => $headers['lon'], 'lat' => $headers['lat']];
        }

        $user_id = $user_id ? $user_id : self::getMyId();

        // 2. 取缓存 (注意：这里的 Key 建议后续也可以统一常量化)
        $location = CacheHelper::get('USER_LOCATION_' . $user_id);

        // 3. 默认北京天安门
        return $location ? $location : ['lon' => 116.397128, 'lat' => 39.916527];
    }

    /**
     * [核心修正] 验证支付密码
     * 必须查库，因为 Redis 里的 Identity 白名单不包含 pay_pwd，
     * 直接用 Yii::$app->user->identity->pay_pwd 会导致验证失败。
     */
    /**
     * 判断当前用户是否已经设置了支付密码
     * 与 authPayPwd 区分:这里不做密码对比,只回答"有没有设过"
     */
    public static function hasPayPwd(): bool
    {
        $userId = self::getMyId();
        if (!$userId) {
            return false;
        }
        $payPwdHash = User::find()
            ->select(['pay_pwd'])
            ->where(['id' => $userId])
            ->scalar();
        return !empty($payPwdHash);
    }

    public static function authPayPwd($pwdStr)
    {
        $userId = self::getMyId();
        if (!$userId) {
            return false;
        }

        // 1. 只查 pay_pwd 一个字段，性能极高 (scalar 返回字符串)
        // 即使是 User::findOne 也是极快的，但 scalar 更省内存
        $payPwdHash = User::find()
            ->select(['pay_pwd'])
            ->where(['id' => $userId])
            ->scalar();

        // 2. 如果库里没设置密码，直接失败
        if (empty($payPwdHash)) {
            return false;
        }

        // 3. 进行哈希验证
        if (Yii::$app->security->validatePassword($pwdStr, $payPwdHash)) {
            return true;
        }

        return false;
    }

    public static function getDefaultAvatar()
    {
        return ServerHelper::getHost() . '/statics/avatar.png';
    }

    /** 已注销账号在 C 端的占位昵称 */
    const DELETED_NICKNAME = '账号已注销';

    /**
     * C 端展示用昵称:已注销账号一律显示占位文案,不暴露原昵称。
     *
     * 注销是「墓碑节点」——关系链原样保留(见 UserBanForm::del,不动 parent_id/UserPath),
     * 但个人信息不该继续对外展示。手机号在注销时已替换为 DEL_<id>,昵称/头像此前仍在漏。
     * 后台(UserInfoForm / UserListForm)不走这里:运营需要认出是谁。
     *
     * @param array|User|null $user 至少含 nickname、is_delete、status
     */
    public static function displayNickname($user): string
    {
        if (!$user) {
            return '';
        }
        $isDelete = (int)(is_array($user) ? ($user['is_delete'] ?? 0) : $user->is_delete);
        $status   = (int)(is_array($user) ? ($user['status'] ?? 0) : $user->status);
        if ($isDelete === 1 || $status === User::STATUS_DELETE) {
            return self::DELETED_NICKNAME;
        }
        return (string)(is_array($user) ? ($user['nickname'] ?? '') : $user->nickname);
    }

    /** C 端展示用头像:已注销账号回落默认头像,不暴露原头像 */
    public static function displayAvatar($user): string
    {
        if (!$user) {
            return self::getDefaultAvatar();
        }
        $isDelete = (int)(is_array($user) ? ($user['is_delete'] ?? 0) : $user->is_delete);
        $status   = (int)(is_array($user) ? ($user['status'] ?? 0) : $user->status);
        if ($isDelete === 1 || $status === User::STATUS_DELETE) {
            return self::getDefaultAvatar();
        }
        $avatar = (string)(is_array($user) ? ($user['avatar_url'] ?? '') : $user->avatar_url);
        return $avatar ?: self::getDefaultAvatar();
    }
}