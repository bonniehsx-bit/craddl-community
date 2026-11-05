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

namespace app\plugins\mch\helpers;

use app\plugins\mch\models\Mch;

/**
 * 入驻「系统字段」注册表 —— 后台只能在这个固定目录里调开关/必填/标签/排序,无法凭空造字段。
 *
 * 这是「字段可配置」的默认底座:每个 field_key 对应 plugin_mch 的真实列与固定渲染组件,
 * plugin_mch_apply_field 表只存「覆盖值」,无覆盖时一律用这里的默认 —— 因此表为空 = 当前写死行为。
 *
 * ⚠️ 与当前 uniapp/plugins1/mch/apply.vue 的显隐/必填/文案逐字段对齐,改这里前先核对那份。
 * 注:营业执照已移入证照库(见 presetCerts);身份证正反面仍为系统字段。
 */
class MchApplyFieldRegistry
{
    /** 字段分组(用于移动端分段展示) */
    public static function groups()
    {
        return [
            'basic'   => '基本信息',
            'address' => '经营地址',
            'contact' => '联系方式',
            'media'   => '店铺展示',
            'cert'    => '证件信息',
        ];
    }

    /**
     * 核心身份字段 —— 始终启用 + 必填,后台不可停用/改必填,防止配出"无法识别商家"的表单。
     * 其余字段(分类/街道/地图/营业时间/Logo/Banner/简介)可在后台自由开关。
     * @return string[]
     */
    public static function coreKeys()
    {
        return ['mch_name', 'region', 'address', 'legal_person_name', 'mobile', 'id_card_front', 'id_card_back'];
    }

    /**
     * 系统字段完整目录。types=适用主体类型;required=默认必填;value_column=对应 plugin_mch 列(复合字段为 null,由 save 特殊处理)。
     * label_map / placeholder_map:按主体类型覆盖文案(还原个人租户的差异化叫法)。
     */
    public static function systemFields()
    {
        $UP = Mch::TYPE_UP_STORE;       // 1 线上
        $DOWN = Mch::TYPE_DOWN_STORE;   // 2 门店
        $INDIV = Mch::TYPE_INDIVIDUAL;  // 4 个人
        $ALL = [$UP, $DOWN, $INDIV];

        return [
            [
                'field_key' => 'mch_name', 'component' => 'text', 'group' => 'basic',
                'types' => $ALL, 'required' => true, 'sort' => 10, 'value_column' => 'mch_name',
                'label' => '商家名称', 'label_map' => [$INDIV => '真实姓名'],
                'placeholder' => '请填写商家/公司名称', 'placeholder_map' => [$INDIV => '请填写真实姓名'],
            ],
            [
                'field_key' => 'cat_id', 'component' => 'category', 'group' => 'basic',
                'types' => [$UP, $DOWN], 'required' => true, 'sort' => 20, 'value_column' => 'cat_id',
                'label' => '商家分类', 'placeholder' => '请选择分类',
            ],
            [
                'field_key' => 'region', 'component' => 'region', 'group' => 'address',
                'types' => $ALL, 'required' => true, 'sort' => 30, 'value_column' => null,
                'label' => '所在城市', 'placeholder' => '选择省市区',
            ],
            [
                'field_key' => 'town', 'component' => 'town', 'group' => 'address',
                'types' => $ALL, 'required' => true, 'sort' => 40, 'value_column' => 'town_code',
                'label' => '街道/乡镇', 'placeholder' => '请选择街道/乡镇',
            ],
            [
                'field_key' => 'address', 'component' => 'textarea', 'group' => 'address',
                'types' => $ALL, 'required' => true, 'sort' => 50, 'value_column' => 'address',
                'label' => '详细地址', 'placeholder' => '街道、楼牌号等',
            ],
            [
                'field_key' => 'location', 'component' => 'location', 'group' => 'address',
                'types' => [$DOWN], 'required' => true, 'sort' => 60, 'value_column' => null,
                'label' => '地图位置', 'placeholder' => '点击选择门店地图位置',
            ],
            [
                'field_key' => 'opening_hours', 'component' => 'timerange', 'group' => 'basic',
                'types' => [$DOWN], 'required' => true, 'sort' => 70, 'value_column' => 'opening_hours',
                'label' => '营业时间', 'placeholder' => '',
            ],
            [
                'field_key' => 'legal_person_name', 'component' => 'text', 'group' => 'contact',
                'types' => $ALL, 'required' => true, 'sort' => 80, 'value_column' => 'legal_person_name',
                'label' => '联系人', 'label_map' => [$INDIV => '本人姓名'],
                'placeholder' => '请填写联系人/法人姓名', 'placeholder_map' => [$INDIV => '请填写本人真实姓名'],
            ],
            [
                'field_key' => 'mobile', 'component' => 'phone', 'group' => 'contact',
                'types' => $ALL, 'required' => true, 'sort' => 90, 'value_column' => 'mobile',
                'label' => '联系电话', 'placeholder' => '请填写联系电话',
            ],
            [
                'field_key' => 'logo', 'component' => 'image', 'group' => 'media',
                'types' => [$UP, $DOWN], 'required' => true, 'sort' => 100, 'value_column' => 'logo',
                'label' => '商家 Logo', 'placeholder' => '',
            ],
            [
                'field_key' => 'banner_images', 'component' => 'images', 'group' => 'media',
                'types' => [$UP, $DOWN], 'required' => true, 'sort' => 110, 'value_column' => 'banner_images',
                'label' => '店铺 Banner(最多 5 张)', 'placeholder' => '', 'limit' => 5,
            ],
            [
                'field_key' => 'description', 'component' => 'textarea', 'group' => 'media',
                'types' => [$UP, $DOWN], 'required' => true, 'sort' => 120, 'value_column' => 'description',
                'label' => '商家介绍', 'placeholder' => '请填写商家简介',
            ],
            [
                'field_key' => 'id_card_front', 'component' => 'image', 'group' => 'cert',
                'types' => $ALL, 'required' => true, 'sort' => 130, 'value_column' => 'id_card_front',
                'label' => '法人身份证人像面', 'label_map' => [$INDIV => '本人身份证人像面'], 'placeholder' => '',
            ],
            [
                'field_key' => 'id_card_back', 'component' => 'image', 'group' => 'cert',
                'types' => $ALL, 'required' => true, 'sort' => 140, 'value_column' => 'id_card_back',
                'label' => '法人身份证国徽面', 'label_map' => [$INDIV => '本人身份证国徽面'], 'placeholder' => '',
            ],
        ];
    }

