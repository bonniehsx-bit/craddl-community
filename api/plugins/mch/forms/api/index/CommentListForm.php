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
namespace app\plugins\mch\forms\api\index;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\GoodsComment;
use app\models\Order;
use yii\helpers\Json;

class CommentListForm extends BaseModel
{
    public $mch_id;
    public $page;
    public $limit;

    public function rules()
    {
        return [
            [['mch_id'], 'required'],
            [['mch_id', 'page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 10],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 1. 找出该商家的所有已完成、已评价的订单ID
        $orderIds = Order::find()->select('id')
            ->where([
                'mch_id' => $this->mch_id,
                'is_delete' => 0,
                'status' => Order::STATUS_COMPLETED,
                'comment_status' => 1
            ])
            ->column();

        if (empty($orderIds)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
                'list' => [],
                'pagination' => null,
            ]);
        }

        // 2. 根据订单ID查询评价
        $query = GoodsComment::find()->alias('gc')
            ->with(['user', 'goods']) // 预加载用户信息和商品信息
            ->where([
                'gc.mall_id' => \Yii::$app->mall->id,
                'gc.is_delete' => 0,
                'gc.order_id' => $orderIds
            ]);

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy('gc.created_at DESC')
            ->asArray()
            ->all();

        // 3. 格式化返回数据
        $formattedList = [];
        foreach ($list as $item) {
            $formattedList[] = [
                'user_name' => $item['user']['nickname'] ?? '匿名用户',
                'avatar' => $item['user']['avatar_url'] ?? '',
                'date' => date('Y-m-d', $item['created_at']),
                'grade_level' => $item['grade_level'],
                'content' => $item['content'],
                'images' => Json::decode($item['pic_list']) ?: [],
                'purchased' => $item['goods']['name'] ?? '未知商品',
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list' => $formattedList,
            'pagination' => $pagination,
        ]);
    }
}