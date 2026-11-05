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

namespace app\plugins\mch\forms\admin\member;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Order;
use app\models\User;
use app\plugins\mch\models\MchMemberRemark;

class MchMemberListForm extends BaseModel
{
    public $page;
    public $limit = 15;
    public $keyword;
    public $type = 'in_store'; // 'in_store' or 'recommended'

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['keyword', 'type'], 'string'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $currentMch = \Yii::$app->mch;

        $query = null;

        if ($this->type === 'in_store') {
            // --- 进店用户查询 ---
            $consumerUserIds = Order::find()->select('user_id')
                ->where(['mch_id' => $currentMch->id, 'pay_status' => Order::PAY_STATUS_PAID, 'is_delete' => 0])
                ->distinct()
                ->column();

            if (empty($consumerUserIds)) {
                return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => [], 'pagination' => null]);
            }

            $query = User::find()->alias('u')->where(['u.id' => $consumerUserIds]);

            // 定义消费总额的子查询
            $subQuery = Order::find()->select(['user_id', 'SUM(pay_price) as total_consumption'])
                ->where(['mch_id' => $currentMch->id, 'pay_status' => Order::PAY_STATUS_PAID, 'is_delete' => 0])
                ->groupBy('user_id');

            // 定义备注的子查询
            $remarkSubQuery = MchMemberRemark::find()->select(['user_id', 'remark'])
                ->where(['mch_id' => $currentMch->id, 'is_delete' => 0]);

            // ======================= [核心修正] 开始 =======================
            // 关联订单统计子查询
            $query->leftJoin(['o_stats' => $subQuery], 'o_stats.user_id = u.id');
            // 关联备注子查询
            $query->leftJoin(['mmr' => $remarkSubQuery], 'mmr.user_id = u.id');

            // 添加所需字段到 SELECT
            $query->addSelect(['u.*', 'o_stats.total_consumption', 'mmr.remark']);
            // ======================= [核心修正] 结束 =======================

        } elseif ($this->type === 'recommended') {
            // --- 推荐用户查询 ---
            $query = User::find()->alias('u')->where(['u.bind_mch_id' => $currentMch->id]);

            // 关联查询备注表
            $remarkSubQuery = MchMemberRemark::find()->select(['user_id', 'remark'])
                ->where(['mch_id' => $currentMch->id, 'is_delete' => 0]);
            $query->leftJoin(['mmr' => $remarkSubQuery], 'mmr.user_id = u.id');

            $query->addSelect(['u.*', 'mmr.remark']);
        }

        if (!$query) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无效的查询类型');
        }

        // 通用搜索逻辑
        if ($this->keyword) {
            $query->andWhere([
                'OR',
                ['LIKE', 'u.nickname', $this->keyword],
                ['u.id' => $this->keyword],
                ['u.mobile' => $this->keyword],
            ]);
        }

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy(['u.id' => SORT_DESC])
            ->asArray()
            ->all();

        // 数据格式化
        foreach ($list as &$item) {
            $item['total_consumption'] = !empty($item['total_consumption']) ? number_format($item['total_consumption'], 2, '.', '') : '0.00';
            $item['remark'] = $item['remark'] ?? '';
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $this->getPaginationInfo($pagination)]);
    }
}