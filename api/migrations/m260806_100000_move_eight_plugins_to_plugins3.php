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
 * 8 个插件的 uniapp 页面从 plugins 分包搬到 plugins3,改写库里存着的旧链接。
 *
 * 搬家的是 uniapp/plugins/{name} → uniapp/plugins3/{name},代码里的引用已随之改掉。
 * 但页面路径不只存在代码里 —— 后台配的个人中心入口、首页装修的跳转都把完整链接
 * 写进了库,不改这里的话前端编译完一切正常,用户点过去全是空白页。
 *
 * ## 为什么只替换带前导斜杠的
 * 库里同时存着两类长得很像的路径:
 *   · `/plugins/yuda/index`      C 端页面链接      ← 要改
 *   · `plugins/yuda/goods/index` admin/mch 组件路径 ← 不能改(那两个前端目录没搬家)
 * 二者只差一个前导斜杠。少了这个限定就会把后台菜单的 component 一起改掉,
 * 结果是后台点菜单白屏。代码侧的替换用的是同一条规则。
 *
 * ## 幂等
 * 旧值 → 新值的定向改写,重复执行匹配不到旧值,不会产生二次影响。
 */
class m260806_100000_move_eight_plugins_to_plugins3 extends Migration
{
    /** 搬到 plugins3 分包的插件 */
    const PLUGINS = ['aidb', 'group', 'freshmarket', 'hotel', 'lottery', 'lucky', 'pb', 'yuda'];

    /** @var array 受影响的表 => 列 */
    const TARGETS = [
        '{{%option}}'   => 'value',   // 个人中心入口配置、首页装修配置
        '{{%diy_page}}' => 'data',    // 自定义页装修数据
    ];

    public function safeUp()
    {
        $this->rewrite(true);
    }

    public function safeDown()
    {
        $this->rewrite(false);
    }

    /**
     * @param bool $forward true=plugins→plugins3,false=反向回滚
     */
    private function rewrite(bool $forward)
    {
        $from = $forward ? 'plugins'  : 'plugins3';
        $to   = $forward ? 'plugins3' : 'plugins';

        foreach (self::TARGETS as $table => $column) {
            if ($this->db->getTableSchema($table, true) === null) {
                continue;
            }
            $t = $this->db->quoteTableName($table);
            $c = $this->db->quoteColumnName($column);

            // 先按插件名逐个把候选行捞出来,避免在大表上做全表正则
            $names = implode('|', self::PLUGINS);
            $rows = $this->db->createCommand(
                "SELECT `id`, {$c} AS v FROM {$t} WHERE {$c} REGEXP '/{$from}/({$names})/'"
            )->queryAll();

            $n = 0;
            foreach ($rows as $row) {
                if ($row['v'] === null || $row['v'] === '') {
                    continue;
                }
                $new = $row['v'];
                foreach (self::PLUGINS as $p) {
                    // 定向字符串替换,不做正则 —— 这些列是 JSON,路径埋在任意层级,
                    // 按字符串替换比解析再遍历更不容易出错,也不会改动 JSON 的其它格式。
                    $new = str_replace("/{$from}/{$p}/", "/{$to}/{$p}/", $new);
                }
                if ($new === $row['v']) {
                    continue;
                }
                $this->db->createCommand()->update($table, [$column => $new], ['id' => $row['id']])->execute();
                $n++;
            }
            if ($n > 0) {
                echo "    {$table}.{$column}: 改写 {$n} 行\n";
            }
        }
    }
}
