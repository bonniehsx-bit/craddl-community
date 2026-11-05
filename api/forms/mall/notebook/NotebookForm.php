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
 * Date: 2023/1/13
 * Time: 14:23
 */

namespace app\forms\mall\notebook;


use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Notebook;

class NotebookForm extends BaseModel
{

    public $id;
    public $content;
    public $title;
    public $level;


    public function rules()
    {
        return [
            [['id','level'], 'integer'],
            [['content'], 'string'],
            [['title'], 'string', 'max' => 64],
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if(!$this->title){
            return ResponseHelper::send(ApiCode::CODE_FAIL,'请输入标题');
        }
        $model = Notebook::findOne(['id' => $this->id, 'admin_id' => \Yii::$app->admin->id]);
        if (!$model) {
            $model = new Notebook();
            $model->mall_id = $this->mall_id;
            $model->admin_id = \Yii::$app->admin->id;
        }
        $model->level=$this->level;
        $model->title=$this->title;
        $model->content = $this->content;
        if (!$model->save()) {
            return $this->responseErrorMsg($model);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '保存成功');
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $model = Notebook::getOne($this->id);
        if (!$model) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '日志不存在');
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['notebook' => $model->attributes]);
    }

    public function delete()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $model = Notebook::findOne(['id' => $this->id, 'admin_id' => \Yii::$app->admin->id]);
        if (!$model) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '日志不存在');
        }
        $model->is_delete = 1;
        if (!$model->save()) {
            return $this->responseErrorInfo($model);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');
    }

}