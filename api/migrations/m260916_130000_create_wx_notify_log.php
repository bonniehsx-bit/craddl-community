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
 * 公众号模板消息发送记录（平台核心，所有插件共用）
 *
 * 为什么要这张表：模板消息这条链路原来**只写 Yii::warning 日志文件** ——
 * 没绑公众号、场景没配模板、微信返回错误，全都是静默降级(设计如此,不能让通知失败打断业务),
 * 于是运营侧完全不可观测：发没发出去、为什么没发出去，只能上服务器 grep 日志。
 * （同样的问题在 plaza 支付回调那边已经用 bc_notify_log 解过一次，这里把它做成核心能力。）
 *
 * 写入点只有一处：`app\listeners\WxNotifyListener`（全仓库唯一调微信模板接口的地方）。
 * 写日志失败绝不能影响发送，监听器里整段 try/catch 兜住。
 */
class m260916_130000_create_wx_notify_log extends Migration
{
    const T = '{{%wx_notify_log}}';

    public function safeUp()
    {
        if ($this->db->schema->getTableSchema(self::T) !== null) {
            echo "    > 表 wx_notify_log 已存在，跳过建表。\n";
            return;
        }

        $this->createTable(self::T, [
            'id'          => $this->primaryKey(),
            'mall_id'     => $this->integer()->notNull()->defaultValue(0)->comment('商城 ID'),
            'plugin'      => $this->string(50)->notNull()->defaultValue('')->comment('来源插件，取 scene 的第一段，如 plaza'),
            'scene'       => $this->string(100)->notNull()->defaultValue('')->comment('场景键，如 plaza.audit.todo'),
            'user_id'     => $this->integer()->notNull()->defaultValue(0)->comment('接收人 user_id'),
            'openid'      => $this->string(128)->notNull()->defaultValue('')->comment('接收人公众号 openid，未绑定时为空'),
            'template_id' => $this->string(100)->notNull()->defaultValue('')->comment('实际使用的模板 ID，未配置时为空'),
            'status'      => $this->tinyInteger(1)->notNull()->defaultValue(0)->comment('1=成功 2=失败 3=跳过(没绑/没配模板)'),
            'reason'      => $this->string(255)->notNull()->defaultValue('')->comment('失败或跳过的原因(含微信 errcode/errmsg)'),
            'page_url'    => $this->string(255)->notNull()->defaultValue('')->comment('模板消息里的小程序跳转页'),
            'payload'     => $this->text()->null()->comment('发给微信的模板数据(已按 field_map 翻译)，排查用'),
        ]);

        // 后台按商城倒序翻最近的记录 / 按场景和接收人排查，都走这两个索引
        $this->createIndex('idx_wx_log_mall_time',  self::T, ['mall_id', 'created_at']);
        $this->createIndex('idx_wx_log_mall_scene', self::T, ['mall_id', 'scene']);
        $this->createIndex('idx_wx_log_mall_user',  self::T, ['mall_id', 'user_id']);
    }

    public function safeDown()
    {
        $this->dropTable(self::T);
    }
}
