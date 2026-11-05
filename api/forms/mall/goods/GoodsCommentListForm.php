<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-13
 * Time: 20:52
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

namespace app\forms\mall\goods;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Goods;
use app\models\GoodsComment; // [!! 引入 !!]
use app\models\User;
use yii\helpers\Json;

class GoodsCommentListForm extends BaseModel
{
    public $page;
    public $goods_id;
    public $keyword;
    public $limit;
    public $search_date;
    public $grade_level; // [!! 新增 !!] 评价等级

    public function rules()
    {
        return [
            [['keyword'], 'string'],
            [['search_date'], 'safe'],
            [['page', 'limit', 'goods_id', 'grade_level'], 'integer'], // [!! 修改 !!]
            [['limit'], 'default', 'value' => 10]
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        // 不带 mall_id 会列出全平台所有商城的评价(还预加载了昵称头像和商品名)
        $query = GoodsComment::find()->alias('c')
            ->where(['c.is_delete' => 0, 'c.mall_id' => \Yii::$app->mall->id]);

        // 【优化】关键词搜索逻辑
        if ($this->keyword) {
            // 这两个子查询同样限定本商城:主查询虽然已经按 mall_id 收口、匹配到别家的 id
            // 也筛不出记录,但不限定的话 IN 列表会随全平台商品/用户数一起膨胀,白白拖慢查询。
            $mallId = \Yii::$app->mall->id;
            // 1. 查找匹配商品名的 goods_id
            $matchingGoodsIds = Goods::find()->where(['like', 'name', $this->keyword])
                ->andWhere(['mall_id' => $mallId])->select('id')->column();
            // 2. 查找匹配用户昵称的 user_id
            $matchingUserIds = User::find()->where(['like', 'nickname', $this->keyword])
                ->andWhere(['mall_id' => $mallId])->select('id')->column();

            $query->andWhere([
                'or',
                ['like', 'c.content', $this->keyword], // 搜索评价内容
                ['c.goods_id' => $matchingGoodsIds],   // 搜索商品ID
                ['c.user_id' => $matchingUserIds]      // 搜索用户ID
            ]);
        }

        if ($this->search_date && count($this->search_date) == 2) {
            $start_date = strtotime($this->search_date[0]);
            $end_date = strtotime($this->search_date[1]);
            $query->andWhere(['between', 'c.created_at', $start_date, $end_date]);
        }

        if ($this->goods_id) {
            $query->andWhere(['c.goods_id' => $this->goods_id]);
        }
        if (!empty($this->grade_level) && in_array((int)$this->grade_level, [1, 2, 3, 4, 5])) {
            $query->andWhere(['c.grade_level' => (int)$this->grade_level]);
        }

        // 【性能优化】使用 with() 预加载替代 JOIN
        $pagination = null;
        $list = $query->with([
            'user' => function ($q) {
                $q->select('id, nickname, avatar_url');
            },
            'goods' => function ($q) {
                $q->select('id, name, cover_pic');
            }
        ])
            ->page($pagination, $this->limit, $this->page)
            ->orderBy('c.id DESC')
            ->asArray()
            ->all();

        // 批量处理数据
        foreach ($list as &$item) {
            // 将关联数据提取到顶层，以兼容前端
            $item['nickname'] = $item['user']['nickname'] ?? '';
            $item['avatar_url'] = $item['user']['avatar_url'] ?? '';
            $item['name'] = $item['goods']['name'] ?? '';
            $item['cover_pic'] = $item['goods']['cover_pic'] ?? '';

            // 解码图片列表
            try {
                if (!empty($item['pic_list'])) {
                    $decoded = Json::decode($item['pic_list']);
                    // 如果解码后还是字符串（可能是由于之前的二次编码导致），尝试再次解码
                    if (is_string($decoded)) {
                        $secondDecode = json_decode($decoded, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($secondDecode)) {
                            $decoded = $secondDecode;
                        }
                    }
                    $item['pic_list'] = is_array($decoded) ? $decoded : [];
                } else {
                    $item['pic_list'] = [];
                }
            } catch (\Exception $e) {
                $item['pic_list'] = [];
            }

            // 清理掉嵌套的关联数据
            unset($item['user'], $item['goods']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
