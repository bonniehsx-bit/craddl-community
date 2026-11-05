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

namespace app\forms\mall\express;

use app\models\BaseModel;
use app\models\Express;

class ExpressListForm extends BaseModel
{
    public $keyword;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['keyword'], 'string', 'max' => 255],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        // 快递公司是「平台标准集(mall_id=0,各商城共用) + 本商城自建」的混合模型,
        // 所以是 IN(0, 当前商城) —— 一刀切按当前商城过滤会把 95 条标准快递全隐藏掉,
        // 不过滤则会列出别家商城自建的。删除侧同口径,见 ExpressDestroyForm。
        $query = Express::find()
            ->where(['is_delete' => 0, 'mall_id' => [0, \Yii::$app->mall->id]]);

        if ($this->keyword) {
            $query->andWhere(['like', 'name', $this->keyword]);
        }

        $pagination = null;
        $list = $query->orderBy('sort ASC, id DESC')
            ->page($pagination, $this->limit, $this->page)
            ->all();

        return [
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $list,
                'pagination' => $pagination,
            ]
        ];
    }
}
