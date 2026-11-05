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

use app\events\GoodsEvent;
use app\models\Goods;
use Yii;

class GoodsEventDispatcherPipe
{
    /**
     * @param GoodsContext $context
     * @param \Closure $next
     * @return mixed
     */
    public function handle($context, \Closure $next)
    {
        // 核心修复：还原触发带有插件配置载荷 (如: 分销 share，会员价 vip) 的广播事件
        $goodsEvent = new GoodsEvent();
        $goodsEvent->goods = $context->goods;
        // 尝试从表单实体获取前端提交的 plugins_data
        $goodsEvent->pluginData = property_exists($context->form, 'plugins_data') ? $context->form->plugins_data : [];
        Yii::$app->trigger(GoodsEvent::GOODS_AFTER_SAVE, $goodsEvent);

        // 兼容遗留的全局编辑事件
        $legacyEvent = new \app\events\GoodsEvent();
        $legacyEvent->goods = $context->goods;
        Yii::$app->trigger(Goods::EVENT_EDIT, $legacyEvent);
        
        return $next($context);
    }
}
