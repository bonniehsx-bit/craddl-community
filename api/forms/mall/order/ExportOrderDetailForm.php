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

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods; // 保留用于 LEFT JOIN
use app\models\GoodsCat; // [!! 移除 !!] (不再需要)
use app\models\Order;
use app\models\OrderDetail;
use app\models\User;
use app\plugins\mch\models\Mch;
use yii\helpers\Json;

/**
 * [!! 逻辑重构 !!]
 * 按订单筛选条件 导出 订单明细
 * Class ExportOrderDetailForm
 * @package app\forms\mall\goods
 */
class ExportOrderDetailForm extends BaseModel
{
    // 接收所有来自前端的筛选参数
    public $status;
    public $keyword;
    public $mch_name;
    public $search_date;
    public $cat_ids = []; // [!! 保留接收，但不使用 !!]
    public $is_check;     // [!! 保留接收，但不使用 !!]
    public $source;
    public $is_export;

    public function rules()
    {
        return [
            [['status', 'is_check', 'source'], 'string'],
            [['keyword', 'mch_name'], 'string'],
            [['search_date', 'cat_ids'], 'safe'],
            [['is_export'], 'integer'],
        ];
    }

    /**
     * 主执行方法
     * @return array
     */
    public function export()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // [!! 步骤 1: 直接构建订单明细查询 !!]
            $detailQuery = $this->buildDetailQuery();

            // [!! 步骤 2: 获取所有相关明细数据 !!]
            $list = $detailQuery->asArray()->all();

            if (empty($list)) {
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '没有可导出的数据', ['list' => []]);
            }

            // [!! 步骤 3: 格式化数据 !!]
            $exportList = $this->formatList($list);

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $exportList]);

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * [!! 逻辑重构 !!]
     * 步骤 1: 构建订单明细(OrderDetail)查询
     * @return \yii\db\ActiveQuery
     */
    private function buildDetailQuery()
    {
        // [核心] 直接从 OrderDetail 开始
        $query = OrderDetail::find()->alias('od')
            ->where(['od.is_delete' => 0])
            ->innerJoin(['o' => Order::tableName()], 'o.id = od.order_id')
            ->leftJoin(['u' => User::tableName()], 'u.id = o.user_id')
            ->leftJoin(['g' => Goods::tableName()], 'g.id = od.goods_id') // 保留 Join，用于获取 factory 和 goods_no
            ->leftJoin(['m' => Mch::tableName()], 'm.id = o.mch_id') // [!! 关键修改 !!] 关联 o.mch_id
            ->andWhere(['o.is_delete' => 0, 'o.mall_id' => \Yii::$app->mall->id]);

        // 最佳实践：只导出已付款的订单明细
        $query->andWhere(['>=', 'o.pay_status', Order::PAY_STATUS_PAID]);

        // --- 开始应用所有筛选条件 ---

        // 订单状态
        if (isset($this->status) && $this->status != -1) {
            $query->andWhere(['o.status' => $this->status]);
        }

        // 订单创建日期
        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $begin_at = strtotime($this->search_date[0] . ' 00:00:00');
            $end_at = strtotime($this->search_date[1] . ' 23:59:59');
            $query->andWhere(['between', 'o.created_at', $begin_at, $end_at]);
        }

        // 订单来源 (平台/商家)
        if (isset($this->source) && $this->source !== '') {
            if ($this->source === '0') {
                $query->andWhere(['o.mch_id' => 0]); // 平台自营订单
            } else if ($this->source === '1') {
                $query->andWhere(['>', 'o.mch_id', 0]); // 商家订单
            }
        }

        // 商家名称
        if ($this->mch_name) {
            // [!! 关键修改 !!] m.mch_name 是通过 o.mch_id 关联的
            $query->andWhere(['like', 'm.mch_name', $this->mch_name]);
        }

        // 关键词
        if ($this->keyword) {
            $query->andWhere(['or',
                ['like', 'o.order_no', $this->keyword],      // 搜订单号
                ['like', 'u.nickname', $this->keyword],      // 搜用户昵称
                ['like', 'o.name', $this->keyword],          // 搜收货人姓名
                ['like', 'g.name', $this->keyword], // 搜商品现在的名称
                OrderDetail::hasGoodsSnapshot() ? ['like', 'od.goods_name', $this->keyword] : ['like', 'g.name', $this->keyword], // 搜下单时的商品名(快照)
            ]);
        }

        // [!! 移除 !!] $cat_ids 和 $is_check 不再用于筛选

        $query->select([
            'od.order_no', 'od.num', 'od.price', 'od.total_price', 'od.attr',
            // 名称 / 货号用下单时的快照(m261014_120000),老数据回退商品表现在的
            OrderDetail::hasGoodsSnapshot() ? new \yii\db\Expression("COALESCE(NULLIF(od.goods_name, ''), g.name) AS goods_name") : 'g.name as goods_name',
            'o.status as order_status', 'o.created_at as order_created_at', 'o.pay_at',
            'o.name as receiver_name', 'o.mobile as receiver_mobile', 'o.address',
            'u.id as user_id', 'u.nickname',
            'g.id as goods_id',
            OrderDetail::hasGoodsSnapshot() ? new \yii\db\Expression("COALESCE(NULLIF(od.goods_no, ''), g.no) AS goods_no") : 'g.no as goods_no',
            'g.factory',
            'm.mch_name' // 获取商家名称
        ]);

        $query->orderBy('o.created_at DESC, od.id ASC');

        return $query;
    }

    /**
     * 步骤 2: 格式化导出列表
     * @param array $list
     * @return array
     */
    private function formatList(array $list)
    {
        $exportList = [];
        $orderModel = new Order(); // 用于获取状态文本

        foreach ($list as $item) {
            // 格式化规格
            $attr_text = '';
            if ($item['attr']) {
                try {
                    $attrs = Json::decode($item['attr']);
                    if (is_array($attrs)) {
                        $attr_parts = [];
                        foreach ($attrs as $attr) {
                            $attr_parts[] = "{$attr['group']}:{$attr['value']}";
                        }
                        $attr_text = implode('; ', $attr_parts);
                    }
                } catch (\Exception $e) {
                    $attr_text = '规格解析失败';
                }
            }

            $exportList[] = [
                'order_no' => $item['order_no'],
                'order_status_text' => $orderModel->getStatusText($item['order_status']),
                'order_created_at' => $item['order_created_at'] ? date('Y-m-d H:i:s', $item['order_created_at']) : '',
                'pay_at' => $item['pay_at'] ? date('Y-m-d H:i:s', $item['pay_at']) : '未付款',
                'user_id' => $item['user_id'],
                'nickname' => $item['nickname'],
                'receiver_name' => $item['receiver_name'],
                'receiver_mobile' => $item['receiver_mobile'],
                'address' => $item['address'],
                'goods_id' => $item['goods_id'],
                'goods_no' => $item['goods_no'],
                'goods_name' => $item['goods_name'], // 下单时的商品名(快照),老订单补齐 / 回退见 select
                'mch_name' => $item['mch_name'] ?? '平台自营',
                'factory' => $item['factory'] ?? '',
                'attr_text' => $attr_text,
                'num' => $item['num'],
                'price' => $item['price'],
                'total_price' => $item['total_price'],
            ];
        }

        return $exportList;
    }
}