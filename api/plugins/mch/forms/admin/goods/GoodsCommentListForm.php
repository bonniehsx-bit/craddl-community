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
use app\models\Goods;
use app\models\GoodsComment;
use app\models\User;
use Yii;

/**
 * 商家端:我的商品评价（只读）。
 *
 * 缺这个页面之前，商家看不到自己商品的任何评价 —— 差评来了不知道、不能核实，
 * 只能求平台运营帮忙查。平台端有 CommentList/CommentDelete/Comment 三个接口，
 * 商家端一个都没有。
 *
 * ⛔ 刻意只读，不提供回复与删除:
 *   · 回复需要 goods_comment 加 reply/reply_at 三列 + C 端展示，属独立功能，本期不做
 *   · **商家不能删评价** —— 平台端的 CommentDelete 是平台的仲裁权。
 *     商家能自己删差评，评价体系就废了;恶意差评应走申诉由平台裁决，不是自己动手。
 *
 * 越权防护:评价表本身没有 mch_id，只能 JOIN goods 反查。
 * `g.mch_id = 当前商户` 是唯一的隔离条件，**不能省** —— 省了就是全平台评价随便看。
 */
class GoodsCommentListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $keyword;        // 商品名 / 评价内容
    public $grade_level;    // 好评1 / 中评2 / 差评3
    public $goods_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'grade_level', 'goods_id'], 'integer'],
            [['keyword'], 'string', 'max' => 50],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return ['code' => ApiCode::CODE_ERROR, 'msg' => $this->getFirstErrorText()];
        }
        $mch = Yii::$app->mch;
        if (!$mch) {
            return ['code' => ApiCode::CODE_ERROR, 'msg' => '您不是商户，无权查看'];
        }

        $query = GoodsComment::find()->alias('c')
            // ⚠ 隔离全靠这个 JOIN:评价表没有 mch_id,只能反查商品归属
            ->innerJoin(['g' => Goods::tableName()], 'g.id = c.goods_id')
            ->leftJoin(['u' => User::tableName()], 'u.id = c.user_id')
            ->where([
                'c.mall_id'  => Yii::$app->mall->id,
                'c.is_delete' => 0,
                'g.mch_id'   => $mch->id,
            ]);

        if ($this->goods_id) {
            $query->andWhere(['c.goods_id' => (int) $this->goods_id]);
        }
        if ($this->grade_level) {
            $query->andWhere(['c.grade_level' => (int) $this->grade_level]);
        }
        if ($this->keyword !== null && trim((string) $this->keyword) !== '') {
            $kw = trim((string) $this->keyword);
            $query->andWhere(['or', ['like', 'g.name', $kw], ['like', 'c.content', $kw]]);
        }

        // 各评级的条数,给前端做标签页角标。克隆一份,避免污染上面的分页查询
        $countQuery = clone $query;
        $gradeCount = [];
        foreach ($countQuery->select(['c.grade_level', 'n' => 'COUNT(*)'])
                     ->groupBy('c.grade_level')->asArray()->all() as $r) {
            $gradeCount[(int) $r['grade_level']] = (int) $r['n'];
        }

        $pagination = null;
        $list = $query
            ->select([
                'c.id', 'c.goods_id', 'c.content', 'c.pic_list', 'c.grade_level',
                'c.is_second', 'c.created_at',
                'g.name AS goods_name', 'g.cover_pic AS goods_pic',
                'u.nickname', 'u.avatar_url',
            ])
            ->orderBy(['c.id' => SORT_DESC])
            ->page($pagination, $this->limit, $this->page)
            ->asArray()->all();

        $gradeText = [1 => '好评', 2 => '中评', 3 => '差评'];
        foreach ($list as &$row) {
            $row['grade_text'] = $gradeText[(int) $row['grade_level']] ?? '好评';
            $row['pic_list']   = $row['pic_list'] ? (json_decode($row['pic_list'], true) ?: []) : [];
            $row['created_text'] = $row['created_at'] ? date('Y-m-d H:i', (int) $row['created_at']) : '';
            $row['nickname']   = $row['nickname'] ?: '匿名用户';
        }
        unset($row);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '',
            'data' => [
                'list'       => $list,
                'pagination' => $pagination,
                'grade_count' => [
                    'good'   => $gradeCount[1] ?? 0,
                    'medium' => $gradeCount[2] ?? 0,
                    'bad'    => $gradeCount[3] ?? 0,
                ],
            ],
        ];
    }
}
