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

namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use app\models\OrderDetail;
use Yii;
use yii\db\Expression;

class MchPendingSettlementListForm extends BaseModel
{
    public $page;
    public $limit = 15;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
        ];
    }

    /**
     * 获取待结算订单列表
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在');
        }

        // --- 核心查询逻辑 (与看板统计完全一致) ---
        $query = Order::find()->alias('o')
            ->where([
                'o.mall_id' => $mch->mall_id,
                'o.mch_id' => $mch->id,
                'o.is_delete' => 0,
                'o.pay_status' => Order::PAY_STATUS_PAID,
            ])
            ->andWhere(['or',
                ['o.status' => Order::STATUS_TO_BE_SHIPPED],
                ['o.status' => Order::STATUS_SHIPPED],
                ['and',
                    ['o.status' => Order::STATUS_COMPLETED],
                    ['o.settlement_status' => Order::SETTLEMENT_STATUS_PENDING]
                ]
            ]);

        // 使用 JOIN 和 GROUP BY 来计算每个订单的待结算总额
        $query->select([
            'o.id',
            'o.order_no',
            'o.created_time',
            'o.status',
            'pending_amount' => new Expression('SUM(od.mch_income_price)')
        ])
            ->leftJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
            ->groupBy('o.id');

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy(['o.id' => SORT_DESC])
            ->asArray()
            ->all();

        // 附加状态文本
        foreach ($list as &$item) {
            $item['status_text'] = (new Order())->getStatusText($item['status']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list,
            'pagination' => $pagination,
        ]);
    }
}