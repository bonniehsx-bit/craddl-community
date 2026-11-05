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
use app\models\UserUpgradeLog;
use Yii;

/**
 * 后台【升级日志】列表 —— 展示 user_upgrade_log(用户等级升级记录)。
 * 接口 /mall/user/upgrade-log,组件 admin/src/views/user/upgrade-log.vue。
 */
class UserUpgradeLogListForm extends BaseModel
{
    public $page;
    public $limit;
    public $keyword;    // 昵称 / 手机号 / 用户ID
    public $reason_by;  // 0=消费满(金额) 1=购买商品 2=直推会员;空=全部

    public function rules()
    {
        return [
            [['page', 'limit', 'reason_by'], 'integer'],
            [['keyword'], 'string'],
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

        $query = UserUpgradeLog::find()->alias('l')->where(['l.mall_id' => $mallId, 'l.is_delete' => 0]);

        if ($this->reason_by !== null && $this->reason_by !== '') {
            $query->andWhere(['l.reason_by' => (int)$this->reason_by]);
        }
        if ($this->keyword) {
            $kw = trim((string)$this->keyword);
            $uids = User::find()->select('id')->where(['mall_id' => $mallId])
                ->andWhere(['or', ['like', 'nickname', $kw], ['like', 'mobile', $kw], ['id' => (int)$kw]])
                ->column();
            $query->andWhere(['l.user_id' => $uids ?: [0]]);
        }

        // UserUpgradeLog 无自定义 page() 作用域,手动分页;最新在前
        $page = max(1, (int)$this->page);
        $limit = max(1, (int)$this->limit);
        $total = (int)$query->count();
        $rows = $query->orderBy(['l.id' => SORT_DESC])->offset(($page - 1) * $limit)->limit($limit)->asArray()->all();
        $pagination = ['total_count' => $total, 'page_size' => $limit, 'current_page' => $page];

        // 补用户信息 + 等级名(升级前 / 升级后)
        $userIds = array_column($rows, 'user_id');
        $users = $userIds
            ? User::find()->select(['id', 'nickname', 'avatar_url', 'mobile'])
                ->where(['id' => $userIds])->indexBy('id')->asArray()->all()
            : [];
        $levelNames = Level::find()->select(['id', 'name'])->where(['mall_id' => $mallId])->indexBy('id')->asArray()->all();

        $reasonText = [0 => '消费满额', 1 => '购买商品', 2 => '直推会员', 3 => '后台调整'];

        $list = array_map(function ($r) use ($users, $levelNames, $reasonText) {
            $u = $users[$r['user_id']] ?? [];
            $bid = (int)$r['before_level_id'];
            $aid = (int)$r['after_level_id'];
            return [
                'id'                => (int)$r['id'],
                'user_id'           => (int)$r['user_id'],
                'nickname'          => $u['nickname'] ?? '',
                'avatar_url'        => $u['avatar_url'] ?? '',
                'mobile'            => $u['mobile'] ?? '',
                'before_level_id'   => $bid,
                'before_level_name' => $bid ? ($levelNames[$bid]['name'] ?? "等级{$bid}") : '普通',
                'after_level_id'    => $aid,
                'after_level_name'  => $aid ? ($levelNames[$aid]['name'] ?? "等级{$aid}") : '普通',
                'reason_by'         => (int)$r['reason_by'],
                'reason_text'       => $reasonText[(int)$r['reason_by']] ?? '其他',
                'marks'             => $r['marks'] ?? '',
                'created_time'      => !empty($r['created_at']) ? date('Y-m-d H:i:s', (int)$r['created_at']) : ($r['created_time'] ?? ''),
            ];
        }, $rows);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', [
            'list'       => $list,
            'pagination' => $pagination,
        ]);
    }
}
