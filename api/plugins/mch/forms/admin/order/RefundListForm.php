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

namespace app\plugins\mch\forms\admin\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use app\models\OrderRefund;
use app\services\RefundAttentionService;
use app\models\User;
use yii\helpers\Json;

class RefundListForm extends BaseModel
{
    public $page;
    public $limit;
    public $status;
    public $keyword;

    public function rules()
    {
        return [
            [['page', 'limit', 'status'], 'integer'],
            [['limit'], 'default', 'value' => 10],
            [['keyword'], 'string']
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = \Yii::$app->mch;

        $query = OrderRefund::find()->alias('r')
            ->where([
                'r.mall_id' => \Yii::$app->mall->id,
                'r.is_delete' => 0,
                'r.mch_id' => $currentMch->id
            ])
            // 顾客只给头像、昵称:asArray() 连表出来的是用户整行(含登录令牌、密码哈希),不走模型的隐藏字段
            ->with(['user' => fn($q) => $q->select(['id', 'nickname', 'avatar_url']), 'orderDetail', 'goods']);

        // 状态筛选
        if ($this->status !== null && $this->status != -1) {
            // 前端传 15 代表 "待商家处理" (包含：已同意待退货 + 用户已发货)
            if ($this->status == 15) {
                $query->andWhere(['in', 'r.status', [
                    OrderRefund::STATUS_AGREED,       // 1
                    OrderRefund::STATUS_USER_SHIPPED  // 5
                ]]);
            } else {
                $query->andWhere(['r.status' => $this->status]);
            }
        }

        // 关键词筛选
        if ($this->keyword) {
            // 只连表过滤、不预加载:joinWith 默认会预加载,用户整行会覆盖掉上面的只取头像昵称
            $query->joinWith(['order o', 'user u'], false);
            $query->andWhere([
                'or',
                ['like', 'r.order_no', $this->keyword],
                ['like', 'o.order_no', $this->keyword],
                ['like', 'u.nickname', $this->keyword],
                ['like', 'u.mobile', $this->keyword],
            ]);
        }

        $list = $query->select(['r.*'])
            ->orderBy('r.id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->asArray()
            ->all();

        // 退款进度(商户说法):确认打款后退款由平台的定时任务执行,失败了售后单会一直停在「退款中」
        $tasks = RefundAttentionService::latestForAfterSales(array_column($list, 'id'));

        // 数据格式化
        foreach ($list as &$item) {
            $task = $tasks[(int)$item['id']] ?? null;
            $item['refund_task'] = $task ? RefundAttentionService::brief($task, true) : null;
            $item['created_at_text'] = date('Y-m-d H:i', $item['created_at']);
            $item['user_express_at_text'] = $item['user_express_at'] ? date('Y-m-d H:i', $item['user_express_at']) : '';

            $item['pic_list'] = !empty($item['pic_list']) ? Json::decode($item['pic_list']) : [];

            $item['attr_text'] = '';
            if (isset($item['orderDetail']['attr'])) {
                try {
                    $attrData = Json::decode($item['orderDetail']['attr']);
                    $attrList = $attrData['attr_list'] ?? [];
                    $item['attr_text'] = implode(' ', array_column($attrList, 'attr_name'));
                } catch (\Exception $e) {}
            }

            // 下单时的商品(明细上的快照),老数据回退商品表
            $item['goods_cover'] = (string)($item['orderDetail']['goods_pic'] ?? '') ?: ($item['goods']['cover_pic'] ?? '');
            $item['goods_name'] = (string)($item['orderDetail']['goods_name'] ?? '') ?: ($item['goods']['name'] ?? '');
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => $pagination
        ]);
    }
}