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

/**
 * AI 调用日志（平台级审计 + 成本统计）
 *
 * 所有插件的 AI 调用统一记这张表，后台可按商城/插件/能力维度看用量与成功率。
 * 原先各插件自建日志表（如 plugin_beauty_ai_call_log），导致同一商城的 AI 花销
 * 散在多处、无法汇总，故上收核心。
 *
 * · plugin   来源插件名（核心自身调用留空）
 * · ability  能力大类（text / vision / asr），用于跨插件汇总
 * · category 业务细分，由调用方自定义（如 llm_card_reco、skin_detect），保留业务语义
 * · status   0成功 1降级到备用供应商 2失败
 *
 * @property int    $id
 * @property int    $mall_id
 * @property int    $mch_id
 * @property string $plugin
 * @property string $ability
 * @property string $category
 * @property string $provider
 * @property int    $biz_id
 * @property int    $input_size
 * @property int    $tokens_in
 * @property int    $tokens_out
 * @property int    $duration_ms
 * @property float  $cost_amount
 * @property int    $status
 * @property string $error_msg
 * @property int    $is_delete
 * @property int    $created_at
 * @property int    $updated_at
 */
class AiCallLog extends BaseActiveRecord
{
    /** 能力大类 —— 与 AiProviderInterface 的三类能力对应 */
    const ABILITY_TEXT   = 'text';   // 文本生成 chat()
    const ABILITY_VISION = 'vision'; // 图片理解 analyzeImage()
    const ABILITY_ASR    = 'asr';    // 语音识别（AsrProviderInterface，提交/查询两段式）
    const ABILITY_IMAGE  = 'image';  // 文生图（ImageProviderInterface，独立驱动 + 独立接入点）

    const STATUS_OK       = 0; // 成功
    const STATUS_FALLBACK = 1; // 主供应商失败，降级供应商接管
    const STATUS_FAILED   = 2; // 全部失败

    public static function tableName()
    {
        return '{{%ai_call_log}}';
    }

    public function rules()
    {
        return [
            [['mall_id'], 'required'],
            [['mall_id', 'mch_id', 'biz_id', 'input_size', 'tokens_in',
              'tokens_out', 'duration_ms', 'status'], 'integer'],
            [['plugin', 'ability', 'category', 'provider'], 'string', 'max' => 30],
            [['cost_amount'], 'number', 'min' => 0],
            [['error_msg'], 'string', 'max' => 500],
            [['status'], 'default', 'value' => self::STATUS_OK],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'mall_id'     => '商城',
            'mch_id'      => '门店',
            'plugin'      => '来源插件',
            'ability'     => '能力',
            'category'    => '调用类型',
            'provider'    => '供应商',
            'biz_id'      => '关联业务ID',
            'input_size'  => '入参体量',
            'tokens_in'   => '输入Token',
            'tokens_out'  => '输出Token',
            'duration_ms' => '耗时(ms)',
            'cost_amount' => '扣费(元)',
            'status'      => '状态',
            'error_msg'   => '错误信息',
        ];
    }

    /** 状态中文 */
    public static function statusText(int $status): string
    {
        return [
            self::STATUS_OK       => '成功',
            self::STATUS_FALLBACK => '降级到备用供应商',
            self::STATUS_FAILED   => '失败',
        ][$status] ?? '未知';
    }
}
