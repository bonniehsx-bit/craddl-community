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

namespace app\services\order\pricing;

use app\models\FreightTpl;
use app\models\UserAddress;
use yii\helpers\Json;

/**
 * 运费计算器 - 处理快递运费计算
 * 
 * 职责：
 * 1. 根据运费模板计算快递费
 * 2. 处理统一运费和按件/重量计费
 * 3. 处理区域运费规则
 */
class ShippingCalculator
{
    /**
     * 计算快递运费
     * 
     * @param array $freightItems 运费项列表
     * @param UserAddress|null $address 收货地址
     * @return string|false 运费金额，false表示不在配送范围
     */
    public function calculateExpressFee(array $freightItems, ?UserAddress $address)
    {
        if (!$address) {
            return '0.00';
        }
        
        $expressFee = '0.00';
        $tplGroups = [];
        
        // 1. 分类处理：统一运费 vs 模板运费
        foreach ($freightItems as $item) {
            // 统一运费(express_type = 1)
            if ($item['express_type'] == 1) {
                $expressFee = bcadd($expressFee, $item['freight_price'], 2);
            }
            
            // 模板运费(express_type = 2)
            if ($item['express_type'] == 2) {
                $tplId = $item['freight_id'];
                
                if (!isset($tplGroups[$tplId])) {
                    $tplGroups[$tplId] = [
                        'total_num' => 0,
                        'total_weight' => '0.00',
                    ];
                }
                
                $tplGroups[$tplId]['total_num'] += $item['num'];
                $tplGroups[$tplId]['total_weight'] = bcadd(
                    $tplGroups[$tplId]['total_weight'],
                    $item['weight'],
                    2
                );
            }
        }
        
        // 2. 无模板运费，直接返回
        if (empty($tplGroups)) {
            return $expressFee;
        }
        
        // 3. 查询运费模板
        $tplIds = array_keys($tplGroups);
        $freightTpls = FreightTpl::find()
            ->where(['id' => $tplIds, 'is_delete' => 0, 'enabled' => 1])
            ->with('freightPrices')
            ->indexBy('id')
            ->all();
        
        // 4. 逐个模板计算运费
        foreach ($tplGroups as $tplId => $group) {
            if (!isset($freightTpls[$tplId])) {
                continue;
            }
            
            $tpl = $freightTpls[$tplId];
            
            // 匹配运费规则
            $rule = $this->matchFreightRule($tpl, $address);
            
            if (!$rule) {
                // 不在配送范围
                return false;
            }
            
            // 计算该模板的运费
            $tplFee = $this->calculateTemplateRate($tpl, $rule, $group);
            $expressFee = bcadd($expressFee, $tplFee, 2);
        }
        
        return $expressFee;
    }
    
    /**
     * 匹配运费规则
     * 
     * @param FreightTpl $tpl
     * @param UserAddress $address
     * @return mixed|null 运费规则或null
     */
    private function matchFreightRule(FreightTpl $tpl, UserAddress $address)
    {
        if (empty($tpl->freightPrices)) {
            return null;
        }
        
        // 1. 优先匹配指定区域
        foreach ($tpl->freightPrices as $priceRule) {
            $sendCodes = Json::decode($priceRule->send_codes);
            
            if (is_array($sendCodes)) {
                if (in_array($address->province_code, $sendCodes) ||
                    in_array($address->city_code, $sendCodes) ||
                    in_array($address->county_code, $sendCodes)) {
                    return $priceRule;
                }
            }
        }
        
        // 2. 使用默认规则(is_union = 1)
        foreach ($tpl->freightPrices as $priceRule) {
            if ($priceRule->is_union == 1) {
                return $priceRule;
            }
        }
        
        return null;
    }
    
    /**
     * 计算模板运费
     * 
     * @param FreightTpl $tpl
     * @param mixed $rule
     * @param array $group
     * @return float|int
     */
    private function calculateTemplateRate(FreightTpl $tpl, $rule, array $group)
    {
        // 纯计算逻辑抽到静态方法 calcFee()，便于单元测试（运费算错影响每单，是高价值算钱点）
        return self::calcFee(
            (int)$tpl->price_type,
            (float)($group['total_weight'] ?? 0),
            (float)($group['total_num'] ?? 0),
            (float)$rule->first_num,
            (float)$rule->first_price,
            (float)$rule->other_num,
            (float)$rule->other_price
        );
    }

    /**
     * 运费计算（纯函数，无副作用，可单测）
     *
     * 规则：首件/首重固定费 + 续件/续重按段计费
     *   续费 = ceil((计费单位 - 首件数) / 续件数) × 续件单价
     * 用 bc 定点(精度2)避免浮点误差。
     *
     * @param int   $priceType   计费方式：1=按重量(total_weight)，其它=按件数(total_num)
     * @param float $totalWeight 总重量
     * @param float $totalNum    总件数
     * @param float $firstNum    首件/首重数量
     * @param float $firstPrice  首件/首重费用
     * @param float $otherNum    续件/续重数量（<=0 时按 1 兜底，防除零）
     * @param float $otherPrice  续件/续重单价
     * @return float 运费
     */
    public static function calcFee(
        int $priceType,
        float $totalWeight,
        float $totalNum,
        float $firstNum,
        float $firstPrice,
        float $otherNum,
        float $otherPrice
    ): float {
        $unitValue = ($priceType == 1) ? $totalWeight : $totalNum;
        if ($unitValue <= 0) {
            return 0.0;
        }

        $fee = bcadd((string)$firstPrice, '0', 2);          // 首件/首重
        if ($unitValue > $firstNum) {
            $extraValue = $unitValue - $firstNum;
            $on = $otherNum > 0 ? $otherNum : 1;            // 防除零
            $extraCount = ceil($extraValue / $on);
            $extraFee = bcmul((string)$extraCount, (string)$otherPrice, 2);
            $fee = bcadd($fee, $extraFee, 2);
        }

        return (float)$fee;
    }
}
