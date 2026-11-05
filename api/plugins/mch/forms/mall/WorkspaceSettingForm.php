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
namespace app\plugins\mch\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\plugins\mch\models\Mch;
use app\helpers\OptionHelper;

class WorkspaceSettingForm extends BaseModel
{
    public $employee_actions;
    public $manager_actions;
    public $cards_config; // 顶部固定卡片配置：每个卡片限定哪些主体类型可见

    /**
     * 系统注册的"固定卡片"列表
     *
     * center.vue 顶部除了 actions 数组外，还有几个写死结构的卡片：
     *   - stats: 销售/营业额统计看板
     *   - order: 订单管理（待发货/待核销/售后）
     *   - goods: 商品管理（出售中/已下架/审核中）
     *
     * 默认：仅 type=1(线上) 和 type=2(门店) 可见，个人租户不展示
     * 运营可在 workspace.vue 后台勾选每个卡片适用的主体类型
     */
    public static function getDefaultCardsConfig()
    {
        return [
            'stats' => ['key' => 'stats', 'label' => '销售/营业额统计', 'types' => [1, 2]],
            'order' => ['key' => 'order', 'label' => '订单管理',       'types' => [1, 2]],
            'goods' => ['key' => 'goods', 'label' => '商品管理',       'types' => [1, 2]],
        ];
    }

    public function rules()
    {
        return [
            [['employee_actions', 'manager_actions', 'cards_config'], 'safe'],
        ];
    }

    public function get()
    {
        $setting = OptionHelper::get('mch_center_menu_setting', \Yii::$app->mall->id, []);

        $employee_actions = $setting['employee_actions'] ?? null;
        $manager_actions = $setting['manager_actions'] ?? null;
        $cards_config = $setting['cards_config'] ?? null;

        // 非有效数组一律视为 null，触发默认回退
        if (!is_array($manager_actions) || empty($manager_actions)) $manager_actions = null;
        if (!is_array($employee_actions) || empty($employee_actions)) $employee_actions = null;
        if (!is_array($cards_config) || empty($cards_config)) $cards_config = null;

        // 任一端为 null 时，该端回退到系统默认菜单
        $defaultAll = null;
        if ($manager_actions === null || $employee_actions === null) {
            $defaultAll = \app\plugins\mch\forms\admin\mch\MchActionsForm::getDefaultAllActions();
        }
        if ($manager_actions === null) {
            $manager_actions = $defaultAll;
        }
        if ($employee_actions === null) {
            $employee_actions = [];
            foreach ($defaultAll as $action) {
                if (empty($action['managerOnly'])) {
                    $employee_actions[] = $action;
                }
            }
        }
        if ($cards_config === null) {
            $cards_config = self::getDefaultCardsConfig();
        } else {
            // 合并新版默认卡片，避免历史配置缺少某 key 时整张卡片消失
            $cards_config = array_merge(self::getDefaultCardsConfig(), $cards_config);
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => 'success',
            'data' => [
                'employee_actions' => $employee_actions ?: [],
                'manager_actions'  => $manager_actions ?: [],
                'cards_config'     => $cards_config,
            ]
        ];
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->getErrorResponse();
        }

        // 空值统一转 null，确保 get() 能正确 fallback 到默认菜单
        $setting = [
            'employee_actions' => !empty($this->employee_actions) && is_array($this->employee_actions) ? $this->employee_actions : null,
            'manager_actions'  => !empty($this->manager_actions) && is_array($this->manager_actions) ? $this->manager_actions : null,
            'cards_config'     => !empty($this->cards_config) && is_array($this->cards_config) ? $this->cards_config : null,
        ];

        $res = OptionHelper::set(
            'mch_center_menu_setting',
            $setting,
            \Yii::$app->mall->id
        );

        if (!$res) {
            return [
                'code' => ApiCode::CODE_ERROR,
                'msg' => '保存失败'
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '保存成功'
        ];
    }
}
