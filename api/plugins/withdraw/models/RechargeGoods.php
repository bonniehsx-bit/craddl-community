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

namespace app\plugins\withdraw\models;

use app\models\BaseActiveRecord;
use app\plugins\withdraw\services\AssetService;

/**
 * 充值商品的插件级配置 —— 一个商品对应一个到账钱包。
 *
 * 为什么是一对一而不是档位级选钱包:
 *   档位级可选的话,规格维度会变成「金额 × 钱包」的笛卡尔积,运营配起来很乱。
 *   要充两种资产就建两个商品(「余额充值」「积分充值」),
 *   而且分开之后**还能各自配不同的分销比例**,比塞在一个商品里更灵活。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $goods_id
 * @property string $asset_type
 * @property string $remark
 */
class RechargeGoods extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_withdraw_recharge_goods}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'goods_id', 'asset_type'], 'required'],
            [['mall_id', 'goods_id'], 'integer'],
            [['asset_type'], 'string', 'max' => 32],
            [['remark'], 'string', 'max' => 255],
            [['asset_type'], 'default', 'value' => 'balance'],
        ];
    }


    /** 到账资产的展示名(走品牌词库 / 钱包注册表) */
    public function assetLabel(): string
    {
        return AssetService::label((string)$this->asset_type);
    }

    /** 按商品取配置 */
    public static function ofGoods(int $goodsId, int $mallId): ?self
    {
        return static::findOne(['goods_id' => $goodsId, 'mall_id' => $mallId, 'is_delete' => 0]);
    }
}
