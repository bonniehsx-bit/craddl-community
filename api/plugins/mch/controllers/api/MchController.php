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
/**
 * Created by PhpStorm.
 * User: JUST
 * Date: 2022/6/28
 * Time: 14:35
 */

namespace app\plugins\mch\controllers\api;


use app\controllers\api\filters\LoginFilter;
use app\core\ApiCode;
use app\forms\mall\goods\AddAttrForm;
use app\forms\mall\goods\CatLevelForm;
use app\forms\mall\goods\DeleteAttrForm;
use app\helpers\ResponseHelper;
use app\plugins\ApiController;
use app\plugins\mch\forms\api\mch\CatListForm;
use app\plugins\mch\forms\api\mch\GoodsCatForm;
use app\plugins\mch\forms\api\mch\MchApplyForm;
use app\plugins\mch\forms\api\mch\MchGoodsListForm;
use app\plugins\mch\forms\api\mch\GoodsForm;
use app\plugins\mch\forms\api\mch\MchForm;
use app\plugins\mch\forms\api\mch\MchOrderListForm;
use app\plugins\mch\forms\api\mch\MchOrderSendForm;
use app\plugins\mch\forms\api\mch\MyGoodsListForm;
use app\plugins\mch\forms\api\mch\OrderClerkForm;
use app\plugins\mch\forms\api\mch\OrderForm;
use app\plugins\mch\forms\api\mch\OrderGoodsLogListForm;
use app\plugins\mch\forms\api\mch\OrderRefundForm;
use app\plugins\mch\forms\api\mch\OrderRefundListForm;
use app\plugins\mch\forms\api\mch\SetClerkForm;


class MchController extends ApiController
{
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [
            'login' => [
                'class' => LoginFilter::class,
                'ignore' => ['apply-data'], // 获取入驻配置不需要登录（分类等是公开信息）
            ]
        ]);
    }

    /**
     * 接收并处理商户入驻申请
     * @return \yii\web\Response
     */
    public function actionApply()
    {
        $form = new MchApplyForm();
        // 将POST请求的所有数据赋值给 form 模型
        $form->attributes = $this->request->post();
        // 调用 save 方法执行核心逻辑
        return $this->asJson($form->save());
    }

    public function actionApplyData()
    {

        $form = new MchApplyForm();
        // 将POST请求的所有数据赋值给 form 模型
        $form->attributes = $this->request->get();
        // 调用 save 方法执行核心逻辑
        return $this->asJson($form->get());
    }
}