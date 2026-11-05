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

namespace app\forms\mall\express;

use app\models\BaseModel;
use app\models\Express;

class ExpressEditForm extends BaseModel
{
    public $id;
    public $name;
    public $code;
    public $key;
    public $ali_key;
    public $sort;

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['id', 'sort'], 'integer'],
            [['name', 'code', 'key', 'ali_key'], 'string', 'max' => 64],
            [['code', 'key', 'ali_key'], 'default', 'value' => ''],
            [['sort'], 'default', 'value' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '快递公司名称',
            'code' => '快递编码',
            'key' => '核心Key',
            'ali_key' => '阿里菜鸟Key',
            'sort' => '排序',
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        if ($this->id) {
            $model = Express::findOne(['id' => $this->id, 'is_delete' => 0]);
            if (!$model) {
                return [
                    'code' => 1,
                    'msg' => '数据不存在或已删除',
                ];
            }
            // 和删除同口径:平台标准集(mall_id=0)是各商城共用的,不允许在商城后台改。
            // 改比删更隐蔽 —— 把 code/key 改错,全平台的物流查询会一起失效,
            // 而列表看上去一切正常,很难追到是哪个商城动的。
            if ((int)$model->mall_id === 0) {
                return [
                    'code' => 1,
                    'msg' => '平台标准快递公司为全平台共用，不可编辑',
                ];
            }
            if ((int)$model->mall_id !== \Yii::$app->mall->id) {
                return [
                    'code' => 1,
                    'msg' => '数据不存在或已删除',
                ];
            }
        } else {
            $model = new Express();
            $model->mall_id = \Yii::$app->mall->id ?? 0; // 尽量兼容有mall_id的环境
        }

        $model->name = $this->name;
        $model->code = $this->code;
        $model->key = $this->key;
        $model->ali_key = $this->ali_key;
        $model->sort = $this->sort;

        if ($model->save()) {
            return [
                'code' => 0,
                'msg' => '保存成功',
            ];
        } else {
            return $this->getErrorResponse($model);
        }
    }
}
