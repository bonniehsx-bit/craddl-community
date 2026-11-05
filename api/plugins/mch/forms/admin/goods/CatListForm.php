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

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Cat;
use Yii;

class CatListForm extends BaseModel
{
    public $mch_id;
    public $parent_cat_id;
    public $type;
    public $is_tree;

    public function rules()
    {
        return [
            [['mch_id', 'parent_cat_id', 'type', 'is_tree'], 'integer'],
            [['mch_id', 'parent_cat_id', 'is_tree'], 'default', 'value' => 0],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 模拟总后台的数据返回结构
        $mch_id = $this->mch_id;

        // 如果获取私有分类，需要强制使用当前的 mch_id，绝不可以查其他商户的
        if ($mch_id != 0) {
            $mch_id = Yii::$app->mch->id;
        }

        // 如果明确指定需要拉取树(is_tree=1)，或者没有传递任何上级ID且没有明确传出type要求时，我们拉取全量树，否则严格拉取当前层级
        if ($this->is_tree || (\Yii::$app->request->get('parent_cat_id') === null && \Yii::$app->request->get('type') === null)) {
            // 以树状结构拉取分类
            $list = Cat::find()->where([
                'mall_id' => Yii::$app->mall->id,
                'mch_id' => $mch_id,
                'is_delete' => 0,
            ])->orderBy('sort ASC, id ASC')->asArray()->all();

            $res = $this->buildTree($list, 0);
        } else {
            // 严格拉取当前上下级关系的扁平列表
            $parentId = $this->parent_cat_id ?: 0;
            $list = Cat::find()->where([
                'mall_id' => Yii::$app->mall->id,
                'mch_id' => $mch_id,
                'parent_id' => $parentId,
                'is_delete' => 0,
            ])->orderBy('sort ASC, id ASC')->asArray()->all();
            
            // 为了兼顾前端的 is_child 箭头判定
            foreach ($list as &$item) {
                // 如果是第一级或者第二级，则尝试查询是否有子级
                $hasChild = Cat::find()->where([
                    'mall_id' => Yii::$app->mall->id,
                    'mch_id' => $mch_id,
                    'parent_id' => $item['id'],
                    'is_delete' => 0,
                ])->exists();
                $item['is_child'] = $hasChild;
            }
            unset($item);
            $res = $list;
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => 'success',
            'data' => [
                'list' => $res,
            ],
        ];
    }

    private function buildTree($elements, $parentId = 0)
    {
        $branch = array();
        foreach ($elements as $element) {
            if ($element['parent_id'] == $parentId) {
                $children = $this->buildTree($elements, $element['id']);
                if ($children) {
                    $element['child'] = $children;
                    $element['is_child'] = true;
                } else {
                    $element['is_child'] = false;
                }
                $branch[] = $element;
            }
        }
        return $branch;
    }
}
