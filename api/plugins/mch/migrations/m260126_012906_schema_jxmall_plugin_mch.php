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
class m260126_012906_schema_jxmall_plugin_mch extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 创建表: jxmall_plugin_mch
        $this->createTable('{{%plugin_mch}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->unsigned()->notNull(),
            'user_id' => $this->integer()->unsigned()->notNull()->comment('店长的用户ID'),
            'mch_name' => $this->string(255)->notNull()->defaultValue('')->comment('商户/门店名称'),
            'is_hide' => $this->tinyInteger()->null()->defaultValue(0)->comment('是否隐藏'),
            'cat_id' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('门店分类ID'),
            'type' => $this->tinyInteger()->notNull()->defaultValue(2)->comment('商家类型: 1=供应商, 2=门店'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('状态：0=待审核, 1=已通过, 2=已驳回, 3=已禁用'),
            'balance' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('商家货款余额'),
            'total_balance' => $this->decimal(12, 2)->notNull()->defaultValue('0.00')->comment('累计余额'),
            'logo' => $this->string(255)->notNull()->defaultValue('')->comment('门店Logo'),
            'tel' => $this->string(45)->notNull()->defaultValue('')->comment('联系电话 (座机)'),
            'mobile' => $this->string(45)->notNull()->defaultValue('')->comment('联系手机号'),
            'address' => $this->string(255)->notNull()->defaultValue('')->comment('详细地址'),
            'refund_name' => $this->string(255)->null()->comment('售后退货联系人'),
            'refund_mobile' => $this->string(32)->null()->comment('售后退货联系电话'),
            'refund_address' => $this->string(512)->null()->comment('售后退货详细地址'),
            'location_address' => $this->string(128)->null()->comment('地理位置'),
            'province_code' => $this->integer()->null()->comment('省代码'),
            'city_code' => $this->integer()->null()->comment('市代码'),
            'county_code' => $this->integer()->null()->comment('区代码'),
            'town_code' => $this->integer()->null()->comment('镇代码'),
            'latitude' => $this->decimal(10, 6)->null()->comment('纬度'),
            'longitude' => $this->decimal(10, 6)->null()->comment('经度'),
            'opening_hours' => $this->string(255)->notNull()->defaultValue('')->comment('营业时间'),
            'banner_images' => $this->text()->null()->comment('Banner图 (JSON数组)'),
            'description' => $this->text()->null()->comment('门店介绍'),
            'remarks' => $this->string(255)->null()->defaultValue('')->comment('备注 (可用于存储驳回理由)'),
            'business_license' => $this->string(255)->notNull()->defaultValue('')->comment('营业执照图片'),
            'legal_person_name' => $this->string(45)->notNull()->defaultValue('')->comment('法人姓名'),
            'id_card_front' => $this->string(255)->notNull()->defaultValue('')->comment('法人身份证正面'),
            'id_card_back' => $this->string(255)->notNull()->defaultValue('')->comment('法人身份证背面'),

            'delivery_options' => $this->string(255)->null()->comment('配送方式 (JSON数组)'),
            'pickup_discount_rate' => $this->decimal(5, 2)->notNull()->defaultValue('0.00')->comment('自提优惠折扣(%)'),
            'pickup_discount_max_amount' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('自提优惠最大金额'),
            'city_delivery_distance' => $this->decimal(10, 2)->null()->comment('同城配送最大距离(km)'),
            'rating' => $this->decimal(3, 2)->notNull()->defaultValue('5.00')->comment('商家综合评分(5分制)'),
            'city_delivery_config' => $this->text()->null()->comment('同城配送配置 (JSON格式)'),
            'is_service_provider' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否为服务商: 0=否, 1=是'),
            'service_provider_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('服务商审核状态: 0=未申请, 1=待审核, 2=已通过, 3=已驳回'),
            'delivery_fee_share_ratio' => $this->decimal(5, 2)->notNull()->defaultValue('60.00')->comment('配送员分成比例(%)'),
            'service_provider_id' => $this->integer()->unsigned()->notNull()->defaultValue(0)->comment('绑定的服务商ID'),
            'recruitment_partner_id' => $this->integer()->null()->defaultValue(0)->comment('归属的招商合伙人用户ID'),
            'recruitment_center_id' => $this->integer()->null()->defaultValue(0)->comment('归属的招商中心用户ID'),
            'is_first_order_discount_enabled' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否开启首单优惠: 1=是, 0=否'),
            'first_order_discount_amount' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('首单优惠金额'),
            'first_order_discount_total_stock' => $this->integer()->notNull()->defaultValue(0)->comment('首单优惠总桶数(名额)'),
            'first_order_discount_start_time' => $this->dateTime()->null()->comment('首单优惠开始时间'),
            'first_order_discount_end_time' => $this->dateTime()->null()->comment('首单优惠结束时间'),
            'good_rating_rate' => $this->decimal(5, 2)->notNull()->defaultValue('100.00')->comment('好评率(%)'),
            'rating_count' => $this->integer()->notNull()->defaultValue(0)->comment('被计入评分的总评价数'),
            'is_commission_open' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否开启分销: 0=关闭, 1=开启'),
            'cashier_status' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('收款码进件状态: 0=未申请, 10=审核中, 20=开通成功, 30=已驳回'),
            'cashier_remark' => $this->string(255)->null()->defaultValue('')->comment('进件审核备注/驳回理由'),
            'other_certs' => $this->text()->null()->comment('其他证件图片JSON')
        ]);

        $this->createIndex('idx-mch-is_service_provider', '{{%plugin_mch}}', ['is_service_provider'], false);
        $this->createIndex('idx-mch-service_provider_id', '{{%plugin_mch}}', ['service_provider_id'], false);
        $this->createIndex('idx-mch-service_provider_status', '{{%plugin_mch}}', ['service_provider_status'], false);
        $this->createIndex('idx-plugin_mch-cat_id', '{{%plugin_mch}}', ['cat_id'], false);
        $this->createIndex('idx-plugin_mch-type', '{{%plugin_mch}}', ['type'], false);
        $this->createIndex('idx_cashier_status', '{{%plugin_mch}}', ['cashier_status'], false);
        $this->createIndex('idx_mch_is_commission_open', '{{%plugin_mch}}', ['is_commission_open'], false);
        $this->createIndex('idx_mch_recruitment_center', '{{%plugin_mch}}', ['recruitment_center_id'], false);
        $this->createIndex('idx_mch_recruitment_partner', '{{%plugin_mch}}', ['recruitment_partner_id'], false);
        $this->createIndex('uidx-plugin_mch-user_id', '{{%plugin_mch}}', ['user_id'], false);

        // 创建表: jxmall_plugin_mch_cat
        $this->createTable('{{%plugin_mch_cat}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('mall_id'),
            'cover_pic' => $this->string(512)->notNull()->defaultValue('')->comment('图标'),
            'cat_name' => $this->string(45)->notNull()->defaultValue('')->comment('分类名称'),
            'is_show' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否显示 1=显示 0=隐藏'),
            'order_num' => $this->integer()->null()->defaultValue(0)->comment('排序')
        ]);


        // 创建表: jxmall_plugin_mch_clerk
        $this->createTable('{{%plugin_mch_clerk}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull()->comment('商家ID'),
            'user_id' => $this->integer()->notNull()->comment('用户ID'),
            'is_manager' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否为管理员(店长): 1=是, 0=否'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('状态: 1=启用, 0=禁用')
        ]);

        $this->createIndex('idx_mch_id', '{{%plugin_mch_clerk}}', ['mch_id'], false);
        $this->createIndex('idx_mch_user_unique', '{{%plugin_mch_clerk}}', ['mch_id', 'user_id'], true);
        $this->createIndex('idx_user_id', '{{%plugin_mch_clerk}}', ['user_id'], false);

        // 创建表: jxmall_plugin_mch_favorite
        $this->createTable('{{%plugin_mch_favorite}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->notNull()->comment('收藏的用户ID'),
            'mch_id' => $this->integer()->notNull()->comment('被收藏的商户ID')
        ]);

        $this->createIndex('idx-plugin_mch_favorite-mch_id', '{{%plugin_mch_favorite}}', ['mch_id'], false);
        $this->createIndex('uk-plugin_mch_favorite-user_id-mch_id', '{{%plugin_mch_favorite}}', ['user_id', 'mch_id'], true);

        // [MIGRATED] group_buy tables moved to Group Plugin migrations

        // 创建表: jxmall_plugin_mch_hotel
        $this->createTable('{{%plugin_mch_hotel}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull()->comment('关联主商户ID plugin_mch.id'),
            'star_rating' => $this->tinyInteger()->null()->defaultValue(0)->comment('星级: 0=无星, 1-5=星级'),
            'open_year' => $this->string(10)->null()->defaultValue('')->comment('开业年份，如 2023'),
            'check_in_time' => $this->string(10)->null()->defaultValue('14:00')->comment('最早入住时间'),
            'check_out_time' => $this->string(10)->null()->defaultValue('12:00')->comment('最晚离店时间'),
            'service_tags' => $this->text()->null()->comment('设施服务标签JSON: ["免费Wifi","免费停车","游泳池"]'),
            'policy' => $this->text()->null()->comment('酒店政策JSON: {"pet":0, "cancel":"不可取消"}'),
            'notice' => $this->string(500)->null()->defaultValue('')->comment('订房必读/公告'),
            'settlement_model' => $this->tinyInteger()->null()->defaultValue(0)->comment('结算模式:0=抽佣,1=底价'),
            'special_industry_license' => $this->string(255)->null()->defaultValue('')->comment('特种行业许可证'),
            'fire_safety_certificate' => $this->string(255)->null()->defaultValue('')->comment('消防安全合格证'),
            'policy_image' => $this->string(255)->null()->defaultValue('')->comment('酒店政策图片'),
            'cancel_limit_time' => $this->string(16)->null()->defaultValue('18:00')->comment('最晚取消时间(如18:00)')
        ]);

        $this->createIndex('idx_mch_id', '{{%plugin_mch_hotel}}', ['mch_id'], true);

        // 创建表: jxmall_plugin_mch_hotel_daily_price
        $this->createTable('{{%plugin_mch_hotel_daily_price}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'goods_id' => $this->integer()->notNull()->comment('关联商品ID'),
            'date' => $this->date()->notNull()->comment('日期 YYYY-MM-DD'),
            'price' => $this->decimal(10, 2)->notNull()->defaultValue('0.00')->comment('当日售价'),
            'stock' => $this->integer()->notNull()->defaultValue(0)->comment('当日总库存'),
            'sales' => $this->integer()->notNull()->defaultValue(0)->comment('当日已售/占用量'),
            'status' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('当日开关房状态: 1开, 0关'),
            'cost_price' => $this->decimal(10, 2)->null()->defaultValue('0.00')->comment('结算底价/成本价')
        ]);

        $this->createIndex('uk_goods_date', '{{%plugin_mch_hotel_daily_price}}', ['goods_id', 'date'], true);

        // 创建表: jxmall_plugin_mch_hotel_order
        $this->createTable('{{%plugin_mch_hotel_order}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull()->comment('商城ID'),
            'mch_id' => $this->integer()->notNull()->defaultValue(0)->comment('商户ID'),
            'order_id' => $this->integer()->notNull()->comment('关联主订单ID'),
            'order_detail_id' => $this->integer()->notNull()->comment('关联订单详情ID (核心外键)'),
            'goods_id' => $this->integer()->notNull()->comment('商品ID/房型ID'),
            'booking_start_date' => $this->date()->notNull()->comment('入住日期'),
            'booking_end_date' => $this->date()->notNull()->comment('离店日期'),
            'nights' => $this->integer()->null()->defaultValue(1)->comment('入住晚数'),
            'guest_name' => $this->string(255)->null()->defaultValue('')->comment('入住人姓名'),
            'guest_mobile' => $this->string(50)->null()->defaultValue('')->comment('入住人电话'),
            'arrival_time' => $this->string(20)->null()->defaultValue('')->comment('预计到店时间'),
            'latest_cancel_time' => $this->integer()->null()->defaultValue(0)->comment('最晚取消截止时间戳')
        ]);

        $this->createIndex('idx_booking_date', '{{%plugin_mch_hotel_order}}', ['booking_start_date', 'booking_end_date'], false);
        $this->createIndex('idx_mch_id', '{{%plugin_mch_hotel_order}}', ['mch_id'], false);
        $this->createIndex('idx_order_detail_id', '{{%plugin_mch_hotel_order}}', ['order_detail_id'], true);
        $this->createIndex('idx_order_id', '{{%plugin_mch_hotel_order}}', ['order_id'], false);

        // 创建表: jxmall_plugin_mch_hotel_room
        $this->createTable('{{%plugin_mch_hotel_room}}', [
            'id' => $this->primaryKey(),
            'mall_id' => $this->integer()->notNull(),
            'mch_id' => $this->integer()->notNull()->defaultValue(0),
            'goods_id' => $this->integer()->notNull()->comment('关联主商品表ID'),
            'bed_type' => $this->string(64)->notNull()->defaultValue('')->comment('床型：大床/双床/圆床'),
            'breakfast' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('早餐：0无, 1单早, 2双早'),
            'window' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否有窗：0无, 1有'),
            'people' => $this->integer()->notNull()->defaultValue(2)->comment('宜住人数'),
            'area' => $this->integer()->notNull()->defaultValue(0)->comment('房间面积(平米)'),
            'policy' => $this->text()->null()->comment('入住政策/退改规则(JSON)'),
            'is_member_price' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否支持会员折扣:0=否,1=是'),
            'is_coupon' => $this->tinyInteger()->notNull()->defaultValue(1)->comment('是否支持优惠券:0=否,1=是'),
            'is_integral' => $this->tinyInteger()->notNull()->defaultValue(0)->comment('是否支持积分抵扣:0=否,1=是'),
            'max_deduct_integral' => $this->integer()->notNull()->defaultValue(0)->comment('最大使用积分数'),
            'member_price_rules' => $this->text()->null()->comment('会员折扣规则JSON')
        ]);

        $this->createIndex('idx_goods_id', '{{%plugin_mch_hotel_room}}', ['goods_id'], false);
        $this->createIndex('idx_mch_id', '{{%plugin_mch_hotel_room}}', ['mch_id'], false);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%plugin_mch_hotel_room}}');
        $this->dropTable('{{%plugin_mch_hotel_order}}');
        $this->dropTable('{{%plugin_mch_hotel_daily_price}}');
        $this->dropTable('{{%plugin_mch_hotel}}');
        // group_buy related drops shifted to group plugin
        $this->dropTable('{{%plugin_mch_favorite}}');
        $this->dropTable('{{%plugin_mch_clerk}}');
        $this->dropTable('{{%plugin_mch_cat}}');
        $this->dropTable('{{%plugin_mch}}');
    }
}
