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

namespace app\plugins\mch\forms\admin\order;

use app\forms\mall\order\OrderExportForm;
use Yii;

class MchOrderExportForm extends OrderExportForm
{
    public function asyncExport()
    {
        // 覆盖 mch_id，确保商家只能导出自己的订单，防止参数篡改
        // 必须取 MchFilter 注入的 Yii::$app->mch:Yii::$app->mchId 在商户端恒为 0,
        // 原来导出的是本商城全部平台自营单(含买家姓名电话地址),自己的单反而一条没有
        $this->mch_id = (int)Yii::$app->mch->id;
        return parent::asyncExport();
    }
}
