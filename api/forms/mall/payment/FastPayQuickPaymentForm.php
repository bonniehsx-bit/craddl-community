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

namespace app\forms\mall\payment;

use app\core\ApiCode;
use app\forms\mall\payment\PaymentModel;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\payments\FastPayQuickPayment; // 引用快捷支付网关类

class FastPayQuickPaymentForm extends PaymentModel
{
    public $setting;

    public function rules()
    {
        return [
            [['setting'], 'safe'],
        ];
    }

    /**
     * 保存支付配置
     */
    public function save()
    {
        $payment = $this->payment;


        // 绑定支付类型类名
        $this->setting['payment_type'] = FastPayQuickPayment::class;

        // ======================= [核心修复] 开始 =======================

        // 使用 null 合并运算符 (??) 来确保旧配置永远是一个数组
        // 这里获取的是 FastPayQuickPayment 对应的旧配置
        $oldSetting = $this->getPayment(FastPayQuickPayment::class) ?? [];

        $payment[FastPayQuickPayment::class] = array_merge(
            $oldSetting,    // 保证是一个数组 (旧配置或空数组)
            $this->setting  // 新提交的配置
        );

        // ======================= [核心修复] 结束 =======================

        OptionHelper::set('payments', $payment, \Yii::$app->mall->id);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    /**
     * 查询并显示支付配置
     */
    public function search()
    {
        $this->setting = $this->getPayment(FastPayQuickPayment::class);
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', $this->setting);
    }
}