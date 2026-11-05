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

namespace app\plugins\mch\forms\admin\mch;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch; // 引入 Mch 模型以使用常量
use app\plugins\mch\models\MchClerk;
use Yii;

class MchActionsForm extends BaseModel
{
    /**
     * 系统全部默认的源生业务功能库
     */
    /**
     * 系统全部默认的源生业务功能库
     *
     * 角色过滤字段 `types`：限定该 action 仅对哪些 mch.type 可见
     *   1=线上商家, 2=线下门店, 3=酒店/民宿(已废弃), 4=个人租户
     *   未配置 / 空数组 → 全主体可见
     *
     * @向后兼容 老配置项仍支持 storeOnly / supplierOnly / managerOnly。
     *   见 getActions() 的过滤逻辑。
     */
    /**
     * 各插件注入的工作台入口(Plugin::getMchWorkbenchActions)。
     *
     * 走挂载而不是把插件名写进上面那张默认表 —— 底座不认识业务插件,
     * 插件没装/没授权时它的入口自然就不出现。
     * 单个插件抛异常不能拖垮整个工作台,所以逐个 try。
     */
    public static function getPluginActions(): array
    {
        $out = [];
        try {
            foreach (\Yii::$app->plugin->getAllPlugins() as $plugin) {
                if (!is_object($plugin) || !method_exists($plugin, 'getMchWorkbenchActions')) {
                    continue;
                }
                try {
                    foreach ((array)$plugin->getMchWorkbenchActions() as $act) {
                        if (!empty($act['name']) && !empty($act['url'])) {
                            $out[] = $act;
                        }
                    }
                } catch (\Throwable $e) {
                    \Yii::warning('插件工作台入口读取失败: ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            \Yii::warning('聚合插件工作台入口失败: ' . $e->getMessage());
        }
        return $out;
    }

    public static function getDefaultAllActions()
    {
        return [
            // --- 全主体通用功能 ---
            ['name' => '商品管理', 'icon' => 'shopping-cart', 'color' => '#2979FF', 'bgColor' => '#ECF3FF', 'url' => '/plugins1/mch/admin/goods-list', 'types' => [1, 2]],
            ['name' => '商品分类', 'icon' => 'category', 'color' => '#00B4A6', 'bgColor' => '#E6F9F7', 'url' => '/plugins1/mch/admin/cat-list', 'types' => [1, 2]],
            ['name' => '会员管理', 'icon' => 'man-add', 'color' => '#9044FF', 'bgColor' => '#F2ECFF', 'url' => '/plugins1/mch/member-list', 'types' => [1, 2]],
            ['name' => '优惠券管理', 'icon' => 'coupon', 'color' => '#FF5A5A', 'bgColor' => '#FFEBEB', 'url' => '/plugins1/mch/coupon-list', 'managerOnly' => true, 'types' => [1, 2]],
            ['name' => '运费配置', 'icon' => 'car', 'color' => '#FFC600', 'bgColor' => '#FFF9E6', 'url' => '/plugins1/mch/admin/freight', 'types' => [1, 2]],
            ['name' => '店员管理', 'icon' => 'man-add-fill', 'color' => '#007AFF', 'bgColor' => '#EAF5FF', 'url' => '/plugins1/mch/clerk-list', 'managerOnly' => true, 'types' => [1, 2]],
            ['name' => '配送设置', 'icon' => 'setting', 'color' => '#18B566', 'bgColor' => '#E7FCF7', 'url' => '/plugins1/mch/delivery-setting', 'managerOnly' => true, 'types' => [1, 2]],
            ['name' => '货款明细', 'icon' => 'rmb-circle', 'color' => '#4CAF50', 'bgColor' => '#E8F5E9', 'url' => '/plugins1/mch/funds-center', 'managerOnly' => true, 'types' => [1, 2, 4]],
            ['name' => '资料', 'icon' => 'account', 'color' => '#909399', 'bgColor' => '#F4F4F5', 'url' => '/plugins1/mch/admin/info-edit', 'managerOnly' => true, 'types' => [1, 2, 4]],

            // --- 仅线下门店 (type=2) ---
            ['name' => '订单管理', 'icon' => 'file-text', 'color' => '#FFA500', 'bgColor' => '#FEF5E5', 'url' => '/plugins1/mch/orders', 'types' => [2]],
            ['name' => '订单核销', 'icon' => 'fingerprint', 'color' => '#3c9cff', 'bgColor' => '#e8f4ff', 'url' => '/plugins1/mch/admin/verify', 'types' => [2]],
            ['name' => '核销记录', 'icon' => 'list', 'color' => '#888BF5', 'bgColor' => '#F2F2FF', 'url' => '/plugins1/mch/verify-records', 'managerOnly' => true, 'types' => [2]],

            // --- 仅线上商家 (type=1) ---
            ['name' => '订单管理', 'icon' => 'file-text', 'color' => '#FFA500', 'bgColor' => '#FEF5E5', 'url' => '/plugins1/mch/orders-online', 'types' => [1]],

            // --- 个人租户 (type=4) 默认入口 ---
            ['name' => '我的发布', 'icon' => 'flag', 'color' => '#722ED1', 'bgColor' => '#F5EEFF', 'url' => '/plugins1/mch/admin/goods-list', 'types' => [4]],
            ['name' => '我的订单', 'icon' => 'order', 'color' => '#FFA500', 'bgColor' => '#FEF5E5', 'url' => '/plugins1/mch/orders', 'types' => [4]],
        ];
    }

    /**
     * 根据当前登录用户的角色和商家类型，获取其在门店中心的操作列表
     * @return array
     */
    public function getActions()
    {
        $mch = Yii::$app->mch;
        $user = Yii::$app->user->identity;
        if (!$mch || !$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '无法获取店铺或用户信息');
        }

        // 这里修复一个致命漏洞：店铺的创建持有人(user_id)拥有绝对的超级店长权限，他往往不需要作为店员存在于MchClerk表中
        $isManager = false;
        if ($mch->user_id == $user->id) {
            $isManager = true;
        } else {
            $clerk = MchClerk::findOne(['mch_id' => $mch->id, 'user_id' => $user->id, 'is_delete' => 0]);
            $isManager = $clerk ? (bool)$clerk->is_manager : false;
        }
        // 商圈租户判定：plaza 卸载或未装时跳过
        // 用 class_exists 守卫 + FQN，避免 plaza 真被卸载时整个商户中心入口列表崩
        // （之前只检查了 jxmall_plugin 表记录但没检查类是否真实存在，假阳性会 fatal）
        $isPlazaTenant = false;
        $plazaStoreCls = '\app\plugins\plaza\models\PlazaStore';
        $plazaLeaseCls = '\app\plugins\plaza\models\PlazaLease';
        if (\app\models\Plugin::find()->where(['name' => 'plaza', 'is_delete' => 0])->exists()
            && class_exists($plazaStoreCls)
            && class_exists($plazaLeaseCls)) {
            $storeExists = $plazaStoreCls::find()->where(['mch_id' => $mch->id, 'is_delete' => 0])->exists();
            // 合同挂在【承租方】上,不再有 mch_id 列。这个商户有没有在租,要先解析出它的承租方 ——
            // 走 plaza 自己的 TenantResolver(商户 → 店主 user_id → 租户),别在这里手写一遍链路。
            $leaseExists = false;
            $resolverCls = '\app\plugins\plaza\services\TenantResolver';
            if (class_exists($resolverCls)) {
                $tn = $resolverCls::byMch((int) $mch->id, (int) $mch->mall_id);
                $leaseExists = $tn && $plazaLeaseCls::find()
                    ->where(['tenant_id' => $tn->id, 'mall_id' => $mch->mall_id, 'status' => 1, 'is_delete' => 0])
                    ->exists();
            }
            if ($storeExists || $leaseExists) {
                $isPlazaTenant = true;
            }
        }

        // ================= 获取自定义大厅配置 ==================
        $setting = \app\helpers\OptionHelper::get('mch_center_menu_setting', \Yii::$app->mall->id, []);
        $managerActions = (isset($setting['manager_actions']) && is_array($setting['manager_actions'])) ? $setting['manager_actions'] : null;
        $employeeActions = (isset($setting['employee_actions']) && is_array($setting['employee_actions'])) ? $setting['employee_actions'] : null;

        // 任一端未配置时，该端回退到默认菜单
        if ($managerActions === null || $employeeActions === null) {
            $defaultAll = array_merge(self::getDefaultAllActions(), self::getPluginActions());
            if ($managerActions === null) $managerActions = $defaultAll;
            if ($employeeActions === null) {
                $employeeActions = array_values(array_filter($defaultAll, function ($a) {
                    return empty($a['managerOnly']);
                }));
            }
        }

        // 走动态下发引擎（未配置的端已被上面填充为默认菜单）
        $rawActions = $isManager ? $managerActions : $employeeActions;

        // 过滤：主体类型(types) + 非店长隐藏 managerOnly + 商圈专属
        // 新格式 `types: [1,2,4]` 多选主体；
        // 旧格式 storeOnly/supplierOnly 仍保留以兼容存量配置。
        $filtered = array_filter($rawActions, function ($action) use ($isManager, $isPlazaTenant, $mch) {
            // 社区版没有收银台插件，兼容存量工作台中留下的收款码入口。
            if (in_array(explode('?', (string) ($action['url'] ?? ''), 2)[0], [
                '/plugins1/mch/cashier-board',
                '/plugins1/mch/my-paycode',
                '/plugins1/mch/paycode-apply',
            ], true)) {
                return false;
            }
            if (!$isManager && !empty($action['managerOnly'])) {
                return false;
            }
            // --- 新格式：types 多选 ---
            if (!empty($action['types']) && is_array($action['types'])) {
                if (!in_array((int)$mch->type, array_map('intval', $action['types']), true)) {
                    return false;
                }
            } else {
                // --- 旧格式回退（向后兼容） ---
                if ($mch->type == Mch::TYPE_UP_STORE && !empty($action['storeOnly'])) {
                    return false;
                }
                if ($mch->type == Mch::TYPE_DOWN_STORE && !empty($action['supplierOnly'])) {
                    return false;
                }
                // 注：旧格式没有 individualOnly，所以 type=4 个人租户在旧菜单里全部可见，
                //     运营请在 workspace.vue 中迁移到新的 types 多选格式
            }
            if (!empty($action['plazaOnly']) && !$isPlazaTenant) {
                return false;
            }
            return true;
        });

        // 固定卡片可见性（stats / order / goods 等）
        // 默认 [1,2] 可见，运营可在 workspace.vue 调整每个卡片的 types
        $cardsConfig = $setting['cards_config'] ?? null;
        if (!is_array($cardsConfig) || empty($cardsConfig)) {
            $cardsConfig = \app\plugins\mch\forms\mall\WorkspaceSettingForm::getDefaultCardsConfig();
        } else {
            // 合并默认，避免历史配置缺少某 key
            $cardsConfig = array_merge(\app\plugins\mch\forms\mall\WorkspaceSettingForm::getDefaultCardsConfig(), $cardsConfig);
        }

        $visibleCards = [];
        foreach ($cardsConfig as $key => $card) {
            $types = isset($card['types']) && is_array($card['types']) ? array_map('intval', $card['types']) : [];
            // types 为空 = 全主体可见
            $visibleCards[$key] = empty($types) ? true : in_array((int)$mch->type, $types, true);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'list'           => array_values($filtered),
            'is_manager'     => $isManager,
            'visible_cards'  => $visibleCards,  // { stats: bool, order: bool, goods: bool }
            'mch_type'       => (int)$mch->type, // 让前端直接拿到当前主体类型
        ]);
    }
}
