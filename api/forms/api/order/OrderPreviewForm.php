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

namespace app\forms\api\order;

use app\core\ApiCode;
use app\models\BaseModel;
use app\services\OrderService;

/**
 * 订单预览表单 (DTO)
 * 职责：
 * 1. 接收 plugin, type, form_data
 * 2. 验证必填项
 * 3. 委托 OrderService 执行真正的预览逻辑
 */
class OrderPreviewForm extends BaseModel
{
    // === 路由参数 ===
    public $plugin;
    public $type;

    // === 业务参数 (JSON/Array) ===
    public $form_data;

    public function rules()
    {
        return [
            // 基础校验：form_data 必填
            [['form_data'], 'required', 'message' => '缺少业务参数'],
            [['plugin', 'type'], 'string'],
            [['form_data'], 'safe'],
        ];
    }

    /**
     * 统一入口
     * Controller 直接调用 $form->search()
     */
    public function search()
    {
        // 1. 基础校验 (只校验是否传了 form_data)
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 2. 【核心】直接委托给 Service
            // 把“自己”($this)传给 Service，Service 会读取 $this->form_data 并调用 Handler
            $data = (new OrderService())->preview($this);

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => 'success',
                'data' => $data
            ];

        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage()
            ];
        }
    }
}