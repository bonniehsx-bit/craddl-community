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
namespace app\plugins\mch\forms\admin\common;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;

class PlatformCatListForm extends BaseModel
{
    public function getList()
    {
        // 查询所有平台的、未删除的分类
        $allCats = Cat::find()
            ->where([
                'mall_id' => \Yii::$app->mall->id,
                'mch_id' => 0, // 核心条件：只查平台
                'is_delete' => 0,
            ])
            ->asArray()
            ->all();

        // 构建树状结构
        $treeList = $this->buildTree($allCats);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $treeList,
            ]
        ];
    }

    private function buildTree(array &$elements, $parentId = 0)
    {
        $branch = [];
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['children'] = $children;
                }
                // [优化] 二级分类是叶子节点，不需要 children 属性，让前端组件更好渲染
                if ($element['parent_id'] > 0) {
                    unset($element['children']);
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}