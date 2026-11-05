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
use app\models\CommonPayOrder;
use app\models\Order;
use app\payments\FastPayMpwxPayment;
use app\payments\MpwxPayment;
use Yii;

/**
 * 微信履约推送记录列表（重构版）
 * 数据源：CommonPayOrder 表（push 相关字段）
 */
class OrderPushLogListForm extends BaseModel
{
    public $keyword;
    public $push_status; // 0=未推送 1=成功 2=失败
    public $page = 1;
    public $limit = 20;

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['push_status', 'page', 'limit'], 'integer'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = Yii::$app->mall->id;

        $query = CommonPayOrder::find()->alias('cpo')
            ->leftJoin(Order::tableName() . ' o', 'o.order_no = cpo.order_no AND o.is_delete = 0')
            ->where([
                'cpo.mall_id' => $mallId,
                'cpo.is_pay' => 1,
                'cpo.payment' => [MpwxPayment::class, FastPayMpwxPayment::class],
            ])
            ->andWhere(['>=', 'cpo.created_at', time() - 30 * 86400]);

        if ($this->push_status !== null && $this->push_status !== '') {
            $query->andWhere(['cpo.is_push_wechat' => $this->push_status]);
        }

        if ($this->keyword) {
            $query->andWhere(['or',
                ['like', 'cpo.order_no', $this->keyword],
                ['like', 'cpo.transaction_id', $this->keyword],
            ]);
        }

        $count = $query->count();

        $list = $query->select([
                'cpo.id',
                'cpo.order_no',
                'cpo.transaction_id',
                'cpo.pay_price',
                'cpo.is_push_wechat',
                'cpo.push_errors',
                'cpo.push_retry_count',
                'cpo.push_wechat_params',
                'cpo.created_at as pay_time',
                'o.status as order_status',
                'o.deliver_type',
                'o.type as order_type',
            ])
            ->orderBy(['cpo.is_push_wechat' => SORT_ASC, 'cpo.id' => SORT_DESC])
            ->offset(($this->page - 1) * $this->limit)
            ->limit($this->limit)
            ->asArray()
            ->all();

        $statusMap = [0 => '待推送', 1 => '已成功', 2 => '推送失败'];
        $orderStatusMap = [0 => '已关闭', 10 => '待付款', 20 => '待发货', 30 => '待收货', 40 => '已完成'];

        foreach ($list as &$item) {
            $item['push_status_text'] = $statusMap[(int)$item['is_push_wechat']] ?? '未知';
            $item['order_status_text'] = $orderStatusMap[(int)($item['order_status'] ?? 0)] ?? '--';
            $item['pay_time_text'] = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : '--';
            $item['can_retry'] = (int)$item['is_push_wechat'] !== 1; // 非成功状态都可以重试
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $list,
            'pagination' => [
                'total_count' => (int)$count,
                'current_page' => (int)$this->page,
                'pageSize' => (int)$this->limit,
            ],
        ]);
    }

    /**
     * 手动重推单条记录
     */
    public function retry()
    {
        $id = Yii::$app->request->post('id');
        if (!$id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '缺少ID');
        }

        $cpo = CommonPayOrder::findOne(['id' => $id, 'mall_id' => Yii::$app->mall->id]);
        if (!$cpo) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '记录不存在');
        }

        if ($cpo->is_push_wechat == 1) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该记录已推送成功，无需重试');
        }

        // 重置状态为待推送，下次 cron 自动处理
        $cpo->is_push_wechat = 0;
        $cpo->push_errors = '';
        $cpo->push_retry_count = 0;
        $cpo->save(false, ['is_push_wechat', 'push_errors', 'push_retry_count']);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '已重置为待推送，系统将在1分钟内自动重试');
    }
}
