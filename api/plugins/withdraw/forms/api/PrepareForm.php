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

namespace app\plugins\withdraw\forms\api;

use app\core\ApiCode;
use app\helpers\UserHelper;
use app\models\BaseModel;
use app\models\User;
use app\plugins\withdraw\models\Channel;
use app\plugins\withdraw\services\AmountCalculator;
use app\plugins\withdraw\services\AssetService;
use app\plugins\withdraw\services\WithdrawService;

/**
 * C 端 - 提现准备页
 *
 * 按当前用户类型（商户/普通用户）跑渠道资格并集，按资金来源分组返回。
 * 仅返回「至少有一个可用渠道」的资金来源。
 */
class PrepareForm extends BaseModel
{
    public $asset_type; // 可选：只取某资金来源

    public function rules()
    {
        return [
            [['asset_type'], 'string'],
        ];
    }

    public function getData()
    {
        /** @var User $user */
        $user = UserHelper::getUser();
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先登录');
        }

        // 审核版(小程序 / App 提审中的版本号)整块不开放:不管从哪个页面跳进提现页,都只看到「暂无可用的提现方式」
        if (\app\helpers\VersionHelper::checkIsAuditMode((int)$this->mall_id)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
                'assets'   => [],
                'notice'   => '',
                'disabled' => 1,
            ]);
        }

        // 插件级设置：全局开关 + 提现须知
        $setting = \app\plugins\withdraw\forms\admin\setting\SettingForm::get((int)$this->mall_id);
        if (empty($setting['enabled'])) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
                'assets'   => [],
                'notice'   => $setting['notice'],
                'disabled' => 1, // 提现已全局停用
            ]);
        }

        $service = new WithdrawService();
        $assetKeys = $this->asset_type
            ? [$this->asset_type]
            : AssetService::allAssetTypes((int)$this->mall_id); // 核心 4 + 授权插件可提现钱包

        $assets = [];
        foreach ($assetKeys as $assetType) {
            if (!AssetService::isValid($assetType)) {
                continue;
            }
            // 逐用户可见性:扩展钱包对该用户不可见则跳过(如渠道专户按后台开关);
            // 放在 entries 开关前,兼顾 asset_type 深链——防止绕过入口直接对隐藏钱包发起提现
            if (\app\services\WalletRegistry::exists($assetType)
                && !\app\services\WalletRegistry::isVisible($assetType, (int)$user->id)) {
                continue;
            }
            // 各资产入口总开关：关闭则该资金来源处处不展示（与中心页按钮、prepare 一致）
            if (empty($setting['entries'][$assetType])) {
                continue;
            }
            $channels = $service->getAvailableChannels($user, (int)$this->mall_id, $assetType);
            if (empty($channels)) {
                continue;
            }

            $available = AssetService::balance($user, $assetType);
            $assets[] = [
                'key'       => $assetType,
                'label'     => AssetService::label($assetType),
                'available' => $available,
                'channels'  => array_map(function (Channel $c) use ($available, $service, $user) {
                    // 用可用余额给个到账预览（最终以后端 apply 计算为准）
                    $preview = AmountCalculator::calc($available, (string)$c->fee_rate, (string)$c->tax_rate);
                    // 资料是否就绪（委托网关 checkUserEligibility，balance 恒就绪）
                    $elig = $service->checkEligibility($user, $c);
                    return [
                        'id'           => (int)$c->id,
                        'name'         => $c->name,
                        'subtitle'     => $c->subtitle,
                        'icon'         => $c->icon,
                        'color'        => $c->color,
                        'destination'  => $c->destination,
                        // 进件必须按 fund_class 精确路由(灵工/对公/银行卡 destination 可能都是 bank),
                        // 前端"去完善资料"要带上它,否则会落到 destination 默认的 BankFund 被锁死。
                        'fund_class'   => $c->fund_class,
                        'fee_rate'     => $c->fee_rate,
                        'tax_rate'     => $c->tax_rate,
                        'min_amount'   => $c->min_amount,
                        'max_amount'   => $c->max_amount,
                        'multiple'     => $c->multiple,
                        // 渠道级每日次数 / 金额上限(0=不限),提现规则里要写给用户看(小程序审核要求)
                        'day_limit_count'  => (int)$c->day_limit_count,
                        'day_limit_amount' => $c->day_limit_amount,
                        'arrival_desc' => $c->arrival_desc,
                        // 提现规则:后台配了就下发(一行一条切成数组),前端追加在额度 / 次数 / 时间 / 到账这几条必备规则后面。
                        'rules'        => self::splitRules($c->rules),
                        'need_audit'   => (int)$c->need_audit,
                        'onboarding_ready' => !empty($elig['is_eligible']),
                        'need_onboarding'  => !empty($elig['require_onboarding']),
                        'preview_actual_by_all' => $preview['actual'],
                    ];
                }, $channels),
            ];
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'assets' => $assets,
            'notice' => $setting['notice'],
            // 全局每日提现次数(跨渠道合计,0=不限)
            'day_limit_count' => (int)($setting['day_limit_count'] ?? 0),
        ]);
    }

    /**
     * 把后台填的「提现规则」多行文本切成数组(一行一条)。
     * 兼容 \r\n / \r / \n 三种换行,过滤空行与首尾空白;未配置返回 [],
     * 由 C 端回退它自带的默认规则文案(见 withdraw.vue 的 withdrawRules)。
     */
    private static function splitRules($raw): array
    {
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }
        $lines = preg_split('/\r\n|\r|\n/', $raw);
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return $out;
    }
}
