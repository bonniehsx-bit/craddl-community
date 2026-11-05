<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2022-05-26
 * Time: 19:31
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

use app\controllers\BaseController;
use app\helpers\OptionHelper;
use app\helpers\WechatHelper;
use app\models\CommonPayRefund;

// 【核心】引入新流水表
use Yii;
use yii\helpers\Json;

class RefundController extends BaseController
{
    public $enableCsrfValidation = false;

    public function actionIndex()
    {
        $logPrefix = '[微信 退款回调]';
        Yii::warning("====== {$logPrefix} 开始处理 ======");

        // 1. 尝试获取 JSON 数据 (可能是某些聚合支付或银行通道)
        $rawBody = $this->request->getRawBody();
        // --- 场景: 微信原生 XML 回调 ---
        $xml = $rawBody;
        if (!$xml) {
            return '回调数据为空';
        }

        // 解析 XML 获取 mch_id 以便加载配置
        $res = WechatHelper::xmlToArray($xml);
        if ($res && isset($res['mch_id'])) {
            Yii::warning("{$logPrefix} 收到微信XML回调，商户号: {$res['mch_id']}");

            // 加载对应商户号的支付配置
            $payment = OptionHelper::get('wechat_mch_' . $res['mch_id']);
            if (!$payment) {
                Yii::error("{$logPrefix} 未找到商户号 {$res['mch_id']} 的配置信息");
                return;
            }

            // 配置微信 SDK
            Yii::$app->params['wechatPaymentConfig'] = [
                'app_id' => $payment['wechat_app_id'],
                'mch_id' => $payment['wechat_mch_id'],
                'secret_key' => !empty($payment['wechat_v3_key']) ? $payment['wechat_v3_key'] : $payment['wechat_pay_secret'], // API V3 密钥
                'v2_secret_key' => $payment['wechat_pay_secret'], // API V2 密钥
                // API 证书
                'certificate' => $payment['wechat_cert_pem_path'],
                'private_key' => $payment['wechat_key_pem_path'],
                'notify_url' => '',
            ];

            $app = Yii::$app->wechat->payment;

            try {
                $server = $app->getServer();

                // 处理退款通知 (EasyWeChat V6 Server 自动验证签名并解析数据)
                $server->handleRefunded(function ($message) use ($logPrefix) {
                    if (!$message) {
                        Yii::error("{$logPrefix} 解密失败或无解密数据");
                        return;
                    }

                    Yii::warning("{$logPrefix} 解密成功: " . Json::encode($message));

                    // 1. 获取商户退款单号 (我们在 RefundTask 里生成的 out_refund_no)
                    // V6 的 message 通常是一个数组或者具有魔术访问属性的对象
                    $outRefundNo = $message['out_refund_no'] ?? null;

                    if (!$outRefundNo) {
                        Yii::error("{$logPrefix} 无法从通知数据中获取 out_refund_no");
                        return;
                    }

                    // 2. 查找流水记录
                    /** @var CommonPayRefund $refundTask */
                    $refundTask = CommonPayRefund::findOne(['out_refund_no' => $outRefundNo]);

                    if (!$refundTask) {
                        Yii::warning("{$logPrefix} 未找到对应流水: {$outRefundNo}");
                        return; // 告诉微信不要再发了，虽然我们没找到单子
                    }

                    // 3. 根据微信返回的状态更新流水
                    // refund_status: SUCCESS-退款成功, CHANGE-退款异常, REFUNDCLOSE-退款关闭
                    $wxStatus = $message['refund_status'] ?? '';

                    if ($wxStatus === 'SUCCESS') {
                        if ($refundTask->status != CommonPayRefund::STATUS_SUCCESS) {
                            // 状态变化：PENDING/PROCESSING/FAIL → SUCCESS
                            // 意味着 cron 没有同步处理成功（比如超时抛异常被标 FAIL），现在由异步回调兜底把业务状态闭环
                            $refundTask->status = CommonPayRefund::STATUS_SUCCESS;
                            $refundTask->updated_at = time();
                            if (empty($refundTask->success_at)) {
                                $refundTask->success_at = time();
                            }
                            $refundTask->save();
                            Yii::warning("{$logPrefix} 流水 {$refundTask->id} 确认退款成功");

                            // 【核心】触发业务层同步（关单/售后完成/佣金扣回等）
                            // 避免 cron 已标 FAIL 但网关实际成功的场景下，订单状态永远不闭环
                            try {
                                \app\services\RefundSuccessDispatcher::dispatch($refundTask);
                            } catch (\Exception $e) {
                                Yii::error("{$logPrefix} 退款成功但业务回写失败 ID:{$refundTask->id} - " . $e->getMessage());
                            }
                        }
                        // else: cron 已经处理过了，notify 只是再来一次确认，幂等跳过
                    } else {
                        // 退款异常 (如银行卡注销等，资金会退回商户余额)
                        // 标记为失败，允许管理员在后台重置或人工介入
                        if ($refundTask->status != CommonPayRefund::STATUS_FAIL) {
                            $refundTask->status = CommonPayRefund::STATUS_FAIL;
                            $refundTask->error_msg = "微信状态:{$wxStatus}";
                            $refundTask->updated_at = time();
                            $refundTask->save();
                            Yii::warning("{$logPrefix} 流水 {$refundTask->id} 退款异常: {$wxStatus}");
                        }
                    }
                });

                $response = $server->serve();
                Yii::$app->response->statusCode = $response->getStatusCode();
                Yii::$app->response->content = $response->getBody()->getContents();
                Yii::$app->response->send();
                return;
            } catch (\Exception $e) {
                Yii::error("{$logPrefix} 处理回调异常: " . $e->getMessage());
                return;
            }
        }

        Yii::warning("{$logPrefix} 无法识别的请求格式");
    }
}
