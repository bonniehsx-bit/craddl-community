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
namespace app\forms\api\site;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\Option;

/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/11/15
 * Time: 14:31
 */

class SiteForm extends \app\models\BaseModel
{
    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $setting = OptionHelper::get(Option::NAME_SYS_SETTING, 0);
        if ($setting) {
            $info['copyright'] = $setting['copyright'];
            $info['copyright_url'] = $setting['copyright_url'];
            $info['header_pic'] = $setting['header_pic'];
            $info['is_register'] = $setting['is_register'];
            $info['sub_name'] = $setting['sub_name'];
            $info['record'] = $setting['record'];
            $info['name'] = $setting['name'];
            $info['logo'] = $setting['logo'];
            $info['title'] = $setting['title'];
            $info['try_day'] = $setting['try_day'];
            $info['login_pic'] = $setting['login_pic'];
            $info['login_logo'] = $setting['login_logo'];
            $info['websocket_url'] = $setting['websocket_url'];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['setting' => $info ?? null]);
    }
}