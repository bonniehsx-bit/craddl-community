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
use app\models\BaseModel;

/**
 * 读取随发布包交付的协议正文，后台与交付文件保持同一来源。
 */
class SourceLicenseForm extends BaseModel
{
    public function search(): array
    {
        $path = \Yii::getAlias('@app/SOURCE-LICENSE.md');
        if (!is_file($path) || !is_readable($path)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '协议文件暂不可用，请联系系统管理员'];
        }

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '协议文件读取失败，请联系系统管理员'];
        }

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '', 'data' => ['content' => $content]];
    }
}
