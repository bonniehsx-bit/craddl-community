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

use app\models\BaseModel;
use app\models\User;
use app\plugins\mch\models\Mch;

class BaseMchForm extends BaseModel
{
    /**
     * [核心重构] 根据用户ID，检查其名下所有店铺，同步更新其最终的 is_mch 状态
     * @param int $userId
     * @throws \Exception
     */
    protected function _syncUserOverallMchStatus($userId)
    {
        if (!$userId) {
            return;
        }

        $user = User::findOne($userId);
        if (!$user) {
            \Yii::warning("同步商家身份时未找到用户, User ID: {$userId}");
            return;
        }

        // 检查该用户是否还存在任何一个“已通过”且“未删除”的店铺
        $hasActiveMch = Mch::find()->where([
            'user_id' => $userId,
            'status' => 1, // 已通过
            'is_delete' => 0,
        ])->exists();

        $isMch = $hasActiveMch ? 1 : 0;

        if ($user->is_mch != $isMch) {
            $user->is_mch = $isMch;
            if (!$user->save()) {
                throw new \Exception('更新用户商家身份时发生错误: ' . $this->getErrorMsg($user));
            }
        }
    }
}