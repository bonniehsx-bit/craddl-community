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
 * 小程序代码上传记录表 {{%mpwx_upload_log}}。
 *
 * 后台「上传代码」每次上传(成功/失败)落一条:版本号、备注、状态、操作人、时间。
 * 仅记录用途，不参与业务逻辑。created_at 等通用字段由 app\core\Migration 自动追加。
 */
class m260626_100000_create_mpwx_upload_log extends Migration
{
    public function safeUp()
    {
        if (\Yii::$app->db->schema->getTableSchema('{{%mpwx_upload_log}}') !== null) {
            return;
        }
        $this->createTable('{{%mpwx_upload_log}}', [
            'id'       => $this->primaryKey(),
            'mall_id'  => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'appid'    => $this->string(64)->notNull()->defaultValue('')->comment('小程序appid'),
            'version'  => $this->string(64)->notNull()->defaultValue('')->comment('上传版本号'),
            'remark'   => $this->string(255)->notNull()->defaultValue('')->comment('上传备注/描述'),
            'status'   => $this->tinyInteger(1)->notNull()->defaultValue(1)->comment('状态:1=成功 0=失败'),
            'msg'      => $this->text()->comment('结果信息/失败原因'),
            'admin_id' => $this->integer()->notNull()->defaultValue(0)->comment('操作管理员ID'),
        ], null, '小程序代码上传记录');

        $this->createIndex('idx_mpwx_log_mall_appid', '{{%mpwx_upload_log}}', ['mall_id', 'appid']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%mpwx_upload_log}}');
    }
}
