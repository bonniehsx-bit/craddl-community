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
use Yii;

/**
 * [新增] 专门用于处理商户状态变更的Form
 */
class MchStatusForm extends BaseMchForm
{
    public $id;
    public $status;

    public function rules()
    {
        return [
            [['id', 'status'], 'required'],
            [['id', 'status'], 'integer'],
            // 增加一个范围验证，确保只能变更为合法的状态
            ['status', 'in', 'range' => [1, 3]], // 1=启用(通过), 3=禁用
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => '商户ID',
            'status' => '目标状态',
        ];
    }

    public function update()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $mch = Mch::findOne([
                'id' => $this->id,
                'is_delete' => 0,
                'mall_id' => Yii::$app->mall->id,
            ]);
            if (!$mch) {
                throw new \Exception('商户不存在');
            }

            if ($mch->status == $this->status) {
                throw new \Exception('请勿重复操作');
            }

            $mch->status = $this->status;
            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }

            // [核心] 调用 Mch 模型自身的方法来同步用户身份
            $this->_syncUserOverallMchStatus($mch->user_id);
            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}