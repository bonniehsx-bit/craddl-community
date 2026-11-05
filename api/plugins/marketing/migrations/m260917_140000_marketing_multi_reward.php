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
 * 营销插件:奖励从「只发券」扩展为 券 / 积分 / 余额,并支持阶梯档位。
 *
 *  1. 新表 plugin_marketing_reward(活动 → 档位 → 奖励项),并把旧 campaign_coupon 数据搬过来(档位 0)
 *  2. grant_log 加 奖励类型/奖励项/序号/档位/数额/资产流水ID/状态/回收时间;coupon_id 放宽为默认 0
 *  3. grant_log 去重改为唯一索引 (campaign_id,user_id,dedup_key,reward_id,seq)
 *     —— 发余额是真金白银,只靠「先查一遍」挡不住并发重复发放;历史行 seq 回填为 id 保证不冲突
 *  4. campaign 加 积分/余额 预算与已发累计
 *
 * 旧表 campaign_coupon 保留不删(回滚兜底),代码不再读写。
 */
class m260917_140000_marketing_multi_reward extends Migration
{
    public function safeUp()
    {
        // 1. 奖励项表
        $t = '{{%plugin_marketing_reward}}';
        if ($this->db->getTableSchema($t, true) === null) {
            $this->createTable($t, [
                'id' => $this->primaryKey(),
                'mall_id' => $this->integer()->notNull(),
                'campaign_id' => $this->integer()->notNull()->comment('活动ID'),
                'tier_no' => $this->integer()->notNull()->defaultValue(0)->comment('档位序号(0=非阶梯活动)'),
                'reward_type' => $this->string(20)->notNull()->defaultValue('coupon')->comment('奖励类型: coupon/integral/balance'),
                'coupon_id' => $this->integer()->notNull()->defaultValue(0)->comment('券模板ID(仅 coupon)'),
                'amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('数额(积分数/余额元;coupon 不用)'),
                'num' => $this->integer()->notNull()->defaultValue(1)->comment('张数(仅 coupon)'),
            ], null, '营销活动-奖励项');
            $this->createIndex('idx_campaign_tier', $t, ['campaign_id', 'tier_no']);

            // 旧活动券 → 奖励项(档位 0)
            $old = '{{%plugin_marketing_campaign_coupon}}';
            if ($this->db->getTableSchema($old, true) !== null) {
                $this->execute(
                    "INSERT INTO {$this->raw($t)} (mall_id, campaign_id, tier_no, reward_type, coupon_id, amount, num, is_delete, created_at, updated_at, deleted_at, created_time, updated_time)"
                    . " SELECT mall_id, campaign_id, 0, 'coupon', coupon_id, 0, num, 0, created_at, updated_at, 0, created_time, updated_time"
                    . " FROM {$this->raw($old)} WHERE is_delete = 0"
                );
            }
        }

        // 2. 发放流水扩列
        $g = '{{%plugin_marketing_grant_log}}';
        $schema = $this->db->getTableSchema($g, true);
        $cols = [
            'reward_type' => $this->string(20)->notNull()->defaultValue('coupon')->comment('奖励类型: coupon/integral/balance'),
            'reward_id' => $this->integer()->notNull()->defaultValue(0)->comment('奖励项ID(历史券行为0)'),
            'seq' => $this->integer()->notNull()->defaultValue(0)->comment('同一奖励项内序号(券多张时区分)'),
            'tier_no' => $this->integer()->notNull()->defaultValue(0)->comment('档位序号'),
            'amount' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('积分数/余额元'),
            'ref_id' => $this->integer()->notNull()->defaultValue(0)->comment('资产流水ID(积分为 integral_log.id)'),
            'status' => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('1=已发放 2=已回收'),
            'reverted_at' => $this->integer()->notNull()->defaultValue(0)->comment('回收时间'),
        ];
        foreach ($cols as $name => $def) {
            if ($schema->getColumn($name) === null) {
                $this->addColumn($g, $name, $def);
            }
        }
        $this->alterColumn($g, 'coupon_id', $this->integer()->notNull()->defaultValue(0)->comment('券模板ID(非券奖励为0)'));

        // 3. 唯一索引兜底去重。历史行(reward_id=0)一次发多张同券时 seq 都是 0,先回填 seq=id 再建
        if (!$this->indexExists($g, 'uk_grant_slot')) {
            $this->execute("UPDATE {$this->raw($g)} SET seq = id WHERE reward_id = 0");
            $this->createIndex('uk_grant_slot', $g, ['campaign_id', 'user_id', 'dedup_key', 'reward_id', 'seq'], true);
        }
        if ($this->indexExists($g, 'idx_campaign_user_dedup')) {
            $this->dropIndex('idx_campaign_user_dedup', $g); // 已被唯一索引最左前缀覆盖
        }
        if (!$this->indexExists($g, 'idx_user_source_status')) {
            $this->createIndex('idx_user_source_status', $g, ['user_id', 'source_event', 'status']);
        }

        // 4. 活动:积分/余额预算
        $c = '{{%plugin_marketing_campaign}}';
        $cs = $this->db->getTableSchema($c, true);
        $campaignCols = [
            'budget_integral' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('积分预算(0=不限)'),
            'budget_balance' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('余额预算元(0=不限)'),
            'grant_integral' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已发积分(扣除回收)'),
            'grant_balance' => $this->decimal(12, 2)->notNull()->defaultValue(0)->comment('已发余额(扣除回收)'),
        ];
        foreach ($campaignCols as $name => $def) {
            if ($cs->getColumn($name) === null) {
                $this->addColumn($c, $name, $def);
            }
        }
    }

    public function safeDown()
    {
        $c = '{{%plugin_marketing_campaign}}';
        $cs = $this->db->getTableSchema($c, true);
        foreach (['budget_integral', 'budget_balance', 'grant_integral', 'grant_balance'] as $name) {
            if ($cs->getColumn($name) !== null) {
                $this->dropColumn($c, $name);
            }
        }

        $g = '{{%plugin_marketing_grant_log}}';
        if ($this->indexExists($g, 'idx_user_source_status')) {
            $this->dropIndex('idx_user_source_status', $g);
        }
        if ($this->indexExists($g, 'uk_grant_slot')) {
            $this->dropIndex('uk_grant_slot', $g);
        }
        if (!$this->indexExists($g, 'idx_campaign_user_dedup')) {
            $this->createIndex('idx_campaign_user_dedup', $g, ['campaign_id', 'user_id', 'dedup_key']);
        }
        // 非券奖励行在旧结构下无意义,回滚时删除
        $this->delete($g, ['<>', 'reward_type', 'coupon']);
        $gs = $this->db->getTableSchema($g, true);
        foreach (['reward_type', 'reward_id', 'seq', 'tier_no', 'amount', 'ref_id', 'status', 'reverted_at'] as $name) {
            if ($gs->getColumn($name) !== null) {
                $this->dropColumn($g, $name);
            }
        }

        if ($this->db->getTableSchema('{{%plugin_marketing_reward}}', true) !== null) {
            $this->dropTable('{{%plugin_marketing_reward}}');
        }
    }

    private function raw(string $table): string
    {
        return '`' . $this->db->schema->getRawTableName($table) . '`';
    }

    private function indexExists(string $table, string $name): bool
    {
        return (bool)$this->db->createCommand(
            "SHOW INDEX FROM {$this->raw($table)} WHERE Key_name = :k",
            [':k' => $name]
        )->queryOne();
    }
}
