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

namespace app\forms\api\finance;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\CommissionLog;
use app\models\Order;
use app\models\User;
use app\services\CommissionTypeRegistry;
use Yii;

/**
 * 移动端：当前用户的分润记录列表
 * 以 CommissionLog 为数据源，包含"待结算/已结算/已失效"全状态。
 * 类型元数据由 CommissionTypeRegistry 动态下发，新插件无须改动本文件。
 */
class CommissionLogListForm extends BaseModel
{
    public $page = 1;
    public $limit = 15;
    public $status;     // 0 待结算 / 1 已结算 / 2 已失效；留空=全部
    public $type;       // type 字符串筛选，留空=全部
    public $date_start; // YYYY-MM-DD
    public $date_end;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['status'], 'integer'],
            [['type', 'date_start', 'date_end'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 15],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $userId = Yii::$app->user->id;
        $mallId = $this->mall_id;

        // 1. 主查询
        $query = CommissionLog::find()->alias('cl')
            ->where([
                'cl.user_id' => $userId,
                'cl.mall_id' => $mallId,
                'cl.is_delete' => 0,
            ]);

        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['cl.status' => (int)$this->status]);
        }
        if ($this->type) {
            $query->andWhere(['cl.type' => $this->type]);
        }
        if ($this->date_start) {
            $query->andWhere(['>=', 'cl.created_at', strtotime($this->date_start . ' 00:00:00')]);
        }
        if ($this->date_end) {
            $query->andWhere(['<=', 'cl.created_at', strtotime($this->date_end . ' 23:59:59')]);
        }

        // 关联订单/买家以丰富溯源信息
        $query->leftJoin(['o' => Order::tableName()], 'o.id = cl.order_id')
              ->leftJoin(['bu' => User::tableName()], 'bu.id = cl.buy_user_id')
              ->select([
                  'cl.*',
                  'o.order_no',
                  'bu.nickname as buyer_nickname',
                  'bu.avatar_url as buyer_avatar',
              ])
              ->orderBy(['cl.id' => SORT_DESC]);

        $pagination = null;
        $list = $query->page($pagination, $this->limit, $this->page)->asArray()->all();

        // 2. 类型元数据（用于下发 tag 颜色、类型文案）
        $typeMeta = CommissionTypeRegistry::getAll($mallId);
        $statusMap = CommissionLog::getStatusMap();

        // 3. 汇总统计（当前用户三态合计）
        $sumQuery = CommissionLog::find()
            ->where([
                'user_id' => $userId,
                'mall_id' => $mallId,
                'is_delete' => 0,
            ]);
        $stats = [
            'pending' => (float)(clone $sumQuery)->andWhere(['status' => CommissionLog::STATUS_PENDING])->sum('commission_amount'),
            'settled' => (float)(clone $sumQuery)->andWhere(['status' => CommissionLog::STATUS_SETTLED])->sum('commission_amount'),
            'invalid' => (float)(clone $sumQuery)->andWhere(['status' => CommissionLog::STATUS_INVALID])->sum('commission_amount'),
        ];
        $stats['pending'] = number_format($stats['pending'], 2, '.', '');
        $stats['settled'] = number_format($stats['settled'], 2, '.', '');
        $stats['invalid'] = number_format($stats['invalid'], 2, '.', '');

        // 4. 格式化 list（仅保留移动端需要的字段，裁剪敏感 extra_data）
        $formatted = [];
        foreach ($list as $item) {
            $type = $item['type'];
            $meta = $typeMeta[$type] ?? ['label' => $type, 'tag_type' => '', 'rate_label' => '佣金比例', 'group' => 'unknown'];
            $formatted[] = [
                'id'               => (int)$item['id'],
                'type'             => $type,
                'type_text'        => $meta['label'] ?: $type,
                'tag_type'         => $meta['tag_type'] ?? '',
                'group'            => $meta['group'] ?? 'unknown',
                'commission_amount' => number_format($item['commission_amount'], 2, '.', ''),
                'status'           => (int)$item['status'],
                'status_text'      => $statusMap[$item['status']] ?? '未知',
                'level'            => (int)$item['level'],
                'rate'             => $item['commission_rate'] !== null ? (float)$item['commission_rate'] : null,
                'calculation_price' => $item['calculation_price'] !== null ? number_format($item['calculation_price'], 2, '.', '') : null,
                'order_no'         => $item['order_no'] ?: '',
                'buyer_nickname'   => $item['buyer_nickname'] ?: '平台',
                'buyer_avatar'     => $item['buyer_avatar'] ?: '',
                'remark'           => $item['remark'] ?: '',
                'created_at'       => date('Y-m-d H:i:s', $item['created_at']),
                'settled_at'       => $item['settled_at'] > 0 ? date('Y-m-d H:i:s', $item['settled_at']) : '',
            ];
        }

        // 5. 下发类型选项（仅包含当前用户实际产生过的 type，避免下拉冗余）
        $userTypes = CommissionLog::find()
            ->select('type')->distinct()
            ->where(['user_id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])
            ->column();
        $typeOptions = [];
        foreach ($userTypes as $t) {
            $m = $typeMeta[$t] ?? ['label' => $t, 'tag_type' => '', 'group' => 'unknown'];
            $typeOptions[] = [
                'value'    => $t,
                'label'    => $m['label'] ?: $t,
                'tag_type' => $m['tag_type'] ?? '',
                'group'    => $m['group'] ?? 'unknown',
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '获取成功',
            'data' => [
                'list'         => $formatted,
                'pagination'   => $pagination,
                'stats'        => $stats,
                'type_options' => $typeOptions,
                'status_options' => [
                    ['value' => CommissionLog::STATUS_PENDING, 'label' => '待结算'],
                    ['value' => CommissionLog::STATUS_SETTLED, 'label' => '已结算'],
                    ['value' => CommissionLog::STATUS_INVALID, 'label' => '已失效'],
                ],
            ],
        ];
    }
}
