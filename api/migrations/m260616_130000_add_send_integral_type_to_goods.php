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
 * 商品「赠送积分」增加赠送方式:0=固定积分(每件 × 数量,现状) 1=按实付金额比例(%)。
 *
 * 复用 goods.send_integral 作为"值"(与积分抵扣 use_integral_type/use_integral 同款):
 *   send_integral_type=0 → send_integral 是每件赠送积分数
 *   send_integral_type=1 → send_integral 是百分比(按明细实付金额 pay_price × % 赠送)
 *
 * 列存在性走 information_schema 直查(本环境 getTableSchema 带缓存,refresh 不稳),确保幂等。
 */
class m260616_130000_add_send_integral_type_to_goods extends Migration
{
    public function safeUp()
    {
        if (!$this->columnExists('send_integral_type')) {
            $this->addColumn(
                '{{%goods}}',
                'send_integral_type',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('赠送积分方式:0=固定积分(每件) 1=按实付金额比例(%)')->after('send_integral')
            );
        }
    }

    public function safeDown()
    {
        if ($this->columnExists('send_integral_type')) {
            $this->dropColumn('{{%goods}}', 'send_integral_type');
        }
    }

    private function columnExists(string $col): bool
    {
        $db = \Yii::$app->db;
        $table = $db->tablePrefix . 'goods';
        return (bool)$db->createCommand(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            [':t' => $table, ':c' => $col]
        )->queryScalar();
    }
}
