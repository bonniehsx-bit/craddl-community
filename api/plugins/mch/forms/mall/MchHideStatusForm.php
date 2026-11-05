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

namespace app\plugins\mch\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;

/**
 * Class MchHideStatusForm
 * @package app\plugins\mch\forms\mall
 *
 * 用于处理商户前端显示/隐藏状态的更新
 */
class MchHideStatusForm extends BaseModel
{
    public $id;
    public $is_hide;

    public function rules()
    {
        return [
            [['id', 'is_hide'], 'required'],
            [['id'], 'integer'],
            [['is_hide'], 'in', 'range' => [0, 1]],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'      => '商户ID',
            'is_hide' => '隐藏状态',
        ];
    }

    /**
     * 更新状态
     * @return array
     */
    public function update()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 确保商户存在且未被软删除，且属于当前商城
            // ——只按 id 找的话，任一商城的运营都能把别家商城的商户从前台藏掉。
            $mch = Mch::findOne([
                'id' => $this->id,
                'is_delete' => 0,
                'mall_id' => \Yii::$app->mall->id,
            ]);
            if (!$mch) {
                return [
                    'code' => ApiCode::CODE_FAIL,
                    'msg'  => '商户不存在',
                ];
            }

            $mch->is_hide = $this->is_hide;
            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg'  => '操作成功',
            ];
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg'  => $e->getMessage(),
            ];
        }
    }
}