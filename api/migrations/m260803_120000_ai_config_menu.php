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
use app\helpers\OptionHelper;
use yii\db\Query;

/**
 * 后台「商城管理 → AI 配置」菜单 + 存量配置上收。
 *
 * 背景：AI 供应商配置原先散在各插件自己的设置页（beauty 存在 plugin_beauty_setting），
 * 导致同一个商城可能出现多份互相打架的密钥。现上收为商城级公共配置，
 * 所有用到 AI 的插件共用一份。
 *
 * 组件 admin/src/views/mall/ai-config.vue，接口 /mall/ai-config/*，
 * 存储 OptionHelper key = ai_setting（见 app\services\ai\AiProviderFactory）。
 *
 * 幂等：菜单已存在则跳过；配置搬迁只在核心尚未配置时执行，不覆盖已有值。
 */
class m260803_120000_ai_config_menu extends Migration
{
    /** 核心 AI 配置存储键 */
    private const CORE_KEY = 'ai_setting';

    /** 需要从插件配置搬上来的字段 */
    private const FIELDS = [
        'ai_provider', 'ai_api_key', 'ai_base_url', 'ai_model',
        'ai_fallback_provider', 'ai_fallback_api_key',
        'asr_secret_id', 'asr_secret_key',
        'image_host_whitelist',
    ];

    public function safeUp()
    {
        $this->createMenu();
        $this->migrateBeautySetting();
    }

    public function safeDown()
    {
        $this->delete('{{%menu}}', ['menu_id' => 'mall-ai-config']);
        echo "    > 已移除 AI 配置菜单（上收的配置数据保留，不回滚）\n";
    }

    private function createMenu(): void
    {
        $exists = (new Query())->from('{{%menu}}')->where(['menu_id' => 'mall-ai-config'])->exists();
        if ($exists) {
            echo "    > 菜单 mall-ai-config 已存在，跳过\n";
            return;
        }
        $this->insert('{{%menu}}', [
            'menu_id'     => 'mall-ai-config',
            'menu_name'   => 'AI 配置',
            'icon'        => 'el-icon-cpu',
            'menu_type'   => 'C',
            'order_num'   => 51,       // 紧随「名词配置」(50)
            'parent_id'   => 'mall',   // 挂在「商城管理」下
            'is_cache'    => 1,
            'hidden'      => 0,
            'is_frame'    => 1,
            'status'      => 0,
            'path'        => 'ai-config',
            'component'   => 'mall/ai-config',
            'query'       => '',
            'perms'       => '',
            'link'        => '',
            'plugin'      => 'mall',
            'active_menu' => '',
            'breadcrumb'  => 1,
            'name'        => 'mall-ai-config',
            'show_tag'    => 1,
        ]);
        echo "    > 已创建「商城管理 → AI 配置」菜单\n";
    }

    /**
     * 把 beauty 插件里已配好的 AI 密钥搬到核心，避免上线后运维要重配一遍。
     * 逐商城处理；核心已有配置的商城直接跳过，不覆盖。
     */
    private function migrateBeautySetting(): void
    {
        $rows = (new Query())
            ->select(['mall_id'])
            ->from('{{%option}}')
            ->where(['name' => 'plugin_beauty_setting'])
            ->column();

        if (!$rows) {
            echo "    > 未发现 beauty 存量 AI 配置，跳过搬迁\n";
            return;
        }

        $moved = 0;
        foreach ($rows as $mallId) {
            $mallId = (int)$mallId;

            $core = OptionHelper::get(self::CORE_KEY, $mallId, []);
            if (is_array($core) && !empty($core['ai_provider'])) {
                continue; // 核心已配置，尊重现状
            }

            $old = OptionHelper::get('plugin_beauty_setting', $mallId, []);
            if (!is_array($old)) {
                continue;
            }

            $new = is_array($core) ? $core : [];
            $hit = false;
            foreach (self::FIELDS as $f) {
                if (isset($old[$f]) && $old[$f] !== '') {
                    $new[$f] = $old[$f];
                    $hit = true;
                }
            }
            if (!$hit) {
                continue;
            }

            OptionHelper::set(self::CORE_KEY, $new, $mallId);
            $moved++;
            echo "    > mall#{$mallId} 的 AI 配置已上收到核心\n";
        }

        echo "    > 配置搬迁完成，共 {$moved} 个商城\n";
        echo "    > 注：插件侧旧配置保留未删，确认无误后可自行清理\n";
    }
}
