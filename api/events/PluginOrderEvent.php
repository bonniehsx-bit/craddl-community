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

use yii\base\Event;

/**
 * PluginOrderEvent
 *
 * 在订单记录落库完成、form_data 仍在内存的时间窗口内触发。
 * 携带 order_id 和用户提交的 plugin_params，供插件读取并持久化自己的扩展数据。
 *
 * 触发位置：OrderCreateJob::execute() → createOrder() 成功之后
 * 事件名称：order.after_create（见 Order::EVENT_PLUGIN_AFTER_CREATE）
 */
class PluginOrderEvent extends Event
{
    /**
     * @var int 刚落库的订单 ID
     */
    public $order_id;

    /**
     * @var int 商城 ID
     */
    public $mall_id;

    /**
     * @var array 前端提交的插件扩展参数
     *            结构：['brand' => ['is_cloud_storage' => 1], 'other_plugin' => [...]]
     */
    public $plugin_params = [];

    /**
     * 便捷方法：读取指定插件的某个参数
     *
     * @param string $plugin  插件标识，如 'brand'
     * @param string $key     参数键名，如 'is_cloud_storage'
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function getParam(string $plugin, string $key, $default = null)
    {
        return $this->plugin_params[$plugin][$key] ?? $default;
    }
}
