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

namespace app\plugins\marketing\services;

use app\models\Order;
use app\models\User;
use app\services\EffectiveSpend;
use yii\db\Query;

/**
 * 人群定向引擎
 *
 * 复用平台既有身份/交易数据,不新建标签体系(开发规范 §8)。
 * 人群规则(audience_json)支持维度:
 *   - level_ids       array 会员等级(空=不限)
 *   - consume_min     float 累计实付下限(0=不限;实付减掉已退回的钱)
 *   - consume_max     float 累计实付上限(0=不限)
 *   - only_first      int   1=仅从未支付下单的用户(首单/拉新人群)
 *   - reg_start       int   注册时间戳下限(0=不限)
 *   - reg_end         int   注册时间戳上限(0=不限)
 *   - last_order_days int   末次支付下单距今>=N天(沉睡人群,0=不限;要求用户至少有过1单)
 *
 * 「支付过」按平台口径认已支付、部分退款两种支付状态(EffectiveSpend::PAID_STATUSES)——
 * 原来只认「已支付」,退过一件的单整张不算:老客被当成新客、累计消费少算、沉睡人群漏人。
 *
 * ⚠ Order.created_at 为 INT 时间戳,一律整型比较(开发规范 INT 陷阱)。
 */
class AudienceService
{
    /** 一张订单的实收:实付减掉已退回的钱(与后台首页「销售额」同一个算法) */
    private const NET_PAID = 'GREATEST(pay_price - IFNULL(refund_price, 0), 0)';

    /**
     * 构建人群 User 查询(未分页,调用方自行取 id / 迭代)。
     * @param int $exceptOrderId 事件触发时正在处理的那张订单,「仅首单人群」不把它算作「付过款的单」
     */
    public static function build(int $mallId, array $rule, int $exceptOrderId = 0): \yii\db\ActiveQuery
    {
        $query = User::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0]);

        // 等级
        $levelIds = array_values(array_filter(array_map('intval', (array)($rule['level_ids'] ?? []))));
        if ($levelIds) {
            $query->andWhere(['level_id' => $levelIds]);
        }

        // 注册时间段
        $regStart = (int)($rule['reg_start'] ?? 0);
        $regEnd = (int)($rule['reg_end'] ?? 0);
        if ($regStart > 0) {
            $query->andWhere(['>=', 'created_at', $regStart]);
        }
        if ($regEnd > 0) {
            $query->andWhere(['<=', 'created_at', $regEnd]);
        }

        // 仅首单人群:从未有过已支付订单。
        // 付款触发时要排除正在处理的这张单:付款事件发出时它已经是「已支付」,不排除的话
        // 「付款时发 + 仅首单人群」的活动会把每个新客都判成老客,一次都发不出去
        if (!empty($rule['only_first'])) {
            $sub = (new Query())->select('o.user_id')->from(['o' => Order::tableName()])
                ->where('o.user_id = ' . User::tableName() . '.id')
                ->andWhere(['o.pay_status' => EffectiveSpend::PAID_STATUSES, 'o.is_delete' => 0])
                ->limit(1);
            if ($exceptOrderId > 0) {
                $sub->andWhere(['<>', 'o.id', $exceptOrderId]);
            }
            $query->andWhere(['not exists', $sub]);
        }

        // 累计消费区间(∑已支付订单实收)
        $consumeMin = (float)($rule['consume_min'] ?? 0);
        $consumeMax = (float)($rule['consume_max'] ?? 0);
        if ($consumeMin > 0 || $consumeMax > 0) {
            $agg = (new Query())->select('user_id')->from(Order::tableName())
                ->where(['pay_status' => EffectiveSpend::PAID_STATUSES, 'is_delete' => 0, 'mall_id' => $mallId])
                ->groupBy('user_id');
            $having = [];
            if ($consumeMin > 0) {
                $having[] = 'SUM(' . self::NET_PAID . ') >= ' . number_format($consumeMin, 2, '.', '');
            }
            if ($consumeMax > 0) {
                $having[] = 'SUM(' . self::NET_PAID . ') <= ' . number_format($consumeMax, 2, '.', '');
            }
            $agg->having(implode(' AND ', $having));
            $query->andWhere(['id' => $agg]);
        }

        // 沉睡人群:有过下单,且最近一单在 N 天前
        $lastDays = (int)($rule['last_order_days'] ?? 0);
        if ($lastDays > 0) {
            $threshold = time() - $lastDays * 86400;
            $agg = (new Query())->select('user_id')->from(Order::tableName())
                ->where(['pay_status' => EffectiveSpend::PAID_STATUSES, 'is_delete' => 0, 'mall_id' => $mallId])
                ->groupBy('user_id')
                ->having("MAX(created_at) <= {$threshold}");
            $query->andWhere(['id' => $agg]);
        }

        return $query;
    }

    /**
     * 单个用户是否命中人群(事件触发时用)。
     * build() 主查询无 join(过滤均走子查询),故裸列名 id 不歧义。
     */
    public static function matchUser(int $mallId, int $userId, array $rule, int $exceptOrderId = 0): bool
    {
        return self::build($mallId, $rule, $exceptOrderId)->andWhere(['id' => $userId])->exists();
    }

    /**
     * 取命中人群的用户ID(群发/定时扫描用)。
     * @param int $afterUserId 断点续发游标:只取 id > afterUserId
     * @param int $limit       批量上限
     */
    public static function userIds(int $mallId, array $rule, int $afterUserId = 0, int $limit = 0): array
    {
        $query = self::build($mallId, $rule)->select('id');
        if ($afterUserId > 0) {
            $query->andWhere(['>', 'id', $afterUserId]);
        }
        $query->orderBy(['id' => SORT_ASC]);
        if ($limit > 0) {
            $query->limit($limit);
        }
        return array_map('intval', $query->column());
    }
}
