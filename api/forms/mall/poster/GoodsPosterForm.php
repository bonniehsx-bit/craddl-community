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
use app\models\Goods;
use app\services\GoodsPosterRenderer;
use app\services\PosterCanvas;

/**
 * 商品海报设置:从四套固定模板里选一套,再配 主题色 / 主标题 / 副标题 / 推荐语
 */
class GoodsPosterForm extends BaseModel
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
            'setting' => PosterHelper::getGoodsPoster($this->mall_id),
            'templates' => GoodsPosterRenderer::TEMPLATES,
            'limits' => [
                'main_title' => PosterHelper::GOODS_MAIN_TITLE_MAX,
                'sub_title' => PosterHelper::GOODS_SUB_TITLE_MAX,
                'recommend' => PosterHelper::GOODS_RECOMMEND_MAX,
            ],
            // 预览用真实的商城名、logo 和一件在售商品
            'mall' => ['name' => $mallName, 'logo' => $mallLogo],
            'sample_goods' => $this->sampleGoods(),
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
        if (!isset(GoodsPosterRenderer::TEMPLATES[$data['template'] ?? ''])) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择海报模板');
        }
        // 取色器可以被清空成 null:存进去价格就没颜色了,这里直接拦
        if (!PosterCanvas::parseHex($data['theme_color'] ?? null)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择主题色');
        }
        $checks = [
            ['main_title', '主标题', PosterHelper::GOODS_MAIN_TITLE_MAX, true],
            ['sub_title', '副标题', PosterHelper::GOODS_SUB_TITLE_MAX, false],
            ['recommend', '推荐语', PosterHelper::GOODS_RECOMMEND_MAX, true],
        ];
        foreach ($checks as [$key, $label, $max, $required]) {
            $value = trim((string)($data[$key] ?? ''));
            if ($required && $value === '') {
                return $this->apiResponse(ApiCode::CODE_FAIL, "请填写{$label}");
            }
            if (mb_strlen($value) > $max) {
                return $this->apiResponse(ApiCode::CODE_FAIL, "{$label}最多 {$max} 个字");
            }
        }

        // 只存认得的五项,老版本的坐标配置 / bg_image 随这次保存清掉
        $setting = PosterHelper::normalizeGoodsPoster([
            'template' => $data['template'],
            'theme_color' => $data['theme_color'],
            'main_title' => (string)($data['main_title'] ?? ''),
            'sub_title' => (string)($data['sub_title'] ?? ''),
            'recommend' => (string)($data['recommend'] ?? ''),
        ]);
        OptionHelper::set('goods_poster_setting', $setting, $this->mall_id);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /** 最近上架的一件有主图的商品,没有就返回 null,前端画占位 */
    private function sampleGoods(): ?array
    {
        $goods = Goods::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0, 'status' => 1])
            ->andWhere(['<>', 'cover_pic', ''])
            ->orderBy(['id' => SORT_DESC])
            ->one();
        if (!$goods) {
            return null;
        }
        return [
            'name' => (string)$goods->name,
            'price' => GoodsPosterRenderer::formatPrice($goods->price),
            'origin_price' => (float)$goods->origin_price > (float)$goods->price ? GoodsPosterRenderer::formatPrice($goods->origin_price) : '',
            'is_negotiable' => (int)$goods->is_negotiable,
            'sales' => (int)$goods->virtual_sales,
            'cover_pic' => (string)$goods->cover_pic,
        ];
    }
}
