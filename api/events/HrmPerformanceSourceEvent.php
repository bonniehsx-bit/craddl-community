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
 * HRM 绩效来源收集事件
 *
 * 用途：由 HrmPerformanceSourceRegistry::getAll() 触发，
 *       各插件在自己的 listener 中响应此事件，注册自己作为绩效来源的元数据。
 *       HRM 本身不硬编码任何外部插件名称。
 *
 * listener 写法示例：
 *
 *   public function register() {
 *       Yii::$app->on(HrmPerformanceSourceEvent::EVENT_COLLECT, function(HrmPerformanceSourceEvent $e) {
 *           $e->add('plaza', ['label' => '商圈招商提成', 'tag_type' => 'warning']);
 *       });
 *   }
 */
class HrmPerformanceSourceEvent extends Event
{
    const EVENT_COLLECT = 'HRM_PERFORMANCE_SOURCE_COLLECT';

    /**
     * 收集容器 [source_plugin_key => metaArray]
     * @var array
     */
    public $sources = [];

    /**
     * 当前触发的商城 ID
     * @var int|null
     */
    public $mallId;

    /**
     * 注册一个来源。重复 key 时以最后一次 add 为准。
     */
    public function add(string $key, array $meta): self
    {
        $key = trim($key);
        if ($key !== '') {
            $this->sources[$key] = $meta;
        }
        return $this;
    }
}
