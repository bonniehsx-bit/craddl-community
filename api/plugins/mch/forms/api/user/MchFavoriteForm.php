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
namespace app\plugins\mch\forms\api\user;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchFavorite;
use Yii;

class MchFavoriteForm extends BaseModel
{
    public $mch_id;

    public function rules()
    {
        return [
            [['mch_id'], 'required'],
            [['mch_id'], 'integer'],
        ];
    }

    /**
     * [核心] 切换收藏状态 (收藏/取消收藏)
     * @return array
     */
    public function toggle()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }


        // 1. 验证商家是否存在且已审核
        $mch = Mch::findOne(['id' => $this->mch_id, 'is_delete' => 0, 'status' => 1]);
        if (!$mch) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '商家不存在或未营业'];
        }

        // 2. 查找用户是否已收藏该商家 (包括已软删除的)
        $favorite = MchFavorite::findOne([
            'user_id' => Yii::$app->user->id,
            'mch_id' => $this->mch_id,
        ]);

        if ($favorite) {
            // --- 如果记录已存在 ---
            if ($favorite->is_delete == 0) {
                // a. 当前是收藏状态 -> 执行取消收藏 (软删除)
                $favorite->is_delete = 1;
                if ($favorite->save()) {
                    return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '已取消收藏', 'data' => ['is_favorite' => false]];
                } else {
                    return $this->responseErrorInfo($favorite);
                }
            } else {
                // b. 当前是取消状态 -> 执行重新收藏 (恢复)
                $favorite->is_delete = 0;
                if ($favorite->save()) {
                    return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '收藏成功', 'data' => ['is_favorite' => true]];
                } else {
                    return $this->responseErrorInfo($favorite);
                }
            }
        } else {
            // --- 如果记录不存在 -> 执行新增收藏 ---
            $newFavorite = new MchFavorite();
            $newFavorite->mall_id = Yii::$app->mall->id;
            $newFavorite->user_id = Yii::$app->user->id;
            $newFavorite->mch_id = $this->mch_id;

            if ($newFavorite->save()) {
                return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '收藏成功', 'data' => ['is_favorite' => true]];
            } else {
                return $this->responseErrorInfo($newFavorite);
            }
        }
    }
}