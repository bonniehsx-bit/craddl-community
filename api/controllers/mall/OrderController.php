<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-02
 * Time: 19:39
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


use app\forms\mall\order\ExcelSendForm;
use app\forms\mall\order\ExportOrderDetailForm;
use app\forms\mall\order\ExpressLogEorderForm;
use app\forms\mall\order\ExpressLogListForm;
use app\forms\mall\order\OrderAddressModifyForm;
use app\forms\mall\order\OrderExportForm;
use app\forms\mall\order\OrderExpressUpdateForm;
use app\forms\mall\order\OrderForm;
use app\forms\mall\order\OrderVerifyForm;
use app\forms\mall\order\OrderListForm;
use app\forms\mall\order\OrderPushLogListForm;
use app\forms\mall\order\OrderRefundForm;
use app\forms\mall\order\OrderRefundListForm;
use app\forms\mall\order\OrderSendForm;
use app\models\Order;
use app\services\OrderService;
use app\services\RefundService;
use Yii;

class OrderController extends Controller
{
    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-02
     * @Time: 19:40
     * @Note:订单列表
     */
    public function actionList()
    {
        $form = new OrderListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 订单列表「按插件订单类型」筛选项 —— 仅返回本商城【授权插件】声明的订单类型。
     * 数据源 OrderTypeRegistry::all() 已内置 resolveAuthorizedPluginNames 过滤。
     */
    public function actionOrderTypes()
    {
        return $this->asJson([
            'code' => 0,
            'msg'  => '',
            'data' => ['list' => \app\services\order\OrderTypeRegistry::all()],
        ]);
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-02
     * @Time: 22:18
     * @Note:订单详情
     */
    public function actionDetail()
    {
        $form = new OrderForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-03
     * @Time: 10:18
     * @Note:修改收货信息
     */
    public function actionAddressModify()
    {
        $form = new OrderAddressModifyForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-03
     * @Time: 15:39
     * @Note:发货
     * @return \yii\web\Response
     */
    public function actionSend()
    {
        $form = new OrderSendForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-03
     * @Time: 15:36
     * @Note:确认收货
     */
    public function actionConfirm()
    {
        $form = new OrderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->confirm());
    }

    /**
     * @Note: 强制核销（PC总控或商家端主动验证自提/无需物流订单）
     */
    public function actionVerifyPickup()
    {
        $form = new OrderVerifyForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->verify());
    }

    /**
     * Created by: ganxh
     * Date: 2021/11/9
     * Time: 14:18
     * Note:订单结束
     * @return \yii\web\Response
     */
    public function actionFinish()
    {
        $form = new OrderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->finish());
    }

    // 在 OrderController 类中，增加以下 action

    /**
     * [新增] 强制结算订单
     */
    public function actionForceSettle()
    {
        $order_id = Yii::$app->request->post('order_id');
        // 只认本商城的单:原来按 id 直接查,拿别家商城的订单号也能结算
        $order = Order::findOne(['id' => (int)$order_id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);

        if (!$order) {
            return $this->asJson(['code' => 1, 'msg' => '订单不存在']);
        }
        // 有售后还在处理就先不结 —— 与定时结算同一个判断,钱结了又要退、可能扣成负数
        $afterSale = OrderService::afterSaleBlockReason($order);
        if ($afterSale !== '') {
            return $this->asJson(['code' => 1, 'msg' => $afterSale]);
        }

        $service = new OrderService();
        if ($service->orderSettle($order)) {
            return $this->asJson(['code' => 0, 'msg' => '订单已强制结算']);
        } else {
            return $this->asJson(['code' => 1, 'msg' => $service->getErrorMsg()]);
        }
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-12-28
     * @Time: 23:46
     * @Note:关闭订单
     * @return \yii\web\Response
     */
    public function actionClose()
    {
        $form = new OrderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->close());
    }

    public function actionPay()
    {
        $form = new OrderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->pay());
    }

    /**
     * 获取售后订单列表
     */
    public function actionRefund()
    {
        $form = new OrderRefundListForm();
        $form->attributes = Yii::$app->request->get();
        return $this->asJson($form->getList());
    }


    /**
     * 审核售后申请 (同意/拒绝)
     */
    public function actionRefundStatus()
    {
        $form = new OrderRefundForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->save());
    }

    /**
     * 确认退款打款
     */
    public function actionRefundPay()
    {
        $form = new OrderRefundForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->pay());
    }

    public function actionExpressLog()
    {
        $form = new ExpressLogListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 发货后修改快递公司/单号
     */
    public function actionExpressUpdate()
    {
        $form = new OrderExpressUpdateForm();
        $form->attributes = $this->request->post();
        return $form->save();
    }

    public function actionRefundExchangeComplete()
    {
        $form = new OrderRefundForm();
        $form->attributes = \Yii::$app->request->post();
        return $this->asJson($form->exchangeComplete());
    }

    public function actionExpressLogEorder()
    {
        $form = new ExpressLogEorderForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $this->asJson($form->setEorder());
        }
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2022-01-19
     * @Time: 19:08
     * @Note:订单批量发货
     * @return \yii\web\Response
     */
    public function actionExcelSend()
    {
        $form = new ExcelSendForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->send());
    }



    /**
     * [新增] 异步导出 - 触发任务
     * 前端调用此接口，获取任务 token
     */
    public function actionAsyncExport()
    {
        $form = new OrderExportForm();
        $form->attributes = $this->request->get();
        // 调用 Form 中的 asyncExport 方法，投递队列任务
        return $this->asJson($form->asyncExport());
    }

    /**
     * [新增] 异步导出 - 查询状态
     * 前端轮询调用此接口，检查任务进度和获取下载链接
     */
    public function actionExportStatus()
    {
        $form = new OrderExportForm();
        $token = $this->request->get('token');
        // 调用 Form 中的 checkStatus 方法读取缓存
        return $this->asJson($form->checkStatus($token));
    }

    public function actionRemarks()
    {

        $form = new OrderForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->remarks());
    }

    public function actionOrderPushLog()
    {
        $form = new OrderPushLogListForm();
        $form->attributes = $this->request->get();
        return $form->getList();
    }

    /**
     * 手动重推微信履约
     * POST /mall/order/push-retry
     */
    public function actionPushRetry()
    {
        $form = new OrderPushLogListForm();
        return $this->asJson($form->retry());
    }

    public function actionExportDetails()
    {
        $form = new ExportOrderDetailForm();
        $form->attributes = $this->request->get();
        return $form->export();
    }
}
