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

namespace app\plugins\distribution\models;

use app\models\BaseActiveRecord;

/**
 * 分销用户档案（瘦身版）。
 *
 * 分销商等级/升级已统一并入平台会员等级（User.level_id → Level），本表不再存等级。
 * 仅保留「单独取消某用户分销资格」这一运营开关——会员等级的 is_inviter 是按等级的，
 * 无法表达「某个 inviter 用户因作弊被单独封禁分销」，故保留 is_disabled。
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id
 * @property int $is_disabled  0=正常  1=已取消分销资格（停计佣）
 */
class DistributionUser extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%plugin_distribution_user}}';
    }

    public function rules()
    {
        return [
            [['mall_id', 'user_id'], 'required'],
            [['mall_id', 'user_id', 'is_disabled', 'created_at', 'updated_at'], 'integer'],
        ];
    }

    /**
     * 获取或创建分销商档案（仅用于落「禁用」标记）
     */
    public static function getOrCreate(int $mallId, int $userId): self
    {
        $model = self::findOne(['mall_id' => $mallId, 'user_id' => $userId]);
        if (!$model) {
            $model = new self();
            $model->mall_id = $mallId;
            $model->user_id = $userId;
            $model->save(false);
        }
        return $model;
    }

    /**
     * 返回这批用户里「已取消分销资格」的 user_id 集合（key=user_id），用于发钱前批量过滤。
     * @return array<int,int>
     */
    public static function disabledSet(int $mallId, array $userIds): array
    {
        if (empty($userIds)) return [];
        $ids = self::find()
            ->where(['mall_id' => $mallId, 'user_id' => $userIds, 'is_disabled' => 1])
            ->select('user_id')->column();
        return array_flip(array_map('intval', $ids));
    }
}
