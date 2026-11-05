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
namespace app\forms\api\user;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsFavorite;
use Yii;

/**
 * Class NewGoodsFavoriteListForm
 * @package app\forms\api\user
 *
 * [新版] 专门用于获取商品收藏列表
 */
class NewGoodsFavoriteListForm extends BaseModel
{
    public $page;
    public $limit = 10;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['page'], 'default', 'value' => 1],
        ];
    }

    /**
     * 获取商品收藏列表
     * @return array
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = GoodsFavorite::find()->alias('gf')
            ->where(['gf.user_id' => Yii::$app->user->id, 'gf.is_delete' => 0])
            // 使用 INNER JOIN With，确保只返回未被删除或下架的有效商品
            ->innerJoinWith(['goods g' => function ($query) {
                /** @var \yii\db\ActiveQuery $query */
                $query->where(['g.is_delete' => 0, 'g.status' => 1]);
            }]);

        // 先获取总数，用于前端分页判断
        $count = (clone $query)->count();

        // 再进行分页查询
        $list = $query->select([
            'g.id',
            'g.name',
            'g.cover_pic as image_url',
            'g.price as price_current',
            'g.origin_price as price_original',
            'gf.id as favorite_id' // 关键：返回收藏记录本身的ID，用于删除
        ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy(['gf.created_at' => SORT_DESC])
            ->asArray()
            ->all();

        // 对查询结果进行二次处理，例如格式化价格
        foreach ($list as &$item) {
            $item['tag'] = ''; // 示例，您可以根据商品属性动态生成
            $item['price_current'] = (float)$item['price_current'];
            $item['price_original'] = (float)$item['price_original'];
        }
        unset($item); // 断开最后一个元素的引用

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $list,
                'total' => (int) $count,
                'pagination' => $pagination,
            ]
        ];
    }
}