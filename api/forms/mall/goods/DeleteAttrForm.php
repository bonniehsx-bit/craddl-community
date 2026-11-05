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
 * Date: 2022/8/18
 * Time: 17:32
 */

namespace app\forms\mall\goods;


use app\core\ApiCode;
use app\helpers\ResponseHelper;
use app\models\Attr;
use app\models\BaseModel;
use app\models\Goods;

class DeleteAttrForm extends BaseModel
{
    public $id;

    public function rules()
    {
        return [
            [['id'], 'integer'],

        ];
    }

    public function delete()
    {
        $attr = Attr::getOne($this->id);
        if (!$attr) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '规格不存在或已被删除');
        }
        if (!$attr->delete()) {
            return $this->responseErrorMsg($attr);
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '删除成功');
    }

}