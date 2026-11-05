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

namespace app\forms\mall\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\helpers\OptionHelper;
use app\services\ai\AiProviderFactory;
use app\services\ai\AsrProviderFactory;
use app\services\ai\ImageProviderFactory;
use Yii;

/**
 * 商城级 AI 配置读写（后台「商城管理 → AI 配置」）
 *
 * 这是全平台唯一的 AI 供应商配置入口，所有用到 AI 能力的插件共用这一份。
 *
 * ── 字段消费方 ──────────────────────────────────────────────
 *   ai_provider / ai_api_key / ai_base_url / ai_model
 *        → AiProviderFactory::create()          主供应商（文本 + 图片理解）
 *   ai_fallback_provider / ai_fallback_api_key
 *        → AiProviderFactory::createFallback()  主供应商失败时自动接管
 *   asr_provider / asr_secret_id / asr_secret_key
 *        → 各插件的语音转写（AsrProviderFactory，与 LLM 无关的独立驱动 + 密钥）
 *   image_provider / image_api_key / image_base_url / image_model
 *        → 文生图（ImageProviderFactory）。**与主供应商分开配**：火山的生图要单独开
 *          一个推理接入点，和文本接入点不是同一个，共用一份配置必然出错
 *   image_host_whitelist
 *        → 图片类 AI 能力的来源域名白名单（防 SSRF），本站域名恒定放行
 *
 * GET  → 读取（api_key / secret_key 脱敏为 ••••••）
 * POST → 保存（前端回传脱敏占位时保留库内原值）
 */
class AiConfigForm extends BaseModel
{
    public const OPTION_KEY = AiProviderFactory::OPTION_KEY;

    /** 脱敏占位符（与前端约定一致） */
    private const MASK = '••••••';

    // ── 主供应商 ──────────────────────────────────────────────────────────
    public $ai_provider  = 'mock';
    public $ai_api_key   = '';
    public $ai_base_url  = '';
    public $ai_model     = '';

    // ── 降级供应商（主供应商失败时自动切换）───────────────────────────────
    public $ai_fallback_provider = '';
    public $ai_fallback_api_key  = '';

    // ── 语音识别（ASR，独立驱动 + 独立密钥）───────────────────────────────
    public $asr_provider   = 'tencent';
    public $asr_secret_id  = '';
    public $asr_secret_key = '';

    // ── 文生图（独立驱动 + 独立接入点）────────────────────────────────────
    public $image_provider = 'mock';
    public $image_api_key  = '';
    public $image_base_url = '';
    public $image_model    = '';

    // ── 图片来源域名白名单（防 SSRF）──────────────────────────────────────
    /** 换行或逗号分隔，支持 *.example.com；本站域名恒定允许，无需填写 */
    public $image_host_whitelist = '';

    /** 持久化字段白名单 */
    private static $FIELDS = [
        'ai_provider', 'ai_api_key', 'ai_base_url', 'ai_model',
        'ai_fallback_provider', 'ai_fallback_api_key',
        'asr_provider', 'asr_secret_id', 'asr_secret_key',
        'image_provider', 'image_api_key', 'image_base_url', 'image_model',
        'image_host_whitelist',
    ];

    /** 需要脱敏的敏感字段 */
    private static $SECRET_FIELDS = ['ai_api_key', 'ai_fallback_api_key', 'asr_secret_key', 'image_api_key'];

    public function rules()
    {
        $providerValues = array_column(AiProviderFactory::providers(), 'value');
        $asrValues      = array_column(AsrProviderFactory::providers(), 'value');
        $imageValues    = array_column(ImageProviderFactory::providers(), 'value');
        return [
            [['ai_provider'], 'in', 'range' => $providerValues],
            [['ai_fallback_provider'], 'in', 'range' => array_merge([''], $providerValues)],
            [['asr_provider'], 'in', 'range' => $asrValues],
            [['image_provider'], 'in', 'range' => $imageValues],
            [['image_api_key', 'image_base_url'], 'string'],
            [['image_model'], 'string', 'max' => 128],
            [['ai_api_key', 'ai_base_url', 'ai_model', 'ai_fallback_api_key'], 'string'],
            [['ai_model'], 'string', 'max' => 128],
            [['asr_secret_id', 'asr_secret_key'], 'string', 'max' => 255],
            [['image_host_whitelist'], 'string', 'max' => 1000],
        ];
    }

    /**
     * 读取配置（敏感字段脱敏）
     */
    public function get(): array
    {
        $setting = AiProviderFactory::setting((int)Yii::$app->mall->id);

        $data = [];
        foreach (self::$FIELDS as $key) {
            $data[$key] = $setting[$key] ?? $this->$key;
        }
        foreach (self::$SECRET_FIELDS as $key) {
            if (!empty($data[$key])) {
                $data[$key] = self::MASK;
            }
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'setting'      => $data,
                'providers'    => AiProviderFactory::providers(),
                'asrProviders' => AsrProviderFactory::providers(),
                'imageProviders' => ImageProviderFactory::providers(),
            ],
        ];
    }

    /**
     * 保存配置
     */
    public function save(): array
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId  = (int)Yii::$app->mall->id;
        $setting = AiProviderFactory::setting($mallId);

        foreach (self::$FIELDS as $key) {
            // 敏感字段：前端回传脱敏占位 → 保留库内原值
            if (in_array($key, self::$SECRET_FIELDS, true) && $this->$key === self::MASK) {
                continue;
            }
            $setting[$key] = $this->$key;
        }

        OptionHelper::set(self::OPTION_KEY, $setting, $mallId);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功'];
    }

    /**
     * 测试主供应商连通性（用当前表单数据实测）
     *
     * 注意：这里只发一句文本对话，**通过不代表图片理解和语音识别可用**，
     * 那两类要用联调页（AiDebugForm）分别实测。
     */
    public function test(): array
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $apiKey = $this->ai_api_key;
        if ($apiKey === self::MASK || $apiKey === '') {
            $setting = AiProviderFactory::setting((int)Yii::$app->mall->id);
            $apiKey  = $setting['ai_api_key'] ?? '';
        }

        $provider = AiProviderFactory::build(
            $this->ai_provider, $apiKey, $this->ai_base_url, $this->ai_model
        );

        if (!$provider->isAvailable()) {
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg'  => "供应商 [{$provider->getName()}] 不可用：API Key 未配置或格式错误",
            ];
        }

        try {
            $reply = $provider->chat(
                '你是经营助手，请用一句话回复。',
                '你好，连通性测试',
                ['max_tokens' => 50, 'temperature' => 0.3]
            );
            return [
                'code' => ApiCode::CODE_SUCCESS,
                'data' => ['reply' => $reply, 'provider' => $provider->getName()],
            ];
        } catch (\Throwable $e) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }
}
