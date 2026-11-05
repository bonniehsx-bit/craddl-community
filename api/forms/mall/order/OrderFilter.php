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

namespace app\forms\mall\order;

use app\models\Order;
use app\models\User;
use app\models\UserPath;
use app\services\order\OrderTypeRegistry;
use app\services\UserService;
use yii\db\ActiveQuery;

/**
 * 后台订单列表 / 订单导出共用的筛选条件 —— 加筛选只改这里,两边同时生效。
 *
 * 以前两边各写一份,前后几次都是列表改了、导出漏了:昵称、订单类型、推荐人在导出里都不生效,
 * 列表筛完再点导出,导出的是全部订单。
 *
 * 只管「页面上的筛选条件」。各自特有的留在各自那边:
 *   列表 —— 统计、分页、预加载;
 *   导出 —— 勾选导出的订单号、可导出类型白名单。
 *
 * 查询必须以 alias('o') 起头;买家手机 / 昵称要关联用户表,别名固定 u。
 */
class OrderFilter
{
    /**
     * 时间筛选可选的字段。未知值回退下单时间 —— 漏配一项不会报错、只会静默筛错
     */
    const DATE_FIELDS = [
        'created_at' => 'o.created_at',
        'pay_at'     => 'o.pay_at',   // ⛔ 「支付」必须是 pay_at,别再传 settled_at:没结算的已付款单会一张都筛不出
        'send_at'    => 'o.send_at',
        'finish_at'  => 'o.finish_at',
        'settled_at' => 'o.settled_at',
    ];

    /**
     * @param array $p 表单 attributes(已校验)
     * @param bool $withMch 导出在「勾选导出」提前返回之前已单独加过商家条件,这里就不再加
     */
    public static function apply(ActiveQuery $query, array $p, bool $withMch = true): void
    {
        // 支付方式:前端传的是分组(wechat…),对应一组支付类名
        if (!empty($p['payment'])) {
            $paymentMap = Order::paymentMap();
            if (isset($paymentMap[$p['payment']])) {
                $query->andWhere(['o.payment_class' => $paymentMap[$p['payment']]['payments']]);
            }
        }

        // 买家账号手机:只留数字后子串匹配,支持输任意一段。
        // 注意列表上显示的是「收货电话」(o.mobile),不是这里过滤的账号手机
        $joinedUser = false;
        $buyerMobile = preg_replace('/\D/', '', (string)($p['buyer_mobile'] ?? ''));
        if ($buyerMobile !== '') {
            $query->innerJoin(['u' => User::tableName()], 'u.id = o.user_id');
            $joinedUser = true;
            $query->andWhere(['like', 'u.mobile', $buyerMobile]);
        }
        // 买家昵称:模糊匹配
        $nickname = trim((string)($p['nickname'] ?? ''));
        if ($nickname !== '') {
            if (!$joinedUser) {
                $query->innerJoin(['u' => User::tableName()], 'u.id = o.user_id');
            }
            $query->andWhere(['like', 'u.nickname', $nickname]);
        }

        $query->andFilterWhere(['o.id' => $p['id'] ?? null])
            ->andFilterWhere(['like', 'o.mobile', $p['mobile'] ?? null])       // 收货电话:模糊匹配
            ->andFilterWhere(['o.user_id' => $p['user_id'] ?? null])
            ->andFilterWhere(['o.referrer_id' => $p['referrer_id'] ?? null])   // 推荐人快照
            ->andFilterWhere(['o.deliver_type' => $p['deliver_type'] ?? null])
            ->andFilterWhere(['like', 'o.order_no', $p['order_no'] ?? null])
            ->andFilterWhere(['o.type' => $p['type'] ?? null]);

        // 插件订单类型:授权插件注册表 key → order.plugin 集合。越权 / 无效 key 映射为空 → 不返回数据
        $pluginType = $p['plugin_type'] ?? null;
        if ($pluginType !== null && $pluginType !== '') {
            $plugins = OrderTypeRegistry::pluginsForKeys([$pluginType]);
            $query->andWhere(!empty($plugins) ? ['o.plugin' => $plugins] : '0=1');
        }

        if ($withMch) {
            self::applyMch($query, $p);
        }

        // 状态:-1 / 空 = 全部;refund = 有售后的
        $status = $p['status'] ?? -1;
        if ($status !== '' && $status !== null && $status != -1) {
            if ($status === 'refund') {
                $query->andWhere(['>', 'o.refund_status', Order::REFUND_STATUS_NO_REFUND]);
            } else {
                $query->andWhere(['o.status' => $status]);
            }
        }

        $date = $p['search_date'] ?? null;
        if (is_array($date) && count($date) == 2) {
            $field = self::DATE_FIELDS[$p['search_date_type'] ?? ''] ?? 'o.created_at';
            $query->andWhere(['between', $field, strtotime($date[0] . ' 00:00:00'), strtotime($date[1] . ' 23:59:59')]);
        }

        // 团队上级:该用户整棵下级树(不含自己)的订单
        if (!empty($p['parent_id'])) {
            $subQuery = UserPath::find()
                ->select('user_id')
                ->where(['like', 'path', UserService::subtreeLike($p['parent_id']), false])
                ->andWhere(['!=', 'user_id', $p['parent_id']]);
            $query->andWhere(['in', 'o.user_id', $subQuery]);
        }
    }

    /** 商家:all_mch = 所有商家的单;0 = 平台自营;其它 = 指定商家 */
    public static function applyMch(ActiveQuery $query, array $p): void
    {
        $mchId = $p['mch_id'] ?? null;
        if ($mchId === null || $mchId === '') {
            return;
        }
        if ($mchId === 'all_mch') {
            $query->andWhere(['>', 'o.mch_id', 0]);
        } else {
            $query->andWhere(['o.mch_id' => $mchId]);
        }
    }
}
