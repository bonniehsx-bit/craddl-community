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
 * 分佣类型收集事件
 *
 * 用途：由 CommissionTypeRegistry::getAll() 在汇总类型时触发，
 *       各业务/插件 listener 响应此事件，往 $types 里追加自己的类型元数据。
 *
 * listener 写法示例：
 *
 *   public function register() {
 *       Yii::$app->on(CommissionTypeEvent::EVENT_COLLECT, function(CommissionTypeEvent $e) {
 *           $e->add('yuda_order', [
 *               'label' => '遇搭拼单分润',
 *               'tag_type' => 'success',
 *               'rate_label' => '商家分润比例',
 *               'rate_desc_mode' => 'commission',
 *               'group' => 'yuda',
 *           ]);
 *       });
 *   }
 */
class CommissionTypeEvent extends Event
{
    const EVENT_COLLECT = 'COMMISSION_TYPE_COLLECT';

    /**
     * 收集容器 [type => metaArray]
     * listener 通过 add()/$types 追加
     * @var array
     */
    public $types = [];

    /**
     * 当前触发的商城 ID（listener 可按需区分 mall）
     * @var int|null
     */
    public $mallId;

    /**
     * 追加一个类型。重复 key 时以最后一次 add 为准（便于覆盖内置元数据）。
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
