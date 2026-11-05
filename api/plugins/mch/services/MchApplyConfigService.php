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

namespace app\plugins\mch\services;

use app\helpers\OptionHelper;
use app\plugins\mch\helpers\MchApplyFieldRegistry;
use app\plugins\mch\models\MchApplyField;
use app\plugins\mch\models\MchCertDef;

/**
 * 入驻配置解析服务 —— 把「注册表默认值」叠加「DB 覆盖」,产出某主体类型的最终字段配置与证照清单。
 *
 * 解析口径:
 *   - 字段:以 MchApplyFieldRegistry::fieldsForType() 为底,plugin_mch_apply_field 的同 (mall,type,field_key) 行覆盖之。
 *           表里没有对应行 → 用注册表默认。⇒ 全表为空时,结果 = 当前写死行为(零行为变化)。
 *   - 证照:plugin_mch_cert_def 为准;预置证照(营业执照)由 ensureCertDefaults() 惰性补齐,新商城自动获得。
 *
 * 说明:查询用 BaseActiveRecord::find() 已自动按当前商城 + is_delete=0 过滤;这里仍显式带 mall_id,
 * 以便无商城上下文(如 CLI 测试)时也正确。
 */
class MchApplyConfigService
{
    /**
     * 取某主体类型的最终字段配置(含未启用项,按 sort 升序)。
     * C 端按 is_enabled 过滤展示;后端校验按 is_enabled + is_required 判定。
     *
     * @param int $mallId
     * @param int $type 主体类型 1/2/4
     * @return array 列表,每项含 field_key/label/placeholder/component/group/is_enabled/is_required/sort/value_column/limit
     */
    /**
     * @param bool $applyDefaultAddress 是否应用「统一经营地址」过滤。
     *   入驻侧(C 端渲染 / 后端校验)传 true —— 地址字段整组退场;
     *   后台「入驻字段」管理页传 false —— 那里要能看到完整字段目录,
     *   否则开启统一地址后管理员会以为四个字段丢了,保存时还可能把它们的配置误清。
     */
    public static function getFieldConfig($mallId, $type, $applyDefaultAddress = true)
    {
        $type = (int)$type;
        $defaults = MchApplyFieldRegistry::fieldsForType($type); // field_key => config

        $rows = MchApplyField::find()
            ->where(['mall_id' => $mallId, 'type' => $type, 'is_delete' => 0])
            ->all();
        foreach ($rows as $r) {
            $key = $r->field_key;
            if (!isset($defaults[$key])) {
                continue; // 注册表已无此字段(下线),忽略历史覆盖
            }
            $defaults[$key]['is_enabled']  = (int)$r->is_enabled;
            $defaults[$key]['is_required'] = (int)$r->is_required;
            if ($r->label !== null && $r->label !== '') {
                $defaults[$key]['label'] = $r->label;
            }
            if ($r->placeholder !== null && $r->placeholder !== '') {
                $defaults[$key]['placeholder'] = $r->placeholder;
            }
            $defaults[$key]['sort'] = (int)$r->sort;
        }

        $list = array_values($defaults);
        usort($list, function ($a, $b) {
            return $a['sort'] <=> $b['sort'] ?: strcmp($a['field_key'], $b['field_key']);
        });

        // 统一经营地址:开启后整组地址字段退场,商户不用填、也填不了。
        //
        // 之所以在这里过滤而不是在前端藏起来 —— 这个方法是字段配置的**唯一出口**,
        // C 端渲染表单(MchApplyForm::getConfig)和后端必填校验(MchApplyForm::checkConfig)
        // 读的都是它。在这一处剔掉,两边同时生效,不会出现「前端不显示、后端还卡必填」;
        // uniapp 那边是 v-for 按列表渲染的,字段没了自然就不画,一行都不用改。
        //
        // 注意这几个字段本来在 coreKeys 里(后台不可停用),所以不能靠字段开关实现,
        // 必须由这个独立开关整组带走。
        if ($applyDefaultAddress && self::useDefaultAddress($mallId)) {
            $hidden = array_flip(self::DEFAULT_ADDRESS_FIELDS);
            $list = array_values(array_filter($list, function ($f) use ($hidden) {
                return !isset($hidden[$f['field_key']]);
            }));
        }

        return $list;
    }

    /** 开启统一经营地址后,入驻表单要隐藏、并由后台默认值填充的字段 */
    const DEFAULT_ADDRESS_FIELDS = ['region', 'town', 'address', 'location'];

    /**
     * 该商城是否启用了「统一经营地址」。
     * 只有开关打开**且**省市区和详细地址都配全了才算数 —— 配了一半就生效的话,
     * 商家既填不了地址、写进去的又是空值,不如让他自己填。
     */
    public static function useDefaultAddress($mallId): bool
    {
        $s = self::defaultAddress($mallId);
        return !empty($s['use_default_address'])
            && $s['province_code'] !== '' && $s['city_code'] !== '' && $s['address'] !== '';
    }

