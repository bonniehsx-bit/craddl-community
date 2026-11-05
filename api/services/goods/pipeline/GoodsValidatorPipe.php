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

use app\models\Cat;
use app\models\FreightTpl;

class GoodsValidatorPipe
{
    /**
     * @param GoodsContext $context
     * @param \Closure $next
     * @return mixed
     * @throws \Exception
     */
    public function handle($context, \Closure $next)
    {
        $form = $context->form;
        $mch = $context->mch;

        if (isset($context->goodsData['video_url']) && strpos($context->goodsData['video_url'], 'http') !== 0) {
            $context->goodsData['video_url'] = '';
        }
        // 放宽对特定插件商品的分类限制 (允许 lucky 免选分类)
        $targetType = $context->goodsType ?? $context->goods->type ?? '';
        $isCatRequired = !in_array($targetType, [\app\models\Goods::TYPE_BS, \app\models\Goods::TYPE_VOUCHER, 'integral', 'exchange', 'group', 'lucky', 'yuda']);
        
        if ($form->cat_id) {
            $platformCategory = Cat::findOne(['id' => $form->cat_id, 'mch_id' => 0, 'is_delete' => 0]);
            if (!$platformCategory) {
                throw new \Exception("选择的商品分类不存在或无效");
            }
        } elseif ($isCatRequired) {
            throw new \Exception("请选择一个商品分类");
        }

        // 运费安全校验
        // 运费模板是「商城级」资源（FreightForm / FreightListForm 全部按 mall_id 归属，
        // mch_id 列恒为 0 从未被写入），故此处必须按 mall_id 校验，不能用 mch_id。
        if ($form->express_type == 2) { // 运费模板
            if ($form->freight_id) {
                $tplExists = FreightTpl::find()->where([
                    'id' => $form->freight_id,
                    'mall_id' => \Yii::$app->mall->id,
                    'is_delete' => 0
                ])->exists();
                if (!$tplExists) {
                    throw new \Exception('选择的运费模板无效或不属于您');
                }
            }
        }

        // 受保护商品类型（如收银台的默认收银商品）由各插件 Plugin::getOrderBehaviors() 的
        // goods_protected 声明，核心不再硬编码 checkout。
        if ($context->goods && \app\services\order\OrderBehaviorRegistry::isGoodsProtected((string)$context->goods->type)) {
            throw new \Exception('默认收银商品不可操作');
        }

        return $next($context);
    }
}
