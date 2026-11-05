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
/**
 * Created by PhpStorm.
 * User: dujxmall
 * Date: 2023/1/17
 * Time: 11:43
 */

namespace app\helpers;


use app\models\Noun;

class NounHelper
{
    /**
     * 品牌词库标准字典 —— 唯一权威 key 清单
     *
     * key 一律小写英文；value 为默认词。
     * ⚠️ 必须与 uniapp/utils/nouns.js 的 NOUN_DEFAULTS 保持一致。
     *    新增词时两处同步 + 更新 docs/开发规范.md 词库字典。
     */
    const DICTIONARY = [
        'balance'    => '余额',
        'integral'   => '积分',
        'commission' => '佣金',
        'withdraw'   => '提现',
        'coupon'     => '优惠券',
        'level'      => '等级',
        // 后台充值/扣除交易流水描述(ChargeForm 写入余额/积分/佣金明细,用户可见)。后台可改叫法。
        'charge_add'    => '后台充值',
        'charge_sub'    => '后台扣除',
        'charge_source' => '后台操作',
        // 余额到账流水文案模板(BalanceFund 打款到余额时写入用户明细,后台可改)。
        // 占位符 {key}：数据变量 {type}资产名 {order_no}提现单号 {amount}金额；或词库 key（{withdraw}/{balance} 自动取当前叫法）。见 render()。
        'balance_payout_title'   => '{type}{withdraw}',
        'balance_payout_content' => '{type}{withdraw}至{balance}，{withdraw}单号：{order_no}',
    ];

    /** 词条类型：名词(原子词) / 短语(句中片段·文案·按钮) / 标题(整页标题) / 文案(带占位符的整句模板) */
    const TYPE_NOUN     = 'noun';
    const TYPE_PHRASE   = 'phrase';
    const TYPE_TITLE    = 'title';
    const TYPE_TEMPLATE = 'template';

    /**
     * 核心词元数据（规范化：每个词一组 {type,label,placeholders}）。
     * 与插件 getNounMeta() 同构；原子词只标 type，文案模板另带 label + 占位符说明。
     */
    const CORE_META = [
        'balance'    => ['type' => self::TYPE_NOUN],
        'integral'   => ['type' => self::TYPE_NOUN],
        'commission' => ['type' => self::TYPE_NOUN],
        'withdraw'   => ['type' => self::TYPE_NOUN],
        'coupon'     => ['type' => self::TYPE_NOUN],
        'level'      => ['type' => self::TYPE_NOUN],
        'charge_add'    => ['type' => self::TYPE_PHRASE],
        'charge_sub'    => ['type' => self::TYPE_PHRASE],
        'charge_source' => ['type' => self::TYPE_PHRASE],
        'balance_payout_title' => [
            'type'  => self::TYPE_TEMPLATE,
            'label' => '提现到余额 · 流水标题',
            'placeholders' => ['type' => '资产名（佣金/积分/余额）'],
        ],
        'balance_payout_content' => [
            'type'  => self::TYPE_TEMPLATE,
            'label' => '提现到余额 · 明细文案',
            'placeholders' => ['type' => '资产名（佣金/积分/余额）', 'order_no' => '提现单号'],
        ],
    ];

    /**
     * 示例渲染用的样例数据（后台「展示示例」= 用默认模板 render 这套数据）。
     * 覆盖当前所有文案模板会用到的数据占位符；词库 key 占位符({integral}等)由 render 走 parse 取真实叫法。
     */
    const DEMO_VARS = [
        'type'     => '佣金',
        'order_no' => 'W20260708001',
        'amount'   => '100.00',
        'fee'      => '20.00',
        'gain'     => '50.00',
        'used'     => '5.00',
        'nickname' => '张三',
        'id'       => '6',
        // 分润/分红类文案模板常用占位符
        'subsidy'  => '快递补贴',
        'region'   => '中山市',
        'level'    => '市代',
        'year'     => '2026',
        'month'    => '07',
    ];

