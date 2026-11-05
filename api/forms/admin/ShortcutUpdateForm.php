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
use app\models\Admin;
use app\models\BaseModel;

class ShortcutUpdateForm extends BaseModel
{
    public $shortcuts;

    public function rules()
    {
        return [
            [['shortcuts'], 'safe']
        ];
    }

    public function save()
    {
        $adminId = \Yii::$app->admin->id;
        if (!$adminId) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户未登录');
        }

        // Yii::$app->admin->identity 是 AdminBootstrap 用身份缓存重建的静态快照(动静分离,
        // 刻意不含 ext_data);若直接在它上面 $admin->save(),afterSave 会把不含 ext_data 的
        // 身份缓存写回,读取端永远看不到 → 「保存无效」。这里实时回库取真实记录。
        $admin = Admin::findOne($adminId);
        if (!$admin) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '用户不存在');
        }

        $json = is_array($this->shortcuts) ? json_encode($this->shortcuts, JSON_UNESCAPED_UNICODE) : '[]';
        // 只更新 ext_data 单列:updateAttributes 跳过全量校验(避免历史脏数据致 save() 返回 false),
        // 也不触发 afterSave 重写身份缓存(ext_data 本就不进缓存,无需刷新)。
        $admin->updateAttributes(['ext_data' => $json]);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '快捷菜单保存成功');
    }
}
