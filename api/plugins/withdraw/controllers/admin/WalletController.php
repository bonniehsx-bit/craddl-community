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

use app\models\User;
use app\plugins\ApiController;
use app\plugins\withdraw\forms\admin\wallet\WalletAdjustForm;
use app\plugins\withdraw\forms\admin\wallet\WalletLogListForm;
use Yii;

/**
 * 后台 - 钱包流水(通用钱包入账/扣减明细)+ 手动充值/扣除
 * 路由:/plugin/withdraw/admin/wallet/index、/plugin/withdraw/admin/wallet/adjust
 */
class WalletController extends ApiController
{
    public function actionIndex()
    {
        $form = new WalletLogListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }

    /** 手动充值/扣除 */
    public function actionAdjust()
    {
        $form = new WalletAdjustForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /** 充值弹窗用户搜索:本商城,按昵称 / 手机号 / 用户ID */
    public function actionSearchUser()
    {
        $kw = trim((string)Yii::$app->request->get('keyword', ''));
        $list = [];
        if ($kw !== '') {
            $list = User::find()
                ->select(['id', 'nickname', 'mobile', 'avatar_url'])
                ->where(['mall_id' => (int)Yii::$app->mall->id, 'is_delete' => 0])
                ->andWhere(['or', ['like', 'nickname', $kw], ['like', 'mobile', $kw], ['id' => (int)$kw]])
                ->limit(20)
                ->asArray()
                ->all();
        }
        return $this->asJson(['code' => 0, 'msg' => '', 'data' => ['list' => $list]]);
    }
}
