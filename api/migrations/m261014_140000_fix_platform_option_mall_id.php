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
use yii\db\Query;

/**
 * 修正存错商城号的平台级配置。
 *
 * 原因:平台级配置(商城号 0)第一次插入时,BaseActiveRecord::beforeSave 会把「空」商城号换成当前商城
 * (后台请求带了商城、命令行默认商城 1),库里于是存到了某个商城下。读的人按商城号 0 查不到,
 * 全靠缓存「名字_0」撑着,缓存一丢配置就没了;之后每保存一次又按 0 查不到、再插一行。
 * Option::beforeSave 已改成按调用方给的商城号原样存,这里把已经存错的行改回来。
 *
 * 哪些是平台级配置:全仓按商城号 0 读写的只有 sys、domain、sys_sms_setting、site_setting、
 * wechat_mch_{商户号},没有任何地方按具体商城读写它们,所以这些名字落在非 0 商城号下的行一定是存错的。
 *
 * 每个名字:
 *   - 同名各行里最后保存的那行就是当前值(每次保存,库里那行和缓存写的是同一份)
 *   - 没有商城号 0 的行:把最后保存的那行改成商城号 0;
 *     已经有:最后保存的若是存错的那行,把它的值写进商城号 0 那行
 *   - 其余存错的行标记删除(is_delete=1,可恢复)
 *
 * 不动缓存:缓存里本来就是当前生效的值,不碰它线上行为就不会变;改完库以后缓存丢了也读得回来。
 * 可重复执行,没有存错的数据时什么都不做。不打印配置内容(wechat_mch_* 里有支付密钥)。
 */
class m261014_140000_fix_platform_option_mall_id extends Migration
{
    private const TABLE = '{{%option}}';
    private const NAMES = ['sys', 'domain', 'sys_sms_setting', 'site_setting'];

    public function safeUp()
    {
        $names = array_merge(self::NAMES, (new Query())->select('name')->distinct()->from(self::TABLE)
            ->where(['like', 'name', 'wechat\_mch\_%', false])->column($this->db));

        $fixed = 0;
        foreach (array_unique($names) as $name) {
            $rows = (new Query())->select(['id', 'mall_id', 'value', 'updated_at'])->from(self::TABLE)
                ->where(['name' => $name, 'is_delete' => 0])
                ->orderBy(['updated_at' => SORT_DESC, 'id' => SORT_DESC])
                ->all($this->db);
            $wrong = array_values(array_filter($rows, function ($r) { return (int) $r['mall_id'] !== 0; }));
            if (!$wrong) {
                continue;
            }
            $latest = $rows[0];
            $platform = current(array_filter($rows, function ($r) { return (int) $r['mall_id'] === 0; })) ?: null;

            if ($platform === null) {
                $this->update(self::TABLE, ['mall_id' => 0], ['id' => $latest['id']]);
                $keepId = (int) $latest['id'];
            } else {
                $keepId = (int) $platform['id'];
                if ((int) $latest['mall_id'] !== 0 && $latest['value'] !== $platform['value']) {
                    $this->update(self::TABLE, ['value' => $latest['value'], 'updated_at' => $latest['updated_at']], ['id' => $keepId]);
                }
            }

            $staleIds = array_values(array_diff(array_map('intval', array_column($wrong, 'id')), [$keepId]));
            if ($staleIds) {
                $this->update(self::TABLE, ['is_delete' => 1, 'deleted_at' => time()], ['id' => $staleIds]);
            }
            $fixed++;
            echo "    > {$name}:存错商城号 " . count($wrong) . " 行,已修正\n";
        }
        echo $fixed ? "    > 共修正 {$fixed} 个平台级配置\n" : "    > 没有存错商城号的平台级配置\n";
        return true;
    }

    public function safeDown()
    {
        echo "    > 数据修正,不回滚\n";
        return true;
    }
}
