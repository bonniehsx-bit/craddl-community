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
 * 创建 mch 商家入驻「字段可配置 + 证照库」三张表
 *
 *  - plugin_mch_apply_field : 入驻字段配置覆盖(按 mall_id + 主体类型)。
 *      无记录时回退代码注册表 MchApplyFieldRegistry 的默认值,保证零行为变化。
 *  - plugin_mch_cert_def    : 证照库定义(后台可增减的证照种类,含有效期开关)。
 *      营业执照为预置(preset_key=business_license, is_system=1),由服务层惰性补齐。
 *  - plugin_mch_certificate : 商家提交的证照值(图片 + 有效期),valid_end 独立成列便于到期查询。
 *
 * 设计要点:核心字段仍存 plugin_mch 真实列;本组表只做「配置」与「证照」扩展,不接管核心字段存储。
 * 营业执照证照在提交时回写 plugin_mch.business_license,兼容所有读该列的下游代码。
 *
 * 注:不建 DB 唯一索引 —— BaseActiveRecord 默认软删(is_delete),唯一索引会与软删行冲突,
 * 唯一性统一在应用层 upsert 处理。
 */
class m260625_100000_create_mch_apply_config extends Migration
{
    public function safeUp()
    {
        // 1) 入驻字段配置(覆盖注册表默认值)
        if ($this->db->schema->getTableSchema('{{%plugin_mch_apply_field}}', true) === null) {
            $this->createTable('{{%plugin_mch_apply_field}}', [
                'id'          => $this->primaryKey(),
                'mall_id'     => $this->integer()->notNull(),
                'type'        => $this->tinyInteger()->notNull()->comment('主体类型: 1=线上, 2=门店, 4=个人'),
                'field_key'   => $this->string(64)->notNull()->defaultValue('')->comment('系统字段标识(对应注册表)'),
                'is_enabled'  => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否启用: 1=是, 0=否'),
                'is_required' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否必填: 1=是, 0=否'),
                'label'       => $this->string(64)->null()->comment('自定义标签(空=用注册表默认)'),
                'placeholder' => $this->string(255)->null()->comment('自定义占位提示(空=用注册表默认)'),
                'sort'        => $this->integer()->notNull()->defaultValue(0)->comment('排序(小在前)'),
            ]);
            $this->createIndex('idx_mch_apply_field_mall_type', '{{%plugin_mch_apply_field}}', ['mall_id', 'type'], false);
        }

        // 2) 证照库定义
        if ($this->db->schema->getTableSchema('{{%plugin_mch_cert_def}}', true) === null) {
            $this->createTable('{{%plugin_mch_cert_def}}', [
                'id'            => $this->primaryKey(),
                'mall_id'       => $this->integer()->notNull(),
                'name'          => $this->string(64)->notNull()->defaultValue('')->comment('证照名称'),
                'preset_key'    => $this->string(32)->null()->comment('预置标识(business_license);自定义证照为空'),
                'types'         => $this->string(64)->notNull()->defaultValue('')->comment('适用主体类型,逗号分隔如 1,2'),
                'is_required'   => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否必填: 1=是, 0=否'),
                'need_validity' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否需要有效期: 1=是, 0=否'),
                'is_system'     => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否预置(不可删): 1=是, 0=否'),
                'is_enabled'    => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否启用: 1=是, 0=否'),
                'sort'          => $this->integer()->notNull()->defaultValue(0)->comment('排序(小在前)'),
            ]);
        }

        // 3) 商家提交的证照值
        if ($this->db->schema->getTableSchema('{{%plugin_mch_certificate}}', true) === null) {
            $this->createTable('{{%plugin_mch_certificate}}', [
                'id'            => $this->primaryKey(),
                'mall_id'       => $this->integer()->notNull(),
                'mch_id'        => $this->integer()->notNull()->comment('商家ID plugin_mch.id'),
                'cert_def_id'   => $this->integer()->notNull()->defaultValue(0)->comment('关联证照定义ID'),
                'preset_key'    => $this->string(32)->null()->comment('冗余预置标识,便于回写兼容列'),
                'name_snapshot' => $this->string(64)->notNull()->defaultValue('')->comment('提交时证照名快照'),
                'image'         => $this->string(255)->notNull()->defaultValue('')->comment('证照图片'),
                'valid_start'   => $this->date()->null()->comment('有效期起'),
                'valid_end'     => $this->date()->null()->comment('有效期止'),
                'is_long_term'  => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否长期有效: 1=是, 0=否'),
            ]);
            $this->createIndex('idx_mch_cert_mch_id', '{{%plugin_mch_certificate}}', ['mch_id'], false);
            $this->createIndex('idx_mch_cert_valid_end', '{{%plugin_mch_certificate}}', ['valid_end'], false);
        }
    }

    public function safeDown()
    {
        foreach (['{{%plugin_mch_certificate}}', '{{%plugin_mch_cert_def}}', '{{%plugin_mch_apply_field}}'] as $t) {
            if ($this->db->schema->getTableSchema($t, true) !== null) {
                $this->dropTable($t);
            }
        }
    }
}
