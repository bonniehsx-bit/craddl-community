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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2022/8/29
 * Time: 15:33
 */

namespace app\jobs\goods;

use app\core\Job;
use app\models\GoodsFootmark;
use app\models\User;
use app\models\Goods;

class GoodsFootMarkJob extends Job
{
    public $user_id;
    public $goods_id;
    public $mall_id;
    // 调度器以任务内容作ZSet成员，每次打开有独立标识，避免同一商品的多次浏览被覆盖。
    public $event_id;
    public $viewed_at;

    public function init()
    {
        parent::init();
        if (!$this->event_id) $this->event_id = bin2hex(random_bytes(12));
        if (!$this->viewed_at) $this->viewed_at = time();
    }

    public function execute($params=null)
    {
        if (!$this->user_id || !$this->goods_id || !$this->mall_id) return;
        \Yii::$app->db->transaction(function () {
            // 按用户串行化首次插入和累加，避免并发生成重复足迹或丢次数。
            $user = User::find()->where(['id' => $this->user_id, 'mall_id' => $this->mall_id, 'is_delete' => 0])->forUpdate()->one();
            if (!$user || !Goods::find()->where(['id' => $this->goods_id, 'mall_id' => $this->mall_id, 'is_delete' => 0])->exists()) return;
            $footmark = GoodsFootmark::find()->where([
                'user_id' => $this->user_id, 'goods_id' => $this->goods_id,
                'is_delete' => 0, 'mall_id' => $this->mall_id,
            ])->orderBy(['id' => SORT_DESC])->one();
            if (!$footmark) {
                \Yii::$app->db->createCommand()->insert(GoodsFootmark::tableName(), [
                    'mall_id' => $this->mall_id, 'goods_id' => $this->goods_id, 'user_id' => $this->user_id,
                    'view_count' => 1, 'created_at' => $this->viewed_at, 'updated_at' => $this->viewed_at,
                ])->execute();
            } else {
                // 显式写浏览时间，避免模型自动时间戳将其替换成队列执行时间。
                GoodsFootmark::updateAll([
                    'view_count' => max(1, (int) $footmark->view_count) + 1,
                    'updated_at' => max((int) $footmark->updated_at, (int) $footmark->created_at, (int) $this->viewed_at),
                ], ['id' => $footmark->id, 'mall_id' => $this->mall_id]);
            }
        });
    }
}