    /**
     * 判定词条类型：显式声明优先，否则按 key 启发式兜底。
     *  启发式：key 含 'title' → 标题；否则 → 短语（原子名词一般由 CORE_META/显式声明覆盖）。
     */
    public static function classifyType(string $key, ?string $explicit): string
    {
        if (in_array($explicit, [self::TYPE_NOUN, self::TYPE_PHRASE, self::TYPE_TITLE, self::TYPE_TEMPLATE], true)) {
            return $explicit;
        }
        if (isset(self::CORE_META[$key]['type'])) {
            return self::CORE_META[$key]['type'];
        }
        if (stripos($key, 'title') !== false) {
            return self::TYPE_TITLE;
        }
        return self::TYPE_PHRASE;
    }

    /**
     * 规范化单个词条的展示元数据（供后台词库页）。
     *  $m 为声明的 meta（可空）：{type?, label?, placeholders?}。
     *  返回 {type, label, placeholders, example}——example 由默认值自动 render 一套 DEMO_VARS 得出，零维护。
     */
    public static function buildItemMeta(string $key, $default, array $m, $mall_id = null): array
    {
        return [
            'type'         => self::classifyType($key, $m['type'] ?? null),
            'label'        => (string)($m['label'] ?? ''),
            'placeholders' => is_array($m['placeholders'] ?? null) ? $m['placeholders'] : [],
            'example'      => self::render($default, self::DEMO_VARS, $mall_id),
        ];
    }

    /**
     * 获取核心标准字典 key => 默认词
     */
    public static function dictionary()
    {
        return self::DICTIONARY;
    }

