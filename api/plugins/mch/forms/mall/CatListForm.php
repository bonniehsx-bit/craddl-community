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
use app\helpers\ResponseHelper;
use app\models\BaseModel;
use app\models\Cat;

class CatListForm extends BaseModel
{
    public $mch_id; // [核心] 必须传入商户ID

    public function rules()
    {
        return [
            [['mch_id'], 'required'],
            [['mch_id'], 'integer'],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        try {
            // [核心] 查询时严格限定 mch_id
            $list = Cat::find()->where([
                'mall_id' => \Yii::$app->mall->id,
                'mch_id' => $this->mch_id,
                'is_delete' => 0,
            ])
                ->orderBy('sort ASC, id DESC')
                ->asArray()
                ->all();

            // 将扁平的列表数据，构造成支持二级分类的树状结构
            $treeList = $this->buildTree($list);

            return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $treeList]);

        } catch (\Exception $e) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 辅助方法：将扁平数组构造成树状结构（最多支持二级）
     * @param array $elements
     * @param int $parentId
     * @return array
     */
    private function buildTree(array &$elements, $parentId = 0)
    {
        $branch = [];
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}