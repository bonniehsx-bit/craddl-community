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

namespace app\models;

use yii\base\Model;

/**
 * 模型错误提取工具
 */
class ModelError
{
    /**
     * 获取模型的第一条错误信息
     * @param Model $model
     * @return string
     */
    public function getErrorMsg($model)
    {
        if (!$model instanceof Model) {
            return '参数不是Model实例';
        }

        if (!$model->hasErrors()) {
            return '未知错误'; // save() 返回 false 但 getErrors() 为空的情况
        }

        // 获取所有属性的第一条错误
        $errors = $model->getFirstErrors();

        // 返回数组中的第一个值
        return reset($errors);
    }
}