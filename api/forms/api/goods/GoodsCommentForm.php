<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 20:10
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

namespace app\forms\api\goods;

use app\core\ApiCode;
use app\helpers\JobHelper;
use app\helpers\ResponseHelper;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\GoodsComment;
use app\models\Order;


use Yii;

// [核心] 2. 引入Yii
use yii\helpers\Json;

class GoodsCommentForm extends BaseModel
{
    public $data_list;
    public $order_id;

    public function rules()
    {
        return [
            [['order_id'], 'integer'],
            [['data_list'], 'required'],
            [['data_list'], 'safe'],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // --- 原有的逻辑保持不变 ---
        $order = Order::findOne(['user_id' => UserHelper::getMyId(), 'id' => $this->order_id, 'is_delete' => 0]);
        if (!$order) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单不存在');
        }
        if ($order->comment_times >= 2) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '订单已追评，不可继续评价');
        }
        $list = Json::decode($this->data_list);
        $t = \Yii::$app->db->beginTransaction();

        try { // [优化] 将主要逻辑包裹在 try...catch 中

            // 只有在首次评价时，才更新订单的评价次数和状态
            if ($order->comment_times < 1) {
                $order->comment_times += 1;
                $order->comment_status = 1; // 标记订单为已评价
            } else {
                $order->comment_times += 1;
            }

            foreach ($list as $item) {
                $comment = new GoodsComment();
                $comment->user_id = UserHelper::getMyId();
                $comment->mall_id = $this->mall_id;
                $comment->goods_id = $item['goods_id'];
                $comment->order_id = $this->order_id;
                $comment->content = $item['content'];
                if ($order->comment_times >= 2) {
                    $comment->is_second = 1;
                }
                $comment->pic_list = Json::encode($item['pic_list']);
                $comment->grade_level = $item['grade_level'];
                if (!$comment->save()) {
                    throw new \Exception($this->getErrorMsg($comment));
                }
            }

            if (!$order->save()) {
                throw new \Exception($this->getErrorMsg($order));
            }

            $t->commit();

            // ======================= [核心新增] 开始 =======================
            // 3. 只有在首次评价时，才触发商家评分更新
            // [优化] 移至事务提交后执行，防止事务回滚但任务已下发
            if ($order->comment_times == 1) {
                JobHelper::push(new \app\jobs\order\UpdateMchRatingJob(['order_id' => $order->id]));
            }
            // ======================= [核心新增] 结束 =======================

            return $this->apiResponse(ApiCode::CODE_SUCCESS, '评论成功');
        } catch (\Exception $e) {
            $t->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}
