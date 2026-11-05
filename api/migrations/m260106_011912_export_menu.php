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
 * Auto-generated Migration via Console Command
 */
class m260106_011912_export_menu extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // 2. 插入数据: jxmall_menu
        // 注意：parent_id 已经同步修改为对应的英文 menu_id
        $this->batchInsert('{{%menu}}', ['id', 'menu_id', 'menu_name', 'icon', 'menu_type', 'order_num', 'parent_id', 'is_cache', 'hidden', 'is_frame', 'status', 'path', 'component', 'query', 'perms', 'is_delete', 'created_at', 'updated_at', 'deleted_at', 'created_time', 'updated_time', 'deleted_time', 'link', 'plugin', 'active_menu', 'breadcrumb', 'name', 'show_tag'], [
            // --- 系统管理 (System) ---
            [1, 'system', '系统管理', 'el-icon-s-tools', 'M', 100, '0', 1, 0, 1, 0, 'system', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, '', 0],
            [2, 'system-user', '员工管理', 'el-icon-user-solid', 'C', 1, 'system', 1, 0, 1, 0, 'user', 'system/user/index', '', 'system:user:list', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, 'system-user-index', 1],
            [3, 'system-role', '角色管理', 'el-icon-user', 'C', 2, 'system', 1, 0, 1, 0, 'role', 'system/role/index', '', 'system:role:list', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, 'system-role-index', 1],
            [4, 'system-menu', '菜单管理', 'el-icon-menu', 'C', 3, 'system', 1, 0, 1, 0, 'menu', 'system/menu/index', '', 'system:menu:list', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, 'system-menu-index', 1],

            // --- 商城管理 (Mall) ---
            [5, 'mall', '商城管理', 'el-icon-s-shop', 'M', 0, '0', 1, 0, 1, 0, 'mall', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, '', 1],
            [6, 'mall-setting', '商城设置', 'el-icon-setting', 'C', 0, 'mall', 1, 0, 1, 0, 'index', 'mall/index', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-index', 1],
            [7, 'mall-diy', 'DIY页面', 'el-icon-magic-stick', 'C', 1, 'mall', 1, 0, 1, 0, 'diy', 'mall/diy', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-diy', 1],
            [8, 'mall-diy-edit', '页面编辑', '', 'C', 0, 'mall', 1, 1, 1, 0, 'diy-edit', 'mall/diy-edit', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/mall/diy', 1, 'mall-diy-edit', 0],
            [9, 'mall-checking', '版本审核', 'el-icon-cpu', 'C', 15, 'mall', 1, 0, 1, 0, 'checking', 'mall/checking', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, 'mall-checking', 1],
            [10, 'plugins', '应用管理', 'el-icon-s-grid', 'M', 50, '0', 1, 0, 1, 0, 'plugins', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, '', 1],
            [13, 'mall-alert-adv', '弹窗广告', 'el-icon-message-solid', 'C', 5, 'mall', 1, 0, 1, 0, 'alert-adv', 'mall/alert-adv', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-alert-adv', 1],
            [14, 'mall-user-center', '用户中心', 'el-icon-s-custom', 'C', 6, 'mall', 1, 0, 1, 0, 'user', 'mall/user', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-user', 1],
            [15, 'mall-manage-center', '管理中心', 'el-icon-office-building', 'C', 7, 'mall', 1, 0, 1, 0, 'manage', 'mall/manage', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-manage', 1],
            [16, 'mall-page', '商城页面', 'el-icon-document-copy', 'C', 8, 'mall', 1, 0, 1, 0, 'page', 'mall/page', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-page', 1],
            [17, 'mall-freight', '运费模板', 'el-icon-truck', 'C', 9, 'mall', 1, 0, 1, 0, 'freight', 'mall/freight', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-freight', 1],
            [18, 'mall-freight-edit', '运费模板编辑', 'el-icon-truck', 'C', 9, 'mall', 1, 1, 1, 0, 'freight-edit', 'mall/freight-edit', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-freight-edit', 0],
            [19, 'mall-eorder', '电子面单', 'el-icon-printer', 'C', 10, 'mall', 1, 0, 1, 0, 'eorder', 'mall/eorder', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-eorder', 1],
            [20, 'mall-eorder-edit', '面单编辑', 'el-icon-printer', 'C', 11, 'mall', 1, 1, 1, 0, 'eorder-edit', 'mall/eorder-edit', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-eorder-edit', 0],
            [21, 'mall-upload', '上传设置', 'el-icon-upload', 'C', 15, 'mall', 1, 0, 1, 0, 'upload', 'mall/upload', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-upload', 1],
            [22, 'mall-feedback', '意见反馈', 'el-icon-chat-line-square', 'C', 16, 'mall', 1, 0, 1, 0, 'feedback', 'mall/feedback', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'mall-feedback', 1],

            // --- 海报管理 (Poster) ---
            [23, 'poster', '海报管理', 'el-icon-picture', 'M', 2, '0', 1, 0, 1, 0, 'poster', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, '', 1],
            [24, 'poster-inviter', '邀请海报', 'el-icon-postcard', 'C', 0, 'poster', 1, 0, 1, 0, 'inviter', 'poster/inviter', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'poster-inviter', 1],
            [25, 'poster-goods', '商品海报', 'el-icon-collection', 'C', 1, 'poster', 1, 0, 1, 0, 'goods', 'poster/goods', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'poster-goods', 1],

            // --- 商品管理 (Goods) ---
            [26, 'goods', '商品管理', 'el-icon-goods', 'M', 4, '0', 1, 0, 1, 0, 'goods', NULL, NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, NULL, 1],
            [27, 'goods-index', '商品列表', 'el-icon-s-goods', 'C', 0, 'goods', 1, 0, 1, 0, 'index', 'goods/index', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'goods-index', 1],
            [28, 'goods-edit', '商品编辑', 'el-icon-edit-outline', 'C', 0, 'goods', 1, 1, 1, 0, 'edit', 'goods/edit', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/goods/index', 0, 'goods-edit', 0],
            [29, 'goods-cat', '商品分类', 'el-icon-s-unfold', 'C', 2, 'goods', 1, 0, 1, 0, 'cat', 'goods/cat', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'goods-cat', 1],
            [30, 'goods-cat-edit', '分类编辑', NULL, 'C', 0, 'goods', 1, 1, 1, 0, 'cat-edit', 'goods/cat-edit', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/goods/cat', 0, 'goods-cat-edit', 0],

            // --- 订单管理 (Order) ---
            [31, 'order', '订单管理', 'el-icon-s-order', 'M', 5, '0', 1, 0, 1, 0, 'order', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, '', 1],
            [32, 'order-index', '订单列表', 'el-icon-document', 'C', 0, 'order', 1, 0, 1, 0, 'index', 'order/index', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'order-index', 1],
            [33, 'order-detail', '订单详情', NULL, 'C', 1, 'order', 1, 1, 1, 0, 'detail', 'order/detail', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/order/index', 1, 'order-detail', 0],
            [34, 'order-refund', '售后订单', 'el-icon-refresh-left', 'C', 2, 'order', 1, 0, 1, 0, 'refund', 'order/refund', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'order-refund', 1],
            [35, 'order-express-log', '发货记录', 'el-icon-truck', 'C', 5, 'order', 1, 0, 1, 0, 'express-log', 'order/express-log', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'order-express-log', 1],
            [36, 'order-import-express', '导入发货', 'el-icon-download', 'C', 7, 'order', 1, 0, 1, 0, 'import-express', 'order/import-express', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'order-import-express', 1],

            // --- 用户管理 (User) ---
            [37, 'user', '用户管理', 'el-icon-user-solid', 'M', 7, '0', 1, 0, 1, 0, 'user', NULL, NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, NULL, 1],
            [38, 'user-list', '用户列表', 'el-icon-s-custom', 'C', 0, 'user', 1, 0, 1, 0, 'index', 'user/index', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'user-index', 1],
            [39, 'user-edit', '用户编辑', 'el-icon-edit', 'C', 0, 'user', 1, 1, 1, 0, 'edit', 'user/edit', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/user/index', 0, 'user-edit', 0],
            [40, 'user-level', '用户等级', 'el-icon-medal', 'C', 2, 'user', 1, 0, 1, 0, 'level', 'user/level', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'user-level', 1],
            [41, 'user-level-edit', '等级编辑', 'el-icon-medal-1', 'C', 6, 'user', 1, 1, 1, 0, 'level-edit', 'user/level-edit', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/user/level', 0, 'user-level-edit', 0],
            [42, 'user-setting', '推广设置', 'el-icon-share', 'C', 10, 'user', 1, 0, 1, 0, 'setting', 'user/setting', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'user-setting', 1],

            // --- 营销管理 (Market) ---
            [43, 'market', '营销管理', 'el-icon-s-marketing', 'M', 8, '0', 1, 0, 1, 0, 'market', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, '', 1],
            [44, 'market-coupon', '优惠券', 'el-icon-ticket', 'C', 0, 'market', 1, 0, 1, 0, 'coupon', 'market/coupon', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'market-coupon', 1],

            // --- 财务管理 (Finance) ---
            [45, 'finance', '财务管理', 'el-icon-wallet', 'M', 9, '0', 1, 0, 1, 0, 'finance', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, '', 1],
            [46, 'finance-cash', '提现记录', 'el-icon-money', 'C', 0, 'finance', 1, 0, 1, 0, 'cash', 'finance/cash', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-cash', 1],
            [47, 'finance-balance', '余额记录', 'el-icon-coin', 'C', 2, 'finance', 1, 0, 1, 0, 'balance-log', 'finance/balance-log', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-balance-log', 1],
            [48, 'finance-integral', '积分记录', 'el-icon-present', 'C', 3, 'finance', 1, 0, 1, 0, 'integral-log', 'finance/integral-log', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-integral-log', 1],
            [49, 'finance-price', '佣金记录', 'el-icon-s-data', 'C', 4, 'finance', 1, 0, 1, 0, 'price-log', 'finance/price-log', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-price-log', 1],
            [50, 'finance-cash-setting', '提现设置', 'el-icon-setting', 'C', 6, 'finance', 1, 0, 1, 0, 'cash-setting', 'finance/cash-setting', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-cash-setting', 1],
            [51, 'finance-charge', '充值记录', 'el-icon-bank-card', 'C', 10, 'finance', 1, 0, 1, 0, 'charge', 'finance/charge', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'finance-charge', 1],

            // --- 平台管理 (Platform) ---
            [52, 'platform', '平台管理', 'el-icon-monitor', 'M', 11, '0', 1, 0, 1, 0, 'platform', NULL, NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, NULL, 1],
            [53, 'platform-wechat', '公众号', 'el-icon-chat-dot-round', 'C', 5, 'platform', 1, 0, 1, 0, 'wechat', 'platform/wechat', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'platform-wechat', 1],
            [54, 'platform-mpwx', '小程序', 'el-icon-mobile', 'C', 1, 'platform', 1, 0, 1, 0, 'mpwx', 'platform/mpwx', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'platform-mpwx', 1],
            [55, 'platform-wxapp', '开放平台', 'el-icon-connection', 'C', 10, 'platform', 1, 0, 1, 0, 'wxapp', 'platform/wxapp', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'platform-wxapp', 1],

            // --- 更多营销 ---
            [56, 'market-coupon-edit', '优惠券编辑', NULL, 'C', 10, 'market', 1, 1, 1, 0, 'coupon-edit', 'market/coupon-edit', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/market/coupon', 0, 'market-coupon-edit', 0],
            [57, 'platform-menu', '公众号菜单', 'el-icon-menu', 'C', 10, 'platform', 1, 1, 1, 0, 'menu', 'platform/menu', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 0, 'platform-menu', 0],

            // --- 插件 (Plugins) ---
            [58, 'plugins-list', '应用列表', 'el-icon-menu', 'C', 0, 'plugins', 1, 0, 1, 0, 'index', 'plugins/index', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '', 1, 'plugins-index', 1],

            // --- 杂项与新功能 (Misc) ---
            [59, 'mall-tab-goods', '标签商品', 'el-icon-collection-tag', 'C', 4, 'mall', 1, 0, 0, 0, 'tab-goods', 'mall/tab-goods', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/mall/tab-goods', 1, 'mall-tab-goods', 1],
            [60, 'market-coupon-send', '手动发券', 'el-icon-s-promotion', 'C', 3, 'market', 1, 1, 0, 0, 'coupon-send', 'market/coupon-send', '', '', 0, 0, 0, 0, '2026-01-06 09:18:01', NULL, NULL, '', 'mall', '/market/coupon', 1, 'market-coupon-send', 1],
            [61, 'mall-outside-link', '外部链接', 'el-icon-link', 'C', 6, 'mall', 1, 0, 0, 0, 'outside-link', 'mall/outside-link', '', '', 0, 0, 0, 0, '2026-01-06 09:18:02', NULL, NULL, '', 'mall', '/mall/outside-link', 1, 'mall-outside-link', 1],
            [62, 'mall-fei-printer', '飞鹅打印机', 'el-icon-printer', 'C', 21, 'mall', 1, 0, 0, 0, 'fei-printer', 'mall/fei-printer', '', '', 0, 0, 0, 0, '2026-01-06 09:18:03', NULL, NULL, '', 'mall', '/mall/fei-printer', 1, 'mall-fei-printer', 1],
            [63, 'goods-comment', '评价管理', 'el-icon-chat-line-round', 'C', 8, 'goods', 1, 0, 0, 0, 'goods-comment', 'goods/goods-comment', '', '', 0, 0, 0, 0, '2026-01-06 09:18:04', NULL, NULL, '', 'mall', '', 1, 'goods-goods-comment', 1],
            [64, 'mall-file-manager', '文件管理', 'el-icon-folder-opened', 'C', 100, 'mall', 1, 0, 0, 0, 'file-manager', 'mall/file-manager', '', '', 0, 0, 0, 0, '2026-01-06 09:18:06', NULL, NULL, '', 'mall', '/mall/file-manager', 1, 'mall-file-manager', 1],
            [65, 'mall-noun', '名词配置', 'el-icon-collection', 'C', 50, 'mall', 1, 0, 0, 0, 'noun', 'mall/noun', '', '', 0, 0, 0, 0, '2026-01-06 09:18:06', NULL, NULL, '', 'mall', '/mall/noun', 1, 'mall-noun', 1],
            [66, 'order-push-log', '发货推送', 'el-icon-d-arrow-right', 'C', 10, 'order', 1, 0, 0, 0, 'push-log', 'order/push-log', '', '', 0, 0, 0, 0, '2026-01-06 09:18:06', NULL, NULL, '', 'mall', '', 1, 'order-push-log', 1],
            [67, 'finance-commission-log', '分润记录', 'el-icon-s-data', 'C', 5, 'finance', 1, 0, 0, 0, 'commission-log', 'finance/commission-log', '', '', 0, 0, 0, 0, '2026-01-06 09:18:07', NULL, NULL, '', 'mall', '/finance/commission-log', 1, 'finance-commission-log', 1],
            [68, 'finance-refund-fail', '退款失败', 'el-icon-circle-close', 'C', 10, 'finance', 1, 0, 0, 0, 'refund-fail', 'finance/refund-fail', '', '', 0, 0, 0, 0, '2026-01-06 09:18:07', NULL, NULL, '', 'mall', '/finance/refund-fail', 1, 'finance-refund-fail', 1],
            [71, 'mall-random-minus-setting', '随机优惠', 'el-icon-present', 'C', 2, 'mall', 1, 0, 1, 0, 'random-minus-setting', 'mall/random-minus-setting', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:08', NULL, NULL, '', 'mall', '/mall/random-minus-setting', 1, 'finance-random-minus-setting', 1],

            // --- 支付管理 (Payment) ---

            // --- 分账管理 (Split) ---

            // --- 资金代发/管理 (Fund) ---

            // --- 日志管理 (Logs) ---
            [96, 'logs', '日志管理', 'el-icon-document-checked', 'M', 51, '0', 1, 0, 1, 0, 'logs', '', '', '', 0, 0, 0, 0, '2026-01-06 09:18:09', NULL, NULL, '', 'mall', '', 1, '日志管理', 1],
            [97, 'logs-user', '用户日志', 'el-icon-user', 'C', 1, 'logs', 1, 0, 1, 0, 'user', 'logs/user', '', '', 0, 0, 0, 0, '2026-01-06 09:18:09', NULL, NULL, '', 'mall', '', 1, 'logs-user', 1],
            [98, 'logs-admin', '操作日志', 'el-icon-setting', 'C', 2, 'logs', 1, 0, 1, 0, 'admin', 'logs/admin', '', '', 0, 0, 0, 0, '2026-01-06 09:18:09', NULL, NULL, '', 'mall', '', 1, 'logs-admin', 1],

            // --- 剩余资金/杂项 ---
            [100, 'finance-split-log', '分账记录', 'el-icon-pie-chart', 'C', 6, 'finance', 1, 0, 0, 0, 'split-log', 'finance/split-log', '', '', 0, 0, 0, 0, '2026-01-06 09:18:17', NULL, NULL, '', 'mall', '/finance/split-log', 1, 'finance-split-log', 1],
            [101, 'user-map', '用户关系', 'el-icon-share', 'C', 6, 'user', 1, 0, 1, 0, 'map', 'user/map', NULL, NULL, 0, 0, 0, 0, '2026-01-06 09:18:17', NULL, NULL, '', 'mall', '', 1, 'user-map', 1],
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropTable('{{%menu}}');
    }
}