    /**
     * 完整词库字典 = 核心 + 当前商城已启用插件声明的 getNouns()
     * 用于：key 合法性校验、MallSetting 下发时的默认词兜底
     *
     * @return array key => 默认词（扁平）
     */
    public static function fullDictionary()
    {
        $dict = self::DICTIONARY;
        try {
            foreach (\Yii::$app->plugin->getAllPlugins() as $plugin) {
                $nouns = $plugin->getNouns();
                if (is_array($nouns)) {
                    foreach ($nouns as $k => $v) {
                        if (is_string($k) && $k !== '' && is_string($v)) {
                            $dict[$k] = $v;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Yii::warning('[NounHelper] 聚合插件词库失败: ' . $e->getMessage());
        }
        return $dict;
    }

    /**
     * 分组词库 —— 供后台「词库配置」页按来源分组展示
     *
     * @return array [ ['source'=>'core','label'=>'核心词库','nouns'=>[k=>默认词]], ... ]
     */
    public static function groupedDictionary()
    {
        // 核心组：meta 来自 CORE_META（未列的 key 走 buildItemMeta 内启发式）
        $coreMeta = [];
        foreach (self::DICTIONARY as $k => $default) {
            $coreMeta[$k] = self::buildItemMeta($k, $default, self::CORE_META[$k] ?? []);
        }
        $groups = [
            ['source' => 'core', 'label' => '核心词库', 'nouns' => self::DICTIONARY, 'meta' => $coreMeta],
        ];
        try {
            foreach (\Yii::$app->plugin->getAllPlugins() as $plugin) {
                $nouns = $plugin->getNouns();
                if (is_array($nouns) && !empty($nouns)) {
                    // 插件 getNounMeta()（可选，规范化：key => {type?,label?,placeholders?}）
                    $declared = [];
                    try {
                        $declared = $plugin->getNounMeta();
                        $declared = is_array($declared) ? $declared : [];
                    } catch (\Throwable $e) {
                        $declared = [];
                    }
                    $meta = [];
                    foreach ($nouns as $k => $default) {
                        $meta[$k] = self::buildItemMeta($k, $default, is_array($declared[$k] ?? null) ? $declared[$k] : []);
                    }
                    $groups[] = [
                        'source' => $plugin->getName(),
                        'label'  => $plugin->getLabel(),
                        'nouns'  => $nouns,
                        'meta'   => $meta,
                    ];
                }
            }
        } catch (\Throwable $e) {
            \Yii::warning('[NounHelper] 分组词库聚合失败: ' . $e->getMessage());
        }
        return $groups;
    }

    /**
     * 解析后的完整扁平词表：key => 「商户配置值 ?: 默认词」（核心 + 当前商城已启用插件）。
     * 唯一实现，供：C 端 MallSettingForm 下发 / 后台 admin 全局 $noun 取词，避免两套逻辑漂移。
     *
     * @return array key => 最终值
     */
    public static function resolvedMap($mall_id = null): array
    {
        if ($mall_id === null || (int)$mall_id <= 0) {
            $mall_id = (int)(\Yii::$app->mall->id ?? 1);
        }
        $dict = self::fullDictionary();
        $rows = Noun::find()
            ->andWhere(['is_delete' => 0, 'mall_id' => (int)$mall_id])
            ->select(['key', 'value'])
            ->asArray()->all();
        $configured = array_column($rows, 'value', 'key');

        $out = [];
        foreach ($dict as $key => $defaultWord) {
            $out[$key] = (isset($configured[$key]) && $configured[$key] !== '')
                ? $configured[$key]
                : $defaultWord;
        }
        return $out;
    }

    /**
     * 读取品牌词（后端等价于前端 $noun）。
     *  优先级：商城配置(Redis 缓存→DB) → 核心默认词 DICTIONARY → key 本身。
     *  $mall_id 省略时取当前商城上下文(Yii::$app->mall->id)，不再写死 1。
     *  未配置时也回退到「默认中文词」并缓存，避免：① 露出英文 key ② 反复查库。
     */
    public static function parse($key, $mall_id = null)
    {
        if ($mall_id === null || (int)$mall_id <= 0) {
            $mall_id = (int)(\Yii::$app->mall->id ?? 1);
        }
        $value = RedisHelper::get('noun_setting_' . $key . '_' . $mall_id);
        if (!$value) {
            $value = Noun::find()->andWhere(['is_delete' => 0, 'mall_id' => $mall_id, 'key' => $key])->select('value')->scalar();
            if (!$value) {
                // 未配置 → 回退默认词:核心词查 DICTIONARY、插件词(如 xwy.subsidy_*)查 fullDictionary 聚合的插件 getNouns(),缺失再回退 key。同样缓存,避免每次落库
                $value = self::DICTIONARY[$key] ?? (self::fullDictionary()[$key] ?? $key);
            }
            self::addKey($key, $value, $mall_id);
        }
        return $value;
    }

    /**
     * 渲染文案模板：把模板里的 {key} 占位符替换掉。
     *  - 先查数据变量 $vars（如 {order_no} {amount} {type}）
     *  - 再当词库 key 查 parse()（如 {withdraw}→当前"提现"叫法、{balance}→"余额"）
     *  - 两者都不是 → 原样保留 {key}
     * 商户既能改品牌词自动联动，也能整句重排，实现完全自定义。
     *
     * @param string $tpl    模板串（通常来自 parse('xxx_content') 取回的可配文案）
     * @param array  $vars   数据变量 key=>value
     * @param int|null $mall_id 商城上下文（透传给 parse 取词库配置）
     */
    public static function render($tpl, array $vars = [], $mall_id = null)
    {
        $tpl = (string)$tpl;
        if ($tpl === '' || strpos($tpl, '{') === false) {
            return $tpl;
        }
        return preg_replace_callback('/\{([\w.]+)\}/', function ($m) use ($vars, $mall_id) {
            $k = $m[1];
            if (array_key_exists($k, $vars)) {
                return (string)$vars[$k];
            }
            $word = self::parse($k, $mall_id);
            // parse 未命中会回退成 key 本身 → 非词库 key，保留原占位符不动
            return $word !== $k ? $word : $m[0];
        }, $tpl);
    }

    public static function addKey($key, $value, $mall_id = 1)
    {
        RedisHelper::set('noun_setting_' . $key . '_' . $mall_id, $value);
    }

    public static function removeKey($key, $mall_id = 1)
    {
        RedisHelper::del('noun_setting_' . $key . '_' . $mall_id);
    }
}