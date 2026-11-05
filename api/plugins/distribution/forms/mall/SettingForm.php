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

namespace app\plugins\distribution\forms\mall;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\services\CommissionService;
use app\services\WalletRegistry;
use app\models\Level;
use Yii;

class SettingForm extends BaseModel
{
    public $form;

    public function rules()
    {
        return [['form'], 'safe'];
    }

    public function get()
    {
        $default = $this->getDefaults();
        $setting = OptionHelper::get('distribution_setting', $this->mall_id);

        if (!$setting) {
            $setting = $default;
        } else {
            // levels 为空则用默认；其余字段用默认补全新增项
            if (empty($setting['levels'])) {
                $setting['levels'] = $default['levels'];
            }
            $setting = array_merge($default, $setting);
        }

        // 会员等级列表（分润配置按会员等级配，供前端渲染每个等级一行）
        $memberLevels = Level::find()
            ->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->andWhere(['enabled' => 1])
            ->orderBy(['level' => SORT_ASC])
            ->select(['id', 'name', 'level', 'is_inviter'])
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'setting'       => $setting,
                'member_levels' => $memberLevels,
            ],
        ];
    }

    public function save()
    {
        $this->form = $this->sanitize((array) $this->form);

        if (OptionHelper::set('distribution_setting', $this->form, $this->mall_id)) {
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功'];
        }
        return ['code' => ApiCode::CODE_FAIL, 'msg' => '保存失败'];
    }

    /**
     * 落库前清洗数值项。
     *
     * rules 是 [['form'],'safe'],前端 input-number 的上下限只是界面约束、拦不住直接调接口。
     * settle_delay_days 尤其要卡:due_at = 计提时刻 + N*86400 要写进 INT 列(上限 2147483647),
     * 传个离谱的大数会让计划写库失败 → 佣金既没登记到期计划、又跳过了即时结算,永远卡在待结算。
     * 与 dealer 的 SettingForm 同口径。
     */
    private function sanitize(array $form): array
    {
        if (array_key_exists('settle_delay_days', $form)) {
            $form['settle_delay_days'] = min(365, max(0, (int) $form['settle_delay_days']));
        }
        if (array_key_exists('level_count', $form)) {
            $form['level_count'] = max(0, (int) $form['level_count']);
        }
        if (array_key_exists('commission_base', $form)) {
            $form['commission_base'] = (int) $form['commission_base'];
        }
        if (array_key_exists('self_buy', $form)) {
            $form['self_buy'] = (int) $form['self_buy'] ? 1 : 0;
        }
        if (array_key_exists('is_dist_level_enabled', $form)) {
            $form['is_dist_level_enabled'] = (int) $form['is_dist_level_enabled'] ? 1 : 0;
        }

        // 分润比例卡 0~100。本插件是**多级累加**(一级10% + 二级5% = 共发 15%),
        // 单级配个 10000% 就意味着一张 1000 元的订单要分出 10 万。
        // 这里只卡单级 —— 「各级之和是否允许超过 100%」是业务决策,不由代码替运营定。
        $clampRates = function ($rows) {
            if (!is_array($rows)) {
                return $rows;
            }
            foreach ($rows as $i => $row) {
                if (is_array($row) && array_key_exists('reward_rate', $row)) {
                    $v = ($row['reward_rate'] === '' || $row['reward_rate'] === null)
                        ? '' : number_format(min(100, max(0, (float) $row['reward_rate'])), 2, '.', '');
                    $rows[$i]['reward_rate'] = $v;   // 留空要保持留空:那是「回退全局默认」的信号
                }
            }
            return $rows;
        };
        if (array_key_exists('levels', $form)) {
            $form['levels'] = $clampRates($form['levels']);
        }
        if (array_key_exists('level_rules', $form) && is_array($form['level_rules'])) {
            foreach ($form['level_rules'] as $lvId => $rows) {
                $form['level_rules'][$lvId] = $clampRates($rows);
            }
        }
        if (array_key_exists('settlement_timing', $form)
            && !in_array($form['settlement_timing'], ['completed', 'payment', 'manual'], true)) {
            $form['settlement_timing'] = 'completed';
        }
        if (array_key_exists('payout_target', $form)
            && !in_array((string) $form['payout_target'], $this->allowedPayoutTargets(), true)) {
            // 落到一个不存在的账户 = 钱发不出去还查不到,宁可回落到默认的收入账户
            $form['payout_target'] = 'price';
        }
        return $form;
    }

    /**
     * 可选的落账账户。
     *
     * ⛔ 不写死枚举：核心三账户之外，任何插件通过 Plugin::getWallets() 声明并被本商城授权的
     *    扩展钱包都是合法目标（规范：payoutType 是开放注册表）。所以这里按**活的注册表**取。
     */
    private function allowedPayoutTargets(): array
    {
        $core = [
            CommissionService::PAYOUT_TO_PRICE,
            CommissionService::PAYOUT_TO_BALANCE,
            CommissionService::PAYOUT_TO_INTEGRAL,
        ];
        try {
            $wallets = array_column(WalletRegistry::all($this->mall_id), 'key');
        } catch (\Throwable $e) {
            $wallets = [];   // 注册表异常不该让保存设置整个失败
        }
        return array_values(array_unique(array_merge($core, $wallets)));
    }

    private function getDefaults(): array
    {
        return [
            'is_enable'         => 0,
            'level_count'       => 1,
            'settlement_timing' => 'completed',
            // 佣金计算基数：0=订单实付 1=利润(实付-成本) 2=商品成本 3=商品现价
            'commission_base'   => 0,
            // 自购返佣：开启后分销员自己下单也计佣，自己占一级、上级依次顺延（总发放比例不变）
            'self_buy'          => 0,
            // 延迟结算天数：0=不延迟（按 settlement_timing）；>0 则计提后满 N 天由每日任务发放
            'settle_delay_days' => 0,
            // 佣金落账账户：price=收入(需提现) balance=余额 integral=积分，也可填任意已授权的扩展钱包 key
            'payout_target'     => 'price',
            // 是否启用「按会员等级分润」：0 时 level_rules 一律不生效、全部走全局 levels。
            //
            // ⚠ 默认必须是 0,不能想当然改成 1。这个 key 在 2026-06-01 的重构(90d8b8f23e)里
            //   被从界面和默认值中删掉,但 6-02 的 a8bfbb298d 又把后端依赖加了回来 ——
            //   于是此后新建的商城全都没有这个 key、恒为「关」,而 !empty(null) 也是 false。
            //   默认填 0 = 与这些商城的既有行为**逐分钱一致**;填 1 会让它们下次保存设置时
            //   突然改用 level_rules,分润金额无声突变。
            //   真正的修复是把开关放回界面(设置页「按会员等级分润」tab),让它可见可控。
            'is_dist_level_enabled' => 0,
            // 全局默认多级分润比例（一级/二级…）
            'levels' => [
                ['level' => 1, 'name' => '一级分销', 'reward_rate' => '10.00'],
            ],
            // 按会员等级覆盖比例：{ "会员level_id": [{level,name,reward_rate},...] }
            // 留空的等级走全局默认；单层级留空也回退全局默认
            'level_rules' => new \stdClass(),
        ];
    }
}