    /**
     * 取统一经营地址配置(已归一化成 plugin_mch 的列名,可直接赋值)。
     * @return array{use_default_address:int,province_code:string,city_code:string,county_code:string,town_code:string,address:string,location_address:string,longitude:string,latitude:string}
     */
    public static function defaultAddress($mallId): array
    {
        $setting = OptionHelper::get('mch_setting', $mallId, []) ?: [];
        return [
            'use_default_address' => (int)($setting['use_default_address'] ?? 0),
            'province_code'    => trim((string)($setting['default_province_code'] ?? '')),
            'city_code'        => trim((string)($setting['default_city_code'] ?? '')),
            'county_code'      => trim((string)($setting['default_county_code'] ?? '')),
            'town_code'        => trim((string)($setting['default_town_code'] ?? '')),
            'address'          => trim((string)($setting['default_address'] ?? '')),
            'location_address' => trim((string)($setting['default_location_address'] ?? '')),
            'longitude'        => trim((string)($setting['default_longitude'] ?? '')),
            'latitude'         => trim((string)($setting['default_latitude'] ?? '')),
        ];
    }

    /**
     * 保存某主体类型的字段配置(覆盖行 upsert)。
     * 只处理「可配字段」:核心身份字段(coreKeys)直接跳过 → 永远走注册表默认(启用+必填),不可被后台关。
     * 不在该类型适用范围内的 field_key 一并忽略。v1 不改 sort/label(沿用注册表)。
     *
     * @param int $mallId
     * @param int $type
     * @param array $fields [{field_key, is_enabled, is_required}, ...]
     */
    public static function saveFieldConfig($mallId, $type, array $fields)
    {
        $type = (int)$type;
        $defaults = MchApplyFieldRegistry::fieldsForType($type); // field_key => config(含 core/sort)
        foreach ($fields as $f) {
            $key = is_array($f) ? ($f['field_key'] ?? '') : '';
            if ($key === '' || !isset($defaults[$key])) {
                continue; // 非本类型适用字段,忽略
            }
            if (!empty($defaults[$key]['core'])) {
                continue; // 核心字段不落覆盖,恒用默认
            }
            $row = MchApplyField::find()
                ->where(['mall_id' => $mallId, 'type' => $type, 'field_key' => $key, 'is_delete' => 0])
                ->one();
            if (!$row) {
                $row = new MchApplyField();
                $row->mall_id = $mallId;
                $row->type = $type;
                $row->field_key = $key;
            }
            $row->is_enabled = !empty($f['is_enabled']) ? 1 : 0;
            // 停用即必填归零,后端保底(前端 setting.vue 也归零,这里防直连 API)
            $row->is_required = ($row->is_enabled && !empty($f['is_required'])) ? 1 : 0;
            $row->sort = (int)$defaults[$key]['sort']; // v1 沿用注册表排序
            $row->save(false);
        }
    }

    /**
     * 取某主体类型「已启用」的证照定义(按 sort 升序)。先惰性补齐预置证照。
     *
     * @param int $mallId
     * @param int $type
     * @return array 列表,每项含 id/name/preset_key/is_required/need_validity/is_system/sort
     */
    public static function getCertDefs($mallId, $type)
    {
        $type = (int)$type;
        self::ensureCertDefaults($mallId);

        $rows = MchCertDef::find()
            ->where(['mall_id' => $mallId, 'is_enabled' => 1, 'is_delete' => 0])
            ->orderBy(['sort' => SORT_ASC, 'id' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $r) {
            if (!in_array($type, $r->getTypeList(), true)) {
                continue;
            }
            $out[] = [
                'id'            => (int)$r->id,
                'name'          => $r->name,
                'preset_key'    => $r->preset_key,
                'is_required'   => (int)$r->is_required,
                'need_validity' => (int)$r->need_validity,
                'is_system'     => (int)$r->is_system,
                'sort'          => (int)$r->sort,
            ];
        }
        return $out;
    }

    /**
     * 取证照定义 ActiveRecord(供后端 save 校验用,key=cert_def_id)。
     *
     * @param int $mallId
     * @return MchCertDef[] id => model
     */
    public static function getCertDefModels($mallId)
    {
        self::ensureCertDefaults($mallId);
        $rows = MchCertDef::find()->where(['mall_id' => $mallId, 'is_enabled' => 1, 'is_delete' => 0])->all();
        $map = [];
        foreach ($rows as $r) {
            $map[(int)$r->id] = $r;
        }
        return $map;
    }

    /**
     * 惰性补齐预置证照(营业执照)。按 preset_key 判存,缺失则建 —— 新商城无需迁移种子即自动获得。
     *
     * @param int $mallId
     */
    public static function ensureCertDefaults($mallId)
    {
        foreach (MchApplyFieldRegistry::presetCerts() as $spec) {
            $exists = MchCertDef::find()
                ->where(['mall_id' => $mallId, 'preset_key' => $spec['preset_key'], 'is_delete' => 0])
                ->exists();
            if ($exists) {
                continue;
            }
            $m = new MchCertDef();
            $m->mall_id       = $mallId;
            $m->name          = $spec['name'];
            $m->preset_key    = $spec['preset_key'];
            $m->types         = $spec['types'];
            $m->is_required   = (int)$spec['is_required'];
            $m->need_validity = (int)$spec['need_validity'];
            $m->is_system     = 1;
            $m->is_enabled    = 1;
            $m->sort          = (int)$spec['sort'];
            $m->save(false);
        }
    }
}
