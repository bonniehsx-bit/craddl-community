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
 * 退款流水加「下次自动重试时间」next_retry_at:失败后按原因决定隔多久再试(见 RefundAttentionService::nextRetryAt)。
 *
 * ## 为什么
 * 原来不管什么原因失败,定时任务都是隔 1 分钟连试 3 次就放弃:
 * 余额不足这种等进账就能好的,3 分钟就放弃、只能人工重发;支付方式没开启这种不改配置永远不会好的,白试 3 次。
 * 现在失败时算好下次什么时候再试,0 = 不再自动重试、交给人处理(「退款失败」处理台)。
 *
 * ## 老数据
 * 老规则下还会接着自动重试的失败记录(没试满 3 次、也没被记成功过),置为「现在就到期」,下一轮按新规则接着试;
 * 其余失败记录保持 0 = 已放弃,和原来一样出现在处理台。
 *
 * ## 注意
 * · 只加一列 + 一次条件更新,不动其他字段;幂等:列已存在就跳过加列;
 * · 必须先于代码上线:新代码的定时退款按这一列捞单。
 */
class m260926_110000_add_next_retry_at_to_common_pay_refund extends Migration
{
    const T = '{{%common_pay_refund}}';

    public function safeUp()
    {
        $schema = $this->db->schema->getTableSchema(self::T, true);
        if ($schema->getColumn('next_retry_at') === null) {
            $this->addColumn(self::T, 'next_retry_at', $this->integer()->notNull()->defaultValue(0)
                ->comment('失败后下次自动重试的时间;0=不再自动重试(要人处理)')->after('retry_count'));
        } else {
            echo "    > 列 next_retry_at 已存在,跳过加列。\n";
        }
        $this->update(self::T, ['next_retry_at' => time()], ['and',
            ['status' => 3, 'is_delete' => 0, 'next_retry_at' => 0],
            ['<', 'retry_count', 3],
            ['or', ['success_at' => 0], ['success_at' => null]],
        ]);
        return true;
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema(self::T, true)->getColumn('next_retry_at') !== null) {
            $this->dropColumn(self::T, 'next_retry_at');
        }
        return true;
    }
}
