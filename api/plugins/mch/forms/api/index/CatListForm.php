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
namespace app\plugins\mch\forms\api\index;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\MchCat;
use Yii;

/**
 * Class CatListForm
 * @package app\plugins\mch\forms\api\index
 *
 * 负责获取商家分类列表
 */
class CatListForm extends BaseModel
{
    // 可以增加 limit 参数，用于控制返回的数量，例如首页只显示前10个
    public $limit;

    public function rules()
    {
        return [
            [['limit'], 'integer', 'min' => 1],
        ];
    }

    /**
     * 获取商家分类列表
     * @return array
     */
    public function getList()
    {

        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $query = MchCat::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'is_delete' => 0,
        ]);

        // 按后台设置的排序号（order_num）降序，ID升序排列
        // 排序号越大越靠前
        $query->orderBy(['order_num' => SORT_DESC, 'id' => SORT_ASC]);

        // 如果设置了 limit 参数，则只返回指定数量的分类
        if ($this->limit) {
            $query->limit($this->limit);
        }

        // 只查询前端需要的字段
        $list = $query->select(['id', 'cat_name', 'cover_pic'])
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
            ]
        ];
    }
}