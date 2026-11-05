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
 * Handles the creation of tables from existing database.
 */
class m260101_000000_exclude_jxmall_plugin_ extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 创建表: jxmall_access_token
        $this->createTable('{{%access_token}}', [
            'id' => $this->primaryKey(),
            'model_id' => $this->integer()->null()->defaultValue(0)->comment('模型ID'),
            'model' => $this->string(512)->null()->defaultValue('')->comment('模型'),
            'token' => $this->string(128)->notNull()->comment('token'),
            'expire_at' => $this->integer()->null()->defaultValue(0)->comment('过期时间'),
            'wx_openid' => $this->string(255)->null()->defaultValue('')->comment('微信openid'),
            'ip' => $this->string(64)->null()->comment('ip'),
            'platform' => $this->string(64)->null()->comment('平台')
        ]);

        $this->createIndex('idx-expire_at', '{{%access_token}}', ['expire_at'], false);
        $this->createIndex('idx-ip', '{{%access_token}}', ['ip'], false);
        $this->createIndex('idx-platform', '{{%access_token}}', ['platform'], false);
        $this->createIndex('idx-token', '{{%access_token}}', ['token'], true);
        $this->createIndex('idx-wx_openid', '{{%access_token}}', ['wx_openid'], false);
        $this->createIndex('token', '{{%access_token}}', ['token'], true);

        // 创建表: jxmall_address
        $this->createTable('{{%address}}', [
            'id' => $this->primaryKey(),
            'province_code' => $this->string(15)->null()->defaultValue('')->comment('省代码'),
            'province_name' => $this->string(45)->null()->defaultValue('')->comment('省'),
            'city_code' => $this->string(15)->null()->defaultValue('')->comment('市代码'),
            'city_name' => $this->string(45)->null()->defaultValue('')->comment('市'),
            'county_code' => $this->string(15)->null()->defaultValue('')->comment('区代码'),
            'county_name' => $this->string(45)->null()->defaultValue('')->comment('区'),
            'town_code' => $this->string(15)->null()->defaultValue('')->comment('镇代码'),
            'town_name' => $this->string(45)->null()->defaultValue('')->comment('镇')
        ]);


        // 创建表: jxmall_admin
        $this->createTable('{{%admin}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'name' => $this->string(45)->notNull()->comment('名称'),
            'mall_count' => $this->smallInteger()->notNull()->defaultValue(0)->comment('创建商城数量'),
            'is_expire' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('账号是否过期'),
            'end_at' => $this->integer()->null()->defaultValue(0)->comment('截止于'),
            'is_has_expire' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否会存在过期'),
            'admin_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0总管理员  1 分账户 2员工'),
            'password' => $this->string(255)->null()->defaultValue('')->comment('密码'),
            'username' => $this->string(45)->notNull()->comment('用户名'),
            'access_token' => $this->string(254)->null()->defaultValue('')->comment('token'),
            'login_ip' => $this->string(45)->null()->defaultValue('')->comment('登陆地IP'),
            'auth_key' => $this->string(255)->null()->defaultValue('')->comment('auth_key'),
            'mobile' => $this->string(15)->null()->defaultValue('')->comment('手机号'),
            'created_by' => $this->integer()->notNull()->defaultValue(0)->comment('创建人'),
            'we7_uid' => $this->integer()->null()->defaultValue(0)->comment('已废弃'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'remark' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'role_id' => $this->integer()->null()->defaultValue(0)->comment('角色ID'),
            'avatar' => $this->string(255)->null()->defaultValue('')->comment('头像'),
            'email' => $this->string(64)->null()->defaultValue('')->comment('email'),
            'nickname' => $this->string(45)->null()->defaultValue('')->comment('昵称')
        ]);

        $this->createIndex('idx-admin-mall_id', '{{%admin}}', ['mall_id'], false);
        $this->createIndex('username', '{{%admin}}', ['username'], true);

        // 创建表: jxmall_admin_action_log
        $this->createTable('{{%admin_action_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'admin_id' => $this->integer()->null()->defaultValue(0),
            'model' => $this->string(128)->null()->defaultValue(''),
            'model_id' => $this->integer()->notNull()->defaultValue(0),
            'before_update' => $this->text()->null(),
            'after_update' => $this->text()->null(),
            'remarks' => $this->string(255)->null()->defaultValue(''),
            'log_at' => $this->dateTime()->null()->comment('更新时间')
        ]);


        // 创建表: jxmall_admin_mall
        $this->createTable('{{%admin_mall}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'admin_id' => $this->integer()->notNull()->comment('管理员ID'),
            'role' => $this->string(10)->notNull()->comment('founder     manager   operator')
        ]);


        // 创建表: jxmall_admin_role
        $this->createTable('{{%admin_role}}', [
            'id' => $this->primaryKey(),
            'admin_id' => $this->integer()->null()->comment('admin_id'),
            'role_id' => $this->string(255)->null()->defaultValue('')->comment('角色id')
        ]);


        // 创建表: jxmall_api
        $this->createTable('{{%api}}', [
            'id' => $this->primaryKey(),
            'path' => $this->string(255)->null()->defaultValue('')->comment('接口路径'),
            'api_group' => $this->string(255)->null()->defaultValue('')->comment('分组'),
            'method' => $this->string(10)->null()->defaultValue('')->comment('请求方法'),
            'description' => $this->string(255)->null()->defaultValue('')->comment('简介'),
            'uniqueid' => $this->string(255)->null()->defaultValue('')->comment('唯一ID')
        ]);


        // 创建表: jxmall_attr
        $this->createTable('{{%attr}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'attr_name' => $this->string(64)->null()->defaultValue('')->comment('规格名称'),
            'stock' => $this->integer()->null()->defaultValue(0)->comment('库存'),
            'price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('售价'),
            'cost_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('规格成本价'),
            'cover_pic' => $this->string(512)->null()->defaultValue('')->comment('图标'),
            'goods_id' => $this->integer()->null()->defaultValue(0)->comment('商品ID'),
            'platform_attr_id' => $this->integer()->notNull()->defaultValue(0)->comment('来源平台规格ID (用于商家采购)'),
            'spec_data' => $this->json()->null()->comment('规格组合详情 (JSON格式)'),
            'no' => $this->string(255)->null()->defaultValue('')->comment('SKU商品编码'),
            'weight' => $this->decimal(10, 2)->unsigned()->null()->defaultValue('0.00')->comment('SKU重量(kg)')
        ]);

        $this->createIndex('idx_platform_attr_id', '{{%attr}}', ['platform_attr_id'], false);

        // 创建表: jxmall_balance_log
        $this->createTable('{{%balance_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 支出 1 收入'),
            'content' => $this->string(255)->null()->defaultValue('')->comment('备注内容'),
            'money' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('使用金额'),
            'current' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('当前金额'),
            'from_user_id' => $this->integer()->null()->defaultValue(0)->comment('来自哪个用户'),
            'to_user_id' => $this->integer()->null()->defaultValue(0)->comment('给哪个用户'),
            'title' => $this->string(45)->null()->defaultValue('')->comment('标题'),
            'month_at' => $this->string(10)->null()->comment('月份'),
            'date_at' => $this->date()->null()->comment('日期')
        ]);


        // 创建表: jxmall_bank
        $this->createTable('{{%bank}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'name' => $this->string(45)->notNull()->comment('用户姓名'),
            'account' => $this->string(45)->notNull()->comment('用户账户'),
            'bank_name' => $this->string(45)->notNull()->defaultValue('')->comment('银行名称'),
            'bank_branch_name' => $this->string(45)->null()->defaultValue('')->comment('支行名称'),
            'bank_code' => $this->string(45)->notNull()->defaultValue('')->comment('银行编号'),
            'id_card' => $this->string(64)->notNull()->comment('身份证号码'),
            'mobile' => $this->string(11)->notNull()->comment('手机号'),
            'id_card_pic1' => $this->string(512)->null()->comment('身份证正面'),
            'id_card_pic2' => $this->string(512)->null()->comment('身份证背面'),
            'bank_card_pic1' => $this->string(512)->null()->comment('银行卡正面'),
            'bank_card_pic2' => $this->string(512)->null()->comment('银行卡背面'),
            'is_validity' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否长期有效'),
            'begin_date' => $this->string(30)->null()->comment('开始日期'),
            'end_date' => $this->string(30)->null()->comment('结束日期'),
            'province_code' => $this->integer()->null()->comment('省编码'),
            'city_code' => $this->integer()->null()->comment('市编码'),
            'area_code' => $this->integer()->null()->comment('区编码'),
            'province_name' => $this->string(45)->null()->comment('省'),
            'city_name' => $this->string(45)->null()->comment('市'),
            'area_name' => $this->string(45)->null()->comment('区')
        ]);

        $this->createIndex('user_id', '{{%bank}}', ['user_id'], false);

        // 创建表: jxmall_branch_bank
        $this->createTable('{{%branch_bank}}', [
            'id' => $this->primaryKey(),
            'branch_name' => $this->string(255)->notNull()->defaultValue('')->comment('开户支行名称'),
            'branch_code' => $this->string(50)->notNull()->defaultValue('')->comment('开户支行联行号'),
            'province_code' => $this->string(20)->null()->defaultValue('')->comment('省编码'),
            'city_code' => $this->string(20)->null()->defaultValue('')->comment('市编码')
        ]);

        $this->createIndex('idx-branch_bank-branch_code', '{{%branch_bank}}', ['branch_code'], false);

        // 创建表: jxmall_cart
        $this->createTable('{{%cart}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID'),
            'goods_attr_id' => $this->integer()->notNull()->defaultValue(0)->comment('规格ID'),
            'num' => $this->integer()->notNull()->defaultValue(0)
        ]);


        // 创建表: jxmall_cash
        $this->createTable('{{%cash}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'cash_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('提现金额'),
            'service_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('服务费'),
            'tax_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('税费'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('实际打款金额'),
            'fund_class' => $this->string(255)->null()->comment('实际执行打款的渠道类名'),
            'remark' => $this->string(255)->null()->comment('后台审核/处理备注'),
            'status' => $this->smallInteger()->notNull()->defaultValue(0)->comment('状态 0:待审核, 1:待打款, 2:打款中, 3:已打款, 4:已驳回, 5:打款失败'),
            'cash_type' => $this->string(10)->null()->defaultValue('')->comment('提现方式  wechat balance bank'),
            'type' => $this->string(45)->null()->defaultValue('price')->comment('提现类型：price|balance|integral'),
            'bank_name' => $this->string(45)->null()->defaultValue('')->comment('开户行'),
            'account' => $this->string(45)->null()->defaultValue('')->comment('账户'),
            'receipt' => $this->string(255)->null()->defaultValue('')->comment('打款凭证'),
            'name' => $this->string(45)->null()->defaultValue('')->comment('开户人姓名'),
            'bank_code' => $this->string(20)->null()->defaultValue('')->comment('银行编号'),
            'order_no' => $this->string(45)->null()->comment('提现单号'),
            'extra_info' => $this->text()->null()->comment('额外业务信息 (JSON)'),
            'fund_error' => $this->string(2048)->null()->comment('打款失败原因'),
            'fund' => $this->string(256)->null()->comment('打款工具'),
            'fund_success_at' => $this->timestamp()->null()->comment('打款成功时间'),
            'openid' => $this->string(128)->null()->comment('openid'),
            'platform' => $this->string(20)->null()->comment('来自平台'),
            'wx_transfer_state' => $this->string(255)->null()->comment('微信打款状态'),
            'wx_transfer_info' => $this->string(2048)->null()->comment('微信打款信息'),
            'bank_mobile' => $this->string(20)->null()->comment('银行卡预留手机号'),
            'is_fund' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否到账'),
            'payout_at' => $this->integer()->null()->comment('打款时间'),
            'auto_balance_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('银行卡提现时自动分润到余额的金额'),
            'payout_info' => $this->text()->null()->comment('打款目标详情(JSON)')
        ]);

        $this->createIndex('order_no', '{{%cash}}', ['order_no'], true);
        $this->createIndex('user_id', '{{%cash}}', ['user_id'], false);

        // 创建表: jxmall_cat
        $this->createTable('{{%cat}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(45)->notNull()->comment('名称'),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'cover_pic' => $this->string(255)->null()->defaultValue('')->comment('缩略图'),
            'adv_pic' => $this->string(255)->null()->defaultValue('')->comment('广告图'),
            'is_show' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否展示'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 顶级分类 1 次级分类'),
            'link' => $this->string(128)->null()->defaultValue('')->comment('链接'),
            'sort' => $this->integer()->notNull()->defaultValue(0)->comment('排序'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'parent_id' => $this->integer()->null()->defaultValue(0)->comment('上级分类'),
            'platform_cat_id' => $this->integer()->notNull()->defaultValue(0)->comment('来源平台分类ID (用于商家采购)'),
        ]);

        $this->createIndex('idx_platform_cat_id', '{{%cat}}', ['platform_cat_id'], false);

        // 创建表: jxmall_charge_log
        $this->createTable('{{%charge_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'admin_id' => $this->integer()->notNull()->comment('管理员ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0余额 1积分'),
            'num' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('数量'),
            'before_num' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('充值之前'),
            'after_num' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('充值之后'),
            'remarks' => $this->string(255)->null()->defaultValue('')->comment('备注')
        ]);


        // 创建表: jxmall_commission_log
        $this->createTable('{{%commission_log}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('获得奖励的用户ID (推荐人)'),
            'mall_id' => $this->integer()->unsigned()->notNull(),
            'order_id' => $this->integer()->unsigned()->notNull()->comment('来源订单ID'),
            'order_detail_id' => $this->integer()->unsigned()->notNull()->comment('来源订单详情ID'),
            'buy_user_id' => $this->integer()->unsigned()->notNull()->comment('购买者ID'),
            'commission_amount' => $this->decimal(10, 2)->notNull()->comment('奖励金额'),
            'type' => $this->string(50)->notNull()->defaultValue('distribution')->comment('奖励类型: distribution=分销, recruitment=招商分红'),
            'level' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('分销层级'),
            'extra_data' => $this->text()->null()->comment('附加数据(JSON格式)'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0=待结算, 1=可结算(已入账), 2=无效'),
            'settled_at' => $this->integer()->unsigned()->null()->comment('结算入账时间')
        ]);

        $this->createIndex('idx-commission_log-level', '{{%commission_log}}', ['level'], false);
        $this->createIndex('idx-commission_log-status', '{{%commission_log}}', ['status'], false);
        $this->createIndex('idx-commission_log-type', '{{%commission_log}}', ['type'], false);

        // 创建表: jxmall_common_pay_order
        $this->createTable('{{%common_pay_order}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'order_no' => $this->string(64)->notNull()->comment('订单号'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'order_class' => $this->string(255)->null()->defaultValue('')->comment('调用的类'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('支付金额'),
            'is_pay' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否支付'),
            'pay_at' => $this->integer()->null()->defaultValue(0)->comment('支付时间'),
            'pay_openid' => $this->string(128)->null()->defaultValue('')->comment('支付时候的openid'),
            'platform' => $this->string(45)->null()->defaultValue('')->comment('平台'),
            'remarks' => $this->string(128)->null()->defaultValue('')->comment('备注'),
            'transaction_id' => $this->string(64)->null()->defaultValue('')->comment('交易ID'),
            'is_refund' => $this->tinyInteger()->null()->defaultValue(0)->comment('0 未退款  1已退款 2退款失败'),
            'refund_at' => $this->integer(11)->null()->comment('退款时间'),
            'refund_remarks' => $this->string(512)->null()->defaultValue('')->comment('退款备注'),
            'key' => $this->string(64)->null()->defaultValue('')->comment('多个订单唯一key'),
            'total_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('总额'),
            'mch_order_no' => $this->string(64)->null()->defaultValue('')->comment('汇聚支付商户的订单号'),
            'org_mch_req_time' => $this->string(64)->null()->defaultValue('')->comment('汇聚的下单时间'),
            'err_msg' => $this->string(512)->null()->defaultValue('')->comment('支付错误信息'),
            'refund_order_no' => $this->string(64)->null()->defaultValue('')->comment('退款单号'),
            'payment' => $this->string(512)->null()->comment('支付方式'),
            'is_need_refund' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否需要退款'),
            'refund_order_detail_id' => $this->integer()->null()->defaultValue(0)->comment('退款详情ID'),
            'refund_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('退款金额'),
            'is_push_wechat' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否推送到微信端'),
            'push_errors' => $this->string(5120)->null()->comment('推送错误信息'),
            'is_refund_fail' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否退款失败'),
            'payment_config' => $this->json()->null()->comment('支付配置'),
            'push_wechat_params' => $this->json()->null()->comment('发货推送到微信端的参数')
        ]);

        $this->createIndex('idx_key', '{{%common_pay_order}}', ['key'], false);
        $this->createIndex('idx_push_task', '{{%common_pay_order}}', ['is_push_wechat', 'is_pay', 'platform', 'order_no'], false);
        $this->createIndex('idx_transaction_id', '{{%common_pay_order}}', ['transaction_id'], false);
        $this->createIndex('idx_user_id', '{{%common_pay_order}}', ['user_id'], false);
        $this->createIndex('order_no', '{{%common_pay_order}}', ['order_no'], true);

        // 创建表: jxmall_coupon
        $this->createTable('{{%coupon}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'name' => $this->string(45)->notNull()->comment('优惠券名称'),
            'is_limit_total' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('总数限制'),
            'discount' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('折扣多少'),
            'price' => $this->decimal(10, 2)->null()->comment('[废弃] 请使用 min_price'),
            'time_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('时间类型 0 领取后几天  1日期范围'),
            'total' => $this->integer()->null()->comment('[废弃] 请使用 total_count'),
            'day' => $this->integer()->notNull()->comment('有效天数'),
            'start_at' => $this->integer()->null()->defaultValue(0)->comment('有效时间'),
            'end_at' => $this->integer()->null()->defaultValue(0)->comment('有效截止'),
            'is_join' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否加入领券中心'),
            'user_total' => $this->integer()->null()->comment('[废弃] 请使用 limit_per_user'),
            'is_goods_limit' => $this->tinyInteger()->null()->comment('[废弃] 请使用 appoint_type'),
            'type' => $this->integer()->notNull()->comment('0满减券  1 折扣'),
            'user_total_limit' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('用户领取限制'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'is_expire' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否过期'),
            'min_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('最低消费金额门槛'),
            'sub_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('优惠金额'),
            'total_count' => $this->integer()->notNull()->defaultValue(-1)->comment('发放总数, -1为不限制'),
            'is_enabled' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否启用: 1=是, 0=否'),
            'appoint_type' => $this->string(50)->notNull()->defaultValue('all')->comment('指定范围类型: all=全场/全店通用, goods=指定商品'),
            'appoint_relation' => $this->text()->null()->comment('范围关联ID (JSON格式的商品ID数组)'),
            'can_be_claimed' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否可被用户领取: 1=是, 0=否'),
            'limit_per_user' => $this->integer()->notNull()->defaultValue(1)->comment('每人限领张数, 0为不限制'),
            'is_transferable' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否允许转赠:0=否,1=是')
        ]);


        // 创建表: jxmall_date_balance_log
        $this->createTable('{{%date_balance_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'current' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('当前余额'),
            'date_at' => $this->string(20)->null()->defaultValue('')->comment('日期')
        ]);


        // 创建表: jxmall_date_integral_log
        $this->createTable('{{%date_integral_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'current' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('当前积分'),
            'date_at' => $this->string(20)->null()->defaultValue('')->comment('日期')
        ]);


        // 创建表: jxmall_date_price_log
        $this->createTable('{{%date_price_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'current' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('当前奖励'),
            'date_at' => $this->string(20)->null()->defaultValue('')->comment('日期')
        ]);


        // 创建表: jxmall_diy_page
        $this->createTable('{{%diy_page}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'is_home' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否主页'),
            'data' => $this->text()->notNull()->comment('页面数据'),
            'is_enable' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否启用'),
            'name' => $this->string(45)->notNull()->comment('页面名称'),
            'text_color' => $this->string(15)->null()->defaultValue('#000')->comment('标题栏文字颜色'),
            'title_color' => $this->string(15)->null()->defaultValue('#ffffff')->comment('标题栏颜色')
        ]);


        // 创建表: jxmall_eorder
        $this->createTable('{{%eorder}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'name' => $this->string(45)->notNull()->comment('配置名称'),
            'express_key' => $this->string(10)->notNull()->comment('快递公司key'),
            'account' => $this->string(45)->null()->defaultValue('')->comment('账号'),
            'password' => $this->string(45)->null()->defaultValue('')->comment('账号'),
            'month_code' => $this->string(45)->notNull()->comment('未知'),
            'shop_name' => $this->string(45)->notNull()->comment('网点'),
            'tpl_style' => $this->string(45)->notNull()->comment('模板样式'),
            'pay_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('付款方式'),
            'to_home' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('到家揽间'),
            'is_default' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否默认'),
            'express' => $this->string(255)->null()->defaultValue('')->comment('快递'),
            'shop_code' => $this->string(45)->notNull()->comment('网点编码'),
            'sender_name' => $this->string(45)->notNull()->comment('发件人名字'),
            'sender_mobile' => $this->string(45)->notNull()->comment('发件手机号'),
            'sender_province' => $this->string(45)->notNull()->comment('发件省份'),
            'sender_city' => $this->string(45)->notNull()->comment('发件人城市'),
            'sender_county' => $this->string(45)->notNull()->comment('发件人区域'),
            'sender_address' => $this->string(45)->notNull()->comment('发件人地址')
        ]);


        // 创建表: jxmall_eorder_detail
        $this->createTable('{{%eorder_detail}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'print_tpl' => $this->text()->notNull()->comment('打印模板'),
            'express_no' => $this->string(45)->notNull()->comment('快递单号'),
            'eorder_id' => $this->integer()->notNull()->defaultValue(0)->comment('电子面单ID')
        ]);


        // 创建表: jxmall_express_log
        $this->createTable('{{%express_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'express_no' => $this->string(64)->notNull()->comment('快递单号'),
            'express_name' => $this->string(45)->notNull()->comment('快递名称'),
            'express_code' => $this->string(10)->null()->defaultValue('')->comment('快递编码'),
            'name' => $this->string(45)->notNull()->comment('收件人'),
            'mobile' => $this->string(11)->notNull()->comment('联系电话'),
            'address' => $this->string(128)->notNull()->comment('地址'),
            'eorder_detail_id' => $this->integer()->notNull()->defaultValue(0)->comment('电子面单详情ID'),
            'order_id' => $this->integer()->notNull()->defaultValue(0)->comment('订单ID'),
            'order_detail_id' => $this->string(128)->null()->defaultValue('')->comment('订单详情ID'),
            'order_type' => $this->string(45)->notNull()->defaultValue('mall')->comment('订单类型 默认是商城订单'),
            'num' => $this->integer()->notNull()->defaultValue(0)->comment('商品数量'),
            'is_all' => $this->tinyInteger()->null()->defaultValue(1)->comment('0 分包 1整包'),
            'order_no' => $this->string(45)->null()->defaultValue('')->comment('订单号'),
            'eorder_id' => $this->integer()->notNull()->defaultValue(0)->comment('电子面单ID')
        ]);


        // 创建表: jxmall_express_setting
        $this->createTable('{{%express_setting}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'kdniao_appcode' => $this->string(64)->null()->defaultValue('')->comment('快递鸟APPCODE'),
            'kdniao_customer' => $this->string(64)->null()->defaultValue('')->comment('快递鸟customer')
        ]);


        // 创建表: jxmall_fd_heartbeat
        $this->createTable('{{%fd_heartbeat}}', [
            'id' => $this->primaryKey(),
            'fd' => $this->integer()->null()->defaultValue(0)->comment('fd')
        ]);


        // 创建表: jxmall_feedback
        $this->createTable('{{%feedback}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'content' => $this->string(2048)->notNull()->defaultValue('')->comment('反馈内容')
        ]);


        // 创建表: jxmall_feie_printer
        $this->createTable('{{%feie_printer}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'printer_sn' => $this->string(64)->null()->defaultValue('')->comment('设备号'),
            'account' => $this->string(64)->null()->defaultValue('')->comment('调用的账号'),
            'fe_ukey' => $this->string(64)->null()->defaultValue('')->comment('UKEY'),
            'type' => $this->string(45)->null()->defaultValue('ticket')->comment('打印机类型：ticket、label'),
            'name' => $this->string(64)->null()->defaultValue('')->comment('名称')
        ]);


        // 创建表: jxmall_file
        $this->createTable('{{%file}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'url' => $this->string(512)->null()->defaultValue('')->comment('链接'),
            'thumb_url' => $this->string(512)->null()->defaultValue('')->comment('缩略图链接'),
            'type' => $this->string(15)->notNull()->defaultValue('image')->comment('文件类型'),
            'group_id' => $this->integer()->notNull()->defaultValue(0)->comment('组ID'),
            'name' => $this->string(256)->null()->defaultValue('')->comment('文件名'),
            'user_id' => $this->integer()->notNull()->defaultValue(0)->comment('用户上传的'),
            'admin_id' => $this->integer()->notNull()->defaultValue(0)->comment('管理员上传的'),
            'is_site' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否总平台'),
            'path' => $this->string(256)->null()->defaultValue('')->comment('文件路径'),
            'location' => $this->string(10)->notNull()->defaultValue('local')->comment('存储位置')
        ]);


        // 创建表: jxmall_file_group
        $this->createTable('{{%file_group}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'name' => $this->string(45)->null()->defaultValue('')->comment('组名称'),
            'admin_id' => $this->integer()->notNull()->defaultValue(0)->comment('管理员ID'),
            'is_site' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否总平台')
        ]);


        // 创建表: jxmall_freight_price
        $this->createTable('{{%freight_price}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'tpl_id' => $this->integer()->notNull()->comment('模板ID'),
            'first_num' => $this->integer()->notNull()->defaultValue(0)->comment('数量可能是件数  可能是重量'),
            'first_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('首件费用'),
            'other_num' => $this->integer()->notNull()->defaultValue(0)->comment('续件数量可能是件数  可能是重量'),
            'other_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('续件费用'),
            'send_area' => $this->text()->null()->comment('配送区域'),
            'is_union' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('统一'),
            'send_codes' => $this->text()->null()->comment('区域编码')
        ]);


        // 创建表: jxmall_freight_tpl
        $this->createTable('{{%freight_tpl}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID, 0表示平台自营'),
            'enabled' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否启用'),
            'name' => $this->string(45)->notNull()->comment('名称'),
            'is_default' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('默认模板'),
            'price_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 按件计费   1 按重量计费')
        ]);

        $this->createIndex('idx-freight_tpl-mch_id', '{{%freight_tpl}}', ['mch_id'], false);

        // 创建表: jxmall_goods
        $this->createTable('{{%goods}}', [
            'id' => $this->primaryKey(),
            'type' => $this->string(50)->notNull()->defaultValue('normal')->comment('商品类型: normal=普通商品, mch_checkout=收银商品'),
            'name' => $this->string(255)->notNull()->comment('商品名'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('商品价格'),
            'origin_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('商品原价'),
            'no' => $this->string(255)->null()->defaultValue('')->comment('商品编号'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('状态  1上架 0下架'),
            'cover_pic' => $this->string(255)->notNull()->comment('缩略图'),
            'unit' => $this->string(10)->notNull()->defaultValue('件')->comment('单位'),
            'is_limit' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否开启会员限购'),
            'weight' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('重量'),
            'cost_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('原价'),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'is_attr' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否使用多规格'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'stock' => $this->integer()->null()->defaultValue(0)->comment('库存'),
            'is_area_limit' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('开启区域限购'),
            'sales' => $this->integer()->notNull()->defaultValue(0)->comment('销量'),
            'virtual_sales' => $this->integer()->null()->defaultValue(0)->comment('虚拟销量'),
            'share_title' => $this->string(45)->null()->defaultValue('')->comment('分享标题'),
            'sort' => $this->integer()->notNull()->defaultValue(0),
            'is_recommend' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否推荐'),
            'express_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 包邮  1 统一运费  2 运费模板'),
            'freight_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('运费'),
            'freight_id' => $this->integer()->notNull()->defaultValue(0)->comment('运费模板ID'),
            'is_integral' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否开启积分抵扣'),
            'use_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('使用积分'),
            'integral_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('抵扣金额'),
            'use_integral_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 固定积分   1 按比例'),
            'is_negotiable' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否开启面议'),
            'subtitle' => $this->string(255)->null()->defaultValue('')->comment('商品小标题'),
            'is_member_price' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否启用会员价'),
            'limit_num' => $this->integer()->null()->defaultValue(0)->comment('限购数量'),
            'total_stock' => $this->integer()->null()->defaultValue(0)->comment('累计库存'),
            'warning_stock' => $this->integer()->null()->defaultValue(0)->comment('0 预警库存'),
            'is_special_price' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否特价'),
            'cat_id' => $this->integer()->null()->defaultValue(0)->comment('直接分类id'),
            'property' => $this->string(255)->null()->defaultValue('')->comment('属性'),
            'is_check' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否审核'),
            'attr_group_name' => $this->string(45)->null()->defaultValue('规格')->comment('规格组名'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('上传用户ID'),
            'is_auto_complete' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否自动完成订单'),
            'send_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('赠送积分'),
            'is_refund' => $this->tinyInteger()->null()->defaultValue(1)->comment('支持退款'),
            'platform_goods_id' => $this->integer()->notNull()->defaultValue(0)->comment('来源平台商品ID (用于商家采购)'),
            'is_purchase' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否加入采购'),
            'is_from_supply_chain' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否来自供应链: 0=否, 1=是'),
            'factory' => $this->string(255)->null()->comment('生产厂家')
        ]);

        $this->createIndex('idx_goods_no', '{{%goods}}', ['no'], false);
        $this->createIndex('idx_goods_name', '{{%goods}}', ['name'], false);
        $this->createIndex('idx_is_from_supply_chain', '{{%goods}}', ['is_from_supply_chain'], false);
        $this->createIndex('idx_is_purchase', '{{%goods}}', ['is_purchase'], false);
        $this->createIndex('idx_platform_goods_id', '{{%goods}}', ['platform_goods_id'], false);
        $this->createIndex('idx_type', '{{%goods}}', ['type'], false);

        // 创建表: jxmall_goods_cat
        $this->createTable('{{%goods_cat}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'goods_id' => $this->integer()->notNull()->defaultValue(0)->comment('商品ID'),
            'cat_id' => $this->integer()->notNull()->defaultValue(0)->comment('分类ID'),
            'level' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('分类层级')
        ]);


        // 创建表: jxmall_goods_comment
        $this->createTable('{{%goods_comment}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'order_id' => $this->integer()->notNull()->comment('订单ID'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID'),
            'content' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'pic_list' => $this->string(2048)->null()->defaultValue('')->comment('图片列表'),
            'grade_level' => $this->tinyInteger()->notNull()->defaultValue(5)->comment('评分'),
            'is_second' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否二次评价')
        ]);


        // 创建表: jxmall_goods_coupon
        $this->createTable('{{%goods_coupon}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID'),
            'coupon_id' => $this->integer()->notNull()->comment('优惠券ID')
        ]);


        // 创建表: jxmall_goods_favorite
        $this->createTable('{{%goods_favorite}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID')
        ]);


        // 创建表: jxmall_goods_footmark
        $this->createTable('{{%goods_footmark}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID')
        ]);


        // 创建表: jxmall_goods_info
        $this->createTable('{{%goods_info}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'goods_id' => $this->integer()->notNull()->comment('对应的商品ID'),
            'attr_list' => $this->text()->null()->comment('规格'),
            'detail' => $this->text()->null()->comment('商品详情'),
            'limit_area_list' => $this->text()->null()->comment('限制购买的区域'),
            'pic_list' => $this->string(5120)->null()->defaultValue('')->comment('商品图片'),
            'remarks' => $this->string(126)->null()->defaultValue('')->comment('备注'),
            'permission' => $this->string(5120)->null()->defaultValue('')->comment('权限'),
            'level_price' => $this->string(5120)->null()->defaultValue('')->comment('会员价设置'),
            'content' => $this->string(512)->null()->defaultValue('')->comment('文字内容'),
            'detail_pic_list' => $this->text()->null()->comment('详情的图片组'),
            'spec_template' => $this->json()->null()->comment('规格组模板 (JSON格式)')
        ]);

        // 创建表: jxmall_goods_up_down_job
        $this->createTable('{{%goods_up_down_job}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'goods_id' => $this->integer()->null()->defaultValue(0)->comment('商品ID'),
            'action_at' => $this->integer()->notNull()->defaultValue(0)->comment('执行时间'),
            'action_time' => $this->string(45)->notNull()->defaultValue('')->comment('执行时间'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('1下架  2 上架'),
            'is_done' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否执行')
        ]);


        // 创建表: jxmall_green_integral_log
        $this->createTable('{{%green_integral_log}}', [
            'id' => $this->bigPrimaryKey(),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('用户ID'),
            'mall_id' => $this->integer()->unsigned()->notNull()->comment('商城ID'),
            'type' => $this->tinyInteger()->notNull()->comment('1=收入, 2=支出'),
            'integral' => $this->decimal(12, 2)->notNull()->comment('变动积分(正数)'),
            'balance_before' => $this->decimal(12, 2)->notNull()->comment('变动前余额'),
            'balance_after' => $this->decimal(12, 2)->notNull()->comment('变动后余额'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('备注'),
            'source_id' => $this->integer()->unsigned()->null()->defaultValue(0)->comment('来源ID'),
            'source_type' => $this->string(255)->notNull()->defaultValue('')->comment('来源类型')
        ]);

        $this->createIndex('idx-green_integral_log-source', '{{%green_integral_log}}', ['source_id', 'source_type'], false);

        // 创建表: jxmall_hot_image
        $this->createTable('{{%hot_image}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'name' => $this->string(45)->null()->defaultValue('')->comment('热图名称'),
            'pic_url' => $this->string(255)->null()->defaultValue('')->comment('图片链接'),
            'data' => $this->string(10240)->notNull()->defaultValue('')->comment('数据')
        ]);


        // 创建表: jxmall_huifu_order
        $this->createTable('{{%huifu_order}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'req_seq_id' => $this->string(128)->null()->comment('请求流水号'),
            'req_date' => $this->string(64)->null()->comment('请求日期'),
            'huifu_id' => $this->string(64)->null()->comment('汇付ID'),
            'ord_amt' => $this->decimal(10, 2)->null()->comment('退款金额'),
            'trans_amt' => $this->decimal(10, 2)->null()->comment('交易金额'),
            'order_no' => $this->string(64)->null()->comment('商户订单号'),
            'trade_type' => $this->string(20)->null()->comment('交易类型'),
            'refund_order_no' => $this->string(64)->null()->comment('退款订单号'),
            'refund_req_seq_id' => $this->string(128)->null()->comment('退款请求流水号'),
            'refund_req_date' => $this->string(20)->null()->comment('退款请求日期'),
            'pay_at' => $this->timestamp()->null()->comment('支付时间'),
            'refund_at' => $this->timestamp()->null()->comment('退款时间'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('1 支付 2退款'),
            'error' => $this->text()->null()->comment('错误信息')
        ]);


        // 创建表: jxmall_integral_log
        $this->createTable('{{%integral_log}}', [
            'id' => $this->primaryKey(),
            'idempotency_key' => $this->string(128)->null()->comment('幂等键，用于防止重复操作'),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('变动积分'),
            'content' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 支出 1 收益'),
            'current' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('当前积分'),
            'title' => $this->string(45)->null()->defaultValue('')->comment('标题'),
            'month_at' => $this->string(10)->null()->comment('月份'),
            'date_at' => $this->date()->null()->comment('日期')
        ]);

        $this->createIndex('uk_idempotency_key', '{{%integral_log}}', ['idempotency_key'], true);

        // 创建表: jxmall_integral_transfer_fee_log
        $this->createTable('{{%integral_transfer_fee_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'sender_id' => $this->integer()->notNull()->comment('转出方用户ID'),
            'recipient_id' => $this->integer()->notNull()->comment('接收方用户ID'),
            'transfer_amount' => $this->decimal(10, 2)->notNull()->comment('本次转赠的金额'),
            'service_charge_rate' => $this->decimal(5, 2)->notNull()->comment('当时的手续费率(%)'),
            'fee' => $this->decimal(10, 2)->notNull()->comment('实际收取的手续费'),
            'integral_log_id' => $this->integer()->unsigned()->null()->defaultValue(0)->comment('关联的转出方积分扣除日志ID')
        ]);

        $this->createIndex('idx-integral_transfer_fee_log-sender_id', '{{%integral_transfer_fee_log}}', ['sender_id'], false);


        // 创建表: jxmall_level
        $this->createTable('{{%level}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'name' => $this->string(45)->notNull()->comment('等级名称'),
            'level' => $this->tinyInteger()->notNull()->comment('等级'),
            'pic_url' => $this->string(255)->null()->defaultValue('')->comment('图标'),
            'detail' => $this->string(256)->null()->defaultValue('')->comment('说明'),
            'enabled' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否启用'),
            'is_auto' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否自动升级'),
            'is_discount' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否有折扣'),
            'discount' => $this->decimal(10, 2)->notNull()->comment('折扣比例'),
            'buy_type' => $this->tinyInteger()->null()->defaultValue(0)->comment('0支付 1 订单完成'),
            'is_inviter' => $this->integer()->null()->defaultValue(0)->comment('是否具有推广权限'),
            'is_contract_update' => $this->tinyInteger()->null()->defaultValue(0)->comment('升级之前是否需要签订合同'),
            'contract_tpl_id' => $this->string(64)->null()->defaultValue('')->comment('合同模板ID')
        ]);


        // 创建表: jxmall_mall
        $this->createTable('{{%mall}}', [
            'id' => $this->primaryKey(),
            'admin_id' => $this->smallInteger()->notNull()->comment('创建者管理员的ID'),
            'name' => $this->string(45)->notNull()->comment('商城名称'),
            'is_has_expire' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否存在过期'),
            'end_at' => $this->integer()->null()->defaultValue(0)->comment('到期时间'),
            'is_expire' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否过期'),
            'is_stop' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否停用'),
            'logo' => $this->string(255)->null()->defaultValue('')->comment('logo'),
            'detail' => $this->string(255)->null()->defaultValue('')->comment('商城介绍'),
            'uniacid' => $this->integer()->null()->defaultValue(0)->comment('微擎uniacid，已废弃')
        ]);


        // 创建表: jxmall_mall_menu_option
        $this->createTable('{{%mall_menu_option}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'menu_id' => $this->string(64)->notNull()->comment('系统菜单ID'),
            'custom_name' => $this->string(64)->null()->comment('商户自定义名称'),
            'is_hide' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否隐藏: 0否 1是'),
            'custom_sort' => $this->integer()->null()->comment('自定义排序'),
            'custom_parent_id' => $this->string(64)->null()->comment('自定义父级ID'),
            'custom_path' => $this->string(255)->null()->comment('自定义路由地址')
        ]);

        $this->createIndex('idx-mall_menu_option-mall_menu', '{{%mall_menu_option}}', ['mall_id', 'menu_id'], false);

        // 创建表: jxmall_mall_plugin
        $this->createTable('{{%mall_plugin}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'plugin' => $this->string(45)->null()->defaultValue('')->comment('插件名')
        ]);


        // 创建表: jxmall_mch_balance_log
        $this->createTable('{{%mch_balance_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull()->comment('商家ID'),
            'order_id' => $this->integer()->notNull()->defaultValue(0)->comment('关联的主订单ID'),
            'order_detail_id' => $this->integer()->notNull()->defaultValue(0)->comment('关联的订单详情ID'),
            'type' => $this->tinyInteger()->notNull()->comment('1=收入,0 =支出(提现)'),
            'amount' => $this->decimal(12, 2)->notNull()->comment('变动金额'),
            'before_balance' => $this->decimal(12, 2)->notNull()->comment('变动前余额'),
            'after_balance' => $this->decimal(12, 2)->notNull()->comment('变动后余额'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('备注/事由')
        ]);

        $this->createIndex('idx_mch_id_balance_log', '{{%mch_balance_log}}', ['mch_id'], false);

        // 创建表: jxmall_mch_cash_log
        $this->createTable('{{%mch_cash_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull(),
            'order_no' => $this->string(64)->notNull()->comment('提现单号'),
            'amount' => $this->decimal(12, 2)->notNull()->comment('申请提现金额'),
            'service_fee' => $this->decimal(12, 2)->notNull()->comment('手续费'),
            'tax_fee' => $this->decimal(12, 2)->notNull()->comment('税费'),
            'actual_amount' => $this->decimal(12, 2)->notNull()->comment('实际到账金额'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0=待审核, 1=审核通过(待打款), 2=已打款, 3=已驳回'),
            'bank_info' => $this->text()->null()->comment('提现账户信息 (JSON)'),
            'remark' => $this->string(255)->notNull()->defaultValue('')->comment('后台审核备注'),
            'handled_at' => $this->integer()->null()->comment('后台处理时间'),
            'fund_class' => $this->string(255)->null()->comment('打款渠道')
        ]);

        $this->createIndex('idx_fund_class', '{{%mch_cash_log}}', ['fund_class'], false);
        $this->createIndex('idx_mch_id', '{{%mch_cash_log}}', ['mch_id'], false);
        $this->createIndex('idx_status', '{{%mch_cash_log}}', ['status'], false);
        $this->createIndex('order_no', '{{%mch_cash_log}}', ['order_no'], true);

        // 创建表: jxmall_mch_settlement_log
        $this->createTable('{{%mch_settlement_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->comment('收款实体(商家/服务商)ID'),
            'user_id' => $this->integer()->notNull()->comment('收款实体关联的用户ID'),
            'order_id' => $this->integer()->notNull()->comment('关联的主订单ID'),
            'order_detail_id' => $this->integer()->notNull()->defaultValue(0)->comment('关联订单详情ID(货款结算时)'),
            'order_no' => $this->string(255)->notNull()->defaultValue('')->comment('关联订单号'),
            'amount' => $this->decimal(12, 2)->notNull()->comment('结算金额'),
            'settlement_type' => $this->string(50)->notNull()->defaultValue('goods')->comment('结算类型: goods=商品货款, express=运费收入'),
            'title' => $this->string(255)->notNull()->defaultValue('')->comment('标题'),
            'remark' => $this->text()->null()->comment('备注'),
            'price_log_id' => $this->integer()->notNull()->defaultValue(0)->comment('关联的奖励日志ID')
        ]);

        $this->createIndex('idx_mch_id', '{{%mch_settlement_log}}', ['mch_id'], false);
        $this->createIndex('idx_order_id', '{{%mch_settlement_log}}', ['order_id'], false);
        $this->createIndex('idx_settlement_type', '{{%mch_settlement_log}}', ['settlement_type'], false);

        // 创建表: jxmall_menu
        $this->createTable('{{%menu}}', [
            'id' => $this->primaryKey(),
            'menu_id' => $this->string(45)->null()->defaultValue('')->comment('menu_id'),
            'menu_name' => $this->string(45)->null()->defaultValue('')->comment('role_name'),
            'icon' => $this->string(45)->null()->defaultValue('')->comment('role_name'),
            'menu_type' => $this->string(45)->null()->defaultValue('')->comment('role_key'),
            'order_num' => $this->integer()->null()->defaultValue(0)->comment('order_num'),
            'parent_id' => $this->string(45)->null()->defaultValue('')->comment('parent_id'),
            'is_cache' => $this->tinyInteger()->null()->defaultValue(0)->comment('is_cache'),
            'hidden' => $this->tinyInteger()->null()->defaultValue(0)->comment('hidden'),
            'is_frame' => $this->tinyInteger()->null()->defaultValue(0)->comment('is_frame'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('path'),
            'path' => $this->string(255)->null()->defaultValue('')->comment('状态'),
            'component' => $this->string(255)->null()->defaultValue('')->comment('component'),
            'query' => $this->string(255)->null()->defaultValue('')->comment('query'),
            'perms' => $this->string(255)->null()->defaultValue('')->comment('perms'),
            'link' => $this->string(255)->null()->defaultValue('')->comment('链接'),
            'plugin' => $this->string(45)->null()->defaultValue('mall')->comment('插件'),
            'active_menu' => $this->string(64)->null()->defaultValue('')->comment('高亮菜单'),
            'breadcrumb' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否再面包屑中显示'),
            'name' => $this->string(64)->null()->defaultValue('')->comment('组件名称'),
            'show_tag' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否显示标签')
        ]);

        $this->createIndex('idx_menu_perms', '{{%menu}}', ['perms'], false);
        $this->createIndex('menu_id', '{{%menu}}', ['menu_id'], true);
        $this->createIndex('parent_id', '{{%menu}}', ['parent_id'], false);

        // 创建表: jxmall_merchant_rule
        $this->createTable('{{%merchant_rule}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'title' => $this->string(255)->notNull()->comment('规则标题'),
            'content' => $this->text()->notNull()->comment('规则内容 (富文本)'),
            'sort' => $this->integer()->notNull()->defaultValue(100)->comment('排序，升序')
        ]);


        // 创建表: jxmall_notebook
        $this->createTable('{{%notebook}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'admin_id' => $this->integer()->null()->defaultValue(0)->comment('管理员ID'),
            'content' => $this->text()->null()->comment('内容'),
            'level' => $this->tinyInteger()->null()->defaultValue(0)->comment('0 普通 1重要 2紧急 3重要且紧急'),
            'title' => $this->string(64)->null()->defaultValue('')->comment('标题')
        ]);


        // 创建表: jxmall_noun
        $this->createTable('{{%noun}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'key' => $this->string(255)->null()->defaultValue('')->comment('key'),
            'value' => $this->string(255)->null()->defaultValue('')->comment('value')
        ]);


        // 创建表: jxmall_option
        $this->createTable('{{%option}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'name' => $this->string(45)->notNull()->comment('key'),
            'value' => $this->text()->null()->comment('值')
        ]);


        // 创建表: jxmall_order
        $this->createTable('{{%order}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('下单用户ID'),
            'total_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('总金额'),
            'express_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('运费'),
            'express_income_mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('运费收入归属的实体ID,0平台,>0商家/服务商'),
            'is_express_settled' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('运费是否已结算:0=否,1=是'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('10=待付款, 20=待发货, 30=待收货, 40=已完成, 0=已关闭'),
            'pay_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('支付状态:0=未支付,1=已支付,2=部分退款,3=全部退款'),
            'delivery_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('发货状态:0=未发货,1=部分发货,2=全部发货'),
            'receipt_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('收货状态:0=未收货,1=已收货'),
            'refund_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('售后状态:0=未售后, 10=申请售后,20=售后处理中, 30=售后完成,40=售后关闭'),
            'comment_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('评价状态:0=未评价,1=已评价'),
            'settlement_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('结算状态: 0=待结算, 1=已结算, 2=无需结算/已失效'),
            'finish_at' => $this->integer()->null()->defaultValue(0)->comment('订单完成于'),
            'settled_at' => $this->integer()->null()->comment('结算时间'),
            'send_at' => $this->integer()->null()->defaultValue(0)->comment('发货于'),
            'confirm_at' => $this->integer()->null()->defaultValue(0)->comment('确认于'),
            'apply_cancel' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否申请取消'),
            'apply_cancel_at' => $this->integer()->null()->defaultValue(0)->comment('申请取消的时间'),
            'cancel_at' => $this->integer()->null()->defaultValue(0)->comment('取消于'),
            'apply_refund_at' => $this->integer()->null()->defaultValue(0)->comment('申请售后的时间'),
            'refund_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0退款退货，1换货'),
            'refund_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('退款金额'),
            'agree_refund' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('统一售后申请'),
            'refund_operation_at' => $this->integer()->null()->defaultValue(0)->comment('操作时间'),
            'refund_at' => $this->integer()->null()->defaultValue(0)->comment('退款时间'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'type' => $this->string(45)->notNull()->defaultValue('normal')->comment('订单类型 normal-普通订单 mch_checkout-收银台订单'),
            'address' => $this->string(255)->null()->defaultValue('')->comment('收货地址'),
            'province_code' => $this->string(20)->null()->defaultValue('')->comment('省代码'),
            'city_code' => $this->string(20)->null()->defaultValue('')->comment('市代码'),
            'county_code' => $this->string(20)->null()->defaultValue('')->comment('区代码'),
            'town_code' => $this->string(20)->null()->defaultValue('')->comment('镇代码'),
            'pay_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('支付金额'),
            'order_no' => $this->string(45)->null()->defaultValue('')->comment('订单号'),
            'is_pay' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否支付'),
            'pay_at' => $this->integer()->null()->defaultValue(0)->comment('支付时间'),
            'pay_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 wechat 1 balance'),
            'name' => $this->string(45)->null()->defaultValue('')->comment('收货人'),
            'mobile' => $this->string(11)->null()->defaultValue('')->comment('收货人电话'),
            'remarks' => $this->string(255)->null()->defaultValue('')->comment('订单备注'),
            'deliver_type' => $this->string(20)->notNull()->defaultValue('express')->comment('配送方式: express, pickup, city'),
            'pickup_code' => $this->string(50)->null()->comment('自提核销码'),
            'city_delivery_info' => $this->json()->null()->comment('同城配送信息(JSON)'),
            'send_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 整单发货  1 分包发货'),
            'express_log_id' => $this->integer()->notNull()->defaultValue(0)->comment('快递记录'),
            'coupon_cut_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('优惠券抵扣金额'),
            'use_coupon_goods_id' => $this->integer()->notNull()->defaultValue(0)->comment('使用优惠券抵扣的商品'),
            'user_coupon_id' => $this->integer()->notNull()->defaultValue(0)->comment('用户优惠券ID'),
            'is_use_integral' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否使用积分'),
            'use_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('使用积分'),
            'integral_cut_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('积分抵扣金额'),
            'apply_send_time' => $this->timestamp()->null()->comment('申请时间'),
            'is_apply_send' => $this->integer()->null()->defaultValue(0)->comment('申请发货'),
            'comment_times' => $this->tinyInteger()->null()->defaultValue(0)->comment('评论次数'),
            'push_wechat_status' => $this->integer()->null()->defaultValue(-1)->comment('推送给微信的状态'),
            'is_auto_complete' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否是自动完成订单'),
            'is_can_refund' => $this->tinyInteger()->null()->defaultValue(1)->comment('支持退款'),
            'platform_subsidy_random_minus' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('平台随机减免补贴金额'),
            'goods_total_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('商品总额(优惠前,不含运费)'),
            'payment_class' => $this->string(255)->null()->comment('实际支付方式的类名'),
            'payment_name' => $this->string(100)->null()->comment('实际支付方式的名称(用于后台展示)'),
            'is_immediately' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否立即配送: 1=是, 0=否(预约)'),
            'delivery_time' => $this->string(100)->null()->defaultValue('')->comment('预约配送时间段'),
            'rider_id' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('配送骑手的用户ID'),
            'delivery_task_id' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('关联的配送任务ID (plugin_mch_delivery_task.id)'),
            'rider_confirmed_at' => $this->timestamp()->null()->comment('骑手确认送达时间'),
            'first_order_discount_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('首单优惠金额'),
            'clerk_id' => $this->integer()->notNull()->defaultValue(0)->comment('核销员用户ID'),
            'clerk_name' => $this->string(255)->null()->defaultValue('')->comment('核销员昵称快照'),
            'pickup_discount_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('自提优惠金额'),
            'expected_arrival_time' => $this->string(45)->null()->comment('预计送达时间(商家自配送)'),
            'distance' => $this->decimal(10, 2)->null()->comment('距离'),
            'location' => $this->string(255)->null()->comment('地理位置'),
            'latitude' => $this->decimal(10, 6)->null()->comment('纬度'),
            'longitude' => $this->decimal(10, 6)->null()->comment('经度'),
            'platform_income' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('平台收入'),
            'mch_income' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('商家收入'),
            'seller_remarks' => $this->string(255)->null()->defaultValue('')->comment('商家备注'),
            'plugin' => $this->string(64)->null()->defaultValue('mall')->comment('所属插件标识')
        ]);

        $this->createIndex('idx_mch_status', '{{%order}}', ['mch_id', 'status'], false);
        $this->createIndex('idx_order_no', '{{%order}}', ['order_no'], false);
        $this->createIndex('idx_order_plugin', '{{%order}}', ['plugin'], false);
        $this->createIndex('idx_order_rider_id', '{{%order}}', ['rider_id'], false);
        $this->createIndex('idx-order-deliver_type', '{{%order}}', ['deliver_type'], false);
        $this->createIndex('idx_pay_status', '{{%order}}', ['pay_status'], false);
        $this->createIndex('idx_order_delivery_task_id', '{{%order}}', ['delivery_task_id'], false);
        $this->createIndex('idx_refund_status', '{{%order}}', ['refund_status'], false);
        $this->createIndex('idx_status', '{{%order}}', ['status'], false);
        $this->createIndex('idx_user_status', '{{%order}}', ['user_id', 'status'], false);
        $this->createIndex('idx_mch_id', '{{%order}}', ['mch_id'], false);
        $this->createIndex('idx_delivery_status', '{{%order}}', ['delivery_status'], false);
        $this->createIndex('idx_clerk_id', '{{%order}}', ['clerk_id'], false);
        $this->createIndex('idx-order-status_settlement_status', '{{%order}}', ['status', 'settlement_status'], false);
        $this->createIndex('idx-order-rider_id', '{{%order}}', ['rider_id'], false);
        $this->createIndex('idx-order-pickup_code', '{{%order}}', ['pickup_code'], false);
        $this->createIndex('idx-order-payment_class', '{{%order}}', ['payment_class'], false);
        $this->createIndex('idx-order-delivery_task_id', '{{%order}}', ['delivery_task_id'], false);

        // 创建表: jxmall_order_concession_log
        $this->createTable('{{%order_concession_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'order_id' => $this->integer()->notNull()->comment('关联的订单ID'),
            'order_detail_id' => $this->integer()->notNull()->comment('关联的订单详情ID (唯一)'),
            'user_id' => $this->integer()->notNull()->comment('购买用户ID'),
            'base_price' => $this->decimal(10, 2)->notNull()->comment('计算基准价 (order_detail.total_price)'),
            'concession_rate' => $this->decimal(5, 2)->notNull()->comment('当时应用的让利比例(%)'),
            'concession_amount' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('复购阶段让利金额'),
            'total_concession_amount' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('总让利金额'),
            'initial_concession_amount' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('初购阶段让利金额'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('让利类型: 1=初购阶段让利, 2=复购阶段让利')
        ]);

        $this->createIndex('idx-order_concession_log-order_id', '{{%order_concession_log}}', ['order_id'], false);
        $this->createIndex('uk-order_concession_log-order_detail_id', '{{%order_concession_log}}', ['order_detail_id'], true);

        // 创建表: jxmall_order_detail
        $this->createTable('{{%order_detail}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('单价'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID'),
            'is_price' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否分佣 暂无作用'),
            'price_time' => $this->integer()->null()->defaultValue(0)->comment('分佣时间'),
            'order_no' => $this->string(45)->null()->defaultValue('')->comment('公共订单ID'),
            'attr' => $this->string(512)->null()->defaultValue('')->comment('商品规格'),
            'num' => $this->integer()->notNull()->defaultValue(0)->comment('数量'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 正常 1 有效  2 无效'),
            'is_mch_settled' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('商家货款是否已结算:0=否,1=是'),
            'coupon_cut_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('优惠券减掉的金额'),
            'user_coupon_id' => $this->integer()->null()->defaultValue(0)->comment('用户优惠券ID'),
            'integral_cut_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('积分减掉的金额'),
            'use_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('使用积分'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('实际支付金额'),
            'concession_amount' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('让利金额'),
            'pickup_discount_cut_price' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('分摊的自提优惠'),
            'platform_subsidy_cut_price' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('分摊的平台补贴'),
            'mch_income_price' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('商家实收商品货款'),
            'settlement_price' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('结算金额'),
            'total_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('总计价格'),
            'express_log_id' => $this->integer()->null()->defaultValue(0)->comment('发货记录'),
            'is_send' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否发货'),
            'attr_id' => $this->integer()->null()->defaultValue(0)->comment('规格ID'),
            'refund_status' => $this->smallInteger()->notNull()->defaultValue(0)->comment('售后状态:0=未售后,10=售后中,30=售后完成'),
            'is_send_integral' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否赠送了积分'),
            'send_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('赠送积分数量'),
            'is_can_refund' => $this->tinyInteger()->null()->defaultValue(1)->comment('支持退款'),
            'order_id' => $this->integer()->notNull()->defaultValue(0)->comment('主订单ID'),
            'mch_income_breakdown' => $this->text()->null()->comment('商家货款收入计算明细(JSON)')
        ]);

        $this->createIndex('idx_order_no', '{{%order_detail}}', ['order_no'], false);
        $this->createIndex('idx-order_detail-order_id', '{{%order_detail}}', ['order_id'], false);
        $this->createIndex('goods_id', '{{%order_detail}}', ['goods_id'], false);

        // 创建表: jxmall_order_goods_complete_log
        $this->createTable('{{%order_goods_complete_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('支付金额'),
            'total_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('商品总额'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'goods_id' => $this->integer()->null()->defaultValue(0)->comment('商品ID'),
            'num' => $this->integer()->null()->defaultValue(0)->comment('数量'),
            'order_detail_id' => $this->integer()->null()->defaultValue(0)->comment('订单详情ID'),
            'y' => $this->integer()->null()->defaultValue(0)->comment('年'),
            'm' => $this->integer()->null()->defaultValue(0)->comment('月'),
            'd' => $this->integer()->null()->defaultValue(0)->comment('日'),
            'w' => $this->integer()->null()->defaultValue(0)->comment('周'),
            'q' => $this->integer()->null()->defaultValue(0)->comment('季度')
        ]);


        // 创建表: jxmall_order_goods_pay_log
        $this->createTable('{{%order_goods_pay_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('支付金额'),
            'total_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('商品总额'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'goods_id' => $this->integer()->null()->defaultValue(0)->comment('商品ID'),
            'num' => $this->integer()->null()->defaultValue(0)->comment('数量'),
            'order_detail_id' => $this->integer()->null()->defaultValue(0)->comment('订单详情ID'),
            'y' => $this->integer()->null()->defaultValue(0)->comment('年'),
            'm' => $this->integer()->null()->defaultValue(0)->comment('月'),
            'd' => $this->integer()->null()->defaultValue(0)->comment('日'),
            'w' => $this->integer()->null()->defaultValue(0)->comment('周'),
            'q' => $this->integer()->null()->defaultValue(0)->comment('季度')
        ]);


        // 创建表: jxmall_order_refund
        $this->createTable('{{%order_refund}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'order_id' => $this->integer()->notNull()->comment('订单ID'),
            'order_detail_id' => $this->integer()->notNull(),
            'goods_id' => $this->integer()->notNull()->comment('商品ID'),
            'pic_list' => $this->string(2048)->null()->defaultValue('')->comment('图片列表'),
            'content' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('退款退货 1 换货'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 未处理'),
            'refund_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00'),
            'order_no' => $this->string(45)->null()->defaultValue(''),
            'is_refund' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否退款'),
            'mch_id' => $this->integer()->null()->defaultValue(0)->comment('商家ID'),
            'return_name' => $this->string(255)->null()->comment('退货联系人'),
            'return_mobile' => $this->string(32)->null()->comment('退货联系电话'),
            'return_address' => $this->string(512)->null()->comment('退货地址详情'),
            'user_express' => $this->string(255)->null()->comment('用户发货快递公司'),
            'user_express_no' => $this->string(255)->null()->comment('用户发货快递单号'),
            'user_express_at' => $this->integer()->unsigned()->null()->comment('用户发货时间戳')
        ]);


        // 创建表: jxmall_order_split_detail_log
        $this->createTable('{{%order_split_detail_log}}', [
            'id' => $this->primaryKey(),
            'order_split_log_id' => $this->integer()->notNull()->comment('关联分账主日志ID'),
            'recipient_user_id' => $this->string(255)->notNull()->comment('分账接收方的平台用户ID (例如快付的userId)'),
            'recipient_type' => $this->string(255)->notNull()->comment('接收方类型 (例如 mch)'),
            'recipient_id' => $this->integer()->notNull()->comment('接收方在我方系统的ID (例如 mch_id)'),
            'amount' => $this->decimal(10, 2)->notNull()->comment('分账金额'),
            'description' => $this->string(255)->null()->defaultValue('')->comment('分账描述'),
            'status' => $this->string(50)->notNull()->comment('状态: processing, success, failed'),
            'remark' => $this->string(512)->null()->defaultValue('')->comment('备注')
        ]);

        $this->createIndex('idx_order_split_log_id', '{{%order_split_detail_log}}', ['order_split_log_id'], false);
        $this->createIndex('idx_recipient', '{{%order_split_detail_log}}', ['recipient_type', 'recipient_id'], false);

        // 创建表: jxmall_order_split_log
        $this->createTable('{{%order_split_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'order_id' => $this->integer()->notNull()->comment('关联的原始订单ID'),
            'split_order_no' => $this->string(255)->notNull()->comment('本次分账的唯一订单号'),
            'split_class' => $this->string(255)->notNull()->comment('分账渠道实现类'),
            'total_amount' => $this->decimal(10, 2)->notNull()->comment('本次分账总金额'),
            'status' => $this->string(50)->notNull()->defaultValue('processing')->comment('状态: processing, success, failed'),
            'request_data' => $this->text()->null()->comment('请求报文'),
            'response_data' => $this->text()->null()->comment('响应报文'),
            'notify_data' => $this->text()->null()->comment('异步回调数据'),
            'remark' => $this->string(512)->null()->defaultValue('')->comment('备注')
        ]);

        $this->createIndex('idx_order_id', '{{%order_split_log}}', ['order_id'], false);
        $this->createIndex('idx_split_order_no', '{{%order_split_log}}', ['split_order_no'], false);

        // 创建表: jxmall_order_submit_queue
        $this->createTable('{{%order_submit_queue}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->defaultValue(0)->comment('用户ID'),
            'pre_order_no' => $this->string(255)->notNull()->defaultValue('')->comment('唯一预订单号'),
            'form_data' => $this->text()->notNull()->comment('序列化的订单提交表单数据'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('状态 0=待处理, 1=成功, 2=失败'),
            'order_no' => $this->string(255)->null()->comment('成功后生成的正式订单号'),
            'pay_data_key' => $this->string(255)->null()->comment('支付数据key'),
            'error_msg' => $this->text()->null()->comment('失败时的错误信息')
        ]);

        $this->createIndex('uq_pre_order_no', '{{%order_submit_queue}}', ['pre_order_no'], true);

        // 创建表: jxmall_outside_link
        $this->createTable('{{%outside_link}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'link' => $this->string(512)->null()->defaultValue('')->comment('外部链接地址'),
            'name' => $this->string(62)->null()->defaultValue('')->comment('链接名称')
        ]);


        // 创建表: jxmall_pay_flow
        $this->createTable('{{%pay_flow}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户id'),
            'order_no' => $this->string(45)->null()->defaultValue('')->comment('订单号'),
            'class_name' => $this->string(255)->null()->defaultValue('')->comment('订单类'),
            'wechat_order_no' => $this->string(45)->null()->defaultValue('')->comment('微信交易号'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('支付金额'),
            'type' => $this->tinyInteger()->null()->defaultValue(0)->comment('0减少 1 增加'),
            'pay_type' => $this->string(45)->null()->defaultValue('wechat')->comment('支付方式')
        ]);


        // 创建表: jxmall_payout_log
        $this->createTable('{{%payout_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull()->comment('收款用户ID'),
            'source_id' => $this->integer()->notNull()->comment('关联的源单ID (例如提现申请ID)'),
            'source_type' => $this->string(255)->notNull()->comment('关联的源单模型类名 (例如 MchCashLog::class)'),
            'payout_no' => $this->string(255)->null()->defaultValue('')->comment('第三方支付网关返回的流水号'),
            'fund_class' => $this->string(255)->notNull()->comment('打款渠道类名'),
            'channel' => $this->string(50)->notNull()->comment('打款方式 (e.g. bank, wechat)'),
            'amount' => $this->decimal(10, 2)->notNull()->comment('实际打款金额'),
            'status' => $this->string(50)->notNull()->comment('状态: processing, success, failed'),
            'request_data' => $this->text()->null()->comment('请求报文'),
            'response_data' => $this->text()->null()->comment('响应报文'),
            'remark' => $this->string(512)->null()->defaultValue('')->comment('备注'),
            'completed_at' => $this->integer()->null()->comment('打款完成时间戳')
        ]);

        $this->createIndex('idx_payout_no', '{{%payout_log}}', ['payout_no'], false);
        $this->createIndex('idx_source', '{{%payout_log}}', ['source_type', 'source_id'], false);

        // 创建表: jxmall_plugin
        $this->createTable('{{%plugin}}', [
            'id' => $this->primaryKey(),
            'name' => $this->string(45)->notNull()->comment('插件标识'),
            'label' => $this->string(45)->notNull()->comment('插件名字'),
            'version' => $this->string(10)->notNull()->comment('版本号'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0 分润类 1营销类 2工具'),
            'install_type' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('0本地 1在线'),
            'logo' => $this->string(255)->null()->defaultValue('')->comment('logo'),
            'description' => $this->string(255)->null()->defaultValue('')->comment('介绍')
        ]);


        // 创建表: jxmall_price_log
        $this->createTable('{{%price_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('金额'),
            'remarks' => $this->string(128)->null()->defaultValue('')->comment('备注'),
            'order_detail_id' => $this->integer()->notNull()->defaultValue(0)->comment('订单详情ID'),
            'buy_user_id' => $this->integer()->notNull()->defaultValue(0)->comment('购买者的ID'),
            'order_no' => $this->string(45)->notNull()->defaultValue('')->comment('订单号'),
            'type' => $this->tinyInteger()->null()->defaultValue(0)->comment('0减少  1增加'),
            'title' => $this->string(45)->null()->defaultValue('')->comment('标题'),
            'month_at' => $this->string(10)->null()->comment('月份'),
            'current' => $this->decimal(10, 2)->null()->comment('当前'),
            'date_at' => $this->date()->null()->comment('日期')
        ]);


        // 创建表: jxmall_price_to_balance
        $this->createTable('{{%price_to_balance}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id')
        ]);


        // 创建表: jxmall_printer_log
        $this->createTable('{{%printer_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'printer_sn' => $this->string(64)->null()->defaultValue('')->comment('设备号'),
            'account' => $this->string(64)->null()->defaultValue('')->comment('调用的账号'),
            'content' => $this->string(1024)->null()->defaultValue('')->comment('内容'),
            'res' => $this->string(1024)->null()->defaultValue('')->comment('打印结果'),
            'api' => $this->string(128)->null()->defaultValue('')->comment('调用的接口')
        ]);


        // 创建表: jxmall_purchase_order
        $this->createTable('{{%purchase_order}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->comment('采购的商家ID'),
            'order_no' => $this->string(64)->notNull()->comment('采购单号'),
            'total_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('采购总金额'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(10)->comment('状态: 10=待平台确认, 30=已发货, 40=已完成, 0=已关闭'),
            'address_info' => $this->text()->notNull()->comment('收货地址JSON快照'),
            'remarks' => $this->string(255)->null()->defaultValue('')->comment('商家备注'),
            'shipped_at' => $this->integer()->unsigned()->null()->defaultValue(0)->comment('平台确认/发货时间'),
            'completed_at' => $this->integer()->unsigned()->null()->defaultValue(0)->comment('商家确认收货时间')
        ]);

        $this->createIndex('order_no', '{{%purchase_order}}', ['order_no'], true);

        // 创建表: jxmall_purchase_order_detail
        $this->createTable('{{%purchase_order_detail}}', [
            'id' => $this->primaryKey(),
            'purchase_order_id' => $this->integer()->notNull()->comment('关联采购单ID'),
            'goods_id' => $this->integer()->notNull()->comment('平台商品ID'),
            'attr_id' => $this->integer()->notNull()->defaultValue(0)->comment('平台商品规格ID'),
            'num' => $this->integer()->notNull()->comment('采购数量'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('采购单价'),
            'total_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('采购总价'),
            'goods_info' => $this->text()->notNull()->comment('商品信息快照JSON (名称,封面图,规格等)')
        ]);

        $this->createIndex('idx_purchase_order_id', '{{%purchase_order_detail}}', ['purchase_order_id'], false);

        // 创建表: jxmall_queue
        $this->createTable('{{%queue}}', [
            'id' => $this->primaryKey(),
            'channel' => $this->string(255)->notNull(),
            'job' => $this->string()->notNull(),
            'pushed_at' => $this->integer()->notNull(),
            'ttr' => $this->integer()->notNull(),
            'delay' => $this->integer()->notNull()->defaultValue(0),
            'priority' => $this->integer()->unsigned()->notNull()->defaultValue(1024),
            'reserved_at' => $this->integer()->null(),
            'attempt' => $this->integer()->null(),
            'done_at' => $this->integer()->null(),
            'class' => $this->string(128)->null()->defaultValue('')->comment('class')
        ]);

        $this->createIndex('channel', '{{%queue}}', ['channel'], false);
        $this->createIndex('priority', '{{%queue}}', ['priority'], false);
        $this->createIndex('reserved_at', '{{%queue}}', ['reserved_at'], false);

        // 创建表: jxmall_refund_fail_log
        $this->createTable('{{%refund_fail_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'refund_no' => $this->string(64)->null()->defaultValue('')->comment('退款单号'),
            'order_no' => $this->string(64)->null()->defaultValue('')->comment('订单号'),
            'pay_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('支付金额'),
            'refund_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('退款金额'),
            'remark' => $this->string(512)->null()->defaultValue('')->comment('备注'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('0待处理 1 已退款 2失败'),
            'is_join_bank_pay' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否是汇聚银行支付')
        ]);


        // 创建表: jxmall_refund_order_log
        $this->createTable('{{%refund_order_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'order_no' => $this->string(45)->null()->defaultValue('')->comment('订单号'),
            'refund_order_no' => $this->string(45)->null()->defaultValue('')->comment('退款单号'),
            'refund_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('退款金额'),
            'remark' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'from_pay_type' => $this->string(45)->null()->defaultValue('wechat')->comment('退款来源 wehcat join'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('退款状态 1 成功 2失败'),
            'message' => $this->string(5120)->null()->defaultValue('')->comment('回调通知的消息')
        ]);


        // 创建表: jxmall_role
        $this->createTable('{{%role}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'role_name' => $this->string(45)->null()->defaultValue('')->comment('role_name'),
            'role_key' => $this->string(255)->null()->defaultValue('')->comment('role_key'),
            'role_sort' => $this->integer()->null()->defaultValue(0)->comment('排序'),
            'remark' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('状态'),
            'menu_check_strictly' => $this->tinyInteger()->null()->defaultValue(0)->comment('menu_check_strictly'),
            'admin' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否是管理员'),
            'created_by' => $this->integer()->null()->defaultValue(0)->comment('创建人')
        ]);

        $this->createIndex('idx-role-mall_id', '{{%role}}', ['mall_id'], false);

        // 创建表: jxmall_role_api
        $this->createTable('{{%role_api}}', [
            'id' => $this->primaryKey(),
            'path' => $this->string(255)->null()->defaultValue('')->comment('接口路径'),
            'role_id' => $this->string(90)->null()->defaultValue('')->comment('角色ID'),
            'method' => $this->string(10)->null()->defaultValue('')->comment('请求方法'),
            'uniqueid' => $this->string(255)->null()->defaultValue('')->comment('唯一ID')
        ]);


        // 创建表: jxmall_role_menu
        $this->createTable('{{%role_menu}}', [
            'id' => $this->primaryKey(),
            'role_id' => $this->integer()->null()->defaultValue(0)->comment('role_id'),
            'menu_id' => $this->string(45)->null()->defaultValue('')->comment('menu_id')
        ]);


        // 创建表: jxmall_search_log
        $this->createTable('{{%search_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('搜索用户ID, 0为游客'),
            'keyword' => $this->string(255)->notNull()->comment('搜索关键词')
        ]);

        $this->createIndex('idx_keyword', '{{%search_log}}', ['keyword'], false);

        // 创建表: jxmall_session
        $this->createTable('{{%session}}', [
            'id' => $this->char(40)->notNull(),
            'expire' => $this->integer()->null()->defaultValue(0),
            'DATA' => $this->binary()->null()
        ]);




        // 创建表: jxmall_split_onboarding_log
        $this->createTable('{{%split_onboarding_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull()->comment('关联的用户ID'),
            'h5_register_flow' => $this->string(255)->null()->comment('H5注册回执串'),
            'pay_auth_path' => $this->string(255)->null()->comment('支付授权目录, 用于判断是否完成授权'),
            'application_id' => $this->string(255)->null()->comment('第三方平台返回的应用APPID'),
            'platform_user_id' => $this->string(255)->null()->comment('第三方平台返回的用户唯一标识 (如快付的userId)'),
            'receive_user_id' => $this->string(255)->null()->comment('分账接收方用户ID'),
            'split_class' => $this->string(255)->notNull()->comment('分账渠道实现类'),
            'out_mer_code' => $this->string(255)->notNull()->comment('外部商户号'),
            'status' => $this->string(50)->notNull()->defaultValue('WAIT')->comment('进件状态: wait, success, failed'),
            'request_data' => $this->text()->null()->comment('请求报文'),
            'response_data' => $this->text()->null()->comment('响应报文 (包含H5链接)'),
            'notify_data' => $this->text()->null()->comment('异步回调数据'),
            'remark' => $this->string(512)->null()->defaultValue('')->comment('备注')
        ]);

        $this->createIndex('idx_out_mer_code', '{{%split_onboarding_log}}', ['out_mer_code'], false);
        $this->createIndex('idx_sol_h5_register_flow', '{{%split_onboarding_log}}', ['h5_register_flow'], false);
        $this->createIndex('idx_sol_platform_user_id', '{{%split_onboarding_log}}', ['platform_user_id'], false);
        $this->createIndex('idx_user_id', '{{%split_onboarding_log}}', ['user_id'], false);

        // 创建表: jxmall_tab_goods
        $this->createTable('{{%tab_goods}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'name' => $this->string(64)->null()->defaultValue('')->comment('标签名称'),
            'list' => $this->text()->null()->comment('标签列表')
        ]);


        // 创建表: jxmall_tabbar
        $this->createTable('{{%tabbar}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'setting' => $this->text()->notNull(),
            'color' => $this->string(15)->null()->defaultValue('')
        ]);


        // 创建表: jxmall_user
        $this->createTable('{{%user}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'nickname' => $this->string(45)->null()->defaultValue('')->comment('昵称'),
            'avatar_url' => $this->string(255)->null()->defaultValue('')->comment('头像'),
            'union_id' => $this->string(64)->null()->defaultValue('')->comment('公众号UnionId'),
            'mobile' => $this->string(15)->null()->defaultValue('')->comment('手机号'),
            'access_token' => $this->string(64)->null()->defaultValue('')->comment('token'),
            'auth_key' => $this->string(64)->null()->defaultValue('')->comment('auth key'),
            'login_ip' => $this->string(45)->null()->defaultValue('')->comment('登录IP'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('奖励'),
            'total_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('总奖励'),
            'money' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('余额'),
            'total_money' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('总余额'),
            'is_inviter' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否是邀请者'),
            'inviter_at' => $this->integer()->null()->defaultValue(0)->comment('成为邀请者的时间'),
            'integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('当前积分'),
            'total_integral' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('累计积分'),
            'retained_integral' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('累计留存积分'),
            'platform' => $this->string(45)->null()->defaultValue('WECHAT')->comment('平台'),
            'remarks' => $this->string(255)->null()->defaultValue('')->comment('备注'),
            'parent_id' => $this->integer()->notNull()->defaultValue(0)->comment('推荐人'),
            'parent_at' => $this->integer()->null()->defaultValue(0)->comment('绑定时间'),
            'maybe_parent_id' => $this->integer()->notNull()->defaultValue(0)->comment('可能的上级'),
            'level_id' => $this->integer()->notNull()->defaultValue(0)->comment('等级ID'),
            'level_at' => $this->integer()->null()->defaultValue(0)->comment('等级更新于'),
            'level' => $this->integer()->notNull()->defaultValue(0)->comment('会员等级'),
            'is_manual' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否手动调整'),
            'is_subscribe' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否关注'),
            'birthday' => $this->string(45)->null()->defaultValue('')->comment('会员生日'),
            'year' => $this->integer()->null()->defaultValue(0)->comment('年'),
            'month' => $this->tinyInteger()->null()->defaultValue(0)->comment('月'),
            'day' => $this->tinyInteger()->null()->defaultValue(0)->comment('日'),
            'openid' => $this->string(64)->null()->defaultValue('')->comment('临时openid，用户登录就会重新覆盖新的openid'),
            'password' => $this->string(64)->null()->defaultValue('')->comment('密码'),
            'current_platform' => $this->string(15)->null()->defaultValue('')->comment('用户当前所在平台'),
            'name' => $this->string(64)->null()->defaultValue('')->comment('用户名'),
            'wechat_no' => $this->string(64)->null()->defaultValue('')->comment('微信号'),
            'order_always' => $this->tinyInteger()->null()->defaultValue(0)->comment('总是可以下单 0否 1是'),
            'cashed_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('已成功提现'),
            'lat' => $this->float()->null()->defaultValue(0)->comment('纬度'),
            'lon' => $this->float()->null()->defaultValue(0)->comment('经度'),
            'mch_id' => $this->integer()->null()->defaultValue(0)->comment('用户对应的商户'),
            'is_clerk' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否是核销员'),
            'is_mch' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否是商家'),
            'pay_pwd' => $this->string(64)->null()->defaultValue('')->comment('支付密码'),
            'clerk_time' => $this->dateTime()->null()->comment('成为核销员的时间'),
            'mch_time' => $this->dateTime()->null()->comment('成为商家的时间'),
            'fd' => $this->integer()->null()->defaultValue(0)->comment('socket 链接'),
            'shop_id' => $this->integer()->null()->defaultValue(0)->comment('门店ID'),
            'is_shop' => $this->integer()->null()->defaultValue(0)->comment('是否是店长'),
            'shop_time' => $this->string(45)->null()->defaultValue('')->comment('门店时间'),
            'admin_fd' => $this->integer()->null()->defaultValue(0)->comment('socket 链接'),
            'cashier_fd' => $this->integer()->null()->defaultValue(0)->comment('收银台长连接'),
            'bg_pic' => $this->string(512)->null()->defaultValue('')->comment('背景图用于笔记个人中心显示'),
            'detail' => $this->string(255)->null()->defaultValue('')->comment('简介'),
            'is_avatar' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否完善头像信息'),
            'account_del_time' => $this->dateTime()->null()->comment('注销时间'),
            'parent_date' => $this->date()->null()->comment('绑定上级的日期'),
            'wechat_openid' => $this->string(128)->null()->defaultValue('')->comment('公众号openid'),
            'mpwx_openid' => $this->string(128)->null()->defaultValue('')->comment('小程序openid'),
            'status' => $this->tinyInteger()->null()->defaultValue(0)->comment('用户状态:0 正常 1拉黑 2注销'),
            'backup_mobile' => $this->string(45)->null()->comment('原手机号'),
            'agent_level_id' => $this->integer()->unsigned()->null()->comment('数字代理等级ID'),
            'bind_mch_id' => $this->integer()->null()->comment('用户绑定的商户id'),
            'application_id' => $this->string(64)->null()->comment('分账第三方平台应用ID'),
            'other_platform_user_id' => $this->string(64)->null()->comment('分账第三方平台用户ID'),
            'out_mch_no' => $this->string(64)->null()->comment('外部商户号')
        ]);

        $this->createIndex('idx-user-agent_level_id', '{{%user}}', ['agent_level_id'], false);
        $this->createIndex('idx_user_nickname', '{{%user}}', ['nickname'], false);
        $this->createIndex('access_token', '{{%user}}', ['access_token'], true);

        // 创建表: jxmall_user_action_log
        $this->createTable('{{%user_action_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->defaultValue(0)->comment('商城ID'),
            'user_id' => $this->integer()->null()->defaultValue(0),
            'before_update' => $this->text()->null(),
            'after_update' => $this->text()->null(),
            'remarks' => $this->string(255)->null()->defaultValue(''),
            'log_at' => $this->dateTime()->null()->comment('更新时间')
        ]);


        // 创建表: jxmall_user_address
        $this->createTable('{{%user_address}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'address' => $this->string(255)->notNull()->comment('地址'),
            'province_code' => $this->string(20)->null()->defaultValue('')->comment('省代码'),
            'city_code' => $this->string(20)->null()->defaultValue('')->comment('市代码'),
            'county_code' => $this->string(20)->null()->defaultValue('')->comment('区代码'),
            'town_code' => $this->string(20)->null()->defaultValue('')->comment('镇代码'),
            'is_default' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否默认'),
            'province_name' => $this->string(45)->null()->defaultValue('')->comment('省'),
            'city_name' => $this->string(45)->null()->defaultValue('')->comment('市'),
            'county_name' => $this->string(45)->null()->defaultValue('')->comment('区'),
            'town_name' => $this->string(45)->null()->defaultValue('')->comment('镇'),
            'mobile' => $this->string(11)->null()->defaultValue('')->comment('手机号码'),
            'name' => $this->string(45)->null()->defaultValue('')->comment('收货人姓名'),
            'area' => $this->string(128)->null()->defaultValue('')->comment('所属区域'),
            'detail' => $this->string(128)->null()->defaultValue('')->comment('详细地址'),
            'latitude' => $this->decimal(10, 6)->null()->comment('纬度'),
            'longitude' => $this->decimal(10, 6)->null()->comment('经度'),
            'location' => $this->string(255)->null()->comment('地理位置')
        ]);


        // 创建表: jxmall_user_coupon
        $this->createTable('{{%user_coupon}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'price' => $this->decimal(10, 2)->null()->comment('门槛金额快照 (兼容旧字段)'),
            'discount' => $this->decimal(10, 2)->null()->comment('优惠/折扣快照'),
            'type' => $this->smallInteger()->null()->comment('类型快照'),
            'is_goods_limit' => $this->smallInteger()->null()->comment('商品限制快照 (兼容旧字段)'),
            'coupon_id' => $this->integer()->notNull()->comment('优惠券ID'),
            'start_at' => $this->integer()->null()->defaultValue(0)->comment('有效时间'),
            'end_at' => $this->integer()->null()->defaultValue(0)->comment('有效时间截止'),
            'status' => $this->smallInteger()->notNull()->defaultValue(0)->comment('状态: 0=未使用, 1=已使用, 2=已过期'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'code' => $this->string(45)->null()->defaultValue('')->comment('优惠券码'),
            'remarks' => $this->string(128)->null()->defaultValue('')->comment('备注'),
            'min_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('最低消费金额门槛'),
            'sub_price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('优惠金额'),
            'appoint_type' => $this->string(50)->notNull()->defaultValue('all')->comment('指定范围类型: all, goods, cat'),
            'appoint_relation' => $this->text()->null()->comment('范围关联ID (JSON)'),
            'used_at' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('核销使用时间'),
            'is_transferable' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否允许转赠:0=否,1=是')
        ]);

        $this->createIndex('idx_user_mch_status_end', '{{%user_coupon}}', ['user_id', 'mch_id', 'status', 'end_at'], false);

        // 创建表: jxmall_user_coupon_transfer_log
        $this->createTable('{{%user_coupon_transfer_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_coupon_id' => $this->integer()->notNull()->comment('具体的优惠券ID'),
            'from_user_id' => $this->integer()->notNull()->comment('转出方用户ID'),
            'to_user_id' => $this->integer()->notNull()->comment('接收方用户ID'),
            'remark' => $this->string(255)->null()->defaultValue('')->comment('备注')
        ]);

        $this->createIndex('idx_from_user_id', '{{%user_coupon_transfer_log}}', ['from_user_id'], false);
        $this->createIndex('idx_to_user_id', '{{%user_coupon_transfer_log}}', ['to_user_id'], false);
        $this->createIndex('idx_user_coupon_id', '{{%user_coupon_transfer_log}}', ['user_coupon_id'], false);

        // 创建表: jxmall_user_goods_purchase_history
        $this->createTable('{{%user_goods_purchase_history}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'goods_id' => $this->integer()->notNull(),
            'total_num' => $this->integer()->notNull()->defaultValue(0)->comment('累计购买总数')
        ]);

        $this->createIndex('idx_mall_id', '{{%user_goods_purchase_history}}', ['mall_id'], false);
        $this->createIndex('idx_user_goods_unique', '{{%user_goods_purchase_history}}', ['user_id', 'goods_id'], true);


        // 创建表: jxmall_user_level_count_log
        $this->createTable('{{%user_level_count_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->defaultValue(0)->comment('mall_id'),
            'level_id' => $this->integer()->null()->defaultValue(0)->comment('等级ID'),
            'num' => $this->integer()->null()->defaultValue(0)->comment('数量'),
            'y' => $this->integer()->null()->defaultValue(0)->comment('年'),
            'm' => $this->integer()->null()->defaultValue(0)->comment('月'),
            'd' => $this->integer()->null()->defaultValue(0)->comment('日'),
            'w' => $this->integer()->null()->defaultValue(0)->comment('周'),
            'q' => $this->integer()->null()->defaultValue(0)->comment('季度')
        ]);


        // 创建表: jxmall_user_parent
        $this->createTable('{{%user_parent}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'parent_id' => $this->integer()->notNull()->comment('推荐人ID'),
            'level' => $this->integer()->notNull()->comment('层级')
        ]);


        // 创建表: jxmall_user_relation_fail
        $this->createTable('{{%user_relation_fail}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->null()->comment('商城ID'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID')
        ]);


        // 创建表: jxmall_user_settlement_info
        $this->createTable('{{%user_settlement_info}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull()->comment('关联的用户ID'),
            'real_name' => $this->string(255)->null()->comment('真实姓名'),
            'identity_type' => $this->string(50)->notNull()->defaultValue('ID_CARD')->comment('证件类型 (例如: ID_CARD)'),
            'identity_no' => $this->string(255)->null()->comment('证件号码'),
            'mobile' => $this->string(50)->null()->comment('预留手机号'),
            'extra_info' => $this->text()->null()->comment('扩展信息 (JSON格式，用于存储各渠道进件状态)'),
            'settlement_data' => $this->text()->null()->comment('扩展结算信息 (JSON格式)'),
            'channel_data' => $this->json()->null()->comment('按渠道隔离的专属结算信息(JSON)')
        ]);

        $this->createIndex('idx_user_id', '{{%user_settlement_info}}', ['user_id'], false);
        $this->createIndex('uk_user_id', '{{%user_settlement_info}}', ['user_id'], true);

        // 创建表: jxmall_user_upgrade_log
        $this->createTable('{{%user_upgrade_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull(),
            'before_level_id' => $this->integer()->notNull()->defaultValue(-1),
            'after_level_id' => $this->integer()->notNull()->defaultValue(-1),
            'reason_by' => $this->integer()->notNull()->comment('0 购物满   1购买商品 2直推会员'),
            'marks' => $this->string(45)->notNull()->defaultValue(''),
            'time_at' => $this->timestamp()->null()->comment('升级时间')
        ]);


        // 创建表: jxmall_wechat_order_push_log
        $this->createTable('{{%wechat_order_push_log}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'user_id' => $this->integer()->null()->defaultValue(0)->comment('用户ID'),
            'order_no' => $this->string(64)->notNull()->defaultValue('')->comment('订单号'),
            'transaction_id' => $this->string(64)->notNull()->defaultValue('')->comment('微信支付单号'),
            'upload_time' => $this->string(64)->notNull()->defaultValue('')->comment('推送时间'),
            'order_class' => $this->string(256)->null()->defaultValue('订单类')
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%wechat_order_push_log}}');
        $this->dropTable('{{%user_upgrade_log}}');
        $this->dropTable('{{%user_settlement_info}}');
        $this->dropTable('{{%user_relation_fail}}');
        $this->dropTable('{{%user_parent}}');
        $this->dropTable('{{%user_level_count_log}}');
        $this->dropTable('{{%user_goods_purchase_history}}');
        $this->dropTable('{{%user_coupon_transfer_log}}');
        $this->dropTable('{{%user_coupon}}');
        $this->dropTable('{{%user_address}}');
        $this->dropTable('{{%user_action_log}}');
        $this->dropTable('{{%user}}');
        $this->dropTable('{{%tabbar}}');
        $this->dropTable('{{%tab_goods}}');
        $this->dropTable('{{%split_onboarding_log}}');
        $this->dropTable('{{%session}}');
        $this->dropTable('{{%search_log}}');
        $this->dropTable('{{%role_menu}}');
        $this->dropTable('{{%role_api}}');
        $this->dropTable('{{%role}}');
        $this->dropTable('{{%refund_order_log}}');
        $this->dropTable('{{%refund_fail_log}}');
        $this->dropTable('{{%queue}}');
        $this->dropTable('{{%purchase_order_detail}}');
        $this->dropTable('{{%purchase_order}}');
        $this->dropTable('{{%printer_log}}');
        $this->dropTable('{{%price_to_balance}}');
        $this->dropTable('{{%price_log}}');
        $this->dropTable('{{%plugin}}');
        $this->dropTable('{{%payout_log}}');
        $this->dropTable('{{%pay_flow}}');
        $this->dropTable('{{%outside_link}}');
        $this->dropTable('{{%order_submit_queue}}');
        $this->dropTable('{{%order_split_log}}');
        $this->dropTable('{{%order_split_detail_log}}');
        $this->dropTable('{{%order_refund}}');
        $this->dropTable('{{%order_goods_pay_log}}');
        $this->dropTable('{{%order_goods_complete_log}}');
        $this->dropTable('{{%order_detail}}');
        $this->dropTable('{{%order_concession_log}}');
        $this->dropTable('{{%order}}');
        $this->dropTable('{{%option}}');
        $this->dropTable('{{%noun}}');
        $this->dropTable('{{%notebook}}');

        $this->dropTable('{{%merchant_rule}}');
        $this->dropTable('{{%menu}}');
        $this->dropTable('{{%mch_settlement_log}}');
        $this->dropTable('{{%mch_cash_log}}');
        $this->dropTable('{{%mch_balance_log}}');
        $this->dropTable('{{%mall_plugin}}');
        $this->dropTable('{{%mall_menu_option}}');
        $this->dropTable('{{%mall}}');
        $this->dropTable('{{%level}}');
        $this->dropTable('{{%joinpay_bank_sign}}');
        $this->dropTable('{{%integral_transfer_fee_log}}');
        $this->dropTable('{{%integral_log}}');
        $this->dropTable('{{%huifu_order}}');
        $this->dropTable('{{%hot_image}}');
        $this->dropTable('{{%green_integral_log}}');
        $this->dropTable('{{%goods_up_down_job}}');
        $this->dropTable('{{%goods_info}}');
        $this->dropTable('{{%goods_footmark}}');
        $this->dropTable('{{%goods_favorite}}');
        $this->dropTable('{{%goods_coupon}}');
        $this->dropTable('{{%goods_comment}}');
        $this->dropTable('{{%goods_cat}}');
        $this->dropTable('{{%goods}}');
        $this->dropTable('{{%freight_tpl}}');
        $this->dropTable('{{%freight_price}}');
        $this->dropTable('{{%file_group}}');
        $this->dropTable('{{%file}}');
        $this->dropTable('{{%feie_printer}}');
        $this->dropTable('{{%feedback}}');
        $this->dropTable('{{%fd_heartbeat}}');
        $this->dropTable('{{%express_setting}}');
        $this->dropTable('{{%express_log}}');
        $this->dropTable('{{%eorder_detail}}');
        $this->dropTable('{{%eorder}}');
        $this->dropTable('{{%diy_page}}');
        $this->dropTable('{{%date_price_log}}');
        $this->dropTable('{{%date_integral_log}}');
        $this->dropTable('{{%date_balance_log}}');
        $this->dropTable('{{%coupon}}');
        $this->dropTable('{{%common_pay_order}}');
        $this->dropTable('{{%commission_log}}');
        $this->dropTable('{{%charge_log}}');
        $this->dropTable('{{%cat}}');
        $this->dropTable('{{%cash}}');
        $this->dropTable('{{%cart}}');
        $this->dropTable('{{%branch_bank}}');
        $this->dropTable('{{%bank}}');
        $this->dropTable('{{%balance_log}}');
        $this->dropTable('{{%attr}}');
        $this->dropTable('{{%plugin_area_agent}}');
        $this->dropTable('{{%api}}');
        $this->dropTable('{{%admin_role}}');
        $this->dropTable('{{%admin_mall}}');
        $this->dropTable('{{%admin_action_log}}');
        $this->dropTable('{{%admin}}');
        $this->dropTable('{{%address}}');
        $this->dropTable('{{%access_token}}');
    }
}
