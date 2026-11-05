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
 * 积分业务类型收集事件(镜像 CommissionTypeEvent)
 *
 * 用途:由 IntegralTypeRegistry::getAll() 汇总类型时触发,各业务/插件 listener 响应此事件,
 *       往 $types 里追加自己的积分类型元数据。新增一种积分来源/去向无需改核心枚举。
 *
 * listener 写法示例:
 *   Yii::$app->on(IntegralTypeEvent::EVENT_COLLECT, function (IntegralTypeEvent $e) {
 *       $e->add('order_base', [
 *           'label'     => '消费获积分',
 *           'direction' => 'add',        // add=获取 / sub=消耗
 *           'revertible'=> true,          // 是否可撤销(退款回收)
 *           'group'     => 'consume',
 *       ]);
 *   });
 */
class IntegralTypeEvent extends Event
{
    const EVENT_COLLECT = 'INTEGRAL_TYPE_COLLECT';

    /**
     * 收集容器 [type => metaArray]
     * @var array
     */
    public $types = [];

    /** @var int|null 当前触发的商城 ID */
    public $mallId;

    /**
     * 追加一个类型。重复 key 以最后一次为准(便于覆盖内置元数据)。
     */
    public function add(string $type, array $meta): self
    {
        $type = trim($type);
        if ($type !== '') {
            $this->types[$type] = $meta;
        }
        return $this;
    }
}
