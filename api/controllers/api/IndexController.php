<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-10
 * Time: 16:52
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

namespace app\controllers\api;


use app\forms\api\index\DiyPageForm;
use app\forms\api\index\IndexForm;

class IndexController extends Controller
{

    public function actionIndex()
    {
        $form = new IndexForm();
        return $form->getIndex();
    }

    public function actionDiyPage()
    {
        $form = new DiyPageForm();
        $form->attributes = $this->request->get();
        return $form->getPageData();
    }

    /**
     * @return array
     * H5/App 首页访问上报 (基于 Redis 的轻量化日活统计)
     */
    public function actionVisitReport()
    {
        $mallId = \Yii::$app->mall->id;
        $touristId = \Yii::$app->request->post('tourist_id', '');

        // 1. 确定用户唯一标识 (已鉴权则用UID，否则用游客生成的UUID)
        $uniqueId = '';
        if (!\Yii::$app->user->isGuest && \Yii::$app->user->id) {
            $uniqueId = 'user_' . \Yii::$app->user->id;
        } elseif (!empty($touristId)) {
            $uniqueId = 'tourist_' . $touristId;
        }

        if (empty($uniqueId)) {
            return [
                'code' => 1,
                'msg' => '缺少访问者标识'
            ];
        }

        $date = date('Ymd');
        $pvKey = "jxmall:stat:pv:{$mallId}:{$date}";
        $uvKey = "jxmall:stat:uv:{$mallId}:{$date}";

        $redis = \Yii::$app->redis;

        // 2. 累加 PV (首页打开次数 + 1)
        $redis->incr($pvKey);

        // 3. 去重 UV (集合存入 uniqueId)
        $redis->sadd($uvKey, $uniqueId);

        // 4. (可选) 给新创建的 key 设置过期时间为 48 小时，防止无限增长
        if ($redis->ttl($pvKey) == -1) {
            $redis->expire($pvKey, 172800); // 48 * 3600
        }
        if ($redis->ttl($uvKey) == -1) {
            $redis->expire($uvKey, 172800);
        }

        return [
            'code' => 0,
            'msg' => 'success'
        ];
    }
}
