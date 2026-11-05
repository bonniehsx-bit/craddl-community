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
use app\models\Mall;
use Yii;

class MallEntryListForm extends BaseModel
{
    public function getList()
    {
        /** @var Admin $admin */
        $admin = Yii::$app->admin->identity;

        $query = Mall::find()->where(['is_delete' => 0]);

        // 可进入的商城范围收口在 Admin::accessibleMallIds():
        //   null = 平台超管,不受限;否则就是明确的 id 列表(员工绑死的那个 / 关联授权 + 自己创建的)。
        // 原来这段判断只写在这里,而「要不要给切换商城入口」在前端另写了一套(admin_type === 0),
        // 两处口径分叉 —— 名下管多个商城的商户主账号后端放行、前端却不给入口。现在共用一处。
        $allIds = $admin->accessibleMallIds();
        if ($allIds !== null) {
            if (empty($allIds)) {
                return ResponseHelper::send(ApiCode::CODE_SUCCESS, '暂无可用商城', ['list' => []]);
            }
            $query->andWhere(['id' => $allIds]);
        }

        // 查询结果
        $list = $query->select(['id', 'name', 'logo', 'is_has_expire', 'end_at', 'is_expire', 'is_stop'])
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        // 格式化数据
        foreach ($list as &$item) {
            $item['end_at_text'] = $item['end_at'] ? date('Y-m-d', $item['end_at']) : '永久';
            // 可以在这里处理图片完整路径
            // $item['logo'] = ...
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', [
            'list' => $list
        ]);
    }
}