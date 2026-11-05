<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 10:07
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

namespace app\forms\api\mall;


use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Cat;
use app\models\Goods;
use app\models\GoodsCat;

class CatForm extends BaseModel
{

    /**
     * C 端分类页的**唯一**取数条件:只要平台自己的分类。
     *
     * ⛔ `jxmall_cat` 一张表同时装着「平台分类」(mch_id=0)和「商家自建分类」(mch_id>0)。
     *    这三个接口(/api/goods/cat、cat2、cat-goods)服务的是**平台端**分类页,
     *    原来只按 mall_id 取,商家自建的分类会一起冒出来 —— 顾客在平台分类页看到
     *    某家店私有的分类,点进去的商品口径也不对。
     *
     * ⚠ 现在线上之所以没大面积炸,是因为商家分类的 `is_show` 恰好都是 0 —— 这是**巧合**,
     *    不是隔离:任何一个商家把自己的分类勾成"显示",立刻就漏到平台页上。
     *    所以主隔离必须是 `mch_id = 0`,`is_show = 1` 只是运营的显示开关。
     *
     * @param int $parentId 上级分类;0=顶级
     */
    private static function platformCatQuery(int $parentId)
    {
        return Cat::find()->where([
            'mall_id'   => \Yii::$app->mall->id,
            'is_delete' => 0,
            'mch_id'    => 0,      // 主隔离:平台分类
            'is_show'   => 1,      // 运营开关:未勾显示的不下发
            'parent_id' => $parentId,
        ])->orderBy('sort desc');
    }

    public function search()
    {
        $list = self::platformCatQuery(0)->asArray()->all();
        foreach ($list as &$item) {
            $second_list = self::platformCatQuery((int)$item['id'])->asArray()->all();
            foreach ($second_list as &$item1) {
                $third_list = self::platformCatQuery((int)$item1['id'])->asArray()->all();
                $item1['children'] = $third_list;
            }
            unset($item1);
            $item['children'] = $second_list;
        }
        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }

    public function search2()
    {
        $list = self::platformCatQuery(0)->asArray()->all();
        foreach ($list as &$item) {
            $item['children'] = self::platformCatQuery((int)$item['id'])->asArray()->all();
        }
        unset($item);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }


    /**
     * [最终优化版] 获取带商品的分类列表 (高性能)
     * @return array
     */
    public function searchCatWithGoods()
    {
        // [核心优化] 使用嵌套 with() 预加载，将 N+1 查询降为 3 次查询
        $list = Cat::find()
            ->where([
                'mall_id' => $this->mall_id,
                'is_delete' => 0,
                'mch_id' => 0,      // ⛔ 与 platformCatQuery 同一条隔离:只要平台分类,
                                    //    否则商家自建分类会混进平台分类页(见该方法注释)
                'parent_id' => 0,
                'is_show' => 1
            ])
            ->with([
                'childs' => function ($query) {
                    /** @var \app\models\BaseActiveQuery $query */
                    // 子级同样要挡商家分类 —— 顶级挡住了、子级漏出来一样是错的
                    $query->where(['is_delete' => 0, 'mch_id' => 0, 'is_show' => 1])->orderBy('sort DESC');
                },
                'childs.goods' => function ($query) {
                    /** @var \app\models\BaseActiveQuery $query */
                    $query->where(['is_delete' => 0, 'status' => 1])
                        ->select(['id', 'name', 'cover_pic', 'price', 'cat_id']) // [性能] 只选择需要的字段
                        ->orderBy('sort DESC')->limit(6);
                }
            ])
            ->orderBy('sort DESC')
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list]);
    }
}
