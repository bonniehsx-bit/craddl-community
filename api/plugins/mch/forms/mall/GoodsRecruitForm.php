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

namespace app\plugins\mch\forms\mall;

use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\plugins\mch\models\MchRecruitGoods;
use Yii;

/**
 * 商家推荐奖 —— 商品级配置的读取 / 保存
 *
 * 模仿 area 插件 GoodsAreaForm：商品编辑面板里的「商家推荐奖」Tab。
 *  - rule_type=1 走全局比例（mch_setting.recruit_rate）
 *  - rule_type=2 走独立比例（本商品 rate）
 */
class GoodsRecruitForm extends BaseModel
{
    /**
     * 读取商品的商家推荐奖配置
     */
    public function getSetting($goodsId)
    {
        $data = [
            'is_enable'      => 0,
            'rule_type'      => MchRecruitGoods::RULE_GLOBAL,
            'rate'           => '',
            // 附带全局比例，供前端展示「全局规则是多少」
            'global_setting' => ['recruit_rate' => $this->getGlobalRate()],
        ];
        if ($goodsId > 0) {
            $model = MchRecruitGoods::findByGoodsId((int) $goodsId, (int) Yii::$app->mall->id);
            if ($model) {
                $data['is_enable'] = (int) $model->is_enable;
                $data['rule_type'] = (int) $model->rule_type ?: MchRecruitGoods::RULE_GLOBAL;
                if ($data['rule_type'] === MchRecruitGoods::RULE_CUSTOM) {
                    $data['rate'] = $model->rate;
                }
            }
        }
        return $data;
    }

    /**
     * 保存商品的商家推荐奖配置
     */
    public function save($goodsId, $data)
    {
        if (empty($goodsId)) {
            return;
        }
        $mallId = (int) Yii::$app->mall->id;
        $model  = MchRecruitGoods::findByGoodsId((int) $goodsId, $mallId);
        if (!$model) {
            $model = new MchRecruitGoods();
            $model->mall_id  = $mallId;
            $model->goods_id = (int) $goodsId;
        }
        $model->is_enable = !empty($data['is_enable']) ? 1 : 0;
        $ruleType = (int) ($data['rule_type'] ?? MchRecruitGoods::RULE_GLOBAL);
        $model->rule_type = in_array($ruleType, [MchRecruitGoods::RULE_GLOBAL, MchRecruitGoods::RULE_CUSTOM], true)
            ? $ruleType : MchRecruitGoods::RULE_GLOBAL;

        if ($model->rule_type === MchRecruitGoods::RULE_CUSTOM) {
            $rate = isset($data['rate']) ? (float) $data['rate'] : 0;
            if ($rate < 0) {
                $rate = 0;
            }
            if ($rate > 100) {
                throw new \Exception('商家推荐奖比例不能超过 100%');
            }
            $model->rate = $rate;
        } else {
            // 走全局时清空独立比例，保持数据整洁
            $model->rate = 0;
        }

        if (!$model->save()) {
            Yii::error('商家推荐奖配置保存失败: ' . json_encode($model->getErrors()));
            throw new \Exception('商家推荐奖配置保存失败');
        }
    }

    /**
     * 全局商家推荐奖比例（存在 mch_setting.recruit_rate）
     */
    private function getGlobalRate(): float
    {
        $setting = OptionHelper::get('mch_setting', Yii::$app->mall->id, []);
        return (float) ($setting['recruit_rate'] ?? 0);
    }
}
