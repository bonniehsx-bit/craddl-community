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

use app\models\OrderDetail;
use app\models\PriceLog;
use app\models\User;
use yii\base\Exception;

/**
 * 用户奖励/佣金账户(User.price)的**唯一**写入口。
 *
 * 全仓扫过:除了这里,没有第二处代码写 user.price —— 直接 `$user->price = N` 是零流水的、
 * 对不上账,任何新增的加减都必须走这里。分润统一走 CommissionService,由它再落到本类。
 *
 * ══════════ 职责边界 ══════════
 * 本类只保证一件事:**钱和流水一致**(原子增减 + 同事务落流水 + 记下操作后余额)。
 * 「这个用户该不该被操作」是业务判断,归调用方 —— 所以这里**不按 is_delete 过滤**。
 * 早先三处都带着 is_delete = 0,实测后果是已注销用户**入账、正常扣款、强制扣款全被拒**:
 *   · 佣金追回(CommissionService::revert / plaza 提成扣回)走的正是强制扣款
 *     —— 「拿了佣金再注销」就永远追不回来 = 白送;
 *   · 分润入账也进不去,那笔 CommissionLog 会永久卡在 PENDING,重试多少次都一样;
 *   · 报错还是「用户不存在或扣款失败」,人明明在,排查会一路查错方向。
 * 与 BalanceLogHelper 保持同一口径。
 *
 * ⚠️ 调用方要自己判断能不能对该用户操作,以及 user_id 是不是本商城的
 *    —— user_id 是全局主键,本类不做租户校验。
 */
