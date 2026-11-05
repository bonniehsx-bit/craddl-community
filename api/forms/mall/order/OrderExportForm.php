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

namespace app\forms\mall\order;

use app\core\ApiCode;
use app\helpers\CacheHelper;
use app\models\BaseModel;
use app\models\Order;
use app\models\User;
use app\models\UserAddress;
use yii\helpers\Json;

/**
 * 导出订单列表 (查询逻辑已与 OrderListForm 同步)
 */
class OrderExportForm extends BaseModel
{
    // === 基础字段 ===
    public $page;
    public $limit;
    public $status;
    public $keyword_type; // 保留字段但不处理复杂逻辑
    public $keyword;      // 保留字段但不处理复杂逻辑
    public $search_date;
    public $user_id;
    public $parent_id;
    public $referrer_id; // 按推荐人筛:订单上的推荐人快照,与列表一致
    public $is_export;

    // === 同步 OrderListForm 的字段 ===
    public $payment;
    public $deliver_type;
    public $mch_id;
    public $type;
    public $order_no;
    public $mobile;       // 新增：收件人手机
    public $buyer_mobile; // 新增：购买人手机
    public $nickname;     // 买家昵称,模糊匹配
    public $plugin_type;  // 插件订单类型(注册表 key)
    public $search_date_type; // 时间搜索类型

    // === 导出专用字段 ===
    public $order_ids;
    public $export_fields;
    

    public function rules()
    {
        return [
            [['keyword_type', 'keyword', 'payment', 'deliver_type', 'type', 'export_fields', 'order_no', 'mobile', 'buyer_mobile', 'nickname', 'plugin_type'], 'string'],
            [['keyword'], 'trim'],
            [['page', 'limit', 'user_id', 'parent_id', 'referrer_id', 'is_export'], 'integer'],
            [['status', 'mch_id', 'order_ids'], 'safe'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 10],
            [['status'], 'default', 'value' => -1],
            [['search_date'], 'safe'],
            [['search_date_type'], 'default', 'value' => 'created_at'],
        ];
    }



    public function asyncExport()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $token = md5(uniqid() . time() . rand(1000, 9999));
        $params = $this->attributes;

        // 推送任务队列
        try {
            \app\helpers\JobHelper::push(new \app\jobs\order\OrderExportJob([
                'params' => $params,
                'token' => $token,
                'mall_id' => \Yii::$app->mall->id
            ]));
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => '创建导出任务失败：' . $e->getMessage()
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '导出任务已创建',
            'data' => [
                'token' => $token
            ]
        ];
    }

    public function checkStatus($token)
    {
        $cacheKey = 'order_export_status_' . $token;
        $data = CacheHelper::get($cacheKey);

        if (!$data) {
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'data' => ['status' => 'waiting', 'percentage' => 0]
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => $data
        ];
    }
}