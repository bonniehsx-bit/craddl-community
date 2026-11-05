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

namespace app\models;

use app\models\BaseActiveRecord;

/**
 * 微信推送模板配置
 *
 * @property int         $id
 * @property int         $mall_id
 * @property string      $plugin       所属插件，如 hardware
 * @property string      $scene        场景键，如 hardware.bottle.feeding_end
 * @property string      $title        场景名称（只读展示）
 * @property string|null $template_id  微信公众号模板 ID，null 表示未配置
 * @property string|null $field_map    字段映射 JSON：内部字段名 → 微信模板变量名
 *                                     例：{"device_name":"keyword1","temp":"keyword2"}
 */
class WxNotifyTemplate extends BaseActiveRecord
{
    public static function tableName()
    {
        return '{{%wx_notify_template}}';
    }

    /**
     * 查询指定商城、指定插件的所有场景（用于配置页展示）
     */
    public static function getByPlugin(int $mallId, string $plugin): array
    {
        return static::find()
            ->where(['mall_id' => $mallId, 'plugin' => $plugin])
            ->orderBy(['id' => SORT_ASC])
            ->asArray()
            ->all();
    }

    /**
     * 查询单个场景的模板 ID（向后兼容）
     */
    public static function getTemplateId(int $mallId, string $scene): ?string
    {
        $config = static::getTemplateConfig($mallId, $scene);
        return $config['template_id'] ?? null;
    }

    /**
     * 查询单个场景的完整配置（template_id + field_map）
     *
     * @return array{template_id:string|null, field_map:array}|null
     */
    public static function getTemplateConfig(int $mallId, string $scene): ?array
    {
        $row = static::find()
            ->select(['template_id', 'field_map'])
            ->where(['mall_id' => $mallId, 'scene' => $scene])
            ->asArray()
            ->one();

        if (!$row || empty($row['template_id'])) {
            return null;
        }

        $fieldMap = [];
        if (!empty($row['field_map'])) {
            $decoded = json_decode($row['field_map'], true);
            if (is_array($decoded)) {
                $fieldMap = $decoded;
            }
        }

        return [
            'template_id' => $row['template_id'],
            'field_map'   => $fieldMap,
        ];
    }
}
