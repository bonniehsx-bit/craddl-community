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

namespace app\plugins\distribution\forms\mall;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\Level;
use app\models\User;
use app\plugins\distribution\models\DistributionUser;
use Yii;

/**
 * 分销人员管理
 *
 * 领域语义（分销商等级已并入会员等级）：
 *   · 分销商 = User.is_inviter=1（资格由会员等级 Level.is_inviter 驱动，也可后台手动授予）
 *   · 等级    = 会员等级（User.level_id → Level），插件不再自建等级
 *   · is_disabled（DistributionUser）= 单独取消某用户分销资格（停计佣，仅在禁用时建档）
 */
class UserListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;

    public $keyword;
    public $level_id;   // '' | '0'(无会员等级) | 正整数(会员等级ID)

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            [['keyword', 'level_id'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) return $this->responseErrorInfo();

        $mallId  = Yii::$app->mall->id;
        $setting = OptionHelper::get('distribution_setting', $mallId) ?: [];

        // 以「有推广资格的用户」为源；is_disabled 从 DistributionUser 取；等级取会员等级
        $query = User::find()->alias('u')
            ->leftJoin(['du' => DistributionUser::tableName()], 'du.user_id = u.id AND du.mall_id = u.mall_id')
            ->leftJoin(['lv' => Level::tableName()], 'lv.id = u.level_id AND lv.is_delete = 0')
            ->leftJoin(['pu' => User::tableName()], 'pu.id = u.parent_id')
            ->where(['u.mall_id' => $mallId, 'u.is_delete' => 0, 'u.is_inviter' => 1]);

        if ($this->keyword !== null && $this->keyword !== '') {
            $kw = trim($this->keyword);
            $query->andWhere(['or',
                ['like', 'u.nickname', $kw],
                ['like', 'u.mobile', $kw],
                ['u.id' => ctype_digit($kw) ? (int)$kw : -1],
            ]);
        }

        // 按会员等级筛选
        if ($this->level_id !== null && $this->level_id !== '') {
            if ((string)$this->level_id === '0') {
                $query->andWhere(['or', ['u.level_id' => null], ['u.level_id' => 0]]);
            } else {
                $query->andWhere(['u.level_id' => (int)$this->level_id]);
            }
        }

        $pagination = null;
        $list = $query
            ->page($pagination, (int)$this->limit, (int)$this->page)
            ->select([
                'u.id AS user_id',
                'u.nickname', 'u.avatar_url', 'u.mobile',
                'u.is_inviter', 'u.inviter_at',
                'u.parent_id', 'u.created_at AS user_created_at',
                'u.level_id',
                'lv.name AS level_name',
                'du.is_disabled',
                'pu.nickname AS parent_nickname',
            ])
            ->orderBy(['u.inviter_at' => SORT_DESC, 'u.id' => SORT_DESC])
            ->asArray()
            ->all();

        foreach ($list as &$row) {
            $row['id']               = (int)$row['user_id']; // 前端接口不变，id 仍指 user_id
            $row['level_id']         = (int)($row['level_id'] ?? 0);
            $row['level_name']       = $row['level_name'] ?? '';
            $row['is_disabled']      = (int)($row['is_disabled'] ?? 0);
            $row['is_inviter']       = (int)$row['is_inviter'];
            $row['inviter_at_text']  = !empty($row['inviter_at']) ? date('Y-m-d H:i', (int)$row['inviter_at']) : '';
            $row['created_at_text']  = $row['user_created_at'] ? date('Y-m-d H:i', (int)$row['user_created_at']) : '';
        }
        unset($row);

        // 会员等级列表（供筛选下拉）
        $levels = Level::find()
            ->where(['mall_id' => $mallId, 'is_delete' => 0, 'enabled' => 1])
            ->orderBy(['level' => SORT_ASC])
            ->select(['id', 'name', 'level'])
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'list'            => $list,
                'pagination'      => $pagination,
                'levels'          => $levels,
                'is_dist_enabled' => !empty($setting['is_enable']) ? 1 : 0,
            ],
        ];
    }

    /**
     * 授予 / 取消分销资格（plugin-scoped）
     *   · grant  → User.is_inviter=1 + DistributionUser.is_disabled=0
     *   · revoke → DistributionUser.is_disabled=1（停计佣，保留 User.is_inviter，避免误伤全局邀请关系）
     */
    public function toggleInviter()
    {
        $userId = (int)Yii::$app->request->post('user_id');
        $action = (string)Yii::$app->request->post('action');
        if (!$userId || !in_array($action, ['grant', 'revoke'], true)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '参数错误'];
        }

        $mallId = Yii::$app->mall->id;
        $user = User::findOne(['id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$user) return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户不存在'];

        $distUser = DistributionUser::getOrCreate($mallId, $userId);

        if ($action === 'grant') {
            $changed = [];
            if ((int)$user->is_inviter !== 1) {
                $user->is_inviter = 1;
                $user->inviter_at = time();
                $user->save(false, ['is_inviter', 'inviter_at']);
                $changed[] = '已授予推广资格';
            }
            if ((int)$distUser->is_disabled === 1) {
                $distUser->is_disabled = 0;
                $distUser->save(false, ['is_disabled']);
                $changed[] = '已解除分销停用';
            }
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => $changed ? implode('，', $changed) : '该用户已是正常分销商'];
        }

        // revoke
        if ((int)$distUser->is_disabled === 1) {
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '该用户已是取消状态'];
        }
        $distUser->is_disabled = 1;
        $distUser->save(false, ['is_disabled']);
        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '已取消分销资格（停计佣，档案保留）'];
    }
}
