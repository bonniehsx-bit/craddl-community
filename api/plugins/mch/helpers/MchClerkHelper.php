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

namespace app\plugins\mch\helpers;

use app\models\User;
use app\plugins\mch\models\MchClerk;

/**
 * 店员（MchClerk）对外查询 Helper
 *
 * 对消费方插件（aicare / beauty / massage 等）公开的「店员」只读接口。
 * 消费方插件需要「门店有哪些店员 / 某用户是不是店员」时，统一走本 Helper，
 * 不直接 use MchClerk 模型，也不直接查 plugin_mch_clerk 表（详见 开发规范.md §21）。
 *
 * 店员的创建 / 启停由店长在 mch 后台的店员管理完成，本 Helper 只读。
 */
class MchClerkHelper
{
    /**
     * 某用户是否为某门店的有效店员（含店长）。
     */
    public static function isClerk(int $userId, int $mchId): bool
    {
        if ($userId <= 0 || $mchId <= 0) {
            return false;
        }
        return MchClerk::find()
            ->where(['user_id' => $userId, 'mch_id' => $mchId, 'status' => 1, 'is_delete' => 0])
            ->exists();
    }

    /**
     * 某用户是否为某门店的店长。
     */
    public static function isManager(int $userId, int $mchId): bool
    {
        if ($userId <= 0 || $mchId <= 0) {
            return false;
        }
        return MchClerk::find()
            ->where(['user_id' => $userId, 'mch_id' => $mchId, 'status' => 1, 'is_manager' => 1, 'is_delete' => 0])
            ->exists();
    }

    /**
     * 门店的有效店员列表（含店长），已带用户昵称 / 手机号。
     *
     * @return array [['user_id'=>int,'is_manager'=>int,'nickname'=>string,'mobile'=>string], ...]
     */
    public static function getClerks(int $mchId): array
    {
        if ($mchId <= 0) {
            return [];
        }
        $rows = MchClerk::find()
            ->where(['mch_id' => $mchId, 'status' => 1, 'is_delete' => 0])
            ->select(['user_id', 'is_manager'])
            ->orderBy(['is_manager' => SORT_DESC, 'id' => SORT_ASC])
            ->asArray()
            ->all();
        if (!$rows) {
            return [];
        }

        $userIds = array_column($rows, 'user_id');
        $users   = User::find()
            ->where(['id' => $userIds])
            ->select(['id', 'nickname', 'mobile'])
            ->indexBy('id')
            ->asArray()
            ->all();

        return array_map(function ($r) use ($users) {
            $u = $users[$r['user_id']] ?? [];
            return [
                'user_id'   => (int)$r['user_id'],
                'is_manager'=> (int)$r['is_manager'],
                'nickname'  => $u['nickname'] ?? '',
                'mobile'    => $u['mobile'] ?? '',
            ];
        }, $rows);
    }

    /**
     * 门店的有效店员 user_id 列表。
     *
     * @return int[]
     */
    public static function getClerkUserIds(int $mchId): array
    {
        if ($mchId <= 0) {
            return [];
        }
        return array_map('intval', MchClerk::find()
            ->where(['mch_id' => $mchId, 'status' => 1, 'is_delete' => 0])
            ->select('user_id')
            ->column());
    }

    /**
     * 某用户作为店员所属的门店 ID 列表。
     *
     * @return int[]
     */
    public static function getUserMchIds(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }
        return array_map('intval', MchClerk::find()
            ->where(['user_id' => $userId, 'status' => 1, 'is_delete' => 0])
            ->select('mch_id')
            ->column());
    }
}
