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

namespace app\plugins\clerk\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\clerk\models\ClerkUser;
use Yii;

class UserEditForm extends BaseModel
{
    public $id;
    public $user_id;
    public $name;
    public $status;

    public function rules()
    {
        return [
            [['user_id'], 'required', 'on' => ['save']],
            [['id', 'user_id', 'status'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['status'], 'default', 'value' => 1],
            [['name'], 'default', 'value' => ''],
            [['id'], 'required', 'on' => ['delete']],
        ];
    }

    public function getDetail()
    {
        if (!$this->id) {
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '请求成功',
                'data' => [
                    'detail' => [
                        'name' => '',
                        'status' => 1,
                        'user_id' => ''
                    ]
                ]
            ];
        }

        $detail = ClerkUser::find()->alias('c')
            ->where(['c.id' => $this->id, 'c.mall_id' => Yii::$app->mall->id, 'c.is_delete' => 0])
            ->joinWith(['user u' => function ($query) {
                $query->select('id, nickname, avatar_url');
            }])
            ->asArray()
            ->one();

        if (!$detail) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '记录不存在');
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '请求成功',
            'data' => [
                'detail' => $detail
            ]
        ];
    }

    public function save()
    {
        $this->scenario = 'save';
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            if ($this->id) {
                $model = ClerkUser::findOne(['id' => $this->id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);
                if (!$model) {
                    throw new \Exception('记录不存在');
                }
            } else {
                $model = new ClerkUser();
                $model->mall_id = Yii::$app->mall->id;
                
                // Check if user already exists
                $exists = ClerkUser::find()->where(['mall_id' => Yii::$app->mall->id, 'user_id' => $this->user_id, 'is_delete' => 0])->exists();
                if ($exists) {
                    throw new \Exception('该用户已被添加为平台核销员，请勿重复添加');
                }
            }

            $model->user_id = $this->user_id;
            $model->name = $this->name;
            $model->status = $this->status;

            if (!$model->save()) {
                throw new \Exception($this->getErrorMsg($model));
            }

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '保存成功'
            ];
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage()
            ];
        }
    }

    public function delete()
    {
        $this->scenario = 'delete';
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            $model = ClerkUser::findOne(['id' => $this->id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0]);
            if (!$model) {
                throw new \Exception('记录不存在');
            }
            $model->is_delete = 1;
            $model->deleted_at = time();
            $model->save();

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '删除成功'
            ];
        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage()
            ];
        }
    }
}