    /**
     * 预置证照(随营业执照迁入证照库)。value_column=提交时回写的 plugin_mch 兼容列。
     */
    public static function presetCerts()
    {
        return [
            [
                'preset_key' => 'business_license',
                'name' => '营业执照',
                'types' => '1,2',           // 线上 + 门店
                'is_required' => 1,
                'need_validity' => 1,       // 营业执照默认采集有效期(可后台关)
                'sort' => 10,
                'value_column' => 'business_license',
            ],
        ];
    }

    /** preset_key → plugin_mch 回写列(用于提交时同步,兼容下游读列代码) */
    public static function presetColumn($presetKey)
    {
        foreach (self::presetCerts() as $c) {
            if ($c['preset_key'] === $presetKey) {
                return $c['value_column'] ?? null;
            }
        }
        return null;
    }

    /**
     * 解析某主体类型的「默认字段配置」(未叠加 DB 覆盖前)。
     * 返回 field_key => 规范化配置数组(已就该类型解析 label/placeholder)。
     *
     * @param int $type
     * @return array
     */
    public static function fieldsForType($type)
    {
        $type = (int)$type;
        $out = [];
        foreach (self::systemFields() as $f) {
            if (!in_array($type, $f['types'], true)) {
                continue;
            }
            $label = $f['label_map'][$type] ?? $f['label'];
            $placeholder = $f['placeholder_map'][$type] ?? ($f['placeholder'] ?? '');
            $out[$f['field_key']] = [
                'field_key'    => $f['field_key'],
                'label'        => $label,
                'placeholder'  => $placeholder,
                'component'    => $f['component'],
                'group'        => $f['group'],
                'is_enabled'   => 1,
                'is_required'  => !empty($f['required']) ? 1 : 0,
                'sort'         => $f['sort'],
                'value_column' => $f['value_column'] ?? null,
                'limit'        => $f['limit'] ?? null,
                'core'         => in_array($f['field_key'], self::coreKeys(), true) ? 1 : 0,
            ];
        }
        return $out;
    }

    /** field_key → value_column 映射(供 save 写回核心列) */
    public static function valueColumnMap()
    {
        $map = [];
        foreach (self::systemFields() as $f) {
            if (!empty($f['value_column'])) {
                $map[$f['field_key']] = $f['value_column'];
            }
        }
        return $map;
    }
}
