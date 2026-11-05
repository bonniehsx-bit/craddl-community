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
 * 订单新增「运费是否已打款」标志 is_express_paid,与 is_express_settled 解耦。
 *
 * 背景(H1 资损修复):原 is_express_settled 被两处复用且语义冲突——
 *   · calculateFinalIncome:当"财务已汇总"用,结算时无条件置1(运费已计入 mch_income 统计字段);
 *   · settleFreight:当"运费已打款"用,见1即早退 → 真实运费 commission_log 永不产生 → 第三方商家收不到运费。
 * 拆出独立字段 is_express_paid 专给 settleFreight(打款门 + 打款后置1),is_express_settled 保留"财务汇总"原义。
 *
 * 历史处理(经决策:只修往后 + 历史写销):
 *   把历史已结算单(is_express_settled=1)的 is_express_paid 回填为1 → 视作已结算、不被兜底任务批量补打,
 *   历史运费亏损一笔勾销;新单从此走 settleFreight 正常打运费。
 */
class m260620_100000_add_is_express_paid_to_order extends Migration
{
    public function safeUp()
    {
        if (!$this->columnExists('is_express_paid')) {
            $this->addColumn(
                '{{%order}}',
                'is_express_paid',
                $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('运费是否已打款给商家:0=未打 1=已打(与财务汇总标志 is_express_settled 解耦)')->after('is_express_settled')
            );
            // 历史写销:已结算单视作运费已了结,不补打(避免兜底任务批量补发历史运费)
            $this->execute("UPDATE {{%order}} SET is_express_paid = 1 WHERE is_express_settled = 1");
        }
    }

    public function safeDown()
    {
        if ($this->columnExists('is_express_paid')) {
            $this->dropColumn('{{%order}}', 'is_express_paid');
        }
    }

    private function columnExists(string $col): bool
    {
        $db = \Yii::$app->db;
        $table = $db->tablePrefix . 'order';
        return (bool)$db->createCommand(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c",
            [':t' => $table, ':c' => $col]
        )->queryScalar();
    }
}
