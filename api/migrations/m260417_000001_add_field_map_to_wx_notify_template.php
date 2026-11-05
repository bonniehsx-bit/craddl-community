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
 * wx_notify_template 加字段映射列
 *
 * field_map JSON 示例（内部字段名 → 微信模板变量名）：
 *   {"device_name":"keyword1","temp":"keyword2","threshold":"keyword3","tip":"keyword4"}
 *
 * 管理员在后台配置模板 ID 时一并填写映射，监听器发送前自动翻译字段名。
 */
class m260417_000001_add_field_map_to_wx_notify_template extends Migration
{
    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema('{{%wx_notify_template}}');
        if (!$schema) {
            echo "    > 表不存在，跳过。\n";
            return;
        }
        if (isset($schema->columns['field_map'])) {
            echo "    > field_map 列已存在，跳过。\n";
            return;
        }
        $this->addColumn('{{%wx_notify_template}}', 'field_map',
            $this->text()->defaultValue(null)->comment('字段映射 JSON：内部字段名 → 微信模板变量名'));
    }

    public function safeDown()
    {
        $this->dropColumn('{{%wx_notify_template}}', 'field_map');
    }
}
