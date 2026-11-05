<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 9:13
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

namespace app\forms\api\mall;


use app\core\ApiCode;
use app\helpers\DefaultDataHelper;
use app\helpers\ImageHelper;
use app\helpers\OptionHelper;
use app\helpers\SerializeHelper;
use app\helpers\VersionHelper;
use app\models\BaseModel;
use app\models\Tabbar;

class TabbarForm extends BaseModel
{

    public function search()
    {
        $userPageData = OptionHelper::get('user_center_setting', \Yii::$app->mall->id);
        if (VersionHelper::checkIsAuditMode($this->mall_id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['tabbar' => $this->getDefault()]);
        }
        if (!$userPageData) {
            $tabbar = $this->getDefault();
        } else {
            $tabbar = $userPageData['tabbar'];
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['tabbar' => $tabbar]);
    }

    private function getDefault()
    {

        return [
            'color' => '#E41F19',
            'list' => [
                [
                    'name' => '首页',
                    'url' => '/pages/index/index',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/index.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/index-active.png')
                ],
                [
                    'name' => '分类',
                    'url' => '/others/new-cat-goods/new-cat-goods',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/cat.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/cat-active.png')

                ],
                [
                    'name' => '购物车',
                    'url' => '/others/cart/cart',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/cart.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/cart-active.png')

                ],
                [
                    'name' => '我的',
                    'url' => '/others/user/user',
                    'icon' => ImageHelper::getUrl('/statics/default/tabbar/my.png'),
                    'active_icon' => ImageHelper::getUrl('/statics/default/tabbar/my-active.png')
                ],
            ]
        ];
    }
}
