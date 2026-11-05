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

namespace app\services;

use app\models\Admin;
use app\models\AdminMall;
use app\models\AdminRole;
use app\models\Mall;
use app\models\Menu;
use app\models\Role;
use app\models\RoleMenu;
use Yii;

/**
 * 商城归属/主账号 开通服务
 *
 * 建店交付(MallEditForm) 与 移交商城(MallTransferForm) 复用同一套逻辑：
 *   - createOwnerAccount  新建商户主账号(FOUNDER)
 *   - provisionShopAdminRole  配「店长」角色(含本店全部菜单)
 *   - bindFounder  AdminMall 多对多绑定(founder)
 *
 * 注意：FOUNDER 在其商城内是"商城级超管"(admin_type 驱动全权)，店长角色更多是给个具体 role 载体；
 *       真正的进店/权限放行由 enter() + RouterListForm/actionInfo 的 FOUNDER 分支保证。
 */
class MallProvisionService
{
    /**
     * 新建商户主账号(FOUNDER)并绑定到指定商城。
     * @return array ['ok'=>bool, 'admin_id'=>int, 'msg'=>string]
     */
    public static function createOwnerAccount($mallId, $username, $password, $mobile, $nickname, $createdBy)
    {
        $username = trim((string)$username);
        $mobile   = trim((string)$mobile);
        $password = (string)$password;
        $nickname = trim((string)$nickname) ?: $username;

        if ($username === '' || $password === '' || $mobile === '') {
            return ['ok' => false, 'msg' => '请完善商城主账号的用户名 / 密码 / 手机号'];
        }
        if (mb_strlen($password) < 5) {
            return ['ok' => false, 'msg' => '主账号密码至少 5 位'];
        }
        $exists = Admin::find()->where(['is_delete' => 0])
            ->andWhere(['or', ['username' => $username], ['mobile' => $mobile]])
            ->exists();
        if ($exists) {
            return ['ok' => false, 'msg' => '主账号用户名或手机号已存在，请更换'];
        }

        $owner = new Admin();
        $owner->username   = $username;
        $owner->mobile     = $mobile;
        $owner->nickname   = $nickname;
        $owner->name       = $nickname;
        $owner->password   = Yii::$app->security->generatePasswordHash($password);
        $owner->admin_type = Admin::ADMIN_TYPE_FOUNDER;
        $owner->mall_id    = $mallId;
        $owner->created_by = $createdBy;
        if (!$owner->save()) {
            return ['ok' => false, 'msg' => '主账号创建失败：' . implode('；', $owner->getFirstErrors())];
        }
        return ['ok' => true, 'admin_id' => $owner->id];
    }

    /**
     * 给商城主账号配「店长」角色：含本店全部菜单(基座 mall + 已授权插件)。幂等：本店已有则复用。
     * @param string[] $pluginNames 本店已授权插件
     * @return int role_id
     */
    public static function provisionShopAdminRole(Mall $mall, $ownerId, array $pluginNames, $createdBy)
    {
        $role = Role::findOne(['mall_id' => $mall->id, 'role_key' => 'shop_admin_' . $mall->id, 'is_delete' => 0]);
        if (!$role) {
            $role = new Role();
            $role->mall_id             = $mall->id;
            $role->role_name           = '店长';
            $role->role_key            = 'shop_admin_' . $mall->id;
            $role->role_sort           = 0;
            $role->status              = 1;
            $role->admin               = 1;
            $role->menu_check_strictly = 1;
            $role->created_by          = $createdBy;
            if (!$role->save()) {
                throw new \RuntimeException('店长角色创建失败：' . implode('；', $role->getFirstErrors()));
            }

            $menuIds = Menu::idsByPlugins(array_merge(['mall'], $pluginNames));
            $rows = [];
            $now = time();
            foreach ($menuIds as $mid) {
                $rows[] = [$role->id, (string)$mid, $now];
            }
            if ($rows) {
                Yii::$app->db->createCommand()
                    ->batchInsert(RoleMenu::tableName(), ['role_id', 'menu_id', 'created_at'], $rows)
                    ->execute();
            }
        }

        $owner = Admin::findOne($ownerId);
        if ($owner) {
            $owner->updateAttributes(['role_id' => $role->id]);
        }
        if (!AdminRole::find()->where(['admin_id' => $ownerId, 'role_id' => $role->id])->exists()) {
            $ar = new AdminRole();
            $ar->admin_id = $ownerId;
            $ar->role_id  = $role->id;
            if (!$ar->save()) {
                throw new \RuntimeException('AdminRole 关联失败：' . implode('；', $ar->getFirstErrors()));
            }
        }
        return $role->id;
    }

    /**
     * AdminMall 绑定 owner 为某商城 founder（幂等）。
     */
    public static function bindFounder($mallId, $ownerId)
    {
        $exists = AdminMall::find()
            ->where(['mall_id' => $mallId, 'admin_id' => $ownerId, 'is_delete' => 0])
            ->exists();
        if (!$exists) {
            $am = new AdminMall();
            $am->admin_id = $ownerId;
            $am->role     = AdminMall::FOUNDER;
            $am->mall_id  = $mallId;
            if (!$am->save()) {
                throw new \RuntimeException('AdminMall 关联失败：' . implode('；', $am->getFirstErrors()));
            }
        }
    }
}
