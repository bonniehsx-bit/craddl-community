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
 * user 表加生日索引 (mall_id, is_delete, month, day)。
 *
 * 背景:生日相关的查询有三条链 ——
 *   · marketing 生日券 cron  TriggerService::runBirthday  → where month=? [and day=?]
 *   · plaza 生日券 job       BirthdayCouponJob            → 原用 DATE_FORMAT(birthday,'%m')
 *   · 后台按生日筛人         CouponSendForm / 生日积分发放页
 * 全都跑在每天/每月的定时任务里,而 user 表是全站最大的表之一(实测客户库 8.9 万行)。
 *
 * 加索引前实测:走 idx-user-mall_id_is_delete 命中 42746 行,再逐行算 DATE_FORMAT 过滤。
 * 列顺序按「等值在前、范围在后」:mall_id 与 is_delete 每次都是等值条件,
 * month 多数场景等值,day 可有可无(当月模式不带 day),放最后正好能只用前缀。
 */
class m260726_170000_add_birthday_index_to_user extends Migration
{
    private $table = '{{%user}}';
    private $index = 'idx-user-mall_id_is_delete_month_day';

    public function safeUp()
    {
        $raw = $this->db->schema->getRawTableName($this->table);
        $exists = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i',
            [':t' => $raw, ':i' => $this->index]
        )->queryScalar();

        if ($exists > 0) {
            echo "    索引 {$this->index} 已存在,跳过\n";
            return true;
        }

        $this->createIndex($this->index, $this->table, ['mall_id', 'is_delete', 'month', 'day']);
        echo "    已创建索引 {$this->index}\n";
        return true;
    }

    public function safeDown()
    {
        $raw = $this->db->schema->getRawTableName($this->table);
        $exists = (int) $this->db->createCommand(
            'SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = :t AND index_name = :i',
            [':t' => $raw, ':i' => $this->index]
        )->queryScalar();

        if ($exists > 0) {
            $this->dropIndex($this->index, $this->table);
        }
        return true;
    }
}
