<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-11-29
 * Time: 12:42
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

namespace app\forms\mall\poster;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\helpers\PosterHelper;
use app\helpers\SerializeHelper;
use app\models\BaseModel;
use app\services\InvitePosterRenderer;

/**
 * 推广海报设置:从五套固定模板里选一套,再配 主题色 / 主标题 / 副标题 / 背景图
 */
class InviterPosterForm extends BaseModel
{
    public $data; // 前端传来的 JSON 字符串

    public function rules()
    {
        return [
            [['data'], 'string'],
        ];
    }

    public function search()
    {
        [$mallName, $mallLogo] = PosterHelper::mallBrand($this->mall_id);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'setting' => PosterHelper::getInviterPoster($this->mall_id),
            // 预览顶部显示真实的商城名和 logo
            'mall' => ['name' => $mallName, 'logo' => $mallLogo],
            'templates' => InvitePosterRenderer::TEMPLATES,
            'limits' => [
                'main_title' => PosterHelper::INVITER_MAIN_TITLE_MAX,
                'sub_title' => PosterHelper::INVITER_SUB_TITLE_MAX,
            ],
        ]);
    }

    public function save()
    {
        if (!$this->data) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '配置数据不能为空');
        }
        $data = SerializeHelper::decode($this->data);
        if (!is_array($data)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '数据格式错误');
        }

        if (!isset(InvitePosterRenderer::TEMPLATES[$data['template'] ?? ''])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择海报模板');
        }
        // 取色器可以被清空成 null:存进去海报就画不出来,这里直接拦
        if (!InvitePosterRenderer::parseHex($data['theme_color'] ?? null)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择主题色');
        }
        $main = trim((string)($data['main_title'] ?? ''));
        if ($main === '') {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请填写主标题');
        }
        if (mb_strlen($main) > PosterHelper::INVITER_MAIN_TITLE_MAX) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '主标题最多 ' . PosterHelper::INVITER_MAIN_TITLE_MAX . ' 个字');
        }
        if (mb_strlen(trim((string)($data['sub_title'] ?? ''))) > PosterHelper::INVITER_SUB_TITLE_MAX) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '副标题最多 ' . PosterHelper::INVITER_SUB_TITLE_MAX . ' 个字');
        }

        // 只存认得的五项,老版本的 style_type / text_color / avatar_shape 随这次保存清掉
        $setting = PosterHelper::normalizeInviterPoster([
            'template' => $data['template'],
            'theme_color' => $data['theme_color'],
            'main_title' => $main,
            'sub_title' => (string)($data['sub_title'] ?? ''),
            'bg_image' => (string)($data['bg_image'] ?? ''),
        ]);
        OptionHelper::set('inviter_poster_setting', $setting, $this->mall_id);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }
}
