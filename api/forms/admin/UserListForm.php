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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\BaseModel;
use app\models\Role;
use Yii;
use yii\helpers\Json;

class UserListForm extends BaseModel
{
    public $page;
    public $limit;

    public $username;
    public $nickname;
    public $mobile;
    public $params; // JSON string for time range

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
            [['username', 'nickname', 'mobile', 'params'], 'string'],
            [['username', 'nickname', 'mobile'], 'trim'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        // 【SaaS】查询：必须限定 mall_id
        $query = Admin::find()
            ->where(['mall_id' => Yii::$app->mall->id])
            ->andWhere(['is_delete' => 0]);

        // 模糊搜索
        if ($this->username) {
            $query->andWhere(['like', 'username', $this->username]);
        }
        if ($this->nickname) {
            $query->andWhere(['like', 'nickname', $this->nickname]);
        }
        if ($this->mobile) {
            $query->andWhere(['like', 'mobile', $this->mobile]);
        }

        // 时间范围
        if ($this->params) {
            $params = Json::decode($this->params);
            if (is_array($params) && isset($params['beginTime']) && isset($params['endTime'])) {
                $query->andWhere(['between', 'created_time', $params['beginTime'] . ' 00:00:01', $params['endTime'] . ' 23:59:59']);
            }
        }

        $count = $query->count();
        $list = $query->orderBy(['id' => SORT_ASC])
            ->offset(($this->page - 1) * $this->limit)
            ->limit($this->limit)
            ->select([
                'id', 'nickname', 'avatar', 'username', 'name', 'mobile', 'email',
                'role_id', 'created_time', 'login_ip',
                'is_has_expire', 'end_at', 'is_expire'
            ])
            ->asArray()
            ->all();

        // 批量补充角色名称
        if (!empty($list)) {
            $roleIds = array_values(array_unique(array_filter(array_column($list, 'role_id'))));
            $roleMap = [];
            if (!empty($roleIds)) {
                $roleRows = Role::find()
                    ->where(['id' => $roleIds, 'is_delete' => 0])
                    ->select(['id', 'role_name'])
                    ->asArray()
                    ->all();
                $roleMap = array_column($roleRows, 'role_name', 'id');
            }
            $now = time();
            foreach ($list as &$item) {
                $item['role_name'] = $item['role_id'] && isset($roleMap[$item['role_id']]) ? $roleMap[$item['role_id']] : '';
                // 计算实时账号状态：1=正常 / 2=已过期 / 3=即将过期(7天内)
                $expireFlag = (int)$item['is_expire'];
                if (!$expireFlag && $item['is_has_expire'] && $item['end_at']) {
                    if ($item['end_at'] < $now) {
                        $expireFlag = 1;
                    }
                }
                $item['account_status'] = $expireFlag ? 'expired' : 'active';
            }
            unset($item);
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', [
            'total' => (int)$count,
            'rows'  => $list,
            'pagination' => [
                'total_count' => (int)$count,
                'page_count'  => ceil($count / $this->limit),
                'current_page'=> $this->page,
                'page_size'   => $this->limit
            ]
        ]);
    }
}