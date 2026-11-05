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

namespace app\plugins\withdraw\controllers\admin;

use app\plugins\ApiController;
use app\plugins\withdraw\forms\admin\setting\WalletSettingForm;
use Yii;

/**
 * 后台「扩展钱包设置」(提现中心 → 扩展钱包设置)
 * 路由:/plugin/withdraw/admin/wallet-setting/config
 *
 * 管的是本商城要不要挂载各个扩展钱包、以及它们能不能提现;
 * 钱包余额本身不在这里动(那走用户资产管理 → WalletService)。
 */
class WalletSettingController extends ApiController
{
    /**
     * GET  读取本商城已授权插件声明的钱包及其开关
     * POST 保存开关
     */
    public function actionConfig()
    {
        $form = new WalletSettingForm();
        if (Yii::$app->request->isPost) {
            $form->attributes = Yii::$app->request->post();
            return $this->asJson($form->save());
        }
        return $this->asJson($form->getList());
    }
}
