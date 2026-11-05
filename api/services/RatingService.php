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

namespace app\services;

use app\models\GoodsComment;
use app\models\Order;
use app\plugins\mch\models\Mch;
use Yii;

/**
 * Class RatingService
 * @package app\services
 *
 * [核心服务] 商家评分计算服务
 * 负责根据订单评价，重新计算并更新商家的综合评分、好评率等指标。
 */
class RatingService
{
    // 定义评分计算的时间窗口为180天
    const TIME_WINDOW_SECONDS = 180 * 24 * 60 * 60;

    /**
     * [核心方法] 根据一个已完成评价的订单ID，重新计算并更新对应商家的评分
     * @param int $orderId
     */
    public static function updateMchRatingByOrder(int $orderId)
    {
        Yii::warning("RatingService: 收到评分更新请求，订单ID: {$orderId}");

        $order = Order::findOne($orderId);
        // 如果订单不存在，或者是平台自营订单，则无需计算
        if (!$order || $order->mch_id == 0) {
            Yii::warning("RatingService: 订单 {$orderId} 不存在或为平台自营，任务结束。");
            return;
        }

        $mch = Mch::findOne($order->mch_id);
        if (!$mch) {
            Yii::warning("RatingService: 未找到订单 {$orderId} 关联的商家 {$order->mch_id}。");
            return;
        }

        // 1. 确定用于计算的时间起点
        $startTime = time() - self::TIME_WINDOW_SECONDS;

        // 2. 找出该商家在时间窗口内所有“已完成”且“已评价”的订单ID
        $orderIdsInWindow = Order::find()->select('id')
            ->where([
                'mch_id' => $mch->id,
                'is_delete' => 0,
                'status' => Order::STATUS_COMPLETED,
                'comment_status' => 1 // 确保订单是已评价状态
            ])
            ->andWhere(['>=', 'finish_at', $startTime]) // 按订单完成时间筛选
            ->column();

        if (empty($orderIdsInWindow)) {
            // 如果窗口内没有已评价的订单，则将评分重置为初始状态
            self::resetMchRating($mch);
            Yii::warning("RatingService: 商家 {$mch->id} 在时间窗口内无评价，评分已重置。");
            return;
        }

        // 3. 找出这些订单对应的所有“未删除”的商品评价
        // 注意：一个订单可能有多个商品评价
        $comments = GoodsComment::find()
            ->where(['order_id' => $orderIdsInWindow, 'is_delete' => 0])
            ->asArray()
            ->all();

        if (empty($comments)) {
            self::resetMchRating($mch);
            Yii::warning("RatingService: 商家 {$mch->id} 的订单有关联但评价记录为空，评分已重置。");
            return;
        }

        // 4. 开始计算
        $totalScore = 0;
        $goodCommentCount = 0;
        $totalCommentCount = count($comments);

        foreach ($comments as $comment) {
            // [修复] 直接使用 1-5 分制，兼容旧数据异常处理
            $score = intval($comment['grade_level']);
            if ($score < 1) $score = 1;
            if ($score > 5) $score = 5;

            $totalScore += $score;

            // 定义好评标准：4星及以上算好评
            if ($score >= 4) {
                $goodCommentCount++;
            }
        }

        // 5. 计算最终结果
        $finalRating = $totalScore / $totalCommentCount;
        $goodRate = ($goodCommentCount / $totalCommentCount) * 100;

        // 6. 将结果保存到商家表中
        $mch->rating = round($finalRating, 2);
        $mch->good_rating_rate = round($goodRate, 2);
        $mch->rating_count = $totalCommentCount;

        if ($mch->save()) {
            Yii::warning("RatingService: 商家 {$mch->id} 评分更新成功。新评分: {$mch->rating}, 好评率: {$mch->good_rating_rate}%, 总数: {$mch->rating_count}");
        } else {
            Yii::error("RatingService: 商家 {$mch->id} 评分保存失败。");
        }
    }

    /**
     * 私有辅助方法：将商家评分重置为默认值
     * @param Mch $mch
     */
    private static function resetMchRating(Mch $mch)
    {
        $mch->rating = 5.00;
        $mch->good_rating_rate = 100.00;
        $mch->rating_count = 0;
        $mch->save();
    }
}
