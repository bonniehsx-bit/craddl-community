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
use app\models\BaseModel;
use app\models\Role;
use Yii;
use yii\helpers\Json;

class RoleListForm extends BaseModel
{
    public $page;
    public $limit;

    public $role_key;
    public $role_name;
    public $params; // JSON 字符串

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
            [['role_key', 'role_name', 'params'], 'string'],
            [['role_key', 'role_name'], 'trim'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $this->getErrorMsg());
        }

        // --- SaaS 核心：使用 findByMall() 自动过滤当前商城的角色 ---
        $query = Role::findByMall()
            ->andWhere(['is_delete' => 0]);

        // --- 搜索条件 ---
        if ($this->role_key) {
            $query->andWhere(['like', 'role_key', $this->role_key]);
        }
        if ($this->role_name) {
            $query->andWhere(['like', 'role_name', $this->role_name]);
        }

        // --- 时间范围查询 ---
        if ($this->params) {
            $params = Json::decode($this->params);
            if (is_array($params) && isset($params['beginTime']) && isset($params['endTime'])) {
                $query->andWhere(['between', 'created_time', $params['beginTime'] . ' 00:00:01', $params['endTime'] . ' 23:59:59']);
            }
        }

        $count = $query->count();

        $list = $query->orderBy(['id' => SORT_DESC])
            ->offset(($this->page - 1) * $this->limit)
            ->limit($this->limit)
            ->asArray()
            ->all();

        // --- 数据格式化 ---
        foreach ($list as &$item) {
            $item['menu_check_strictly'] = $item['menu_check_strictly'] ? true : false;
            $item['admin']   = $item['admin'] ? true : false;
            $item['menuIds'] = [];
            $item['id']      = strval($item['id']);
            $item['role_id'] = strval($item['id']);
        }
        unset($item);

        // 使用 ResponseHelper 返回数据
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '查询成功', [
            'total' => (int)$count,
            'rows'  => $list,
        ]);
    }
}