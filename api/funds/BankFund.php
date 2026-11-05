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

namespace app\funds;

use app\helpers\OptionHelper;
use app\models\PayoutLog;
use app\models\UserSettlementInfo;
use Yii;
use yii\helpers\Json;

/**
 * 银行卡打款渠道实现类（全字段版）
 *
 * ── 字段总览 ──
 *   核心信息：account_type / real_name / bank_card_no / bank_name(+bank_code)
 *   支行：bank_branch_name(+bank_branch_no)
 *   联系/身份：mobile（银行预留）/ identity_no
 *   对公专用：province / city / use_desc
 *   备注：remark
 *
 * ── 数据存储 ──
 *   - 物理字段（real_name / mobile / identity_no）：UserSettlementInfo 主表
 *   - 其他业务字段：channel_data JSON 下的 BankFund::class 键
 *   - UserSettlementForm::save() 会自动按 hasAttribute 同步物理字段
 *
 * ── 前端字段类型 ──
 *   text / number / id_card：普通输入
 *   bank_picker：开户银行搜索器（选中后自动填 bank_name + bank_code）
 *   bank_branch_picker：联行号/支行搜索器（选中后自动填 bank_branch_name + bank_branch_no）
 *   select：下拉（{label,value} 数组）
 *   image：图片上传
 */
class BankFund extends BaseFund
{
    /** @var array 从数据库加载的配置 */
    private $config;

    /** 账户类型常量 */
    const ACCOUNT_PRIVATE = 'PRIVATE';
    const ACCOUNT_PUBLIC  = 'PUBLIC';

    public function __construct($mall_id)
    {
        $this->mall_id = $mall_id;
        $fundSettings = OptionHelper::get('funds', $mall_id);

        $this->config = $fundSettings[self::class] ?? ['name' => '银行卡转账', 'status' => 0];
        $this->name = $this->config['name'];
        $this->fund_type = self::class;
    }

    /**
     * @inheritdoc
     * 定义前端用户进件/签约时需要填写的字段
     *
     * 顺序经过设计：账户类型先行（影响后续必填）→ 户名 → 卡号 → 银行 → 手机 → 身份证 → 地址 → 用途 → 备注
     */
    public function getOnboardingFields(): array
    {
        return [
            // ─── 账户类型（决定后续动态必填）────────────────────────────
            [
                'key'         => 'account_type',
                'label'       => '账户类型',
                'type'        => 'select',
                'required'    => true,
                'placeholder' => '请选择账户类型',
                'options'     => [
                    ['label' => '对私（个人账户）', 'value' => self::ACCOUNT_PRIVATE],
                    ['label' => '对公（企业账户）', 'value' => self::ACCOUNT_PUBLIC],
                ],
                'note' => '对公账户需额外填写身份证、开户支行、用途等信息',
            ],

            // ─── 核心信息 ───────────────────────────────────────────
            [
                'key'         => 'real_name',
                'label'       => '账户名',
                'type'        => 'text',
                'required'    => true,
                'placeholder' => '请输入开户姓名 / 企业全称（须与银行预留一致）',
            ],
            [
                'key'         => 'bank_card_no',
                'label'       => '银行卡号',
                'type'        => 'number',
                'required'    => true,
                'placeholder' => '请输入银行卡号或对公账号',
            ],

            // ─── 开户银行（带搜索 picker）──────────────────────────────
            [
                'key'         => 'bank_name',
                'label'       => '开户银行',
                'type'        => 'bank_picker',
                'required'    => true,
                'placeholder' => '请选择开户银行（如：招商银行、工商银行）',
            ],

            // ─── 开户支行（带搜索 picker，对公必填）─────────────────────
            [
                'key'         => 'bank_branch_name',
                'label'       => '开户支行',
                'type'        => 'bank_branch_picker',
                'required'    => false,    // 动态：对公必填，在 onboardPerson 里判定
                'placeholder' => '请选择支行（对公账户必填）',
                'note'        => '对私账户可不填；对公账户必填，用于联行号校验',
            ],

            // ─── 银行预留手机号 ─────────────────────────────────────
            [
                'key'         => 'mobile',
                'label'       => '银行预留手机号',
                'type'        => 'number',
                'required'    => true,
                'placeholder' => '请输入银行预留手机号',
                'note'        => '用于身份验证及打款异常时联系',
            ],

            // ─── 身份证（对私可选，对公必填）─────────────────────────
            [
                'key'         => 'identity_no',
                'label'       => '身份证号',
                'type'        => 'id_card',
                'required'    => false,    // 动态判定：对公必填
                'placeholder' => '请输入持卡人身份证号',
                'note'        => '对私可选；对公必填（用于法人身份核验）',
            ],

            // ─── 开户行所在地区 ─────────────────────────────────────
            [
                'key'         => 'province',
                'label'       => '开户行省份',
                'type'        => 'text',
                'required'    => false,
                'placeholder' => '如：广东省',
            ],
            [
                'key'         => 'city',
                'label'       => '开户行城市',
                'type'        => 'text',
                'required'    => false,
                'placeholder' => '如：佛山市',
            ],

            // ─── 用途说明（对公账户用）───────────────────────────────
            [
                'key'         => 'use_desc',
                'label'       => '收款用途',
                'type'        => 'select',
                'required'    => false,    // 动态：对公必填
                'placeholder' => '请选择收款用途（对公必填）',
                'options'     => [
                    ['label' => '货款',     'value' => 'goods'],
                    ['label' => '服务费',   'value' => 'service'],
                    ['label' => '佣金结算', 'value' => 'commission'],
                    ['label' => '劳务报酬', 'value' => 'labor'],
                    ['label' => '其他',     'value' => 'other'],
                ],
            ],

            // ─── 备注 ───────────────────────────────────────────
            [
                'key'         => 'remark',
                'label'       => '备注',
                'type'        => 'text',
                'required'    => false,
                'placeholder' => '可选，最多 200 字',
            ],
        ];
    }

