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
use yii\helpers\Json;

/**
 * 钱包 key 规范化:mch_funds → mch.funds
 *
 * 钱包 key 的约定是「插件名.字段」(xwy.channel 就是这么定的),货款当初为了沿用
 * 老的 asset_type 值保留了下划线写法,成了唯一的例外。留着它,新插件照抄时就会
 * 分裂出两种风格,后续按前缀解析插件名的逻辑也得为它开特例。
 *
 * 趁现在还没有生产客户在用扩展钱包(全库核查:仅开发库有测试数据)一次改掉。
 *
 * ## 为什么这个迁移必须一次覆盖全部
 * 这个字面量散落在 5 张业务表 + 2 处 option 里。漏掉任何一处的后果都不是"显示不对"
 * 这么轻:
 *   · 漏 user_wallet          → 余额认不出来,用户看到的是 0(钱还在旧 key 下)
 *   · 漏 commission_log.extra_data → 里面记着 payout_type,退款冲账时对不上目标账户,
 *                                    钱扣不回来或扣错账户
 *   · 漏 card 的 settle_asset_type → 实体卡结算打不进货款账户
 *
 * ## 幂等
 * 每条都是「旧值 → 新值」的定向改写,重复执行不会产生二次影响(第二遍匹配不到旧值)。
 */
class m260729_110000_rename_mch_funds_to_mch_dot_funds extends Migration
{
    const OLD = 'mch_funds';
    const NEW = 'mch.funds';

    public function safeUp()
    {
        $db = $this->db;

        // ── 1. 钱包余额与流水(最要紧:认不出 key 等于余额归零)──
        foreach (['{{%user_wallet}}', '{{%user_wallet_log}}'] as $t) {
            if ($db->getTableSchema($t, true) === null) {
                continue;
            }
            $this->update($t, ['wallet_key' => self::NEW], ['wallet_key' => self::OLD]);
        }

        // ── 2. 实体卡的结算资产(批次配置 + 已产生的流水)──
        foreach (['{{%plugin_card_physical_batch}}', '{{%plugin_card_physical_log}}'] as $t) {
            if ($db->getTableSchema($t, true) === null) {
                continue;
            }
            $this->update($t, ['settle_asset_type' => self::NEW], ['settle_asset_type' => self::OLD]);
        }
        // 建表 migration 里把列默认值写死成了 mch_funds,不改的话新建批次又会带出旧值。
        // 历史 migration 不能回头改,只能在这里重设默认值。
        $batch = $db->getTableSchema('{{%plugin_card_physical_batch}}', true);
        if ($batch !== null && $batch->getColumn('settle_asset_type') !== null) {
            $this->alterColumn('{{%plugin_card_physical_batch}}', 'settle_asset_type',
                $this->string(64)->notNull()->defaultValue(self::NEW)->comment('结算资产类型'));
        }

        // ── 3. 分润流水的 payout 目标(JSON,退款冲账要靠它找回账户)──
        $this->rewriteJsonRows('{{%commission_log}}', 'extra_data', function ($data) {
            if (isset($data['payout_type']) && $data['payout_type'] === self::OLD) {
                $data['payout_type'] = self::NEW;
                return $data;
            }
            return null;
        });

        // ── 4. 提现渠道支持的资产(JSON 数组)──
        $this->rewriteJsonRows('{{%plugin_withdraw_channel}}', 'asset_types', function ($data) {
            if (!is_array($data)) {
                return null;
            }
            $i = array_search(self::OLD, $data, true);
            if ($i === false) {
                return null;
            }
            $data[$i] = self::NEW;
            return array_values($data);
        });

        // ── 5. option 里的配置(提现入口开关的对象 key、商家中心菜单等)──
        $this->rewriteJsonRows('{{%option}}', 'value', function ($data) {
            return self::replaceDeep($data) ? $data : null;
        }, "`value` LIKE '%mch\\_funds%' ESCAPE '\\\\'");
    }

    /**
     * 递归把数组里的旧 key/旧值换成新的。
     * 既换值(如 ["balance","mch_funds"]),也换键(如 entries: {"mch_funds":1}) ——
     * option 里两种形态都有。
     *
     * @return bool 是否发生过改动
     */
    private static function replaceDeep(&$data): bool
    {
        if (!is_array($data)) {
            return false;
        }
        $changed = false;
        $out = [];
        foreach ($data as $k => $v) {
            $nk = ($k === self::OLD) ? self::NEW : $k;
            if ($nk !== $k) {
                $changed = true;
            }
            if (is_array($v)) {
                if (self::replaceDeep($v)) {
                    $changed = true;
                }
            } elseif ($v === self::OLD) {
                $v = self::NEW;
                $changed = true;
            }
            $out[$nk] = $v;
        }
        $data = $out;
        return $changed;
    }

    /**
     * 逐行取出 JSON 列、交给回调改写、改动过的写回。
     * 回调返回 null 表示这行不用动。
     */
    private function rewriteJsonRows(string $table, string $column, callable $fn, ?string $where = null)
    {
        if ($this->db->getTableSchema($table, true) === null) {
            return;
        }
        $t = $this->db->quoteTableName($table);
        $c = $this->db->quoteColumnName($column);
        // ⚠ LIKE 里的 `_` 是通配符,直接拼 'mch_funds' 会连 '/mch/funds-center' 这类
        // 无关内容一起捞出来(在大表上白扫一遍)。转义成 mch\_funds 只捞真正相关的行。
        // 注:即便多捞了也不会改错 —— 下面的改写一律用精确相等比较,不做子串替换。
        $like = str_replace('_', '\\_', self::OLD);
        $cond = $where ?: "{$c} LIKE '%{$like}%' ESCAPE '\\\\'";
        $rows = $this->db->createCommand("SELECT `id`, {$c} AS v FROM {$t} WHERE {$cond}")->queryAll();
        $n = 0;
        foreach ($rows as $row) {
            if ($row['v'] === null || $row['v'] === '') {
                continue;
            }
            try {
                $data = Json::decode($row['v']);
            } catch (\Throwable $e) {
                continue;   // 不是合法 JSON 就别碰,免得把脏数据搅得更乱
            }
            $new = $fn($data);
            if ($new === null) {
                continue;
            }
            $this->db->createCommand()->update($table,
                [$column => Json::encode($new)], ['id' => $row['id']])->execute();
            $n++;
        }
        if ($n > 0) {
            echo "    {$table}.{$column}: 改写 {$n} 行\n";
        }
    }

    public function safeDown()
    {
        // 反向改回,便于回滚验证
        foreach (['{{%user_wallet}}', '{{%user_wallet_log}}'] as $t) {
            if ($this->db->getTableSchema($t, true) !== null) {
                $this->update($t, ['wallet_key' => self::OLD], ['wallet_key' => self::NEW]);
            }
        }
        foreach (['{{%plugin_card_physical_batch}}', '{{%plugin_card_physical_log}}'] as $t) {
            if ($this->db->getTableSchema($t, true) !== null) {
                $this->update($t, ['settle_asset_type' => self::OLD], ['settle_asset_type' => self::NEW]);
            }
        }
    }
}
