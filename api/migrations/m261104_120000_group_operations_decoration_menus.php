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

use app\core\Migration;
use yii\db\Query;

/** 统一运营装修入口；既有菜单 ID、组件、权限及页面 URL 保持兼容。 */
class m261104_120000_group_operations_decoration_menus extends Migration
{
    public const ROOT = 'operations-decoration';
    public const MENUS = [
        'mall-diy', 'mall-diy-edit', 'mall-hot-image-list', 'mall-hot-image-edit',
        'mall-user-center', 'mall-manage-center', 'mall-setting-page', 'mall-alert-adv',
        'mall-page', 'mall-outside-link', 'mall-tab-goods', 'mall-noun', 'mall-file-manager',
    ];

    public function safeUp()
    {
        $root = (new Query())->from('{{%menu}}')->where(['menu_id' => self::ROOT])->one();
        if ($root && ($root['plugin'] !== 'mall' || $root['menu_type'] !== 'M' || $root['parent_id'] !== '0' || $root['path'] !== self::ROOT || (int)$root['is_delete'] !== 0)) {
            throw new RuntimeException('运营装修菜单标识被其他配置占用，请先核对');
        }
        if (!$root) {
            $this->insert('{{%menu}}', [
                'menu_id' => self::ROOT, 'menu_name' => '运营装修', 'icon' => 'el-icon-magic-stick',
                'menu_type' => 'M', 'order_num' => 1, 'parent_id' => '0',
                'is_cache' => 1, 'hidden' => 0, 'is_frame' => 1, 'status' => 0,
                'path' => self::ROOT, 'component' => '', 'query' => '', 'perms' => '',
                'link' => '', 'plugin' => 'mall', 'active_menu' => '', 'breadcrumb' => 1,
                'name' => self::ROOT, 'show_tag' => 0,
            ]);
        }

        $menus = (new Query())->from('{{%menu}}')->where([
            'menu_id' => self::MENUS, 'plugin' => 'mall', 'is_delete' => 0,
            'parent_id' => ['mall', self::ROOT],
        ])->all();
        $menuIds = array_column($menus, 'menu_id');
        foreach ($menus as $menu) {
            // 父目录变更时保留绝对地址，历史书签、代码跳转和高亮路径继续使用 /mall/*。
            $path = $menu['path'];
            if ($path !== '' && $path[0] !== '/' && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
                $path = '/mall/' . ltrim($path, '/');
            }
            $this->update('{{%menu}}', ['parent_id' => self::ROOT, 'path' => $path], ['id' => $menu['id']]);
        }

        // 商城保存过的默认父级会覆盖系统菜单，需要一并改；其他显式自定义分组保持原样。
        if ($menuIds) {
            $options = (new Query())->from('{{%mall_menu_option}}')->where(['menu_id' => $menuIds])
                ->andWhere(['or', ['custom_parent_id' => 'mall'], ['custom_parent_id' => ''], ['custom_parent_id' => null]])->all();
            foreach ($options as $option) {
                $changes = [];
                if ($option['custom_parent_id'] === 'mall') {
                    $changes['custom_parent_id'] = self::ROOT;
                }
                $path = $option['custom_path'] ?? '';
                if ($path !== '' && $path[0] !== '/' && !preg_match('/^[a-z][a-z0-9+.-]*:/i', $path)) {
                    $changes['custom_path'] = '/mall/' . ltrim($path, '/');
                }
                if ($changes) {
                    $this->update('{{%mall_menu_option}}', $changes, ['id' => $option['id'], 'mall_id' => $option['mall_id']]);
                }
            }

            // 仅给原本已有这些子菜单的角色补目录授权，不增加任何业务页面权限。
            $roles = (new Query())->select('role_id')->distinct()->from('{{%role_menu}}')
                ->where(['menu_id' => $menuIds, 'is_delete' => 0])->column();
            foreach ($roles as $roleId) {
                if (!(new Query())->from('{{%role_menu}}')->where(['role_id' => $roleId, 'menu_id' => self::ROOT])->exists()) {
                    $this->insert('{{%role_menu}}', ['role_id' => $roleId, 'menu_id' => self::ROOT]);
                }
            }
        }
    }

    public function safeDown()
    {
        // 用户后续在新目录里增加了其他内容时，避免回滚导致孤儿菜单。
        $extra = (new Query())->from('{{%menu}}')->where(['parent_id' => self::ROOT])
            ->andWhere(['not in', 'menu_id', self::MENUS])->exists();
        $customExtra = (new Query())->from('{{%mall_menu_option}}')->where(['custom_parent_id' => self::ROOT])
            ->andWhere(['not in', 'menu_id', self::MENUS])->exists();
        if ($extra || $customExtra) {
            throw new RuntimeException('运营装修目录内已有其他菜单，请先调整后再回滚');
        }
        // 保留绝对地址；回到旧目录后仍然是相同的 /mall/* URL。
        $this->update('{{%menu}}', ['parent_id' => 'mall'], ['menu_id' => self::MENUS, 'plugin' => 'mall', 'parent_id' => self::ROOT]);
        $this->update('{{%mall_menu_option}}', ['custom_parent_id' => 'mall'], ['menu_id' => self::MENUS, 'custom_parent_id' => self::ROOT]);
        $this->delete('{{%role_menu}}', ['menu_id' => self::ROOT]);
        $this->delete('{{%mall_menu_option}}', ['menu_id' => self::ROOT]);
        $this->delete('{{%menu}}', ['menu_id' => self::ROOT, 'plugin' => 'mall', 'parent_id' => '0']);
    }
}
