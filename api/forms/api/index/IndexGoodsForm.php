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

namespace app\forms\api\index;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Goods;

class IndexGoodsForm extends BaseModel
{
    public function getList()
    {
        $query = Goods::find()->where([
            'is_delete' => 0,
            'status' => Goods::STATUS_UP,
            'is_check' => 1,
            'mch_id' => 0,
            'type' => Goods::TYPE_NORMAL
        ]);
        // ->with('info'):预载 GoodsInfo,供会员价计算用,避免逐商品懒加载 N+1
        $list = $query->with('info')->page($pagination, $this->limit, $this->page)->orderBy('sort ASC, created_at DESC')->limit(10)->all();
        // 补会员价/插件代理价(返回模型,走模型版;Level 请求级缓存,无 N+1)
        \app\helpers\GoodsMemberPriceHelper::applyToModelList(
            $list,
            \app\helpers\GoodsMemberPriceHelper::currentLevelId()
        );
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }

}