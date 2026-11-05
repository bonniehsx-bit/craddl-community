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

namespace app\common\helpers;

class WxWorkConst
{
    /**
     * 点击菜单回复内容（该字段商户可以根据自身需求自定义）
     */
    const CLICK_MENU_CONTENT = ">>>点击此处进行支付<<<";

    /**
     * 小卡片支付链接的ICON图标（该字段商户可以根据自身需求自定义）
     */
    const PAY_ICON_URL = "https://quickpay-dev.oss-cn-guangzhou.aliyuncs.com/quickpay/logo/dee2ce5762e84a87a6da62e17fb2e208.png";

    /**
     * CacheKey (适配Yii2缓存)
     */
    const ACCESS_TOKEN    = "wx_work:access_token:";      // access_token
    const KF_ACCOUNT_LIST = "wx_work:kf_account_list:";   // 客服账号列表
    const KF_NEXT_CURSOR  = "wx_work:kf_next_cursor:";    // 客服消息指针
    const WX_LOGO_ID      = "wx_work:wx_logo_id:";        // 消息图标MediaID
    const KF_INCREMENT_ID = "wx_work:kf_increment_id:";   // 自增ID
}