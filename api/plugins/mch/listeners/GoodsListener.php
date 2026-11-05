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

namespace app\plugins\mch\listeners;

use app\events\GoodsEvent;
use app\listeners\BaseListener;
use Yii;

/**
 * mch 插件商品事件监听器
 *
 * 历史版本曾经监听 GOODS_EDIT_FORM_LOAD 事件给前端下发一个独立的"所属商家" tab
 * (plugin-mch-goods-setting)，并监听 GOODS_AFTER_SAVE 事件把 plugins_data['mch'].mch_id
 * 同步回 goods.mch_id。
 *
 * 现在 mch_picker 已内置到 TabBasic 顶部，直接绑定 form.mch_id，由 GoodsBaseSavePipe
 * 通过标准的 $goods->attributes = $form->attributes 同步——不再需要任何中间层。
 *
 * 此 Listener 暂时保留为空壳，避免 Plugin 注册流程报错；后续可以彻底删除注册。
 */
class GoodsListener extends BaseListener
{
    public function register()
    {
        // 不再监听任何事件，mch_picker 全程走标准字段同步
    }
}
