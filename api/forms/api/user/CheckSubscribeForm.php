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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\helpers\WechatHelper;
use app\models\BaseModel;
use app\models\User;

class CheckSubscribeForm extends BaseModel
{

    public function check()
    {
        $user = UserHelper::getUser();
        $openid = UserHelper::getOpenId(User::PLATFORM_WECHAT);

        WechatHelper::init($this->mall_id);
        $is_subscribe = 0;
        try {
            $response = \Yii::$app->wechat->app->getClient()->get('cgi-bin/user/info', [
                'query' => [
                    'openid' => $openid,
                    'lang' => 'zh_CN'
                ]
            ]);
            $user_detail = $response->toArray();
            if ($user_detail && !empty($user_detail['subscribe'])) {
                $is_subscribe = $user_detail['subscribe'] ? 1 : 0;
            }
        } catch (\Exception $e) {
            // 获取失败，不影响主流程，默认为未关注
        }
        if ($user->is_subscribe && $is_subscribe != 1) {
            User::updateAll(['is_subscribe' => 0], ['id' => $user->id]);
        }
        if (!$user->is_subscribe && $is_subscribe == 1) {
            User::updateAll(['is_subscribe' => 1], ['id' => $user->id]);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['is_subscribe' => $is_subscribe]);
    }
}
