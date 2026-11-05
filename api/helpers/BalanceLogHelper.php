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

use app\models\BalanceLog;
use app\helpers\CacheHelper;
use app\models\User;
use yii\base\Exception;

/**
 * 用户余额(User.money)的**唯一**写入口。
 *
 * 全仓扫过:除了这里,没有第二处代码写 user.money —— 直接 `$user->money = N` 是零流水的、
 * 对不上账,任何新增的加减钱都必须走这里。
 *
 * ══════════ 职责边界 ══════════
 * 本类只保证一件事:**钱和流水一致**(原子扣减 + 同事务落流水 + 记下操作后余额)。
 * 「这个用户该不该被操作」是业务判断,归调用方 —— 所以这里**不按 is_delete 过滤**:
 * 已注销用户照样能加钱(退款回退)和扣钱(欠款追回),否则那些钱就永远悬着。
 * 早先 sub 的强制扣款分支单独带了 is_delete=0,导致「用户提了货又注销 → 退款追不回 = 白送」,
 * 而且报错还是「用户余额不足」(余额明明有),排查的人会一路查错方向。已统一去掉。
 *
 * ⚠️ 调用方要自己判断能不能对该用户操作,以及 user_id 是不是本商城的
 *    —— user_id 是全局主键,本类不做租户校验。
 */
class BalanceLogHelper
{
    /**
     * 增加用户余额(原子更新 + 同事务落流水 + 清缓存)。
     *
     * ⚠️ 同时累加 total_money。它虽然叫「总余额」,实际语义是**累计收入**
     *    —— sub 并不减它。字段注释有历史遗留,别照字面理解。
     * @param int $user_id
     * @param float|string $money
     * @param string|null $content
     * @param int $from_user_id
     * @param int $to_user_id
     * @param string|null $title
     * @return bool
     * @throws \Throwable
     */
    public static function add($user_id, $money, $content = null, $from_user_id = 0, $to_user_id = 0, $title = null)
    {
        if (!is_numeric($money) || $money <= 0) {
            throw new Exception('金额必须是大于0的数字！');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 1. 使用原子更新 (同时增加 money 和 total_money)
            // SQL: UPDATE user SET money = money + :val, total_money = total_money + :val WHERE id = :id
            // 不按 is_delete 过滤 —— 见类注释:能不能操作这个用户由调用方判断,
            // 已注销用户也可能要收退款回退的钱
            $updateCount = User::updateAllCounters([
                'money' => $money,
                'total_money' => $money
            ], ['id' => $user_id]);

            if ($updateCount <= 0) {
                throw new Exception('用户不存在或更新失败！');
            }

            // 2. 重新查询用户最新状态 (用于准确记录日志中的 current)
            $user = User::find()
                ->select(['id', 'mall_id', 'money'])
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 3. 记录日志
            $log = new BalanceLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->money = $money;
            $log->type = BalanceLog::TYPE_ADD; // 收入
            $log->content = $content;
            $log->current = $user['money']; // 记录的是数据库运算后的最新值
            $log->from_user_id = (int)$from_user_id;
            $log->to_user_id = (int)$to_user_id;
            $log->date_at = date('Y-m-d');
            $log->title = $title ?: '收入';

            if (!$log->save()) {
                throw new Exception('记录余额日志失败');
            }

            $transaction->commit();

            // 4. 清理用户缓存 (务必在 commit 之后)
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                // 缓存清理失败不应该回滚资金，记录日志即可
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('增加用户余额失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * [最终优化版] 安全地减少用户余额 (原子更新 + 余额检查 + 清理缓存)
     * @param int $user_id
     * @param float|string $money
     * @param string|null $content
     * @param int $from_user_id
     * @param int $to_user_id
     * @param string|null $title
     * @return bool
     * @throws \Throwable
     */
    /**
     * @param bool $ignore_balance 是否忽略余额不足（默认 false；退款追回等场景传 true，允许扣成负数）
     */
    public static function sub($user_id, $money, $content = null, $from_user_id = 0, $to_user_id = 0, $title = null, $ignore_balance = false)
    {
        if (!is_numeric($money) || $money <= 0) {
            throw new Exception('金额必须是大于0的数字！');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 1. 原子更新并检查余额
            if ($ignore_balance) {
                // 强制扣款:只按 ID 匹配,可能扣成负数(退款追回场景)。
                // ⚠️ 这里原本还带着 is_delete = 0,结果是「用户提了货又注销 → 退款追不回 = 白送」,
                //    而且报错还是「用户余额不足」(余额明明有),排查会一路查错方向。
                //    追回本来就是最该对已注销用户生效的场景,已去掉该条件。
                $updateCount = User::updateAllCounters(
                    ['money' => -$money],
                    ['id' => $user_id]
                );
            } else {
                // 正常消费：SQL WHERE money >= :val 保证余额充足
                $updateCount = User::updateAllCounters(
                    ['money' => -$money],
                    ['and', ['id' => $user_id], ['>=', 'money', $money]]
                );
            }

            if ($updateCount <= 0) {
                // 把当前余额带进异常信息。只报一句「余额不足」时,如果真实原因是
                // where 条件里别的项没匹配上,排查的人会盯着余额查半天
                $cur = User::find()->select('money')->where(['id' => $user_id])->scalar();
                if ($cur === false || $cur === null) {
                    throw new Exception('用户不存在！');
                }
                throw new Exception("用户余额不足！当前 {$cur},需扣 {$money}");
            }

            // 2. 重新查询用户最新状态
            $user = User::find()
                ->select(['id', 'mall_id', 'money'])
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 3. 记录日志
            $log = new BalanceLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->money = $money;
            // TYPE_SUB = 0,与 BalanceLogExportJob 的 `type == 1 ? 收入 : 支出` 口径一致(已核对)
            $log->type = BalanceLog::TYPE_SUB;
            $log->content = $content;
            $log->current = $user['money'];
            $log->from_user_id = (int)$from_user_id;
            $log->to_user_id = (int)$to_user_id;
            $log->date_at = date('Y-m-d');
            $log->title = $title ?: '支出';

            if (!$log->save()) {
                throw new Exception('记录余额日志失败');
            }

            $transaction->commit();

            // 4. 清理用户缓存
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('减少用户余额失败: ' . $e->getMessage());
            throw $e;
        }
    }
}