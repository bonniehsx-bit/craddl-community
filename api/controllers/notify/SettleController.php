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

namespace app\controllers\notify;

use Yii;
use yii\helpers\Json;

class SettleController extends \yii\web\Controller
{
    public $enableCsrfValidation = false;

    public function init()
    {
        Yii::$app->isAdmin = false;
    }

    public function actionSettle()
    {
        $rawBody = Yii::$app->request->rawBody;
        Yii::warning('[SettleController] 结算回调: ' . $rawBody);

        try {
            $callbackData = Json::decode($rawBody);
            $data = $callbackData['data'] ?? null;
            if (!$data || empty($data['merOrderNo'])) {
                Yii::warning('[SettleController] 回调数据缺少 merOrderNo');
                return $this->asJson(['respCode' => 'FAIL', 'respDesc' => '缺少订单号']);
            }

            $order = \app\models\CommonPayOrder::findOne(['order_no' => $data['merOrderNo']]);
            if ($order) {
                Yii::warning("[SettleController] 结算订单 {$data['merOrderNo']} 已收到, status=" . ($data['status'] ?? 'unknown'));
            }
        } catch (\Exception $e) {
            Yii::error('[SettleController] 解析异常: ' . $e->getMessage());
        }

        return $this->asJson(['respCode' => 'SUCCESS', 'respDesc' => '订单结算已收到']);
    }
}