    /**
     * @inheritdoc
     * 校验用户提交的进件数据
     *
     * 校验策略：
     *   - 通用必填（账户类型 / 户名 / 卡号 / 开户行 / 手机号）
     *   - 卡号格式
     *   - 手机号格式
     *   - 身份证格式（如填写）
     *   - 对公账户额外必填：身份证 / 支行 / 用途
     */
    public function onboardPerson(array $userData): array
    {
        $accountType = $userData['account_type'] ?? '';

        // ─── 通用必填 ────────────────────────────────────────────
        if (!in_array($accountType, [self::ACCOUNT_PRIVATE, self::ACCOUNT_PUBLIC], true)) {
            return ['status' => 'failed', 'message' => '请选择账户类型（对私 / 对公）'];
        }
        if (empty($userData['real_name']) || mb_strlen(trim($userData['real_name'])) < 2) {
            return ['status' => 'failed', 'message' => '请输入有效的账户名（至少 2 个字符）'];
        }
        if (empty($userData['bank_card_no'])) {
            return ['status' => 'failed', 'message' => '请输入银行卡号'];
        }
        if (empty($userData['bank_name'])) {
            return ['status' => 'failed', 'message' => '请选择开户银行'];
        }
        if (empty($userData['mobile'])) {
            return ['status' => 'failed', 'message' => '请输入银行预留手机号'];
        }

        // ─── 格式校验 ────────────────────────────────────────────
        // 银行卡号：12-19 位纯数字（覆盖借记卡/储蓄卡/对公账号）
        if (!preg_match('/^\d{12,19}$/', $userData['bank_card_no'])) {
            return ['status' => 'failed', 'message' => '银行卡号格式不正确（应为 12-19 位数字）'];
        }
        // 手机号：11 位 1 开头
        if (!preg_match('/^1\d{10}$/', $userData['mobile'])) {
            return ['status' => 'failed', 'message' => '请输入正确的 11 位手机号'];
        }
        // 身份证（如有填写则校验格式）
        if (!empty($userData['identity_no'])) {
            if (!preg_match('/^[1-9]\d{5}(18|19|20)\d{2}((0[1-9])|(1[0-2]))(([0-2][1-9])|10|20|30|31)\d{3}[\dX]$/i', $userData['identity_no'])) {
                return ['status' => 'failed', 'message' => '身份证号格式不正确'];
            }
        }
        // 备注长度
        if (!empty($userData['remark']) && mb_strlen($userData['remark']) > 200) {
            return ['status' => 'failed', 'message' => '备注最多 200 字'];
        }

        // ─── 对公账户专属必填 ────────────────────────────────────
        if ($accountType === self::ACCOUNT_PUBLIC) {
            if (empty($userData['identity_no'])) {
                return ['status' => 'failed', 'message' => '对公账户必须填写法人身份证号'];
            }
            if (empty($userData['bank_branch_name']) && empty($userData['bank_branch_no'])) {
                return ['status' => 'failed', 'message' => '对公账户必须选择开户支行'];
            }
            if (empty($userData['use_desc'])) {
                return ['status' => 'failed', 'message' => '对公账户必须选择收款用途'];
            }
        }

        // ─── 联行号格式校验（如填写）─────────────────────────────
        // 标准联行号 12 位数字
        if (!empty($userData['bank_branch_no']) && !preg_match('/^\d{12}$/', $userData['bank_branch_no'])) {
            return ['status' => 'failed', 'message' => '联行号格式不正确（应为 12 位数字）'];
        }

        return [
            'status'  => 'success',
            'message' => '银行卡信息保存成功',
            'data'    => $userData,
        ];
    }

