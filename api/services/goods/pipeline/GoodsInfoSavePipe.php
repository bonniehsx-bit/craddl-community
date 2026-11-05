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

namespace app\services\goods\pipeline;

use app\models\GoodsInfo;
use Exception;
use yii\helpers\Json;

class GoodsInfoSavePipe
{
    /**
     * @param GoodsContext $context
     * @param \Closure $next
     * @return mixed
     * @throws Exception
     */
    public function handle($context, \Closure $next)
    {
        $form = $context->form;
        $goods = $context->goods;

        $goodsInfo = GoodsInfo::findOne(['goods_id' => $goods->id, 'is_delete' => 0]);
        if (!$goodsInfo) {
            $goodsInfo = new GoodsInfo();
            $goodsInfo->goods_id = $goods->id;
            $goodsInfo->mall_id = $goods->mall_id;
        }
        $goodsInfo->pic_list = Json::encode($form->pic_list ?: []);
        // 抹除阿里 lazyload 图片伪装
        $detailStr = $form->detail ?: '';
        $detailStr = str_replace("data-src", "src", $detailStr);
        $detailStr = str_replace('src="//g.alicdn.com/s.gif"', '', $detailStr);
        $goodsInfo->detail = $detailStr;

        // 向下兼容平台独占或辅助插件字段
        if (isset($form->limit_area_list)) {
            $goodsInfo->limit_area_list = $form->limit_area_list;
        }
        if (isset($form->permission)) {
            $goodsInfo->permission = $form->permission ? Json::encode($form->permission) : null;
        }
        if (isset($form->remarks)) {
            $goodsInfo->remarks = $form->remarks ?: null;
        }
        if (isset($form->is_member_price) && isset($form->level_price)) {
            $goodsInfo->level_price = $form->is_member_price ? Json::encode($form->level_price) : null;
        }

        $isAttr = $context->isAttr ?? 1;
        $specsData = property_exists($form, 'specs_data') ? $form->specs_data : null;

        if (!$context->isPlatformGoods && $isAttr && $specsData && isset($specsData['spec_groups'])) {
            $specGroups = $specsData['spec_groups'];
            $goodsInfo->spec_template = is_array($specGroups) ? Json::encode($specGroups, JSON_UNESCAPED_UNICODE) : $specGroups;
        }

        if (!$goodsInfo->save()) {
            throw new Exception($form->getErrorMsg($goodsInfo));
        }

        return $next($context);
    }
}
