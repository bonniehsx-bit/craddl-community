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
namespace app\services\cart;

use app\models\Cart;
use app\services\order\OrderInputGuard;

/** 仅分享直达下单使用：扣掉本次购买数量，保留好友购物车原有的同规格余量。 */
class SharedCartConsumption
{
    public static function consume(int $mallId, int $userId, int $goodsId, int $attrId, int $num): void
    {
        OrderInputGuard::assertQuantity($num);
        $db = \Yii::$app->db;
        if (!$db->getTransaction()) {
            throw new \LogicException('分享商品购物车清理必须在建单事务内执行');
        }
        $scope = ['mall_id' => $mallId, 'user_id' => $userId, 'goods_id' => $goodsId, 'goods_attr_id' => $attrId, 'is_delete' => 0];
        $sql = Cart::find()->where($scope)->orderBy('id')->createCommand()->getRawSql();
        $rows = $db->createCommand($sql . ' FOR UPDATE')->queryAll();
        foreach ($rows as $row) {
            if ($num <= 0) break;
            $used = min($num, (int) $row['num']);
            if ($used <= 0) continue;
            $condition = array_merge($scope, ['id' => $row['id']]);
            if ($used === (int) $row['num']) {
                $db->createCommand()->delete(Cart::tableName(), $condition)->execute();
            } else {
                $db->createCommand()->update(Cart::tableName(), ['num' => (int) $row['num'] - $used, 'updated_at' => time()], $condition)->execute();
            }
            $num -= $used;
        }
    }
}
