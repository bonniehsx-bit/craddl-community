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
 * AI 调用日志上收核心（平台级审计 + 成本统计）。
 *
 * 原先各插件自建日志表（beauty 的 plugin_beauty_ai_call_log），同一商城的 AI 花销
 * 散在多处无法汇总。现统一记 ai_call_log，新增 plugin / ability 两个维度：
 *   · plugin  来源插件，便于按插件看用量与成本
 *   · ability 能力大类（text/vision/asr），便于跨插件汇总
 * category 保持自由字符串，各插件的业务细分语义（llm_card_reco 等）不丢。
 *
 * 存量数据从 plugin_beauty_ai_call_log 迁入并回填 plugin='beauty'、
 * ability 按 category 推断。旧表保留不删，确认无误后可自行清理。
 */
class m260803_130000_create_ai_call_log extends Migration
{
    public function safeUp()
    {
        $tableOptions = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->createTable('{{%ai_call_log}}', [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->comment('商城'),
            'mch_id'      => $this->integer()->defaultValue(0)->comment('门店（无门店概念的插件留 0）'),
            'plugin'      => $this->string(30)->defaultValue('')->comment('来源插件名，核心自身调用留空'),
            'ability'     => $this->string(30)->defaultValue('')->comment('能力大类 text|vision|asr'),
            'category'    => $this->string(30)->defaultValue('')->comment('业务细分，调用方自定义'),
            'provider'    => $this->string(30)->defaultValue('')->comment('实际供应商'),
            'biz_id'      => $this->integer()->defaultValue(0)->comment('关联业务ID'),
            'input_size'  => $this->integer()->defaultValue(0)->comment('入参体量'),
            'tokens_in'   => $this->integer()->defaultValue(0),
            'tokens_out'  => $this->integer()->defaultValue(0),
            'duration_ms' => $this->integer()->defaultValue(0)->comment('耗时(ms)'),
            'cost_amount' => $this->decimal(10, 4)->defaultValue(0)->comment('扣费(元)'),
            'status'      => $this->tinyInteger(1)->defaultValue(0)->comment('0成功 1降级 2失败'),
            'error_msg'   => $this->string(500)->defaultValue('')->comment('错误信息'),
        ], $tableOptions);

        $this->createIndex('idx_ai_log_mall',   '{{%ai_call_log}}', ['mall_id', 'plugin', 'created_at']);
        $this->createIndex('idx_ai_log_status', '{{%ai_call_log}}', ['mall_id', 'status']);

        $this->migrateBeautyLogs();
    }

    public function safeDown()
    {
        $this->dropTableIfExists('{{%ai_call_log}}');
    }

    /**
     * 搬运 beauty 存量调用日志。旧表不存在（未装过 beauty）时静默跳过。
     */
    private function migrateBeautyLogs(): void
    {
        $old = Yii::$app->db->schema->getTableSchema('{{%plugin_beauty_ai_call_log}}', true);
        if ($old === null) {
            echo "    > 未发现 beauty 存量调用日志表，跳过搬迁\n";
            return;
        }

        // category → ability 推断：语音、图片各自成类，其余归文本
        $abilityCase = "CASE
            WHEN category = 'voice_transcribe' THEN 'asr'
            WHEN category = 'skin_detect'      THEN 'vision'
            ELSE 'text' END";

        $sql = "INSERT INTO {{%ai_call_log}}
                (mall_id, mch_id, plugin, ability, category, provider, biz_id,
                 input_size, tokens_in, tokens_out, duration_ms, cost_amount,
                 status, error_msg, is_delete, created_at, updated_at)
                SELECT mall_id, mch_id, 'beauty', {$abilityCase}, category, provider, biz_id,
                       input_size, tokens_in, tokens_out, duration_ms, cost_amount,
                       status, error_msg, is_delete, created_at, updated_at
                FROM {{%plugin_beauty_ai_call_log}}";

        $n = Yii::$app->db->createCommand($sql)->execute();
        echo "    > 已搬运 beauty 存量调用日志 {$n} 条（旧表保留未删）\n";
    }
}
