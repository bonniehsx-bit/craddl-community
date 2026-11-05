<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
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

namespace app\controllers\mall;

use app\core\ApiCode;
use app\forms\mall\finance\BalanceLogListForm;

// [新增]
use app\forms\mall\finance\ChargeListForm;
use app\forms\mall\finance\CommissionLogListForm;
use app\forms\mall\finance\IntegralLogListForm;
use app\forms\mall\finance\PriceLogListForm;
use app\forms\mall\finance\RefundFailListForm;
use app\forms\mall\finance\RefundLogListForm;
use app\forms\mall\finance\RefundRetryForm;
use app\helpers\CacheHelper;
use Yii;

class FinanceController extends Controller
{

    // ======================= [核心新增] 异步导出接口 =======================



    // ======================= [核心新增] 结束 =======================






    public function actionPriceLog()
    {
        $form = new PriceLogListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionBalanceLog()
    {
        $form = new BalanceLogListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionCharge()
    {
        $form = new ChargeListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionIntegralLog()
    {
        $form = new IntegralLogListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionRefundFail()
    {
        $form = new RefundFailListForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    public function actionRefundRetry()
    {
        $form = new RefundRetryForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->retry());
    }

    /**
     * 「退款失败」处理台的操作:查询结果 / 重新发起 / 登记已退款
     * 路由: /mall/finance/refund-handle (POST: id, action, remark, voucher, verified)
     */
    public function actionRefundHandle()
    {
        $form = new \app\forms\mall\finance\RefundHandleForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->handle());
    }

    /**
     * 需要人工处理的退款条数(后台账户菜单的数字)
     * 路由: /mall/finance/refund-attention
     */
    public function actionRefundAttention()
    {
        return $this->asJson(['code' => 0, 'msg' => '', 'data' => [
            'count' => \app\services\RefundAttentionService::count((int)\Yii::$app->mall->id),
        ]]);
    }

    public function actionCommissionLog()
    {
        $form = new CommissionLogListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->search());
    }

    /**
     * 退款流水记录列表
     * 路由: /mall/finance/refund-log
     */
    public function actionRefundLog()
    {
        $form = new RefundLogListForm();
        $form->attributes = \Yii::$app->request->get();
        return $form->getList();
    }

    /**
     * 1. 提交佣金记录导出任务
     */
    public function actionPriceLogAsyncExport()
    {
        $form = new PriceLogListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->asyncExport());
    }

    /**
     * 2. 佣金记录导出进度查询
     */
    public function actionPriceLogExportStatus()
    {
        $token = \Yii::$app->request->get('token');


        $cacheKey = 'price_log_export_status_' . $token;
        $data = CacheHelper::get($cacheKey);


        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'data' => $data ?: [
                'status' => 'waiting',
                'percentage' => 0,
                'msg' => '任务排队中...'
            ]
        ]);
    }

    /**
     * 余额记录 - 异步导出提交
     */
    public function actionBalanceLogAsyncExport()
    {
        $form = new \app\forms\mall\finance\BalanceLogListForm();
        $form->attributes = \Yii::$app->request->get();
        return $this->asJson($form->asyncExport());
    }

    /**
     * 余额记录 - 导出状态查询
     */
    public function actionBalanceLogExportStatus()
    {
        $token = \Yii::$app->request->get('token');
        // 注意：这里的 Key 必须和 BalanceLogExportJob 中的一致
        $cacheKey = 'balance_log_export_status_' . $token;

        // ★ 必须用 get(读取并保留)，不能用 remove(删除并返回 0/1)
        //   remove 会导致前端轮询永远拿不到 status，进度条卡死、success 时也下不了
        //   与 priceLog / commissionLog 的同类 action 保持一致
        $data = CacheHelper::get($cacheKey);

        return $this->asJson([
            'code' => \app\core\ApiCode::CODE_SUCCESS,
            'data' => $data ?: [
                'status' => 'waiting',
                'percentage' => 0,
                'msg' => '任务排队中...'
            ]
        ]);
    }

    /**
     * [接口] 触发异步导出任务
     * 对应前端: /mall/finance/commission-log-async-export
     */
    public function actionCommissionLogAsyncExport()
    {
        $form = new CommissionLogListForm();
        $form->attributes = \Yii::$app->request->get();
        // 调用我们刚刚写的 asyncExport 方法
        return $this->asJson($form->asyncExport());
    }

    /**
     * [接口] 查询导出进度状态
     * 对应前端: /mall/finance/commission-log-export-status
     */
    public function actionCommissionLogExportStatus()
    {
        $token = \Yii::$app->request->get('token');

        // 必须与 Job 中的 Cache Key 保持绝对一致
        $cacheKey = 'commission_log_export_status_' . $token;
        $data = CacheHelper::get($cacheKey);

        // 如果缓存不存在（可能刚提交还没写入，或者过期），返回等待状态
        if (!$data) {
            return $this->asJson([
                'code' => ApiCode::CODE_SUCCESS,
                'data' => [
                    'status' => 'waiting',
                    'percentage' => 0,
                    'msg' => '任务初始化中...'
                ]
            ]);
        }

        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'data' => $data
        ]);
    }
}