    /**
     * @inheritdoc
     * 检查用户是否有资格提现
     */
    public function checkUserEligibility(array $userData): array
    {
        $userId = $userData['user_id'] ?? null;
        if (!$userId) {
            return ['is_eligible' => false, 'message' => '用户ID缺失'];
        }

        if (empty($this->config['status'])) {
            return ['is_eligible' => false, 'message' => '银行卡提现通道暂时关闭'];
        }

        $statusInfo = $this->checkOnboardingStatus($userId);
        if ($statusInfo['status'] === UserSettlementInfo::FUND_STATUS_SUCCESS) {
            return ['is_eligible' => true, 'message' => '资格校验通过', 'result' => $statusInfo];
        }

        return [
            'is_eligible'       => false,
            'message'           => '请先填写银行卡收款信息',
            'require_onboarding' => true,
        ];
    }

    /**
     * @inheritdoc
     */
    public function checkOnboardingStatus(int $userId): array
    {
        $model = UserSettlementInfo::findOne(['user_id' => $userId, 'mall_id' => $this->mall_id]);
        if (!$model) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }

        $statusInfo = $model->getOnboardingStatus(self::class);
        if (empty($statusInfo)) {
            return ['status' => UserSettlementInfo::FUND_STATUS_NONE, 'message' => '未填写信息'];
        }

        // 回显数据
        $allChannelData = $model->getChannelData();
        $statusInfo['settlement_data'] = $allChannelData[self::class] ?? [];

