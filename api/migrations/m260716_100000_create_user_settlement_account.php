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
 * 结算账户归一化子表 user_settlement_account。
 *
 * 背景:原 user_settlement_info 用两个 JSON blob 存"每渠道账户"(channel_data)和"每渠道进件状态"
 * (extra_info.onboarding_status),key = 打款网关完整类名(FQCN)。blob 方案的硬伤:
 *   · 更新一个渠道要读改写整块 → start() 陈旧实例回写覆盖(lost-update);
 *   · 无法按状态查询/建索引 → 打款对账兜底("找出所有卡 PAYING/DOING 的单")只能全表扫 + PHP 解 JSON;
 *   · channel_data 是 json 列 + 模型又手动 Json::encode = 双重编码地雷。
 *
 * 归一化:把"需要被查询的轴"(渠道 fund_class + 状态 status)拆成索引列,一人×渠道一行;
 * 渠道账户散字段仍按行存 JSON,但用 text 列 + 单层手动 encode(避开双重编码)。
 * FQCN 仍作为渠道标识(守"禁 code 映射"铁律),只是从 JSON key 变成索引列的值。
 *
 * 上线策略:本表先作"影子表"——UserSettlementInfo::afterSave 从 blob 派生同步进来,读仍走 blob。
 * 存量数据由回填命令(settlement-account-backfill)迁入。生产回填+比对通过后再翻转读源。
 */
class m260716_100000_create_user_settlement_account extends Migration
{
    private $table = '{{%user_settlement_account}}';

    public function safeUp()
    {
        // 幂等:已存在则跳过
        if ($this->db->getTableSchema($this->table, true) !== null) {
            echo "    {$this->table} 已存在,跳过\n";
            return;
        }

        $this->createTable($this->table, [
            'id'                => $this->bigPrimaryKey(),
            'mall_id'           => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'user_id'           => $this->integer()->notNull()->comment('用户ID'),
            // 打款网关完整类名(反射用),如 app\funds\BankFund / app\funds\FastPayFlexibleLaborFund。
            // 写入前统一 ltrim 前导反斜杠,与 self::class 规范形式一致。
            'fund_class'        => $this->string(150)->notNull()->comment('打款网关完整类名(FQCN)'),
            // 进件/签约状态:none/pending/pending_verify/success/failed。独立成列 → 可索引对账。
            'status'            => $this->string(20)->notNull()->defaultValue('none')->comment('进件状态'),
            'status_remark'     => $this->string(255)->null()->comment('状态备注(原 onboarding_status.remark)'),
            'fund_user_id'      => $this->string(64)->null()->comment('第三方签约/用户号(FastPay sign_id 等)'),
            'status_updated_at' => $this->integer()->notNull()->defaultValue(0)->comment('状态更新时间戳'),
            // 该渠道账户散字段(bank_card_no/bank_name/支行/alipay_account/身份证照片 fileId…)。
            // 用 text 而非 json:避免 Yii json 列 typecast 与模型手动 encode 叠加成双重编码。单层 JSON。
            'account_data'      => $this->text()->null()->comment('该渠道账户专属信息(单层 JSON)'),
            'is_default'        => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('是否该用户默认收款渠道'),
        ], null, '结算账户归一化子表(一人×渠道一行)');

        // 一人一商城一渠道唯一
        $this->createIndex('uk-usa-user_mall_fund', $this->table, ['user_id', 'mall_id', 'fund_class'], true);
        // 对账核心:按商城+渠道+状态捞(如"所有待对账的 FastPay 单")
        // 逐用户加载走基类自动追加的 (user_id, is_delete) 复合索引,无需再显式建
        $this->createIndex('idx-usa-mall_fund_status', $this->table, ['mall_id', 'fund_class', 'status']);

        // ── 回填存量:遍历 user_settlement_info,复用 syncAccountsFromBlob 把两个 blob 派生进子表 ──
        // 该逻辑正确解双重编码(经模型 getChannelData);UPSERT 幂等,重跑安全。表体量小,直接内联。
        $this->backfill();
    }

    /**
     * 存量回填。逐行 best-effort:单行异常只告警不中断整表迁移。
     */
    private function backfill()
    {
        $total = 0;
        $ok = 0;
        $fail = 0;
        foreach (\app\models\UserSettlementInfo::find()->where(['is_delete' => 0])->each(200) as $info) {
            $total++;
            try {
                $info->syncAccountsFromBlob();
                $ok++;
            } catch (\Throwable $e) {
                $fail++;
                echo "    回填失败 user_id={$info->user_id}: {$e->getMessage()}\n";
            }
        }
        echo "    回填完成:共 {$total} 行,成功 {$ok},失败 {$fail}\n";
    }

    public function safeDown()
    {
        if ($this->db->getTableSchema($this->table, true) !== null) {
            $this->dropTable($this->table);
        }
    }
}
