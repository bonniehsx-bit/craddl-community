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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Level;
use app\models\User;
use app\models\UserTeamStat;
use Yii;
use yii\helpers\Json;

/**
 * 后台【团队数据】列表 —— 展示 user_team_stat(团队人数/消费/等级分布/legs)。
 * 供测试/运维「下单/改等级 → 刷新看数字」直观验证团队统计正确性。
 */
class TeamStatListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;   // 昵称 / 手机号 / 用户ID
    public $order_by;  // team_count(默认) | team_spend | updated
    public $dirty;     // 传 1 只看「待重检」

    public function rules()
    {
        return [
            [['page', 'limit', 'dirty'], 'integer'],
            [['keyword', 'order_by'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        $query = UserTeamStat::find()->alias('s')->where(['s.mall_id' => $mallId]);

        if ($this->dirty !== null && $this->dirty !== '') {
            $query->andWhere(['s.dirty' => (int)$this->dirty]);
        }
        if ($this->keyword) {
            $kw = trim((string)$this->keyword);
            $uids = User::find()->select('id')->where(['mall_id' => $mallId])
                ->andWhere(['or', ['like', 'nickname', $kw], ['like', 'mobile', $kw], ['id' => (int)$kw]])
                ->column();
            $query->andWhere(['s.user_id' => $uids ?: [0]]);
        }

        switch ($this->order_by) {
            case 'team_spend':
                $query->orderBy(['s.team_spend_paid' => SORT_DESC]);
                break;
            case 'updated':
                $query->orderBy(['s.updated_at' => SORT_DESC]);
                break;
            default:
                $query->orderBy(['s.team_count' => SORT_DESC]);
        }

        // UserTeamStat 无自定义 page() 作用域,手动分页
        $page = max(1, (int)$this->page);
        $limit = max(1, (int)$this->limit);
        $total = (int)$query->count();
        $rows = $query->offset(($page - 1) * $limit)->limit($limit)->asArray()->all();
        $pagination = ['total_count' => $total, 'page_size' => $limit, 'current_page' => $page];

        // 补用户信息 + 等级名
        $userIds = array_column($rows, 'user_id');
        $users = $userIds
            ? User::find()->select(['id', 'nickname', 'avatar_url', 'mobile', 'level_id'])
                ->where(['id' => $userIds])->indexBy('id')->asArray()->all()
            : [];
        $levelNames = Level::find()->select(['id', 'name'])->where(['mall_id' => $mallId])->indexBy('id')->asArray()->all();

        $decode = function ($json) {
            if (empty($json)) {
                return [];
            }
            try {
                return Json::decode($json) ?: [];
            } catch (\Throwable $e) {
                return [];
            }
        };

        $list = array_map(function ($r) use ($users, $levelNames, $decode) {
            $u = $users[$r['user_id']] ?? [];
            $lid = (int)($u['level_id'] ?? 0);
            return [
                'user_id'                => (int)$r['user_id'],
                'nickname'               => $u['nickname'] ?? '',
                'avatar_url'             => $u['avatar_url'] ?? '',
                'mobile'                 => $u['mobile'] ?? '',
                'level_name'             => $lid ? ($levelNames[$lid]['name'] ?? "等级{$lid}") : '普通',
                'direct_count'           => (int)$r['direct_count'],
                'team_count'             => (int)$r['team_count'],
                'personal_spend_paid'    => $r['personal_spend_paid'],
                'personal_spend_settled' => $r['personal_spend_settled'],
                'team_spend_paid'        => $r['team_spend_paid'],
                'team_spend_settled'     => $r['team_spend_settled'],
                'personal_order_count'   => (int)$r['personal_order_count'],
                'dirty'                  => (int)$r['dirty'],
                'updated_time'           => $r['updated_time'] ?? '',
                'last_check_at'          => $r['last_check_at'] ? date('Y-m-d H:i:s', (int)$r['last_check_at']) : '',
                // 明细(详情抽屉用):等级分布 / 团队等级分布 / 各直推线 / 已购商品
                'direct_level_map'       => $decode($r['direct_level_map']),
                'team_level_map'         => $decode($r['team_level_map']),
                'team_legs_map'          => $decode($r['team_legs_map']),
                'purchased_goods_map'    => $decode($r['purchased_goods_map']),
            ];
        }, $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list'       => $list,
            'pagination' => $pagination,
        ]);
    }
}
