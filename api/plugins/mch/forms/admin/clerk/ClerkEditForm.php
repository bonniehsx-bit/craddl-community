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

namespace app\plugins\mch\forms\admin\clerk;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use app\plugins\mch\models\MchClerk;
use Yii;

class ClerkEditForm extends BaseModel
{
    public $user_id; // 要添加的店员的用户ID
    public $id;      // 要删除的店员关系ID

    public function rules()
    {
        return [
            [['user_id', 'id'], 'integer'],
        ];
    }

    /**
     * 添加店员
     * @return array
     */
    public function add()
    {
        if (!$this->user_id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '参数 user_id 不能为空');
        }

        $currentMch = Yii::$app->mch;
        $user = User::findOne($this->user_id);
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在');
        }

        // 检查该用户是否已经是本店成员
        $clerk = MchClerk::findOne(['mch_id' => $currentMch->id, 'user_id' => $this->user_id, 'is_delete' => 0]);
        if ($clerk) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '该用户已是本店成员，请勿重复添加');
        }

        $newClerk = new MchClerk();
        $newClerk->mall_id = Yii::$app->mall->id;
        $newClerk->mch_id = $currentMch->id;
        $newClerk->user_id = $this->user_id;
        $newClerk->is_manager = 0; // [明确] 通过此接口添加的永远是普通店员
        if ($newClerk->save()) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '添加成功');
        } else {
            return $this->responseErrorInfo($newClerk);
        }
    }

    /**
     * 移除店员
     * @return array
     */
    public function delete()
    {
        if (!$this->id) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '参数 id 不能为空');
        }
        $currentMch = Yii::$app->mch;

        // 查询要删除的关系记录，并进行严格的权限校验
        $clerk = MchClerk::findOne(['id' => $this->id, 'mch_id' => $currentMch->id, 'is_delete' => 0]);
        if (!$clerk) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '成员关系不存在或无权限操作');
        }

        // [安全] 店长不能通过这个接口移除管理员
        if ($clerk->is_manager) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '不能在此处移除管理员');
        }

        $clerk->is_delete = 1; // 逻辑删除
        if ($clerk->save()) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '移除成功');
        } else {
            return $this->responseErrorInfo($clerk);
        }
    }
}