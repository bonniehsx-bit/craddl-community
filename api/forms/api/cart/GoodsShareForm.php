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
namespace app\forms\api\cart;

use app\models\BaseModel;
use app\services\cart\GoodsShareService;

class GoodsShareForm extends BaseModel
{
    public $token;
    public $title = '';
    public $note = '';
    public $items;
    public $request_key;
    public $mode = 'cart';

    public function rules()
    {
        return [
            [['token'], 'match', 'pattern' => '/^[a-f0-9]{32}$/D'],
            [['request_key'], 'match', 'pattern' => '/^[a-zA-Z0-9_-]{16,32}$/D'],
            [['title', 'note'], 'trim'],
            [['title'], 'string', 'max' => 60],
            [['note'], 'string', 'max' => 200],
            [['mode'], 'in', 'range' => ['cart', 'checkout']],
            [['items'], 'safe'],
            [['page'], 'integer', 'min' => 1],
        ];
    }

    public function run(string $operation): array
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        // 不信任请求里的 mall_id/user_id；公开详情也只能在当前商城读取。
        $mallId = (int) \Yii::$app->mall->id;
        $userId = (int) (\Yii::$app->user->id ?? 0);
        $service = new GoodsShareService();
        try {
            switch ($operation) {
                case 'create':
                    $data = $service->create($mallId, $userId, $this->items, $this->title, $this->note);
                    break;
                case 'detail':
                    $data = $service->detail($mallId, (string) $this->token);
                    break;
                case 'view':
                    $service->recordView($mallId, $userId, (string) $this->token);
                    $data = [];
                    break;
                case 'import':
                    $data = $service->import($mallId, $userId, (string) $this->token, $this->items, (string) $this->request_key, $this->mode);
                    break;
                case 'mine':
                    $data = $service->mine($mallId, $userId, (int) $this->page);
                    break;
                default:
                    throw new \InvalidArgumentException('操作不存在');
            }
            return $this->apiResponse(0, '', $data);
        } catch (\InvalidArgumentException $e) {
            return $this->apiResponse(1, $e->getMessage());
        } catch (\Throwable $e) {
            \Yii::error('好物分享操作失败：' . get_class($e), 'goods-share');
            return $this->apiResponse(1, '操作失败，请稍后重试');
        }
    }
}
