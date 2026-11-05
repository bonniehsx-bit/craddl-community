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
 * 数据清洗:从各商城存量的「商家中心菜单」配置(option.mch_center_menu_setting)里,
 * 剔除指向已删除页面(服务商/骑手/派单)的工作台按钮。
 *
 * 背景:MchActionsForm 读取该 option;若商城自定义过菜单,会渲染其中保存的按钮 url。
 * 配送派单层剔除后,这些按钮指向的页面(provider 系列 / rider 系列 / delivery-task / delivery-log)已不存在,
 * 会在商家工作台留下死按钮。这里把 manager_actions / employee_actions 里这类项过滤掉。
 *
 * 幂等:再次执行不会误删正常项(只按 url 特征匹配已删页)。不可逆(safeDown 空实现)。
 */
class m260727_110000_clean_mch_menu_dispatch_actions extends Migration
{
    /** 已删页面的 url 特征(delivery-setting 是保留页,不在此列) */
    private array $deadPatterns = [
        'provider-center', 'provider-apply', 'provider-tasks', 'provider-task-detail',
        'rider-center', 'rider-list', 'rider-add', 'rider-task-detail',
        'delivery-task', 'delivery-log',
    ];

    public function safeUp()
    {
        $rows = (new Query())
            ->select(['id', 'value'])
            ->from('{{%option}}')
            ->where(['name' => 'mch_center_menu_setting'])
            ->all($this->db);

        $cleaned = 0;
        foreach ($rows as $row) {
            $data = json_decode((string)$row['value'], true);
            if (!is_array($data)) {
                continue;
            }
            $changed = false;
            foreach (['manager_actions', 'employee_actions'] as $sec) {
                if (empty($data[$sec]) || !is_array($data[$sec])) {
                    continue;
                }
                $before = count($data[$sec]);
                $data[$sec] = array_values(array_filter($data[$sec], function ($a) {
                    $url = is_array($a) ? ($a['url'] ?? '') : '';
                    foreach ($this->deadPatterns as $p) {
                        if ($url !== '' && strpos($url, $p) !== false) {
                            return false;
                        }
                    }
                    return true;
                }));
                if (count($data[$sec]) !== $before) {
                    $changed = true;
                }
            }
            if ($changed) {
                $this->update('{{%option}}', [
                    'value' => json_encode($data, JSON_UNESCAPED_UNICODE),
                ], ['id' => $row['id']]);
                $cleaned++;
            }
        }
        echo "    > 清洗 mch_center_menu_setting 死按钮:{$cleaned} 个商城\n";
    }

    public function safeDown()
    {
        echo "m260727_110000_clean_mch_menu_dispatch_actions 不可逆(存量脏数据清洗)\n";
        return false;
    }
}
