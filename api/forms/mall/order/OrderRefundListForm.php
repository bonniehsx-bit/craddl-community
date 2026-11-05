<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-18
 * Time: 9:51
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
use app\models\OrderRefund;
use app\services\RefundAttentionService;
use app\models\User;
use app\models\Goods;

// 引入 Goods 模型
use app\models\Order;
use app\models\OrderDetail;

// 引入 Order 模型
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class OrderRefundListForm extends BaseModel
{
    public $page;
    public $limit;
    public $status;
    public $start_at;
    public $end_at;
    public $keyword;
    /** 精确取某一张售后 / 某一单的全部售后(售后处理弹窗用) */
    public $id;
    public $order_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'id', 'order_id'], 'integer'],
            [['limit'], 'default', 'value' => 10],
            [['start_at', 'end_at', 'keyword'], 'string']
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = OrderRefund::find()->alias('r')
            ->where(['r.mall_id' => \Yii::$app->mall->id, 'r.is_delete' => 0])
            ->leftJoin(['u' => User::tableName()], 'r.user_id = u.id')      // 关联 User 表
            ->leftJoin(['g' => Goods::tableName()], 'r.goods_id = g.id')    // 关联 Goods 表
            ->leftJoin(['o' => Order::tableName()], 'r.order_id = o.id');   // 关联 Order 表
        // 商品快照在订单明细上(迁移 m261014_120000 之后才有):名称 / 图片优先用下单时的,老数据回退商品表
        $snap = OrderDetail::hasGoodsSnapshot();
        if ($snap) {
            $query->leftJoin(['od' => OrderDetail::tableName()], 'od.id = r.order_detail_id');
        }

        if ($this->id) {
            $query->andWhere(['r.id' => (int)$this->id]);
        }
        if ($this->order_id) {
            $query->andWhere(['r.order_id' => (int)$this->order_id]);
        }

        // 状态筛选
        if ($this->status !== null && $this->status != -1) {
            $query->andWhere(['r.status' => $this->status]);
        }

        // 申请时间筛选
        if ($this->start_at && $this->end_at) {
            $query->andWhere(['between', 'r.created_at', strtotime($this->start_at), strtotime($this->end_at) + 86399]);
        }

        // 关键词筛选
        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'r.order_no', $this->keyword],       // 售后单号
                ['like', 'u.nickname', $this->keyword],       // 用户昵称
                ['like', 'u.mobile', $this->keyword],         // 用户手机号 (新增搜索)
                ['like', 'u.name', $this->keyword],           // 用户真实姓名 (新增搜索)
                ['like', 'g.name', $this->keyword],           // 商品名称(现在的)
                $snap ? ['like', 'od.goods_name', $this->keyword] : ['like', 'g.name', $this->keyword], // 商品名称(下单时的快照)
                ['like', 'o.order_no', $this->keyword]        // 原始订单号
            ]);
        }

        // --- 核心优化: 明确 select 字段，包括用户详细信息 ---
        $query->select([
            'r.*', // 选择 OrderRefund 表的所有字段
            'u.nickname',
            'u.avatar_url',
            'u.mobile',
            'u.name as real_name', // 增加 u.mobile 和 u.name
            $snap ? new \yii\db\Expression("COALESCE(NULLIF(od.goods_pic, ''), g.cover_pic) AS cover_pic") : 'g.cover_pic',
            $snap ? new \yii\db\Expression("COALESCE(NULLIF(od.goods_name, ''), g.name) AS goods_name") : 'g.name as goods_name', // 商品信息
            'o.order_no as o_order_no',            // 原始订单号
        ]);

        $list = $query->with([
            'orderDetail' => function ($q) {
                $q->select('id, attr, num');
            }
        ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('r.id DESC')
            ->asArray()
            ->all();

        // 退款进度:售后单确认打款后退款由定时任务执行,失败了售后单会一直停在「退款中」,这里带上最新一笔退款的进度
        $tasks = RefundAttentionService::latestForAfterSales(array_column($list, 'id'));

        foreach ($list as &$item) {
            $task = $tasks[(int)$item['id']] ?? null;
            // out_refund_no:要人管的,前端「去处理」按它在「退款失败处理」里定位到这一笔
            $item['refund_task'] = $task ? RefundAttentionService::brief($task, false) + ['out_refund_no' => $task->out_refund_no] : null;

            // --- 移除昵称脱敏，显示原始昵称给管理员 ---

            $item['created_at_text'] = date('Y-m-d H:i:s', $item['created_at']);

            $orderDetail = $item['orderDetail'] ?? null;
            if ($orderDetail) {
                try {
                    $item['attr'] = isset($orderDetail['attr']) && !empty($orderDetail['attr']) ? Json::decode($orderDetail['attr']) : [];
                } catch (\Exception $e) {
                    \Yii::error("Failed to decode OrderDetail attr for order_detail_id: {$orderDetail['id']}. Error: " . $e->getMessage());
                    $item['attr'] = [];
                }
                $item['num'] = $orderDetail['num'] ?? 0;
            } else {
                $item['attr'] = [];
                $item['num'] = 0;
            }

            try {
                $item['pic_list'] = !empty($item['pic_list']) ? Json::decode($item['pic_list']) : [];
            } catch (\Exception $e) {
                \Yii::error("Failed to decode OrderRefund pic_list for refund_id: {$item['id']}. Error: " . $e->getMessage());
                $item['pic_list'] = [];
            }

            unset($item['orderDetail']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
