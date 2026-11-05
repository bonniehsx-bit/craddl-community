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

namespace app\services\order\pipeline\stages\creation;

use app\core\ApiCode;
use app\forms\api\order\OrderPreviewForm;
use app\services\OrderService;
use app\services\order\pipeline\CreationPayload;
use app\services\order\pipeline\StageInterface;
use yii\helpers\Json;

class ParseAndPreviewStage implements StageInterface
{
    public function handle($payload, \Closure $next)
    {
        /** @var CreationPayload $payload */

        // 1. 解析 form_data
        $payload->data = is_array($payload->form->form_data)
            ? $payload->form->form_data
            : Json::decode($payload->form->form_data);

        if (!is_array($payload->data)) {
            throw new \Exception('业务参数解析失败: form_data 格式错误');
        }

        // 2. 获取预览数据
        $previewResult = $this->getPreviewData($payload);
        if ($previewResult['code'] !== ApiCode::CODE_SUCCESS) {
            throw new \Exception($previewResult['msg'] ?? '订单预览失败');
        }
        $payload->previewData = $previewResult['data'];

        // 插件可自行计价，校验加工后的数量和金额，再进入资源锁定及建单。
        \app\services\order\OrderInputGuard::assertItems($payload->previewData['goods_list'] ?? null);
        foreach ($payload->previewData['goods_list'] as $item) {
            foreach (['price', 'total_price', 'pay_price'] as $field) {
                if (array_key_exists($field, $item)) {
                    \app\services\order\OrderInputGuard::assertAmount($item[$field]);
                }
            }
        }

        return $next($payload);
    }

    private function getPreviewData(CreationPayload $payload)
    {
        $previewForm = new OrderPreviewForm();
        $previewForm->plugin = $payload->form->plugin;
        $previewForm->type = $payload->form->type;
        $previewForm->form_data = $payload->data;

        try {
            $rawData = (new OrderService())->preview($previewForm);
            return ['code' => ApiCode::CODE_SUCCESS, 'data' => $rawData];
        } catch (\Exception $e) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }
}
