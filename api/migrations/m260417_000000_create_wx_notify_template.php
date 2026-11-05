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

/**
 * 微信推送模板配置表（全局，所有插件共用）
 *
 * 设计原则：
 *   - 表结构在此定义，属于平台核心，不归属任何单一插件
 *   - 各插件通过自己的 migration 向此表 INSERT 预置场景行
 *   - 管理员只能修改 template_id，scene/title 由开发者维护
 */
class m260417_000000_create_wx_notify_template extends Migration
{
    public function safeUp()
    {
        // 兼容已通过 hardware 插件 migration 建过表的环境
        if ($this->db->schema->getTableSchema('{{%wx_notify_template}}') !== null) {
            echo "    > 表 wx_notify_template 已存在，跳过建表。\n";
            return;
        }

        $this->createTable('{{%wx_notify_template}}', [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->comment('商城 ID'),
            'plugin'      => $this->string(50)->notNull()->comment('所属插件，如 hardware'),
            'scene'       => $this->string(100)->notNull()->comment('场景键，如 hardware.bottle.feeding_end'),
            'title'       => $this->string(100)->notNull()->comment('场景名称（只读展示）'),
            'template_id' => $this->string(100)->defaultValue(null)->comment('微信公众号模板 ID，为空表示未配置'),
        ]);

        $this->createIndex('idx_wx_notify_mall_scene', '{{%wx_notify_template}}', ['mall_id', 'scene'], true);
        $this->createIndex('idx_wx_notify_plugin',     '{{%wx_notify_template}}', ['mall_id', 'plugin']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%wx_notify_template}}');
    }
}
