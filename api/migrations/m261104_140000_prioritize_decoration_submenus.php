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

/** DIY页面、用户中心置于运营装修前两位，其余菜单相对顺序不变。 */
class m261104_140000_prioritize_decoration_submenus extends Migration
{
    public function safeUp()
    {
        $parent = 'operations-decoration';
        $priority = ['mall-diy', 'mall-user-center'];
        $menus = (new Query())->from('{{%menu}}')->where([
            'parent_id' => $parent, 'is_delete' => 0, 'hidden' => 0,
        ])->all();
        if (count(array_intersect($priority, array_column($menus, 'menu_id'))) !== 2) {
            throw new RuntimeException('请先执行运营装修分组迁移并确认 DIY页面、用户中心菜单存在');
        }
        $ids = array_column($menus, 'menu_id');
        $options = (new Query())->from('{{%mall_menu_option}}')->where(['menu_id' => $ids])
            ->andWhere(['or', ['custom_parent_id' => $parent], ['custom_parent_id' => ''], ['custom_parent_id' => null]])->all();
        // 在现有最小排序值之前留两个位置，其他菜单及自定义排序无需改动。
        $minimum = min(array_column($menus, 'order_num'));
        foreach ($options as $option) {
            if ($option['custom_sort'] !== null && $option['custom_sort'] !== '') {
                $minimum = min($minimum, (int)$option['custom_sort']);
            }
        }
        foreach ($priority as $index => $menuId) {
            $sort = $minimum - 2 + $index;
            $this->update('{{%menu}}', ['order_num' => $sort], ['menu_id' => $menuId, 'parent_id' => $parent]);
            foreach ($options as $option) {
                if ($option['menu_id'] === $menuId && $option['custom_sort'] !== null && $option['custom_sort'] !== '') {
                    $this->update('{{%mall_menu_option}}', ['custom_sort' => $sort], [
                        'id' => $option['id'], 'mall_id' => $option['mall_id'],
                    ]);
                }
            }
        }
    }

    public function safeDown()
    {
        // 各商城原有排序不同，不能以统一默认值覆盖；需要恢复时使用执行前快照。
        return false;
    }
}
