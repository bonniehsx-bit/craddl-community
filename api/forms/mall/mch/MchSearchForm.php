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

namespace app\forms\mall\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;

class MchSearchForm extends BaseModel
{
    public $keyword;
    public $limit;

    public function rules()
    {
        return [
            [['keyword'], 'trim'],
            [['keyword'], 'string'],
            [['limit'], 'integer'],
            [['limit'], 'default', 'value' => 20], // 默认搜索前20条
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // 查询逻辑：只查询当前商城、未删除、且审核通过(status=1)的商户
            $query = Mch::find()->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0,
                'status' => 1, // 1=已通过/营业中 (根据Mch模型常量调整，通常1是正常)
            ]);

            // 模糊搜索
            if ($this->keyword) {
                $query->andWhere(['like', 'mch_name', $this->keyword]);
            }

            // 选择需要的字段，避免返回过多敏感数据
            $list = $query->select(['id', 'mch_name', 'logo', 'mobile'])
                ->orderBy(['id' => SORT_DESC])
                ->limit($this->limit)
                ->asArray()
                ->all();

            // 格式化输出（可选）
            foreach ($list as &$item) {
                // 如果需要处理 logo 图片链接，可以在这里处理
                // $item['logo'] = ...
                // 为了前端兼容，确保有 name 字段
                $item['name'] = $item['mch_name'];
            }
            unset($item);

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '获取成功',
                'data' => [
                    'list' => $list
                ]
            ];

        } catch (\Exception $e) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage()
            ];
        }
    }
}