class PriceLogHelper
{
    /**
     * [最终优化版] 安全地增加用户奖励 (原子更新，防覆盖)
     *
     * @param int $user_id
     * @param float|string $price
     * @param string $remarks
     * @param int $order_detail_id
     * @param string|null $title
     * @return int 日志ID
     * @throws \Throwable
     */
    public static function add($user_id, $price, $remarks, $order_detail_id = 0, $title = null)
    {
        // 1. 基础校验
        if (!is_numeric($price) || $price <= 0) {
            throw new Exception('奖励必须是大于0的数字！');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 2.【核心修改】使用 SQL 原子更新，防止并发覆盖
            // SQL 逻辑: UPDATE user SET total_price = total_price + :price, price = price + :price WHERE id = :id
            // 不按 is_delete 过滤 —— 见类注释:能不能操作这个用户由调用方判断,
            // 已注销用户的分润该入账还是要入账,否则那笔 CommissionLog 永久卡 PENDING
            $updateCount = User::updateAllCounters([
                'total_price' => $price,
                'price' => $price
            ], ['id' => $user_id]);

            if ($updateCount <= 0) {
                throw new Exception('用户不存在或更新失败');
            }

            // 3. 查询最新状态 (仅用于记录日志，不用于计算)
            // 务必重新查询，确保日志里记录的是 Update 之后的金额
            $user = User::find()
                ->select(['id', 'mall_id', 'price']) // 只查需要的字段
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 4. 写入变动日志
            $log = new PriceLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->price = $price;
            $log->type = PriceLog::TYPE_ADD; // 1=收入
            $log->remarks = $remarks;
            $log->date_at = date('Y-m-d');
            $log->current = $user['price']; // 这里的 price 已经是数据库计算后的最新值
            $log->order_detail_id = $order_detail_id;
            $log->title = $title ?: '收入';

            // 补充订单详情信息
            if ($order_detail_id) {
                $orderDetail = OrderDetail::findOne($order_detail_id);
                if ($orderDetail) {
                    $log->buy_user_id = $orderDetail->user_id;
                    $log->order_no = $orderDetail->order_no;
                }
            }

            if (!$log->save()) {
                \Yii::warning($log->getErrors());
                throw new Exception('记录奖励日志失败');
            }

            $transaction->commit();

            // 清缓存必须在 commit 之后,且失败不能往外抛:
            // 此时钱已经落库,而 rollBack() 对已提交事务是空操作 —— 抛出去调用方只看到失败,
            // 一重试就重复入账。(实测:把 redis 指到死端口,price 已 +7.77 却收到异常)
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            return $log->id;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('增加用户奖励失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * [最终优化版] 安全地减少用户奖励 (原子更新 + 条件检查)
     *
     * @param int $user_id
     * @param float|string $price 正数
     * @param string $remarks
     * @param int $order_detail_id
     * @param string|null $title
     * @param bool $ignore_balance 是否忽略余额不足（默认为false，退款追回时设为true）
     * @return bool
     * @throws \Throwable
     */
    public static function sub($user_id, $price, $remarks, $order_detail_id = 0, $title = null, $ignore_balance = false)
    {
        if (!is_numeric($price) || $price <= 0) {
            throw new Exception('扣除金额必须是大于0的数字！');
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            // 1. 构造更新条件
            // 默认只减少可用余额 price，不减少累计收益 total_price (除非业务特殊要求)
            $updateFields = ['price' => -$price]; // 负数即为减

            // 同 add:不按 is_delete 过滤。强制扣款(佣金追回)本来就最该对已注销用户生效
            $where = ['id' => $user_id];

            if ($ignore_balance) {
                // 强制扣款：只根据 ID 扣，可能扣成负数
                $updateCount = User::updateAllCounters($updateFields, $where);
            } else {
                // 正常消费：【核心修改】在 SQL WHERE 条件中直接判断余额
                // SQL: UPDATE user SET price = price - 10 WHERE id = 1 AND price >= 10
                $condition = ['and', $where, ['>=', 'price', $price]];
                $updateCount = User::updateAllCounters($updateFields, $condition);
            }

            // 受影响行数为 0:用户不存在,或(正常扣款时)余额不足。
            // 把当前奖励带进异常信息 —— 只报一句「不足」时,如果真实原因是别的条件没匹配上,
            // 排查的人会盯着余额查半天
            if ($updateCount <= 0) {
                $cur = User::find()->select('price')->where(['id' => $user_id])->scalar();
                if ($cur === false || $cur === null) {
                    throw new Exception('用户不存在！');
                }
                throw new Exception("用户可用奖励不足！当前 {$cur},需扣 {$price}");
            }

            // 2. 查询最新状态 (用于日志)
            $user = User::find()
                ->select(['id', 'mall_id', 'price'])
                ->where(['id' => $user_id])
                ->asArray()
                ->one();

            if (!$user) {
                throw new Exception('获取用户信息失败');
            }

            // 3. 写入变动日志
            $log = new PriceLog();
            $log->mall_id = $user['mall_id'];
            $log->user_id = $user_id;
            $log->price = $price; // 记录正数
            $log->type = PriceLog::TYPE_SUB;
            $log->remarks = $remarks;
            $log->date_at = date('Y-m-d');
            $log->current = $user['price']; // 记录扣除后的余额
            $log->title = $title ?: '支出';
            $log->order_detail_id = $order_detail_id;

            // 补充订单详情信息
            if ($order_detail_id) {
                $orderDetail = OrderDetail::findOne($order_detail_id);
                if ($orderDetail) {
                    $log->buy_user_id = $orderDetail->user_id;
                    $log->order_no = $orderDetail->order_no;
                }
            }
            if (!$log->save()) {
                \Yii::warning($log->getErrors());
                throw new Exception('记录奖励日志失败');
            }

            $transaction->commit();

            // 同 add:缓存清理失败不回滚资金、也不外抛
            try {
                CacheHelper::remove('user_data:' . $user_id);
            } catch (\Throwable $e) {
                \Yii::warning('清理用户缓存失败: ' . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            \Yii::error('减少用户奖励失败: ' . $e->getMessage());
            throw $e;
        }
    }
}