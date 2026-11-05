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

class ExpressDestroyForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'required'],
            [['id'], 'integer'],
        ];
    }

    public function destroy()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        // 只按 id 删有两个问题:一是能删掉别家商城自建的快递公司,二是能删掉
        // mall_id=0 的平台标准集(那是所有商城共用的,删一条全平台都少一条)。
        // 所以这里只放行本商城自建的记录,标准集单独给出明确提示 —— 否则会退化成
        // 「数据不存在」,管理员看着列表里明明有却删不掉,只会以为是坏了。
        $model = Express::findOne(['id' => $this->id, 'is_delete' => 0]);
        if (!$model) {
            return [
                'code' => 1,
                'msg' => '数据不存在或已删除',
            ];
        }
        if ((int)$model->mall_id === 0) {
            return [
                'code' => 1,
                'msg' => '平台标准快递公司为全平台共用，不可删除',
            ];
        }
        if ((int)$model->mall_id !== \Yii::$app->mall->id) {
            return [
                'code' => 1,
                'msg' => '数据不存在或已删除',
            ];
        }

        $model->is_delete = 1;
        $model->deleted_at = time();

        if ($model->save()) {
            return [
                'code' => 0,
                'msg' => '删除成功',
            ];
        } else {
            return $this->getErrorResponse($model);
        }
    }
}
