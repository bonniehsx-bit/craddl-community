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

namespace app\plugins\withdraw\forms\admin\channel;

use app\core\ApiCode;
use app\funds\BaseFund;
use app\models\BaseModel;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\FundRegistry;
use Yii;

/**
 * 提现渠道 - 新建 / 编辑 / 启停
 *
 * 校验要点：
 *  - fund_class 必须是真实存在且为 BaseFund 子类（反射打款依赖它，禁脏数据）
 *  - user_types / asset_types 必须是合法枚举子集
 *  - json 字段（fund_config / onboarding_override）统一序列化存储
 */
class ChannelSaveForm extends BaseModel
{
    public $id;
    public $name;
    public $icon;
    public $subtitle;
    public $color;
    public $fund_class;
    public $destination;          // 到账方式key
    public $fund_config;          // array
    public $user_types;           // array
    public $asset_types;          // array
    public $onboarding_override;  // array
    public $fee_rate;
    public $tax_rate;
    public $min_amount;
    public $max_amount;
    public $multiple;
    public $day_limit_amount;
    public $day_limit_count;
    public $arrival_desc;
    public $need_audit;
    public $sort;
    public $status;
    public $remark;
    public $rules;                // 提现规则自定义文案(一行一条;留空=C端用默认规则)

    public function rules()
    {
        return [
            [['name', 'fund_class'], 'required'],
            [['id', 'day_limit_count', 'need_audit', 'sort', 'status'], 'integer'],
            [['fee_rate', 'tax_rate', 'min_amount', 'max_amount', 'multiple', 'day_limit_amount'], 'number'],
            [['name', 'icon', 'subtitle', 'color', 'fund_class', 'destination', 'arrival_desc', 'remark', 'rules'], 'string'],
            [['fund_config', 'user_types', 'asset_types', 'onboarding_override'], 'safe'],
            [['fee_rate', 'tax_rate', 'min_amount', 'max_amount', 'multiple', 'day_limit_amount'], 'default', 'value' => 0],
            [['need_audit'], 'default', 'value' => 1],
            [['status'], 'default', 'value' => 1],
            [['sort'], 'default', 'value' => 0],
            [['fund_class'], 'validateFundClass'],
            [['destination'], 'validateDestination'],
            // skipOnEmpty=false：空数组/未传也要触发校验（否则空 user_types 会绕过门禁）
            [['user_types'], 'validateUserTypes', 'skipOnEmpty' => false],
            [['asset_types'], 'validateAssetTypes', 'skipOnEmpty' => false],
        ];
    }

    /** 网关类必须真实存在、为 BaseFund 子类,且在 FundRegistry 可用清单内(排除死桩/半成品) */
    public function validateFundClass($attr)
    {
        $cls = $this->$attr;
        if (!class_exists($cls) || !is_subclass_of($cls, BaseFund::class)) {
            $this->addError($attr, '打款网关无效（类不存在或非合法网关）');
            return;
        }
        // 必须落在 FundRegistry 可用清单内——挡住经 excludes 排除的网关(如 openid 取用错位的微信零钱),
        // 防止绕过后台下拉、直接 API 存入一个必失败/会 fatal 的网关。
        $available = array_column(FundRegistry::list((int)\Yii::$app->mall->id), 'class');
        if (!in_array($cls, $available, true)) {
            $this->addError($attr, '该打款网关当前不可用（未完成或已停用），请另选');
        }
    }

    /** 到账方式必须落在所选网关 getSupportedChannels() 之内 */
    public function validateDestination($attr)
    {
        $cls = $this->fund_class;
        if (!class_exists($cls) || !is_subclass_of($cls, BaseFund::class)) {
            return; // fund_class 已会报错
        }
        if (empty($this->destination)) {
            $this->addError($attr, '请选择到账方式');
            return;
        }
        try {
            $fund = \Yii::createObject($cls, [(int)\Yii::$app->mall->id]);
            $supported = $fund->getSupportedChannels();
            if (!in_array($this->destination, $supported, true)) {
                $this->addError($attr, '到账方式不在该网关支持范围内（' . implode('/', $supported) . '）');
            }
        } catch (\Throwable $e) {
            $this->addError($attr, '网关校验失败：' . $e->getMessage());
        }
    }

    public function validateUserTypes($attr)
    {
        $allowed = [Channel::USER_TYPE_USER, Channel::USER_TYPE_MERCHANT];
        $val = is_array($this->$attr) ? $this->$attr : [];
        if (empty($val)) {
            $this->addError($attr, '请至少选择一种适用用户类型');
            return;
        }
        if (array_diff($val, $allowed)) {
            $this->addError($attr, '适用用户类型包含非法值');
        }
    }

    public function validateAssetTypes($attr)
    {
        // 合法资产 = 核心 4 + 本商城授权插件的可提现钱包(统一取注册表,支持插件扩展)
        $allowed = \app\plugins\withdraw\services\AssetService::allAssetTypes((int)Yii::$app->mall->id);
        $val = is_array($this->$attr) ? $this->$attr : [];
        if (empty($val)) {
            $this->addError($attr, '请至少选择一种适用资金来源');
            return;
        }
        if (array_diff($val, $allowed)) {
            $this->addError($attr, '适用资金来源包含非法值');
        }
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)Yii::$app->mall->id;

        if ($this->id) {
            $channel = Channel::findOne(['id' => $this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
            if (!$channel) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '渠道不存在');
            }
        } else {
            $channel = new Channel();
            $channel->mall_id = $mallId;
        }

        $channel->name             = $this->name;
        $channel->icon             = $this->icon;
        $channel->subtitle         = $this->subtitle;
        $channel->color            = $this->color;
        $channel->fund_class       = $this->fund_class;
        $channel->destination      = $this->destination;
        $channel->fund_config      = $this->encodeJson($this->fund_config);
        $channel->user_types       = $this->encodeJson($this->user_types);
        $channel->asset_types      = $this->encodeJson($this->asset_types);
        $channel->onboarding_override = $this->encodeJson($this->onboarding_override);
        $channel->fee_rate         = $this->fee_rate;
        $channel->tax_rate         = $this->tax_rate;
        $channel->min_amount       = $this->min_amount;
        $channel->max_amount       = $this->max_amount;
        $channel->multiple         = $this->multiple;
        $channel->day_limit_amount = $this->day_limit_amount;
        $channel->day_limit_count  = (int)$this->day_limit_count;
        $channel->arrival_desc     = $this->arrival_desc;
        $channel->need_audit       = (int)$this->need_audit;
        $channel->sort             = (int)$this->sort;
        $channel->status           = (int)$this->status;
        $channel->remark           = $this->remark;
        // 提现规则自定义文案:留空则 C 端沿用默认规则(见 PrepareForm 下发与 withdraw.vue 兜底)
        $channel->rules            = (string)$this->rules;

        if (!$channel->save()) {
            return $this->responseErrorInfo($channel);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', ['id' => (int)$channel->id]);
    }

    public function switchStatus()
    {
        $mallId = (int)Yii::$app->mall->id;
        $channel = Channel::findOne(['id' => $this->id, 'mall_id' => $mallId, 'is_delete' => 0]);
        if (!$channel) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '渠道不存在');
        }
        $channel->status = $channel->status == Channel::STATUS_ON ? Channel::STATUS_OFF : Channel::STATUS_ON;
        if (!$channel->save(false)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '操作失败');
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功', ['status' => (int)$channel->status]);
    }

    private function encodeJson($val): string
    {
        if (!is_array($val)) {
            return '';
        }
        return json_encode($val, JSON_UNESCAPED_UNICODE);
    }
}
