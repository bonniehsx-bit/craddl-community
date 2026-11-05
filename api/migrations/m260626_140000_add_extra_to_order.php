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
 * 订单表 {{%order}} 增加通用扩展字段 extra。
 *
 * 用途:各插件存放与该订单相关的「业务附加数据」(JSON),例如商圈账单缴费订单的 {bill_id, bill_amount}。
 * 此前这类数据被塞进 remarks(买家备注),既污染买家备注展示(前端看着像乱码),也语义不符。
 * extra 是后台/系统专用的结构化扩展位,不作为买家备注展示。
 */
class m260626_140000_add_extra_to_order extends Migration
{
    public function safeUp()
    {
        $table = '{{%order}}';
        $schema = \Yii::$app->db->schema->getTableSchema($table, true);
        if ($schema !== null && $schema->getColumn('extra') === null) {
            $this->addColumn($table, 'extra', $this->text()->comment('订单扩展信息(JSON):各插件存业务附加数据(如账单ID等),系统用,不作为买家备注展示'));
        }
    }

    public function safeDown()
    {
        $table = '{{%order}}';
        $schema = \Yii::$app->db->schema->getTableSchema($table, true);
        if ($schema !== null && $schema->getColumn('extra') !== null) {
            $this->dropColumn($table, 'extra');
        }
    }
}
