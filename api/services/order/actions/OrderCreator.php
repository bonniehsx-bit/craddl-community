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

namespace app\services\order\actions;

use app\core\ApiCode;
use app\core\Pipeline;
use app\forms\api\order\OrderSubmitForm;
use app\models\BaseModel;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\stages\creation\ConsumeResourcesStage;
use app\services\order\pipeline\stages\creation\CreateOrderRecordStage;
use app\services\order\pipeline\stages\creation\FinalizeIncomeStage;
use app\services\order\pipeline\stages\creation\InitializeHandlerStage;
use app\services\order\pipeline\stages\creation\LockResourcesStage;
use app\services\order\pipeline\stages\creation\ParseAndPreviewStage;
use app\services\order\pipeline\stages\creation\PostCreationStage;
use app\services\order\pipeline\stages\creation\ProcessOrderDetailsStage;
use Yii;

/**
 * 订单创建服务类 (Refactored to Pipeline)
 */
class OrderCreator extends BaseModel
{
    /** @var OrderSubmitForm */
    public $form;

    /** @var \app\models\User */
    public $user;

    public $mall_id;

    public function __construct($config = [])
    {
        parent::__construct($config);
        $this->user = Yii::$app->user->identity;
        $this->mall_id = $this->user->mall_id;
    }

    /**
     * 创建订单主入口
     * @return array
     */
    public function create()
    {
        $payload = new CreationPayload($this->form, $this->user);

        // 开启数据库事务
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $response = (new Pipeline())
                ->send($payload)
                ->through([
                        // 1. 解析与预览
                    ParseAndPreviewStage::class,
                        // 2. 锁定资源 (悲观锁)
                    LockResourcesStage::class,
                        // 3. 初始化 Handler 并校验库存
                    InitializeHandlerStage::class,
                        // 4. 创建主订单记录
                    CreateOrderRecordStage::class,
                        // 5. 处理订单详情与扣库存
                    ProcessOrderDetailsStage::class,
                        // 6. 计算最终收入归属
                    FinalizeIncomeStage::class,
                        // 7. 核销资源 (优惠券/积分)
                    ConsumeResourcesStage::class,
                        // 8. 收尾: 触发事件、生成支付单
                    PostCreationStage::class,
                ])
                ->then(function (CreationPayload $payload) {
                    return $payload->response;
                });

            $transaction->commit();

            return $response;

        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::error("订单创建异常: " . $e->getMessage() . "\n" . $e->getTraceAsString());

            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage(),
                'error' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ];
        }
    }
}