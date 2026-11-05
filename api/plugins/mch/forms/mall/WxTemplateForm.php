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
use app\models\WxNotifyTemplate;
use Yii;

/**
 * 商家插件微信推送模板配置
 *
 * 场景命名规范：mch.{模块}.{事件}
 */
class WxTemplateForm extends BaseModel
{
    /** @var array [['scene'=>'...','template_id'=>'...','field_map'=>'...']] */
    public $templates;

    public function rules(): array
    {
        return [
            [['templates'], 'safe'],
        ];
    }

    // ── 场景 + 默认字段映射定义 ───────────────────────────────────────────

    public const MCH_SCENES = [
        [
            'scene' => 'mch.entry.submitted',
            'title' => '商家入驻申请通知（发给平台管理员）',
            'default_field_map' => [
                'mch_name' => 'keyword1',  // 申请店铺名
                'mch_type' => 'keyword2',  // 商家类型
                'tip'      => 'keyword3',  // 提示语
            ],
        ],
        [
            'scene' => 'mch.entry.approved',
            'title' => '店铺入驻审核通过通知',
            'default_field_map' => [
                'mch_name' => 'keyword1',  // 店铺名
                'tip'      => 'keyword2',  // 提示语
            ],
        ],
        [
            'scene' => 'mch.entry.rejected',
            'title' => '店铺入驻审核驳回通知',
            'default_field_map' => [
                'mch_name' => 'keyword1',  // 店铺名
                'reason'   => 'keyword2',  // 驳回原因
                'tip'      => 'keyword3',  // 提示语
            ],
        ],
    ];

    // ── 读取 ─────────────────────────────────────────────────────────────

    public function get(): array
    {
        $mallId = Yii::$app->mall->id;
        $templates = [];

        foreach (self::MCH_SCENES as $scene) {
            $row = WxNotifyTemplate::findOne([
                'mall_id'   => $mallId,
                'scene'     => $scene['scene'],
                'is_delete' => 0,
            ]);

            $templates[] = [
                'scene'       => $scene['scene'],
                'title'       => $scene['title'],
                'template_id' => $row ? $row->template_id : '',
                'field_map'   => $row ? $row->field_map : '',
            ];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => ['templates' => $templates],
        ];
    }

    // ── 保存 ─────────────────────────────────────────────────────────────

    public function save(): array
    {
        if (!$this->validate()) return $this->responseErrorInfo();

        $mallId = Yii::$app->mall->id;
        $list   = $this->templates;
        if (!is_array($list)) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '参数格式错误'];
        }

        $validScenes = array_column(self::MCH_SCENES, 'scene');

        foreach ($list as $item) {
            $scene = $item['scene'] ?? '';
            if (!in_array($scene, $validScenes, true)) continue;

            $row = WxNotifyTemplate::findOne([
                'mall_id'   => $mallId,
                'scene'     => $scene,
                'is_delete' => 0,
            ]);
            if (!$row) {
                $row = new WxNotifyTemplate();
                $row->mall_id = $mallId;
                $row->scene   = $scene;
            }

            $row->template_id = $item['template_id'] ?? '';
            $row->field_map   = $item['field_map'] ?? '';
            $row->save(false);
        }

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功'];
    }
}
