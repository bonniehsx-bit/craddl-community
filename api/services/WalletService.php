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

namespace app\services;

use app\models\User;
use app\models\UserWallet;
use app\models\UserWalletLog;
use Yii;
use yii\db\Expression;

/**
 * 通用用户钱包服务(核心) —— 可扩展账户底座。
 *
 * 任意插件 getWallets() 声明的钱包(wallet_key)统一在 user_wallet 存取。
 * 核心(如 CommissionService 给货款入账)与提现插件(经 AssetService 桥接)都调本服务。
 * 入账/扣减都用 SQL 原子操作,复刻核心 LogHelper 的金融硬防线:
 *   - 扣减:`UPDATE ... SET balance=balance-:amt WHERE balance>=:amt`,影响行=0 即余额不足抛异常;
 *   - 入账:`INSERT ... ON DUPLICATE KEY UPDATE balance=balance+:amt`,并发入账不丢更新。
 * 金额一律 number_format 成两位小数字符串后入 SQL(纯数字,无注入风险)。
 */
class WalletService
{
    /** 读取用户某钱包余额(字符串,bc 友好) */
    public static function balance(int $userId, string $key): string
    {
        $w = UserWallet::find()
            ->where(['user_id' => $userId, 'wallet_key' => $key, 'is_delete' => 0])
            ->one();
        return $w ? (string)$w->balance : '0';
    }

    /** 入账(原子 upsert) + 流水。金额<=0 忽略。 */
    public static function add(int $userId, string $key, $amount, string $remark = '', string $title = '', string $source = ''): void
    {
        $amt = self::norm($amount);
        if (bccomp($amt, '0', 2) <= 0) {
            return;
        }
        $mallId = self::mallIdOf($userId);
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);

        $tx = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->db->createCommand()->upsert(
                UserWallet::tableName(),
                [
                    'mall_id' => $mallId, 'user_id' => $userId, 'wallet_key' => $key,
                    'balance' => $amt, 'total' => $amt,
                    'is_delete' => 0, 'created_at' => $now, 'updated_at' => $now,
                    'created_time' => $nowStr, 'updated_time' => $nowStr,
                ],
                [
                    'balance'      => new Expression('[[balance]] + ' . $amt),
                    'total'        => new Expression('[[total]] + ' . $amt),
                    'updated_at'   => $now,
                    'updated_time' => $nowStr,
                ]
            )->execute();

            self::log($mallId, $userId, $key, 1, $amt, self::balance($userId, $key), $remark, $title, $source);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * 扣减 + 流水。金额<=0 忽略。
     * @param bool $force true=允许扣成负数(用于分润回滚:钱可能已被提走,冲账不能因余额不足失败)。
     *                    false=原子校验余额,不足抛异常(提现冻结等正常扣减)。
     */
    public static function sub(int $userId, string $key, $amount, string $remark = '', string $title = '', string $source = '', bool $force = false): void
    {
        $amt = self::norm($amount);
        if (bccomp($amt, '0', 2) <= 0) {
            return;
        }
        $mallId = self::mallIdOf($userId);
        $now = time();
        $nowStr = date('Y-m-d H:i:s', $now);

        $tx = Yii::$app->db->beginTransaction();
        try {
            if ($force) {
                // 冲账:可扣成负数,且行不存在时先建 0 再扣(保证冲账留痕)
                Yii::$app->db->createCommand()->upsert(
                    UserWallet::tableName(),
                    [
                        'mall_id' => $mallId, 'user_id' => $userId, 'wallet_key' => $key,
                        'balance' => '0', 'total' => '0',
                        'is_delete' => 0, 'created_at' => $now, 'updated_at' => $now,
                        'created_time' => $nowStr, 'updated_time' => $nowStr,
                    ],
                    ['updated_at' => $now]
                )->execute();
                $affected = Yii::$app->db->createCommand()->update(
                    UserWallet::tableName(),
                    ['balance' => new Expression('[[balance]] - ' . $amt), 'updated_at' => $now, 'updated_time' => $nowStr],
                    ['user_id' => $userId, 'wallet_key' => $key, 'is_delete' => 0]
                )->execute();
            } else {
                $affected = Yii::$app->db->createCommand()->update(
                    UserWallet::tableName(),
                    ['balance' => new Expression('[[balance]] - ' . $amt), 'updated_at' => $now, 'updated_time' => $nowStr],
                    ['and', ['user_id' => $userId, 'wallet_key' => $key, 'is_delete' => 0], ['>=', 'balance', $amt]]
                )->execute();
                if ($affected < 1) {
                    throw new \Exception('钱包余额不足');
                }
            }

            self::log($mallId, $userId, $key, 2, $amt, self::balance($userId, $key), $remark, $title, $source);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    private static function log(int $mallId, int $userId, string $key, int $type, $amount, $balanceAfter, string $remark, string $title, string $source): void
    {
        $l = new UserWalletLog();
        $l->mall_id = $mallId;
        $l->user_id = $userId;
        $l->wallet_key = $key;
        $l->type = $type;
        $l->amount = $amount;
        $l->balance_after = $balanceAfter;
        $l->remark = $remark;
        $l->title = $title;
        $l->source = $source;
        $l->save(false);
    }

    private static function mallIdOf(int $userId): int
    {
        $u = User::findOne($userId);
        if ($u) {
            return (int)$u->mall_id;
        }
        return (int)(Yii::$app->mall->id ?? 0);
    }

    private static function norm($amount): string
    {
        return number_format((float)$amount, 2, '.', '');
    }
}
