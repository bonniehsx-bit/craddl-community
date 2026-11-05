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

namespace app\events;

use app\models\Goods;
use yii\base\Event;

/**
 * Class GoodsEvent
 * @package app\events
 */
class GoodsEvent extends Event
{
    /**
     * @var Goods $goods 触发当前事件的、完整的商品模型对象
     */
    public $goods;

    /**
     * @var array $pluginData [新增] 用于在主程序和插件之间传递数据
     * 场景1 (LOAD): 初始为空数组，插件往里填充配置信息
     * 场景2 (SAVE): 包含前端提交的所有插件数据，插件从中读取自己的数据
     */
    public $pluginData = [];

    // 定义事件名称常量（建议使用常量代替字符串，防止拼写错误）
    const GOODS_EDIT_FORM_LOAD = 'GOODS_EDIT_FORM_LOAD';
    const GOODS_AFTER_SAVE = 'GOODS_AFTER_SAVE';
}