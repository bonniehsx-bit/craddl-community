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
use yii\db\Expression;
use yii\db\Query;

/** 在商城管理后留出独立排序位，避免运营装修与收付中心同序。 */
class m261104_130000_order_operations_decoration_menu extends Migration
{
    private const ROOT = 'operations-decoration';

    public function safeUp()
    {
        $mall = (new Query())->from('{{%menu}}')->where(['menu_id' => 'mall', 'parent_id' => '0'])->one();
        $root = (new Query())->from('{{%menu}}')->where(['menu_id' => self::ROOT, 'parent_id' => '0'])->one();
        if (!$mall || !$root) {
            throw new RuntimeException('请先执行运营装修分组迁移');
        }
        $position = (int)$mall['order_num'] + 1;
        $collision = (new Query())->from('{{%menu}}')->where(['parent_id' => '0', 'order_num' => $position])
            ->andWhere(['<>', 'menu_id', self::ROOT])->exists();
        if ((int)$root['order_num'] === $position && !$collision) {
            return;
        }
        $roots = (new Query())->select('menu_id')->from('{{%menu}}')->where(['parent_id' => '0'])
            ->andWhere(['<>', 'menu_id', self::ROOT])->column();
        $this->update('{{%menu}}', ['order_num' => new Expression('[[order_num]] + 1')], [
            'and', ['menu_id' => $roots], ['>=', 'order_num', $position],
        ]);
        // 只顺延仍在一级导航里的自定义排序；已被移进其他目录的菜单保留原排序。
        $this->update('{{%mall_menu_option}}', ['custom_sort' => new Expression('[[custom_sort]] + 1')], [
            'and', ['menu_id' => $roots], ['>=', 'custom_sort', $position],
            ['or', ['custom_parent_id' => '0'], ['custom_parent_id' => ''], ['custom_parent_id' => null]],
        ]);
        $this->update('{{%menu}}', ['order_num' => $position], ['menu_id' => self::ROOT, 'parent_id' => '0']);
    }

    public function safeDown()
    {
        $mall = (new Query())->from('{{%menu}}')->where(['menu_id' => 'mall', 'parent_id' => '0'])->one();
        $root = (new Query())->from('{{%menu}}')->where(['menu_id' => self::ROOT, 'parent_id' => '0'])->one();
        if (!$mall || !$root || (int)$root['order_num'] !== (int)$mall['order_num'] + 1) {
            throw new RuntimeException('一级菜单顺序已调整，请核对后再回滚');
        }
        $position = (int)$root['order_num'];
        $roots = (new Query())->select('menu_id')->from('{{%menu}}')->where(['parent_id' => '0'])
            ->andWhere(['<>', 'menu_id', self::ROOT])->column();
        $this->update('{{%menu}}', ['order_num' => new Expression('[[order_num]] - 1')], [
            'and', ['menu_id' => $roots], ['>', 'order_num', $position],
        ]);
        $this->update('{{%mall_menu_option}}', ['custom_sort' => new Expression('[[custom_sort]] - 1')], [
            'and', ['menu_id' => $roots], ['>', 'custom_sort', $position],
            ['or', ['custom_parent_id' => '0'], ['custom_parent_id' => ''], ['custom_parent_id' => null]],
        ]);
    }
}
