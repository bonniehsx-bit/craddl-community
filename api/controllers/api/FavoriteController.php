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

namespace app\controllers\api;

use app\forms\api\favorite\FavoriteListForm;

// [核心] 引入新的商家收藏Form
use app\models\GoodsFavorite;
use app\plugins\mch\models\MchFavorite;
use Yii;

/**
 * Class FavoriteController
 * @package app\controllers\api
 *
 * [新版] 收藏功能统一控制器
 */
class FavoriteController extends Controller
{
    /**
     * 统一的收藏列表接口
     * @return \yii\web\Response
     */
    public function actionList()
    {

        $form = new FavoriteListForm();
        // 接收前端通过GET方式传来的参数（如 type, page, limit）
        $form->attributes = $this->request->get();
        return $this->asJson($form->getList());
    }

    /**
     * 取消收藏 (商品或商家)
     * API: POST /api/favorite/delete
     * @return \yii\web\Response
     */
    public function actionDelete()
    {
        // 接收前端传递的参数
        $type = Yii::$app->request->post('type'); // 'goods' or 'mch'
        $favorite_id = Yii::$app->request->post('favorite_id');

        if (empty($favorite_id) || !in_array($type, ['goods', 'mch'])) {
            return $this->asJson(['code' => 1, 'msg' => '参数错误']);
        }

        $model = null;
        if ($type === 'goods') {
            $model = GoodsFavorite::findOne([
                'id' => $favorite_id,
                'user_id' => Yii::$app->user->id,
                'is_delete' => 0
            ]);
        } else { // $type === 'mch'
            $model = MchFavorite::findOne([
                'id' => $favorite_id,
                'user_id' => Yii::$app->user->id,
                'is_delete' => 0
            ]);
        }

        if (!$model) {
            return $this->asJson(['code' => 1, 'msg' => '收藏记录不存在或已被取消']);
        }

        // 执行软删除
        $model->is_delete = 1;
        if ($model->save()) {
            return $this->asJson(['code' => 0, 'msg' => '已取消收藏']);
        } else {
            Yii::error('取消收藏失败: ' . json_encode($model->errors));
            return $this->asJson(['code' => 1, 'msg' => '操作失败，请稍后再试']);
        }
    }
}