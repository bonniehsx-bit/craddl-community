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

namespace app\forms\mall\log;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Admin;
use app\models\AdminActionLog;
use app\models\BaseModel;

class AdminLogListForm extends BaseModel
{
    public $search_date;
    public $model;      // 模型名,支持短名(User)或全名(app\models\User)
    public $model_id;   // 数据 ID
    public $admin_id;   // 操作人
    public $keyword;    // 在 备注 / 变更前后内容 里模糊搜

    public function rules()
    {
        return [
            [['model_id', 'admin_id'], 'integer'],
            [['model', 'keyword'], 'string'],
            [['search_date'], 'safe'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = AdminActionLog::find()->andWhere(['mall_id' => $this->mall_id]);

        if ($this->search_date && is_array($this->search_date) && count($this->search_date) === 2) {
            $query->andWhere(['>=', 'created_at', strtotime($this->search_date[0])]);
            $query->andWhere(['<=', 'created_at', strtotime($this->search_date[1])]);
        }
        // 模型名库里存的是全限定类名(app\models\User),但人只会输 "User",
        // 所以用 LIKE 匹配:短名、全名都能命中。
        if ($this->model !== null && $this->model !== '') {
            $query->andWhere(['like', 'model', $this->model]);
        }
        if ($this->model_id) {
            $query->andWhere(['model_id' => $this->model_id]);
        }
        if ($this->admin_id) {
            $query->andWhere(['admin_id' => $this->admin_id]);
        }
        // 关键字落在备注和变更内容里 —— 排查"谁把手机号改成了 186xxx"这类问题时最有用。
        // before/after 是 text,LIKE 走不了索引,所以只在明确输了关键字时才加这段。
        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere([
                'or',
                ['like', 'remarks', $this->keyword],
                ['like', 'before_update', $this->keyword],
                ['like', 'after_update', $this->keyword],
            ]);
        }

        $list = $query->orderBy('id desc')->page($pagination, $this->limit, $this->page)->asArray()->all();

        // 补操作人名字:日志只存 admin_id,列表上光看 ID 根本不知道是谁干的。
        // 批量取一次,别在循环里查(N+1)。
        $adminIds = array_values(array_filter(array_column($list, 'admin_id')));
        $admins = $adminIds
            ? Admin::find()->select(['id', 'username', 'nickname'])
                ->where(['id' => $adminIds])->indexBy('id')->asArray()->all()
            : [];
        foreach ($list as &$row) {
            $a = $admins[$row['admin_id']] ?? null;
            $row['admin_name'] = $a ? (($a['nickname'] ?: $a['username']) ?: ('#' . $row['admin_id'])) : '';
            // 模型短名:列表列显示用,全限定类名太长会把表格撑爆
            $row['model_short'] = $row['model'] ? substr(strrchr('\\' . $row['model'], '\\'), 1) : '';
        }
        unset($row);

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
