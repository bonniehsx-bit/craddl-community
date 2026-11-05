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
 * goods 增加「自动下架标记」列 auto_off_at。
 *
 * 背景:core/cron/GoodsDownTask 每 2 分钟把 stock<=0 的商品刷成下架。
 * 运营如果手动把一个 stock=0 的商品上架(预售、按需备货、不管理库存的服务类商品、
 * 供应链代发……),两分钟内就会被系统无声打回,运营只会觉得「上架按钮坏了」。
 *
 * 三态语义:
 *   0   归系统自动管理(默认;从没被自动下架过,或库存已恢复)
 *   >0  被 GoodsDownTask 自动下架的时间戳
 *   -1  运营在自动下架后又手动上架过 → 豁免,系统不再抢方向盘,
 *       直到库存重新 >0(那时 cron 会清回 0,恢复自动管理)
 *
 * 判定与状态流转全部收在 GoodsDownTask 内部,不侵入任何商品编辑/批量上下架入口,
 * 避免将来新增入口时漏改。
 */
class m260821_140000_add_auto_off_at_to_goods extends Migration
{
    public function safeUp()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%goods}}');
        if ($table && !isset($table->columns['auto_off_at'])) {
            $this->addColumn(
                '{{%goods}}',
                'auto_off_at',
                $this->integer()->notNull()->defaultValue(0)
                    ->comment('售罄自动下架标记:0=归系统自动管理 >0=自动下架时间戳 -1=运营手动上架过,豁免')
                    ->after('status')
            );
            // GoodsDownTask 每 2 分钟按 (mall_id, status, auto_off_at, stock) 扫一遍
            $this->createIndex('idx_goods_mall_status_autooff', '{{%goods}}', ['mall_id', 'status', 'auto_off_at']);
        }
    }

    public function safeDown()
    {
        $table = \Yii::$app->db->schema->getTableSchema('{{%goods}}');
        if ($table && isset($table->columns['auto_off_at'])) {
            $this->dropIndex('idx_goods_mall_status_autooff', '{{%goods}}');
            $this->dropColumn('{{%goods}}', 'auto_off_at');
        }
    }
}
