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

namespace app\plugins\mch\models;

use app\models\BaseActiveRecord;

/**
 * 商家推荐奖 —— 商品级配置
 *
 * @property int $id
 * @property int $mall_id
 * @property int $goods_id
 * @property int $is_enable 是否开启 0否 1是
 * @property int $rule_type 分红比例规则 1=全局 2=独立
 * @property float $rate 独立分红比例(%)
 * @property int $created_at
 * @property int $updated_at
 * @property int $is_delete
 */
class MchRecruitGoods extends BaseActiveRecord
{
    const RULE_GLOBAL = 1; // 走全局比例
    const RULE_CUSTOM = 2; // 走独立比例

    public static function tableName()
    {
        return '{{%plugin_mch_recruit_goods}}';
    }

    public static function findByGoodsId(int $goodsId, int $mallId): ?self
    {
        return self::findOne(['goods_id' => $goodsId, 'mall_id' => $mallId, 'is_delete' => 0]);
    }
}
