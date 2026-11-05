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
use app\funds\BaseFund;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\plugins\mch\services\MchSettlementService;
use Yii;
use yii\helpers\ArrayHelper;

class SettingForm extends BaseModel
{
    public $form;

    public function rules()
    {
        return [[['form'], 'safe']];
    }

    public function getSetting()
    {
        // 安全合并：list 字段(allowed_mch_types)走显式覆盖，规避 ArrayHelper::merge 的 append 陷阱
        // （详见 OptionHelper::mergeSetting；开发规范 §2.3.1）
        $setting = OptionHelper::mergeSetting('mch_setting', \Yii::$app->mall->id, $this->getDefaults(), ['allowed_mch_types']);

        // 业务清洗：去重 + 转 int（兼容历史脏数据，曾被 merge append 写成 [1,2,4,"2",...] 混合数组）
        if (isset($setting['allowed_mch_types']) && is_array($setting['allowed_mch_types'])) {
            $setting['allowed_mch_types'] = array_values(array_unique(array_map('intval', $setting['allowed_mch_types'])));
        }

        // [修改] 同时加载普通代付和灵活用工两种渠道
        $allEnabledFunds = array_merge(
            OptionHelper::get('funds', $this->mall_id, []),
            OptionHelper::get('flexible_labor_funds', $this->mall_id, [])
        );

        $bank_funds = [];

        foreach ($allEnabledFunds as $class => $config) {
            if (isset($config['status']) && $config['status'] == 1 && class_exists($class)) {
                try {
                    /** @var BaseFund $fundInstance */
                    $fundInstance = Yii::createObject($class, [$this->mall_id]);
                    $display = $fundInstance->getDisplay();

                    if (in_array('bank', $fundInstance->getSupportedChannels())) {
                        $bank_funds[] = $display;
                    }
                } catch (\Exception $e) {
                    Yii::warning("加载商家插件资金渠道选项 '{$class}' 失败: " . $e->getMessage());
                }
            }
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'setting' => $setting,
                'bank_funds' => $bank_funds,
                // 「商家结算流向」下拉选项:核心账户 + 本商城已授权的可提现钱包(名称走词库)。
                // 装了什么插件就有什么钱包(mch 的货款、xwy 的渠道专户…),后台禁止硬编码这个列表。
                'settle_target_options' => MchSettlementService::settleTargetOptions((int)\Yii::$app->mall->id),
            ]
        ];
    }

    public function saveSetting()
    {
        // ... 此方法与您的版本完全一致，保持不变 ...
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $withdrawRules = $this->form['withdraw_rules'] ?? [];
        if (!empty($withdrawRules)) {
            $minAmount = (float)($withdrawRules['min_amount'] ?? 0);
            $dayMaxAmount = (float)($withdrawRules['day_max_amount'] ?? 0);
            if ($minAmount < 0.01) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '单笔最低提现金额不能小于0.01元'];
            }
            if ($dayMaxAmount > 0 && bccomp($minAmount, $dayMaxAmount, 2) > 0) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '单笔最低提现金额不能大于每日提现限额'];
            }
        }
        // 统一经营地址:开了开关就必须把地址配全。
        // 不拦的话会出现最尴尬的状态 —— 入驻表单已经不显示地址字段了(商家填不了),
        // 而写进库的又是空值,一批商户带着空地址入驻,事后只能一个个补。
        if (!empty($this->form['use_default_address'])) {
            $miss = [];
            if (trim((string)($this->form['default_province_code'] ?? '')) === '') $miss[] = '省份';
            if (trim((string)($this->form['default_city_code'] ?? '')) === '')     $miss[] = '城市';
            if (trim((string)($this->form['default_address'] ?? '')) === '')       $miss[] = '详细地址';
            if ($miss) {
                return ['code' => ApiCode::CODE_FAIL, 'msg' => '开启统一经营地址后,请填写' . implode('、', $miss)];
            }
        }

        // 商家结算流向:资金路径,非法值必须在落库前拦下(否则 MchSettlementService 只能回退 commission,钱进错账户)
        // 取值域是「核心账户 + 任意插件声明的扩展钱包」的开放注册表,不是封闭枚举 —— 校验与结算同源。
        // 空值/未传 = 未配置 → 放行,由 MchSettlementService 回退历史默认 commission。
        $settleTarget = $this->form['mch_settle_target'] ?? null;
        if (!MchSettlementService::isValidSettleTarget($settleTarget)) {
            $allowed = array_column(MchSettlementService::settleTargetOptions((int)\Yii::$app->mall->id), 'key');
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => '非法的商家结算流向,合法值:' . implode(' / ', $allowed),
            ];
        }
        // 非阻断提醒:该目标解析出的资产若没有任何启用的提现渠道,商家看得到货款却提不出来
        // (withdraw 插件对无渠道的资产是静默剔除,不报错)——保存仍然放行,只是把这件事从静默变成可见。
        $settleWarning = '';
        $mallId = (int)\Yii::$app->mall->id;
        $resolvedAsset = MchSettlementService::resolvePayoutType($settleTarget ?: 'commission');
        if (!MchSettlementService::hasWithdrawChannelFor($mallId, $resolvedAsset)) {
            $settleWarning = "「货款结算」目标「{$resolvedAsset}」暂无启用的提现渠道,商家将看得到余额但无法提现,"
                . '请前往提现插件的「渠道管理」为该资产配置至少一条渠道。';
        }
        // 落库前对"列表型"字段去重 + 转 int —— 不管前端传什么(历史脏数据 / 重复勾选),
        // 存进 DB 的永远是干净的 [1,2,4],从源头杜绝再次污染
        if (isset($this->form['allowed_mch_types']) && is_array($this->form['allowed_mch_types'])) {
            $this->form['allowed_mch_types'] = array_values(array_unique(array_map('intval', $this->form['allowed_mch_types'])));
        }
        // 入驻通知管理员：user_ids 去重转 int，避免前端脏数据
        if (isset($this->form['admin_notify']['user_ids']) && is_array($this->form['admin_notify']['user_ids'])) {
            $this->form['admin_notify']['user_ids'] = array_values(array_unique(array_map('intval', $this->form['admin_notify']['user_ids'])));
        }

        if (OptionHelper::set('mch_setting', $this->form, \Yii::$app->mall->id)) {
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功', 'warning' => $settleWarning];
        } else {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '保存失败'];
        }
    }

    private function getDefaults(): array
    {
        return [
            'is_mch_open' => 1,
            'allowed_mch_types' => [1, 2, 4], // 允许入驻的商家类型，默认：线上商家、门店商家、个人租户
            'entry_agreement_title' => '商家入驻协议',
            'entry_agreement_content' => '欢迎入驻！...',
            'instructions_title' => '入驻说明',
            'instructions_content' => '请填写真实有效的信息。',
            'review_type' => 1,

            // 统一经营地址:适用于「所有商户都在同一栋楼」的商城(商场 / 写字楼 / 商业综合体)。
            // 开启后入驻表单不再展示省市区镇 / 详细地址 / 地图选点,提交时一律写入下面配好的默认值。
            //
            // 只作用于**入驻**这一步:后台编辑商户时地址字段照常可改(同栋楼里也会有商户要标楼层铺号),
            // 已入驻的商户也不受影响(不回溯覆盖)。
            'use_default_address' => 0,
            'default_province_code' => '',
            'default_city_code' => '',
            'default_county_code' => '',
            'default_town_code' => '',
            'default_address' => '',
            'default_location_address' => '',
            'default_longitude' => '',
            'default_latitude' => '',

            // 商家推荐奖总开关：0=关闭（不挂载商品「商家推荐奖」Tab、订单不产生商家推荐奖），1=开启。
            'recruit_enable' => 0,
            // 商家推荐奖全局比例（%）：商品配置选「走全局」时按此比例发放。
            // 商家推荐奖 = 商家下单时，其上级（也是商家）按比例获得的分红。
            'recruit_rate' => 0,

            'withdraw_rules' => [
                'is_enabled' => 1,
                'min_amount' => '1.00',
                'max_amount' => '1.00',
                'day_max_count' => '3',
                'day_max_amount' => '10000.00',
                'service_fee_rate' => '0.6',
                'tax_rate' => '0',
                'fund_channel' => '',
                // onboarding_fund_channel 已移除：mch 不再单独维护进件，商家统一走 user 端核心 OnboardingService（渠道用平台 fund_channel）
            ],
            // 收银台配置已迁至 cashier 插件自有的 `cashier_setting`
            // （migration m260708_190000 搬迁存量并从本 option 摘除该键）。
            // 这里不再声明默认值 —— 否则 mergeSetting 会把它凭空写回来。
            'is_show_sales' => 1,

            // 商家结算流向:commission=进店长佣金账户(默认,与历史行为一致)/ balance=余额 / mch.funds=独立货款账户。
            // 默认 commission,保证现有商城零影响;需要"货款账户"分离的商城改此项。被 MchSettlementService 读取。
            'mch_settle_target' => 'commission',

            // 入驻通知管理员：商家提交入驻申请时，按下方配置多通道触达指定管理员。
            'admin_notify' => [
                'enabled'        => 0,          // 总开关
                'user_ids'       => [],         // 接收通知的管理员用户ID
                'users'          => [],         // 前端展示快照 [{id,nickname,avatar}]，后端忽略
                'channels'       => [],         // 启用通道：im / wx / sms
                'sms_dxb_text'   => '【商城】您有新的商家入驻申请：{mch_name}（{mch_type}），请尽快登录后台审核',
                'sms_aliyun_tpl' => '',         // 阿里云短信模板ID（变量 mch_name / mch_type）
                'sms_tx_tpl'     => '',         // 腾讯云短信模板ID
            ],
        ];
    }
}
