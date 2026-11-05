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

namespace app\services;

use app\core\ApiCode;
use app\funds\BaseFund;
use app\helpers\OptionHelper;
use app\models\UserSettlementInfo;
use Yii;

/**
 * 用户进件/签约服务层 [最终完整版]
 *
 * 核心职责：
 * 1. 动态解析渠道配置 (resolveFundClass)。
 * 2. 协调 UserSettlementInfo 模型与具体 Fund 实现类的数据交互。
 * 3. 统一处理进件(start)、验证(verify)和查询(queryBankCode)流程。
 */
class OnboardingService
{
    private $mall_id;

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
    }

    /**
     * [核心方法] 解析渠道标识对应的 Fund 类名
     * 完全依赖后台 `ChannelForm` 保存的配置 `fund_channel`
     *
     * @param string $channelIdentifier 前端传来的标识，例如 'bank', 'alipay', 'wechat', 'balance'
     * @return string|null 具体的全限定类名 (e.g., app\funds\AlipayFund)
     */
    public function resolveFundClass(string $channelIdentifier): ?string
    {
        // 1. 获取后台配置的映射表
        $config = OptionHelper::get('fund_channel', $this->mall_id, []);

        // 2. 特殊逻辑：如果是 'bank' 且是进件场景
        // 优先查看是否配置了“默认进件渠道”(default_onboarding_fund)
        if ($channelIdentifier === 'bank') {

            // 如果没配进件通道，尝试使用打款通道配置作为替补
            if (!empty($config['bank'])) {
                return $config['bank'];
            }
            if (!empty($config['default_onboarding_fund'])) {
                return $config['default_onboarding_fund'];
            }
            // 兜底：如果都没配，默认使用 BankFund (手动转账)
            return 'app\\funds\\BankFund';
        }

        // 3. 其他渠道 (alipay, wechat, balance) 直接查找对应 key
        if (!empty($config[$channelIdentifier])) {
            return $config[$channelIdentifier];
        }

        // 4. 容错逻辑：如果传入的本身就是一个类名，直接返回
        if (class_exists($channelIdentifier)) {
            return $channelIdentifier;
        }

        // 5. 硬编码兜底 (防止后台完全未配置时的崩溃)
        $fallbackMap = [
            'alipay' => 'app\\funds\\AlipayFund',
            'wechat' => 'app\\funds\\WechatFund',
            'balance' => 'app\\funds\\BalanceFund',
        ];

        return $fallbackMap[$channelIdentifier] ?? null;
    }

    /**
     * [核心] 确定本次进件真正要落地的 Fund 类。
     *
     * 优先级:显式传入的 $fundClass(完整类名,须为 BaseFund 子类) > 按语义渠道解析 resolveFundClass($channel)。
     *
     * 为什么需要它:提现渠道(plugin_withdraw_channel)按 `fund_class` 区分(银行卡=BankFund、
     * 灵工=FastPayFlexibleLaborFund、对公=ManualFund),但它们的 `destination` 可能都是 'bank'。
     * 若进件仍按 destination('bank') 解析,三者会被挤到同一个 BankFund——字段拿错、状态共用、
     * 一个 success 全体锁死(灵工的远程签约永远不触发)。所以资格判定/打款用哪个类,进件就必须用同一个类。
     *
     * 校验 is_subclass_of(BaseFund) 同时堵住"客户端传任意类名 → Yii::createObject 实例化"的隐患。
     *
     * @param string|null $fundClass 显式指定的完整类名(可空)
     * @param string      $channel   语义渠道标识(bank/alipay/wechat/balance),$fundClass 无效时的兜底
     * @return string|null
     */
    public function resolveTargetFundClass(?string $fundClass, string $channel): ?string
    {
        // 归一化:去掉前导反斜杠。PHP 的 class_exists/is_subclass_of 对 '\app\funds\X' 与
        // 'app\funds\X' 都为真,但 self::class 恒为无前导斜杠形式。若不归一化,脏数据带斜杠的
        // fund_class 会让"写状态(用传入串)"与"读状态(用 self::class)"落到两个 key,导致签约状态读不到、
        // 用户被反复要求重签。统一成 self::class 的规范形式(无前导斜杠)。
        $fundClass = $fundClass !== null ? ltrim($fundClass, '\\') : null;
        if (!empty($fundClass)
            && class_exists($fundClass)
            && is_subclass_of($fundClass, BaseFund::class)) {
            return $fundClass;
        }
        return $this->resolveFundClass($channel);
    }

    /**
     * 根据渠道标识获取其实例
     * @param string $channel
     * @return BaseFund
     * @throws \Exception
     */
    public function getFundInstanceByChannel(string $channel): BaseFund
    {
        $fundClass = $this->resolveFundClass($channel);

        if (!$fundClass || !class_exists($fundClass)) {
            throw new \Exception("未找到渠道 [{$channel}] 对应的处理类，请检查后台[打款通道配置]。");
        }

        return Yii::createObject($fundClass, [$this->mall_id]);
    }

    /**
     * 开始为用户执行进件/签约流程
     *
     * 逻辑：
     * 1. 解析目标渠道类。
     * 2. 从数据库读取用户的所有信息（物理字段 + 该渠道的JSON数据）。
     * 3. 将合并后的数据交给渠道类的 onboardPerson 方法处理。
     * 4. 根据处理结果更新数据库状态。
     *
     * @param int $userId
     * @param string $targetChannelOrClass 渠道标识('alipay') 或 类名
     * @return array 渠道方返回的结果
     * @throws \Exception
     */
    public function start(int $userId, string $targetChannelOrClass): array
    {
        // 1. 解析真正的目标类名
        $targetFundClass = $this->resolveFundClass($targetChannelOrClass);

        if (!$targetFundClass || !class_exists($targetFundClass)) {
            throw new \Exception("无效的进件渠道或配置缺失: {$targetChannelOrClass}");
        }

        // 实例化 Fund
        /** @var BaseFund $fundInstance */
        $fundInstance = Yii::createObject($targetFundClass, [$this->mall_id]);

        $settlementInfo = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id, 'is_delete' => 0]);
        if (!$settlementInfo) {
            throw new \Exception('用户结算信息不存在，无法开始进件');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 2. 准备全量数据
            // a. 物理字段 (共享字段，如 user_id, mobile, identity_no, real_name)
            $baseData = $settlementInfo->toArray();

            // b. 渠道专属数据 (从 JSON channel_data 中取)
            $allChannelData = $settlementInfo->getChannelData();
            $channelSpecificData = $allChannelData[$targetFundClass] ?? [];

            // c. 合并：渠道专属数据优先级 > 物理字段
            // 这样 Fund 内部可以灵活决定使用哪个字段，Fund 类不需要知道 UserSettlementInfo 的存在
            $fullPayload = array_merge($baseData, $channelSpecificData);

            // 3. 调用渠道进件逻辑
            // BankFund: 仅做校验，返回 success
            // 第三方API (如FastPay): 调用接口，返回 pending 或 success
            $result = $fundInstance->onboardPerson($fullPayload);

            Yii::warning("Onboarding start result [{$targetFundClass}]: " . json_encode($result));

            // 4. 更新该渠道的状态
            if (isset($result['status'])) {
                $statusInfo = [
                    'status' => $result['status'], // e.g., 'success', 'pending_verify'
                    'remark' => $result['message'] ?? '',
                    // 部分渠道可能返回第三方给的用户ID (sub_mchid, sign_id等)
                    'fund_user_id' => $result['data']['userId'] ?? ($result['data']['sign_id'] ?? null),
                ];

                // 将状态更新到 UserSettlementInfo 的 extra_info JSON 中
                $settlementInfo->updateOnboardingStatus($targetFundClass, $statusInfo);

                if (!$settlementInfo->save()) {
                    throw new \Exception('更新进件状态失败: ' . json_encode($settlementInfo->errors));
                }
            }

            $transaction->commit();
            return $result;

        } catch (\Exception $e) {
            $transaction->rollBack();

            // 异常时尝试记录失败状态（可选，视业务需求而定）
            // 如果希望记录报错原因供前端显示，可以打开下面的逻辑
            if (isset($settlementInfo)) {
                $statusInfo = ['status' => UserSettlementInfo::FUND_STATUS_FAILED, 'remark' => $e->getMessage()];
                $settlementInfo->updateOnboardingStatus($targetFundClass, $statusInfo);
                // 这里使用 save(false) 防止再次触发验证异常
                $settlementInfo->save(false);
            }

            throw $e;
        }
    }

    /**
     * 验证进件流程（例如提交短信验证码）
     *
     * @param int $userId
     * @param array $verifyData 前端提交的数据，通常包含 code, channel 等
     * @return array
     * @throws \Exception
     */
    public function verify(int $userId, array $verifyData): array
    {
        $settlementInfo = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$settlementInfo) {
            throw new \Exception('结算信息不存在');
        }

        // 1. 确定要验证哪个渠道
        // 优先用精确类名 fund_class(提现渠道按类区分),否则按语义 channel 解析
        $targetChannel = $verifyData['channel'] ?? null;
        $explicitFundClass = $verifyData['fund_class'] ?? null;
        $targetFundClass = null;

        if (!empty($explicitFundClass) || !empty($targetChannel)) {
            $targetFundClass = $this->resolveTargetFundClass($explicitFundClass, (string)$targetChannel);
        }

        // 如果前端没传 channel，尝试自动查找处于 "待验证" 状态的渠道
        if (!$targetFundClass) {
            $onboardingStatuses = $settlementInfo->getExtraInfo()['onboarding_status'] ?? [];
            foreach ($onboardingStatuses as $fundClass => $statusInfo) {
                // 查找处于 pending 状态的渠道
                if (in_array($statusInfo['status'], [UserSettlementInfo::FUND_STATUS_PENDING, 'pending_verify'])) {
                    $targetFundClass = $fundClass;
                    break;
                }
            }
        }

        if (!$targetFundClass || !class_exists($targetFundClass)) {
            throw new \Exception('未找到需要验证的进件流程');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            /** @var BaseFund $fundInstance */
            $fundInstance = Yii::createObject($targetFundClass, [$this->mall_id]);

            // 调用渠道验证接口
            // 注意：verifyOnboarding 在 BaseFund 基类中有默认实现（直接抛异常），
            // 所以 method_exists 永远为 true，无法判断具体渠道是否真正支持验证；
            // 必须用反射判断该方法是否被子类覆写过。
            $reflection = new \ReflectionMethod($fundInstance, 'verifyOnboarding');
            if ($reflection->getDeclaringClass()->getName() === BaseFund::class) {
                Yii::error("[Onboarding] 渠道 {$targetFundClass} 进入了验证流程，但未实现 verifyOnboarding()，请检查该渠道 onboardPerson() 是否不应返回 pending_verify");
                throw new \Exception('当前渠道暂不支持短信验证，请刷新页面查看认证状态或联系客服');
            }

            $result = $fundInstance->verifyOnboarding($userId, $verifyData);

            // 更新状态
            $statusInfo = [
                'status' => $result['status'], // expected: 'success' or 'pending'
                'remark' => $result['message'] ?? '验证成功',
            ];
            $settlementInfo->updateOnboardingStatus($targetFundClass, $statusInfo);

            if (!$settlementInfo->save()) {
                throw new \Exception('更新验证状态失败');
            }

            $transaction->commit();
            return $result;

        } catch (\Exception $e) {
            $transaction->rollBack();
            // 验证失败更新状态
            $statusInfo = ['status' => UserSettlementInfo::FUND_STATUS_FAILED, 'remark' => '验证失败: ' . $e->getMessage()];
            $settlementInfo->updateOnboardingStatus($targetFundClass, $statusInfo);
            $settlementInfo->save();

            throw $e;
        }
    }

    /**
     * 银行列表查询
     * 动态调用 'bank' 渠道对应的实现类，获取支持的银行列表
     */
    public function queryBankCode(string $bankName): array
    {
        try {
            // 1. 获取当前配置的银行渠道实例
            // 这里的 'bank' 会被 resolveFundClass 解析为 BankFund, FastPayAgentPayFund 等
            $fundInstance = $this->getFundInstanceByChannel('bank');

            // 2. 检查能力：如果渠道不支持查询（如手动打款），直接返回空列表或假数据
            if (!method_exists($fundInstance, 'queryBankInfo')) {
                return [
                    'code' => ApiCode::CODE_SUCCESS,
                    'msg' => '当前渠道无需查询银行信息',
                    'data' => ['list' => []]
                ];
            }

            // 3. 调用查询
            $resultData = $fundInstance->queryBankInfo($bankName);

            return [
                'code' => ApiCode::CODE_SUCCESS,
                'msg' => '查询成功',
                'data' => [
                    'list' => $resultData['bankList'] ?? []
                ]
            ];

        } catch (\Exception $e) {
            Yii::error("[提现服务-银行查询] 异常: " . $e->getMessage());
            return [
                'code' => ApiCode::CODE_FAIL,
                'msg' => $e->getMessage(),
                'data' => null
            ];
        }
    }
}