        return $statusInfo;
    }

    /**
     * @inheritdoc
     * 提取用户银行卡账户信息（供 CashApplyForm / payout 使用）
     *
     * 优先级：channel_data JSON > UserSettlementInfo 物理字段
     */
    public function getUserAccountInfo($settlementInfo): array
    {
        if (!$settlementInfo) {
            return [];
        }

        $allChannelData = $settlementInfo->getChannelData();
        $myData = $allChannelData[self::class] ?? [];

        return [
            'type'             => 'bank',
            // 账户基本信息
            'account_type'     => $myData['account_type'] ?? self::ACCOUNT_PRIVATE,
            'real_name'        => $myData['real_name']    ?? $settlementInfo->real_name,
            'account'          => $myData['bank_card_no'] ?? '',   // 兼容旧字段名
            'bank_card_no'     => $myData['bank_card_no'] ?? '',
            // 开户行
            'bank_name'        => $myData['bank_name']    ?? ($myData['bankName'] ?? ''),  // 兼容历史 bankName
            'bank_code'        => $myData['bank_code']    ?? '',
            // 支行
            'bank_branch_name' => $myData['bank_branch_name'] ?? '',
            'bank_branch_no'   => $myData['bank_branch_no']   ?? '',
            // 联系方式 / 身份
            'mobile'           => $myData['mobile']      ?? $settlementInfo->mobile,
            'identity_no'      => $myData['identity_no'] ?? $settlementInfo->identity_no,
            // 地址 / 用途
            'province'         => $myData['province'] ?? '',
            'city'             => $myData['city']     ?? '',
            'use_desc'         => $myData['use_desc'] ?? '',
            'remark'           => $myData['remark']   ?? '',
        ];
    }

    /**
     * @inheritdoc
     * 银行卡渠道的提现冗余展示 — 把账户全字段都拼成 label/value 数组
     *
     * 字段顺序经过设计：账户类型 → 户名/卡号/银行 → 支行/地区 → 联系/身份 → 用途/备注
     * 空值字段自动跳过（不污染列表）
     */
    public function getPayoutDisplay(array $accountData): array
    {
        $list = [];

        // 账户类型（对私/对公）—— 最重要，放最前面
        $accountType = $accountData['account_type'] ?? self::ACCOUNT_PRIVATE;
        $list[] = [
            'label' => '账户类型',
            'value' => $accountType === self::ACCOUNT_PUBLIC ? '对公账户' : '对私账户',
        ];

        // 户名 / 卡号
        if (!empty($accountData['real_name'])) {
            $list[] = ['label' => '户名', 'value' => $accountData['real_name']];
        }
        if (!empty($accountData['account']) || !empty($accountData['bank_card_no'])) {
            $cardNo = $accountData['bank_card_no'] ?? $accountData['account'];
            $list[] = ['label' => '银行卡号', 'value' => $cardNo];
        }

        // 开户银行（如有银行编码，附带显示）
        if (!empty($accountData['bank_name'])) {
            $bankName = $accountData['bank_name'];
            if (!empty($accountData['bank_code'])) {
                $bankName .= ' [' . $accountData['bank_code'] . ']';
            }
            $list[] = ['label' => '开户银行', 'value' => $bankName];
        }

        // 开户支行 + 联行号
        if (!empty($accountData['bank_branch_name'])) {
            $branch = $accountData['bank_branch_name'];
            if (!empty($accountData['bank_branch_no'])) {
                $branch .= ' (联行号: ' . $accountData['bank_branch_no'] . ')';
            }
            $list[] = ['label' => '开户支行', 'value' => $branch];
        } elseif (!empty($accountData['bank_branch_no'])) {
            $list[] = ['label' => '联行号', 'value' => $accountData['bank_branch_no']];
        }

        // 开户行所在地区（省+市合并）
        $region = trim(($accountData['province'] ?? '') . ' ' . ($accountData['city'] ?? ''));
        if ($region !== '') {
            $list[] = ['label' => '开户地区', 'value' => $region];
        }

        // 联系方式 / 身份信息
        if (!empty($accountData['mobile'])) {
            $list[] = ['label' => '预留手机', 'value' => $accountData['mobile']];
        }
        if (!empty($accountData['identity_no'])) {
            $list[] = ['label' => '身份证号', 'value' => $accountData['identity_no']];
        }

        // 收款用途（对公）
        if (!empty($accountData['use_desc'])) {
            $useMap = [
                'goods' => '货款', 'service' => '服务费', 'commission' => '佣金结算',
                'labor' => '劳务报酬', 'other' => '其他',
            ];
            $list[] = [
                'label' => '收款用途',
                'value' => $useMap[$accountData['use_desc']] ?? $accountData['use_desc'],
            ];
        }

        // 用户备注
        if (!empty($accountData['remark'])) {
            $list[] = ['label' => '备注', 'value' => $accountData['remark']];
        }

        return $list;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedChannels(): array
    {
        return ['bank'];
    }

    /**
     * @inheritdoc
     * 执行打款（手动模式：记录待管理员处理）
     */
    public function payout(array $payoutData): array
    {
        $channel = $payoutData['channel'];
        $userId  = $payoutData['user_id'];

        if ($channel !== 'bank') {
            throw new \Exception("银行卡渠道不支持 '{$channel}' 提现方式。");
        }

        // 必填字段最小集合
        if (empty($payoutData['account']) || empty($payoutData['real_name']) || empty($payoutData['bank_name'])) {
            throw new \Exception('打款参数缺失：银行卡号、户名或开户行不能为空');
        }

        try {
            $payoutLog = new PayoutLog();
            $payoutLog->mall_id     = $this->mall_id;
            $payoutLog->user_id     = $userId;
            $payoutLog->source_id   = $payoutData['source_id'];
            $payoutLog->source_type = $payoutData['source_type'];
            $payoutLog->fund_class  = self::class;
            $payoutLog->channel     = 'bank';
            $payoutLog->amount      = $payoutData['amount'];
            $payoutLog->status      = PayoutLog::STATUS_PENDING;

            // 记录完整打款信息（管理员后台查看用）—— 全部字段透传
            $requestData = [
                'account_type'     => $payoutData['account_type']     ?? self::ACCOUNT_PRIVATE,
                'real_name'        => $payoutData['real_name'],
                'bank_card_no'     => $payoutData['account'],
                'bank_name'        => $payoutData['bank_name'],
                'bank_code'        => $payoutData['bank_code']        ?? '',
                'bank_branch_name' => $payoutData['bank_branch_name'] ?? '',
                'bank_branch_no'   => $payoutData['bank_branch_no']   ?? '',
                'mobile'           => $payoutData['mobile']           ?? '',
                'identity_no'      => $payoutData['identity_no']      ?? '',
                'province'         => $payoutData['province']         ?? '',
                'city'             => $payoutData['city']             ?? '',
                'use_desc'         => $payoutData['use_desc']         ?? '',
                'remark'           => $payoutData['remark'] ?? ($payoutData['user_remark'] ?? '申请提现到银行卡'),
            ];

            $payoutLog->request_data = Json::encode($requestData);
            $payoutLog->payout_no    = 'BANK-' . $payoutData['order_no'];

            if (!$payoutLog->save()) {
                throw new \Exception('保存流水失败: ' . json_encode($payoutLog->errors));
            }

            // 银行卡是线下手动转账:此处只落待办流水,钱尚未打出。
            // 必须返回 'doing'(打款中)而非 'success',否则上层会立即置"已到账",
            // 造成"显示已打款实则未转账"的对账错乱/资损。等管理员线下转账后再手动「确认到账」。
            return [
                'status'    => 'doing',
                'message'   => '申请已记录，等待管理员线下转账后确认到账',
                'payout_no' => $payoutLog->payout_no,
            ];

        } catch (\Exception $e) {
            Yii::error("[BankFund::payout] 异常: " . $e->getMessage());
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @inheritdoc
     * 查询支持的银行列表（按关键字模糊匹配）
     *
     * 数据源：硬编码常用国内商业银行列表（含 CNAPS 银行编码）。
     * 关键字为空时返回全部；否则按 name 模糊匹配，最多返回 30 条。
     */
    public function queryBankInfo($bankName = null): array
    {
        $allBanks = $this->getBankDictionary();
        $keyword = trim((string)$bankName);
        if ($keyword === '') {
            return ['bankList' => array_slice($allBanks, 0, 30)];
        }

        $matched = array_values(array_filter($allBanks, function ($bank) use ($keyword) {
            return mb_stripos($bank['bankName'], $keyword) !== false
                || ($bank['bankCode'] !== '' && mb_stripos($bank['bankCode'], $keyword) !== false);
        }));

        return ['bankList' => array_slice($matched, 0, 30)];
    }

    /**
     * 常用国内商业银行字典（CNAPS 行号前 3 位 + 名称）
     * 银行编码用于跨行转账识别，由 BankFund 直接维护，无需建独立表
     */
    private function getBankDictionary(): array
    {
        return [
            // 大型国有商业银行
            ['bankName' => '中国工商银行',     'bankCode' => 'ICBC'],
            ['bankName' => '中国农业银行',     'bankCode' => 'ABC'],
            ['bankName' => '中国银行',         'bankCode' => 'BOC'],
            ['bankName' => '中国建设银行',     'bankCode' => 'CCB'],
            ['bankName' => '交通银行',         'bankCode' => 'BCM'],
            ['bankName' => '邮政储蓄银行',     'bankCode' => 'PSBC'],
            // 股份制商业银行
            ['bankName' => '招商银行',         'bankCode' => 'CMB'],
            ['bankName' => '中信银行',         'bankCode' => 'CITIC'],
            ['bankName' => '光大银行',         'bankCode' => 'CEB'],
            ['bankName' => '华夏银行',         'bankCode' => 'HXB'],
            ['bankName' => '民生银行',         'bankCode' => 'CMBC'],
            ['bankName' => '广发银行',         'bankCode' => 'CGB'],
            ['bankName' => '平安银行',         'bankCode' => 'PAB'],
            ['bankName' => '兴业银行',         'bankCode' => 'CIB'],
            ['bankName' => '浦发银行',         'bankCode' => 'SPDB'],
            ['bankName' => '浙商银行',         'bankCode' => 'CZB'],
            ['bankName' => '恒丰银行',         'bankCode' => 'HFB'],
            ['bankName' => '渤海银行',         'bankCode' => 'CBHB'],
            // 城市商业银行（部分常用）
            ['bankName' => '北京银行',         'bankCode' => 'BOB'],
            ['bankName' => '上海银行',         'bankCode' => 'BOS'],
            ['bankName' => '宁波银行',         'bankCode' => 'NBCB'],
            ['bankName' => '南京银行',         'bankCode' => 'NJCB'],
            ['bankName' => '江苏银行',         'bankCode' => 'JSB'],
            ['bankName' => '杭州银行',         'bankCode' => 'HZB'],
            ['bankName' => '广州银行',         'bankCode' => 'GZB'],
            ['bankName' => '徽商银行',         'bankCode' => 'HSB'],
            ['bankName' => '成都银行',         'bankCode' => 'BOCD'],
            ['bankName' => '盛京银行',         'bankCode' => 'SJB'],
            ['bankName' => '青岛银行',         'bankCode' => 'QDCCB'],
            ['bankName' => '哈尔滨银行',       'bankCode' => 'HRB'],
            ['bankName' => '天津银行',         'bankCode' => 'BOTJ'],
            ['bankName' => '长沙银行',         'bankCode' => 'BOCS'],
            // 农商行 / 信用社
            ['bankName' => '广州农村商业银行', 'bankCode' => 'GRCB'],
            ['bankName' => '深圳农村商业银行', 'bankCode' => 'SRCB'],
            ['bankName' => '上海农村商业银行', 'bankCode' => 'SHRCB'],
            ['bankName' => '北京农村商业银行', 'bankCode' => 'BJRCB'],
            ['bankName' => '重庆农村商业银行', 'bankCode' => 'CQRCB'],
            ['bankName' => '农村信用合作社',   'bankCode' => 'RCC'],
            // 外资银行（部分）
            ['bankName' => '汇丰银行',         'bankCode' => 'HSBC'],
            ['bankName' => '渣打银行',         'bankCode' => 'SCB'],
            ['bankName' => '花旗银行',         'bankCode' => 'CITI'],
            ['bankName' => '东亚银行',         'bankCode' => 'BEA'],
            // 政策性银行
            ['bankName' => '国家开发银行',     'bankCode' => 'CDB'],
            ['bankName' => '中国进出口银行',   'bankCode' => 'CEXIM'],
            ['bankName' => '中国农业发展银行', 'bankCode' => 'ADBC'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function getDisplay()
    {
        return ['name' => $this->name, 'fund' => self::class];
    }
}
