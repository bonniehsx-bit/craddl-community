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

namespace app\plugins\distribution\listeners;

use app\events\GoodsEvent;
use app\listeners\BaseListener;
use app\plugins\distribution\forms\mall\GoodsDistributionForm;
use Yii;

class GoodsListener extends BaseListener
{
    /**
     * 注册监听器
     */
    public function register()
    {
        Yii::$app->on(GoodsEvent::GOODS_EDIT_FORM_LOAD, [$this, 'editLoad']);
        Yii::$app->on(GoodsEvent::GOODS_AFTER_SAVE, [$this, 'save']);
    }

    /**
     * 加载时注入配置
     */
    public function editLoad(GoodsEvent $event)
    {
        $goodsId = $event->goods ? $event->goods->id : 0;
        try {
            // 首先判断全局开关
            $globalSetting = \app\helpers\OptionHelper::get('distribution_setting', $event->goods ? $event->goods->mall_id : \Yii::$app->mall->id);
            if (!isset($globalSetting['is_enable']) || $globalSetting['is_enable'] != 1) {
                return;
            }

            $form = new GoodsDistributionForm();
            $setting = $form->getSetting($goodsId);

            $event->pluginData[] = [
                'key' => 'distribution',
                'label' => '分销设置',
                'component_name' => 'plugin-distribution-goods-setting',
                'data' => $setting,
                'sort' => 10
            ];
        } catch (\Exception $e) {
            Yii::error("分销插件加载配置失败: " . $e->getMessage());
        }
    }

    /**
     * 保存时提取数据
     * [重点修改] 增加了兼容不同数据结构的查找逻辑
     */
    public function save(GoodsEvent $event)
    {
        $pluginData = $event->pluginData;
        $distributionData = null;

        if (isset($pluginData['distribution'])) {
            $distributionData = $pluginData['distribution'];
        } // --- 2. 尝试遍历查找 (List结构: [['key'=>'distribution', 'data'=>[...]]]) ---
        else if (is_array($pluginData)) {
            foreach ($pluginData as $item) {
                // 判断当前项是否属于 distribution 插件
                if (isset($item['key']) && $item['key'] === 'distribution') {
                    // 您的数据 dump 显示真实数据在 'data' 字段里
                    if (isset($item['data']) && is_array($item['data'])) {
                        $distributionData = $item['data'];
                    } else {
                        // 兜底：如果没有 data 字段，可能 item 本身就是数据
                        $distributionData = $item;
                    }
                    break; // 找到了就停止循环
                }
            }
        }

        // --- 3. 如果找到了数据，执行保存 ---
        if ($distributionData) {
            $goodsId = $event->goods->id;
            try {
                $form = new GoodsDistributionForm();
                $form->save($goodsId, $distributionData);
            } catch (\Exception $e) {
                Yii::error("分销插件保存失败: " . $e->getMessage());
            }
        }
    }
}
