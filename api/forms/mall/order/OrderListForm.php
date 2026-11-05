<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-02
 * Time: 19:41
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

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use yii\helpers\Json;

class OrderListForm extends BaseModel
{
    // === 保持原有字段定义 ===
    public $page;
    public $limit;
    public $status;
    public $keyword_type; // 虽然查询逻辑去掉了，但字段保留以免报错
    public $keyword;      // 虽然查询逻辑去掉了，但字段保留以免报错
    public $search_date;
    public $id;
    public $payment;
    public $deliver_type;
    public $mch_id;
    public $user_id;
    public $parent_id;
    public $referrer_id; // 按推荐人筛:订单上的推荐人快照(下单时的直属上级)
    public $type;
    public $plugin_type; // 按「插件订单类型」筛选(注册表 key → order.plugin 集合)
    public $order_no;

    public $mobile;
    public $buyer_mobile;
    public $nickname; // 买家昵称,模糊匹配
    public $search_date_type; // 时间搜索类型: 'created_at' 下单 / 'pay_at' 支付 / 'finish_at' 完成 / 'settled_at' 结算


    public function rules()
    {
        return [
            [['keyword_type', 'keyword', 'payment', 'deliver_type', 'type', 'plugin_type', 'order_no', 'mobile', 'buyer_mobile', 'nickname'], 'string'],
            [['keyword'], 'trim'],
            [['page', 'limit', 'user_id', 'parent_id', 'referrer_id', 'id'], 'integer'],
            [['status', 'mch_id'], 'safe'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 10],
            [['status'], 'default', 'value' => -1],
            [['search_date'], 'safe'],
            [['search_date_type'], 'default', 'value' => 'created_at'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 初始化查询
        $query = Order::find()->alias('o')
            ->where(['o.is_delete' => 0, 'o.mall_id' => \Yii::$app->mall->id]);
        $query->with([
            'orderDetails.goods' => function ($q) {
                $q->select(['id', 'name', 'cover_pic']);
            },
            'commonPayOrder',
            'user' => function ($q) {
                $q->select(['id', 'nickname', 'avatar_url', 'mobile']);
            },
            'mch' => function ($q) {
                $q->select(['id', 'mch_name']);
            }
        ]);
        // 筛选条件和导出共用一份,加筛选去 OrderFilter 里加
        OrderFilter::apply($query, $this->attributes);

        // 9. 聚合统计 (克隆查询对象，避免影响后续分页)
        // 成交金额只算已付款、没关闭的单(待发货/待收货/已完成):待付款的还没收到钱;
        // 已关闭的要么从没付过、要么已全额退款。实付总额、各项优惠仍按当前筛选出的全部订单统计。
        //
        // ⛔ 这里不再分「平台收入 / 商家收入」,也不读 o.platform_income / o.mch_income:
        //   那两列是按明细货款、平台抽佣、平台补贴层层算出来的净额,自营单还要看插件把多少钱
        //   结给了门店/技师 —— 插件装没装、卸没卸都会让同一批老单算出不同的数(bonnie 上卡券插件
        //   没装,7 张老单就整笔记成了平台的)。顶部统计只是给个量级,不承担分账口径,
        //   直接按订单表的实付额相加,永远对得上。谁该拿多少钱以结算、分润的流水为准。
        $incomeScope = 'o.status IN (' . implode(',', [Order::STATUS_TO_BE_SHIPPED, Order::STATUS_SHIPPED, Order::STATUS_COMPLETED]) . ')';
        $statsData = (clone $query)->select([
            'total_deal_amount' => "COALESCE(SUM(CASE WHEN {$incomeScope} THEN o.pay_price ELSE 0 END), 0)",
            'total_pay_price' => 'COALESCE(SUM(o.pay_price), 0)',
            'total_goods_price' => 'COALESCE(SUM(o.goods_total_price), 0)',
            'total_express_price' => 'COALESCE(SUM(o.express_price), 0)',
            'total_coupon_cut' => 'COALESCE(SUM(o.coupon_cut_price), 0)',
            'total_member_discount' => 'COALESCE(SUM(o.member_discount_price), 0)',
            'total_integral_cut' => 'COALESCE(SUM(o.integral_cut_price), 0)',
            'total_platform_subsidy_random_minus' => 'COALESCE(SUM(o.platform_subsidy_random_minus), 0)',
            'total_pickup_discount_price' => 'COALESCE(SUM(o.pickup_discount_price), 0)',
            'total_first_order_discount_price' => 'COALESCE(SUM(o.first_order_discount_price), 0)',
        ])->asArray()->one();

        // 格式化统计数据
        $stats = [];
        if ($statsData) {
            foreach ($statsData as $k => $v) {
                $stats[$k] = number_format((float)$v, 2, '.', '');
            }
        }

        // 10. 分页列表查询
        $pagination = null;
        $list = $query
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('o.id DESC')
            ->asArray()
            ->all();

        // 推荐人:读订单上的快照 referrer_id(下单时的直属上级),后台改上级不影响历史订单。
        // 为 0 就是没有推荐人,不回退去查买家现在的上级,口径只认快照。昵称一页一次查完,显示最新的
        $parentIds = array_values(array_unique(array_filter(array_map(function ($row) {
            return (int)($row['referrer_id'] ?? 0);
        }, $list))));
        $referrers = $parentIds
            ? User::find()->select(['id', 'nickname'])->where(['id' => $parentIds])->indexBy('id')->asArray()->all()
            : [];

        // 11. 数据格式化处理
        foreach ($list as &$item) {
            $parentId = (int)($item['referrer_id'] ?? 0);
            $item['referrer'] = $parentId > 0
                ? ['id' => $parentId, 'nickname' => (string)($referrers[$parentId]['nickname'] ?? '')]
                : null;

            // 时间格式化

            $item['created_time'] = isset($item['created_at']) ? date('Y-m-d H:i:s', $item['created_at']) : '';
            $item['settled_time'] = (isset($item['settled_at']) && $item['settled_at'] > 0)
                ? date('Y-m-d H:i:s', $item['settled_at']) : '';
            $item['finish_time'] = (isset($item['finish_at']) && $item['finish_at'] > 0)
                ? date('Y-m-d H:i:s', $item['finish_at']) : '';

            // 规格 JSON 安全解析
            if (isset($item['orderDetails']) && is_array($item['orderDetails'])) {
                foreach ($item['orderDetails'] as &$detail) {
                    // 显示下单那一刻的商品(快照),老数据回退商品表
                    OrderDetail::applySnapshotToRow($detail);
                    $detail['attr_list'] = [];
                    if (!empty($detail['attr'])) {
                        try {
                            $detail['attr_list'] = is_string($detail['attr'])
                                ? Json::decode($detail['attr'])
                                : $detail['attr'];
                        } catch (\Exception $e) {
                            $detail['attr_list'] = [];
                        }
                    }
                }
                unset($detail);
            }
        }
        unset($item);
        // 12. 格式化支付列表给前端 Select 筛选使用
        $frontPaymentList = [];
        foreach (Order::paymentMap() as $key => $val) {
            $frontPaymentList[] = [
                'label' => $val['label'],
                'value' => $key, // 前端传回 'wechat', 'alipay' 等
            ];
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination,
            'stats' => $stats,
            'payment_list' => $frontPaymentList, // 新增：支付方式列表
            // 结算说明弹窗用。从配置读、不在前端写死 —— 每个商城的自动确认天数与
            // 售后期都可能不同,写死会让说明与实际对不上,而客服正是拿它答复商家的。
            'settle_help' => [
                'confirm_day' => (int) (\app\helpers\OptionHelper::get('mall_setting', (int) \Yii::$app->mall->id)['auto_confirm_day'] ?? 7),
                'settle_day'  => (int) (\app\helpers\OptionHelper::get('mall_setting', (int) \Yii::$app->mall->id)['order_settle_day'] ?? 0),
            ]
        ]);
    }
}
