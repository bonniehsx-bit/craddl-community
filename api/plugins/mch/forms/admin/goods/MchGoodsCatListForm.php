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

namespace app\plugins\mch\forms\admin\goods;
// [核心] 命名空间已更正

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;
use Yii;

class MchGoodsCatListForm extends BaseModel
{
    /**
     * 获取当前店长的商品分类列表
     * @return array
     */
    public function getList()
    {
        // [核心] 通过插件类获取当前登录用户的商户信息
        // 这个方法封装了“用户是否为商户”的检查
        $mch = Yii::$app->mch;
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '您不是商户，无权操作');
        }

        // [核心] 只查询属于当前登录商户的分类 (mch_id = $mch->id)
        $list = Cat::find()->where([
            'mall_id' => Yii::$app->mall->id,
            'mch_id' => $mch->id,
            'is_delete' => 0,
        ])
            ->orderBy('sort ASC, id DESC')
            ->asArray()
            ->all();

        // 将扁平的列表数据，构造成支持二级分类的树状结构
        $treeList = $this->buildTree($list);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $treeList]);
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