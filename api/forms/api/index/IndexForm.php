<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-10
 * Time: 8:10
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

namespace app\forms\api\index;


use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\SerializeHelper;
use app\helpers\VersionHelper;
use app\models\BaseModel;
use app\models\DiyPage;
use yii\helpers\Json;

class IndexForm extends BaseModel
{

    public function getIndex()
    {
        $mallId = \Yii::$app->mall->id;
        // [审核版本路由] 若当前请求来自审核中的 App / 小程序版本，优先返回"审核版"装修页面（无敏感入口）
        // 若 mall 未配置审核版页面（is_audit_page=1），自然 fallback 到主页 is_home=1
        $homePage = null;
        if (VersionHelper::checkIsAuditMode($mallId)) {
            $homePage = DiyPage::findOne([
                'is_delete'     => 0,
                'is_audit_page' => 1,
                'mall_id'       => $mallId,
            ]);
        }
        if (!$homePage) {
            $homePage = DiyPage::findOne(['is_delete' => 0, 'is_home' => 1, 'mall_id' => $mallId]);
        }
        if (!$homePage) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '未设置主页');
        }
        $name = $homePage->name;
        $homeData = Json::decode($homePage->data);
        // 商品组 / 选项卡商品 / 分类宫格的实时数据(会员价、商品权限、分类商品数),统一见 DiyHydrateHelper
        \app\helpers\DiyHydrateHelper::hydrate($homeData, (int)\Yii::$app->mall->id);
        $pageParams = $homePage->page_params ? Json::decode($homePage->page_params) : null;
        // 主题码：默认 'diy'（走 DIY 装修引擎）；其他值由插件目录约定的 HomeTheme.vue 渲染
        // 后台通过 OptionHelper::set('home_theme', '...', $mallId) 切换
        $homeTheme = (string) (\app\helpers\OptionHelper::get('home_theme', \Yii::$app->mall->id, 'diy') ?: 'diy');
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'page_data'   => $homeData,
            'page_params' => $pageParams,
            'text_color'  => $homePage->text_color,
            'title_color' => $homePage->title_color,
            'name'        => $name,
            'home_theme'  => $homeTheme,
        ]);
    }
}
