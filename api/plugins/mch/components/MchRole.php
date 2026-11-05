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

namespace app\plugins\mch\components;

use yii\base\BaseObject;

/**
 * Class MchRole
 * @package app\plugins\mch\components
 *
 * 一个简单的数据对象，用于存储当前登录用户在特定商家上下文中的角色信息。
 */
class MchRole extends BaseObject
{
    /**
     * @var bool 是否为管理员 (店长)
     */
    public $is_manager = false;

    /**
     * @var bool 是否为普通店员
     * 注意：一个用户可能是店长，此时 is_clerk 可以为 false
     */
    public $is_clerk = false;

    /**
     * @var \app\plugins\mch\models\MchClerk|null 完整的关系模型对象
     */
    public $clerkRelation;
}