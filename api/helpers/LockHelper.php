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

namespace app\helpers;

use Yii;

// 确保 Yii 类被正确引入

class LockHelper
{

    /**
     * 尝试为普通订单支付设置锁
     * @param string|int $order_id 订单ID
     * @return string|false 成功返回锁Token，失败返回 false
     */
    public static function setCommonOrderPay($order_id)
    {
        return self::lock('COMMON_ORDER_PAID_' . $order_id, 5 * 60);
    }

    /**
     * 尝试为用户登录后的操作设置短时锁 (防止短时重复执行)
     * @param string|int $user_id 用户ID
     * @return string|false 成功返回锁Token，失败返回 false
     */
    public static function setUserLogin($user_id)
    {
        // 修复：之前错误写成了 60 秒，现恢复为 5 秒
        return self::lock('USER_LOGIN_' . $user_id, 5);
    }

    /**
     * 尝试为微信订单操作设置锁
     * @param string|int $order_id 订单ID
     * @return string|false 成功返回锁Token，失败返回 false
     */
    public static function setWechatOrder($order_id)
    {
        return self::lock('WECHAT_ORDER_' . $order_id, 30);
    }

    /**
     * 尝试为发送等级操作设置锁
     * @param string|int $order_detail_id 订单详情ID
     * @return string|false 成功返回锁Token，失败返回 false
     */
    public static function setSendLevel($order_detail_id)
    {
        return self::lock('SEND_LEVEL_' . $order_detail_id, 30);
    }

    /**
     * 通用加锁方法 (原子操作)
     * @param string $key 锁的键
     * @param int $sec 过期时间（秒）。必须大于0。
     * @return string|false 成功返回全局唯一的UUID Token，失败返回 false
     */
    public static function lock($key, $sec)
    {
        if (!is_string($key) || empty($key)) {
            Yii::error("LockHelper::lock called with invalid key.", __METHOD__);
            return false;
        }
        if (!is_numeric($sec) || $sec <= 0) {
            Yii::warning("LockHelper::lock called with non-positive expiration ({$sec}) for key: {$key}. Lock not acquired.", __METHOD__);
            return false;
        }

        // 生成高并发下几乎不可能重复的UUID
        $token = uniqid('', true) . bin2hex(random_bytes(8));
        $result = Yii::$app->redis->executeCommand('SET', [$key, $token, 'NX', 'EX', (int)$sec]);
        return ($result === 'OK' || $result === true || $result === 1) ? $token : false;
    }

    /**
     * 通用解锁方法 (原子操作 - 防劫持版)
     * @param string $key 锁的键
     * @param string|null $token 加锁时返回的 Token (传 null 会降级为盲删)
     * @return int 1 表示成功安全释放，0 表示释放失败或锁已被劫持/过期
     */
    public static function unlock($key, $token = null)
    {
        if (!is_string($key) || empty($key)) {
            Yii::error("LockHelper::unlock called with invalid key.", __METHOD__);
            return 0;
        }

        // 兼容旧代码，如果没有传 Token，执行不安全的盲删
        if ($token === null) {
            $deletedCount = Yii::$app->redis->del($key);
            return ($deletedCount > 0) ? 1 : 0;
        }

        // 使用 Lua 脚本确保只有当 key 存放的值等于给定的 token 时，才能够执行删除操作
        $script = <<<LUA
if redis.call("get",KEYS[1]) == ARGV[1] then
    return redis.call("del",KEYS[1])
else
    return 0
end
LUA;
        $result = Yii::$app->redis->executeCommand('EVAL', [$script, 1, $key, $token]);
        return ($result > 0) ? 1 : 0;
    }
}
