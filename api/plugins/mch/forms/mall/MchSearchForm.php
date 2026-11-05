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
namespace app\plugins\mch\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use Yii;

class MchSearchForm extends BaseModel
{
    public $keyword;
    public $ids; // 逗号分隔ID(或数组),用于按ID批量回显已勾选商户(供 DIY「精选商家」配置面板)

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['keyword'], 'trim'],
            [['ids'], 'safe'],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = Mch::find()->alias('m')->where([
            'm.mall_id' => Yii::$app->mall->id,
            'm.is_delete' => 0,
            // 通常只搜索审核通过的商家
            'm.status' => 1,
        ]);

        // 优先按ID批量精确取(用于回显已勾选商户);否则按关键词搜索
        if ($this->ids !== null && $this->ids !== '' && $this->ids !== []) {
            $idArr = is_array($this->ids) ? $this->ids : explode(',', (string)$this->ids);
            $idArr = array_values(array_filter(array_map('intval', $idArr)));
            $query->andWhere(['m.id' => $idArr ?: [0]]);
        } elseif ($this->keyword) {
            // 支持按商家名称模糊搜索，或按商家ID精确搜索
            $query->andWhere([
                'OR',
                ['like', 'm.mch_name', $this->keyword],
                ['m.id' => $this->keyword]
            ]);
        }

        $list = $query
            // 只选择前端需要的字段，提高效率
            ->select(['m.id', 'm.mch_name as name'])
            // 限制返回数量，防止前端卡顿
            ->limit(50)
            ->orderBy(['m.id' => SORT_DESC])
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list
            ]
        ];
    }
}