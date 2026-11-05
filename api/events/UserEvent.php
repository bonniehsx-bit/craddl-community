<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-30
 * Time: 15:13
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

class UserEvent extends Event
{

    const LOGIN = 'user.login';
    const PARENT_CHANGE = "user.parent.change";
    const REGISTER = 'user.register'; // 新用户首次注册落库;插件可监听(如营销新人礼包即时到账)
    // 扩展钱包(user_wallet)被后台手动增减后触发;声明该钱包的插件监听做副作用(同步状态/通知等),
    // 核心只负责通用增减,不认识具体插件钱包语义。
    const WALLET_CHANGED = 'user.wallet.changed';

    public $user_id;

    // —— WALLET_CHANGED 专用载荷(其它事件为空)——
    public $wallet_key;      // 钱包 key(如 xwy.channel)
    public $way;             // 1=增加 2=减少
    public $amount;          // 变更金额(正数,两位小数字符串)
    public $balance_after;   // 变更后余额
    public $admin_id;        // 操作管理员 ID
    public $remark;          // 变更备注
}
