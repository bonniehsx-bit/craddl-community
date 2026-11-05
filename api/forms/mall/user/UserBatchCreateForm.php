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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\models\BaseModel;
use app\helpers\Helper;
use app\helpers\OptionHelper;
use app\helpers\ResponseHelper;
use app\models\User;
use app\services\UserService;

/**
 * 批量创建测试用户 —— 手机号自增。
 *
 * ============================================================
 * 为什么不是循环调 UserCreateForm
 * ============================================================
 * 密码哈希。`generatePasswordHash()` 走 bcrypt,单次 50~100ms —— 那是它
 * 故意设计的慢。循环里每人算一次,500 人就是 25~50 秒,接口直接超时。
 * 而这里全批同一个密码,所以**只算一次、全批复用**,500 人也在 1 秒内。
 *
 * ============================================================
 * 号码去重(本表 mobile / openid 都没有唯一索引,数据库层不拦)
 * ============================================================
 * 重复必须在代码里挡住,而且是两个来源:
 *   ① 库里已存在的号 —— 开跑前一次性把号段内的已用号捞进内存,
 *      不在循环里逐个 exists() 查(500 次查询 vs 1 次)
 *   ② 本批次内部 —— 自增天然不重复,但跳过撞号后要继续往后找,
 *      所以用一个游标推进、而不是按序号直接算号
 *
 * 撞号**跳过而不中止**:库里已有 200 多个用户占着一些号,中途报错退出的话
 * 调用方得自己挑一个没被占的起始号重来。跳过的号在结果里列出来。
 */
class UserBatchCreateForm extends BaseModel
{
    /**
     * 一次最多建多少 —— 硬上限,防手滑也防超时。
     *
     * 200 是产品要求定的。实测(本机 100 个,单位秒):
     *   纯建号 0.21 / 带三种资产 0.68 / 带顺序关系链 1.08 / 全开 2.61
     * 线性外推到 200 约 5 秒(链那部分是平方级,见下),留足了余量。
     * 注意实测是本地库、网络延迟近零;线上每条 SQL 多零点几毫秒,整体会放大数倍。
     */
    const MAX_COUNT = 200;

    /**
     * 开了「顺序关系链」时减半到 100 —— 因为这一档的代价是**平方级**的。
     *
     * bindParent 会连带 TeamStatUpdater::onUserBind,而它要更新该用户的**全部祖先**;
     * 顺序链里第 N 个人有 N-1 个祖先,建 n 人合计约 n²/2 次更新。
     * 100 人 ≈ 5000 次,200 人 ≈ 2 万次 —— 4 倍不是 2 倍。物化路径的存储也是
     * O(深度²)(实测 100 层那条 path 已经 901 字节),同一个数学。
     * 而 100 层链对测深度已经绰绰有余(线上最深也才 173 层)。
     */
    const MAX_COUNT_CHAIN = 100;

    /** 撞号跳过的容忍上限:连续找不到空号就停,避免号段被占满时空转到天荒地老 */
    const MAX_SCAN = 5000;

    /** 起始手机号 */
    public $mobile_start;
    /** 创建数量 */
    public $count;
    /** 昵称前缀,生成「前缀001」 */
    public $nickname_prefix;
    /** 统一登录密码 */
    public $password;
    /** 备注标记 —— 事后按它筛出来批量清理 */
    public $remarks;

    /** 是否授予推广资格(即「推广员」) */
    public $is_inviter;
    /** 是否按顺序串成一条完整关系链:起始上级 → 第1个 → 第2个 → … */
    public $build_chain;
    /** 链的起始上级:手机号或 user_id,留空 = 第 1 个人不挂上级、整批自成一条新链 */
    public $chain_parent;

    /** 初始余额,0/空 = 不发 */
    public $init_money;
    /** 初始积分,0/空 = 不发 */
    public $init_integral;
    /** 初始佣金,0/空 = 不发 */
    public $init_price;

    public function rules()
    {
        return [
            [['mobile_start', 'count'], 'required'],
            [['count'], 'integer', 'min' => 1, 'max' => self::MAX_COUNT,
                'tooBig' => '一次最多创建 ' . self::MAX_COUNT . ' 个', 'tooSmall' => '数量至少为 1'],
            [['mobile_start', 'nickname_prefix', 'password', 'remarks', 'chain_parent'], 'string'],
            [['is_inviter', 'build_chain'], 'integer'],
            // message = 不是数字时的提示;tooSmall = 小于 min 时的提示。
            // 这俩别搞混:只设 message 的话,填 -5 会得到「必须是数字」之外的默认文案,
            // 而填 abc 反倒显示「不能为负数」,提示和实际错因对不上。
            [['init_money', 'init_integral', 'init_price'], 'number', 'min' => 0,
                'message'  => '{attribute}必须是数字',
                'tooSmall' => '{attribute}不能为负数'],
            [['nickname_prefix'], 'default', 'value' => '测试用户'],
            [['password'], 'default', 'value' => '123456'],
            [['remarks'], 'default', 'value' => '批量创建-' . date('Ymd')],
            [['is_inviter', 'build_chain'], 'default', 'value' => 0],
            [['init_money', 'init_integral', 'init_price'], 'default', 'value' => 0],
            [['chain_parent'], 'default', 'value' => ''],
        ];
    }

    public function attributeLabels()
    {
        return [
            'mobile_start'    => '起始手机号',
            'count'           => '创建数量',
            'nickname_prefix' => '昵称前缀',
            'password'        => '登录密码',
            'remarks'         => '备注标记',
            'is_inviter'      => '推广资格',
            'build_chain'     => '按顺序建关系链',
            'chain_parent'    => '起始上级',
            'init_money'      => '初始余额',
            'init_integral'   => '初始积分',
            'init_price'      => '初始佣金',
        ];
    }

    /**
     * 本次实际允许的数量上限。
     * 只有关系链需要额外压 —— 资产是线性开销,200 以内实测扛得住(见 MAX_COUNT 注释)。
     */
    public function maxCount(): int
    {
        return $this->build_chain ? self::MAX_COUNT_CHAIN : self::MAX_COUNT;
    }

    /** 是否要发初始资产 */
    private function hasAsset(): bool
    {
        return (float) $this->init_money > 0
            || (float) $this->init_integral > 0
            || (float) $this->init_price > 0;
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $start = trim((string) $this->mobile_start);
        if (!Helper::isMobile($start)) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, '起始手机号格式不正确!');
        }

        $count = (int) $this->count;

        // 建链时的动态上限。放在 rules 之外做,是因为 rules 里的 max 只能写常量,
        // 而这个值要看 build_chain 开没开(rules 里的 MAX_COUNT 仍兜住绝对上限 200)。
        $maxCount = $this->maxCount();
        if ($count > $maxCount) {
            return ResponseHelper::send(ApiCode::CODE_FAIL, sprintf(
                '开启顺序关系链时一次最多创建 %d 个(建链的开销随人数平方增长);'
                . '请调小数量,或关掉建链后最多 %d 个。',
                $maxCount, self::MAX_COUNT
            ));
        }

        // 起始上级要在开跑前解析并校验 —— 链的第一环挂错了,后面整条链都错。
        $chainParentId = 0;
        if ($this->build_chain && trim((string) $this->chain_parent) !== '') {
            $chainParentId = $this->resolveChainParent(trim((string) $this->chain_parent));
            if ($chainParentId <= 0) {
                return ResponseHelper::send(ApiCode::CODE_FAIL,
                    '起始上级不存在,或不属于本商城 / 已注销 / 已被拉黑。');
            }
        }

        // 号段内已被占用的号,一次捞完 —— 循环里逐个 exists() 是 500 次查询。
        // mobile 和 openid 都要看:UserCreateForm 在 platform=MOBILE 时会把
        // openid 也写成手机号,只查 mobile 会漏掉那批。
        $used = $this->loadUsedMobiles((int) $start, $count);

        // 密码只算这一次
        $passwordHash = $this->password
            ? \Yii::$app->security->generatePasswordHash($this->password)
            : '';

        $setting      = OptionHelper::get('mall_setting', $this->mall_id);
        $defaultAvatar = ($setting && !empty($setting['avatar_url'])) ? $setting['avatar_url'] : '';

        // 序号位宽跟着数量走:建 10 个是 01~10,建 500 个是 001~500
        $padLen = max(2, strlen((string) $count));
        $prefix = $this->nickname_prefix !== '' ? $this->nickname_prefix : '测试用户';

        $created = [];
        $skipped = [];
        $failed  = [];
        $chainFailed = [];   // 建号成功但挂上级失败的
        $assetFailed = [];   // 建号成功但发资产失败的

        $cursor  = (int) $start;   // 号码游标,撞号就往后推,不按序号直接算
        $scanned = 0;

        // 链游标:指向「下一个人应该挂到谁名下」。
        // 起始值是用户填的起始上级(0 = 不挂),此后每成功挂上一个就推进到那个人。
        // 挂失败时**刻意不推进** —— 保持链条连续,不让失败的那个人变成断点,
        // 否则后面所有人都挂在一个孤立节点下,整批数据的关系链结构就废了。
        $chainCursor = $chainParentId;

        while (count($created) < $count) {
            if ($scanned++ >= self::MAX_SCAN) {
                break;  // 号段被占满,别空转
            }

            $mobile = (string) $cursor;
            $cursor++;

            // 自增会越界(13999999999 再加就不是手机号了),每个号都要重验
            if (!Helper::isMobile($mobile)) {
                $failed[] = ['mobile' => $mobile, 'reason' => '号码超出合法范围,已停止'];
                break;
            }

            if (isset($used[$mobile])) {
                $skipped[] = $mobile;
                continue;
            }

            $seq  = count($created) + 1;
            $user = new User();
            $user->mall_id      = $this->mall_id;
            $user->platform     = User::PLATFORM_MOBILE;
            $user->nickname     = $prefix . str_pad((string) $seq, $padLen, '0', STR_PAD_LEFT);
            $user->name         = $user->nickname;
            $user->mobile       = $mobile;
            $user->openid       = $mobile;   // 与 UserCreateForm 的 MOBILE 分支一致
            $user->avatar_url   = $defaultAvatar;
            $user->remarks      = $this->remarks;
            $user->access_token = \Yii::$app->security->generateRandomString();
            $user->auth_key     = \Yii::$app->security->generateRandomString();
            if ($passwordHash) {
                $user->password = $passwordHash;
            }
            // 推广资格:is_inviter 和 inviter_at 必须成对写,与 UserCreateForm 同口径。
            // 只写 is_inviter 会让 inviter_at 停在 0,而 tryBindParent 里有一条
            // `$user->is_inviter && $user->parent_at > $user->inviter_at` 的判断 ——
            // 时间戳是 0 时它恒成立,会把用户误判成「先当推广员、后绑的上级」并清掉
            // maybe_parent_id,以后这个人再也自动绑不上上级。
            if ($this->is_inviter) {
                $user->is_inviter = 1;
                $user->inviter_at = time();
            }

            if (!$user->save()) {
                $failed[] = [
                    'mobile' => $mobile,
                    'reason' => implode(';', array_map(function ($e) {
                        return is_array($e) ? implode(',', $e) : $e;
                    }, $user->getErrors())),
                ];
                continue;
            }

            // 本批次内部也占号:同一个号不会被后面再用到
            $used[$mobile] = true;
            $created[]     = $mobile;

            // ── 挂上级:走 UserService::bindParent 全套 ──
            // 不直写 parent_id —— 那样只有一个字段变了,user_path 没建、团队统计没动、
            // PARENT_CHANGE 事件没发,建出来的是一条"半截"关系链,拿去测分润/级差全是错的。
            if ($this->build_chain) {
                if ($chainCursor > 0) {
                    try {
                        UserService::bindParent($user, $chainCursor, true);   // 后台批量建号,按后台操作绑
                        $chainCursor = $user->id;   // 挂成功才推进
                    } catch (\Throwable $e) {
                        $chainFailed[] = ['mobile' => $mobile, 'reason' => $e->getMessage()];
                        // 不推进 $chainCursor,下一个人继续挂到最后一个挂成功的人名下
                    }
                } else {
                    // 没有起始上级:第 1 个人就是链头,自己不挂,但要当后面人的上级
                    $chainCursor = $user->id;
                }
            }

            // ── 发初始资产:走 ChargeForm,与后台手工「资产变更」完全同一条路 ──
            // 直接 $user->money = N 是零流水的,对不上账;ChargeForm 会同时落
            // 业务流水(balance_log / 积分网关 / price_log)和后台操作日志(ChargeLog)。
            if ($this->hasAsset()) {
                $this->chargeAssets((int) $user->id, $mobile, $assetFailed);
            }
        }

        $msg = sprintf('成功创建 %d 个', count($created));
        if ($skipped) {
            $msg .= sprintf(',跳过已存在 %d 个', count($skipped));
        }
        if ($failed) {
            $msg .= sprintf(',失败 %d 个', count($failed));
        }
        if (count($created) < $count) {
            $msg .= sprintf('(目标 %d 个未凑满)', $count);
        }
        // 挂链/发资产失败要单独说 —— 它们不影响"建了几个号",但数据是残缺的,
        // 混进 failed 里会让人以为号没建成,反而更难排查。
        if ($chainFailed) {
            $msg .= sprintf(',其中 %d 个挂上级失败', count($chainFailed));
        }
        if ($assetFailed) {
            $msg .= sprintf(',%d 笔初始资产发放失败', count($assetFailed));
        }

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, $msg, [
            'created_count'      => count($created),
            'skipped_count'      => count($skipped),
            'failed_count'       => count($failed),
            'chain_failed_count' => count($chainFailed),
            'asset_failed_count' => count($assetFailed),
            // 明细只回前 50 条,建 500 个时全量回传对前端提示没意义
            'created'            => array_slice($created, 0, 50),
            'skipped'            => array_slice($skipped, 0, 50),
            'failed'             => array_slice($failed, 0, 50),
            'chain_failed'       => array_slice($chainFailed, 0, 50),
            'asset_failed'       => array_slice($assetFailed, 0, 50),
        ]);
    }

    /**
     * 解析「起始上级」—— 允许填手机号,也允许填 user_id。
     *
     * 口径与 bindParent 内部那次兜底查询保持一致(mall_id + is_delete=0 + status=NORMAL),
     * 提前查一次是为了**开跑前就报错**:等到循环里第一个人 bindParent 抛异常时,
     * 号已经建出去一批了,回滚不了,只能留一地脏数据。
     *
     * 刻意不校验 is_inviter:bindParent 本身也不要求上级具备推广资格
     * (只有注册流程的 tryBindParent 卡这一关),这里跟着 bindParent 走。
     *
     * @return int 命中的 user_id;0 = 没找到
     */
    private function resolveChainParent(string $input): int
    {
        $query = User::find()
            ->select(['id'])
            ->andWhere([
                'mall_id'   => $this->mall_id,
                'is_delete' => 0,
                'status'    => User::STATUS_NORMAL,
            ]);

        // 11 位数字优先当手机号解;其余按 user_id。
        // 反过来(先试 id)会有歧义:id=19900000001 不可能,但 id=138 和手机号前缀撞不上,
        // 所以只要长度像手机号就按手机号查,查不到再退回按 id 查一次。
        // 长度像手机号就先按手机号查,查不到再退回按 id 查一次。
        // ⚠ mobile 没有唯一索引(见类头注释),重号时必须显式定序,
        //    否则取到哪一条取决于 MySQL 当次的执行计划 —— 同样的输入可能挂到不同的人名下。
        if (Helper::isMobile($input)) {
            $id = (int) (clone $query)->andWhere(['mobile' => $input])
                ->orderBy(['id' => SORT_ASC])->scalar();
            if ($id > 0) {
                return $id;
            }
        }
        if (ctype_digit($input)) {
            return (int) $query->andWhere(['id' => (int) $input])->scalar();
        }
        return 0;
    }

    /**
     * 给一个用户发初始资产 —— 三种各走一次 ChargeForm。
     *
     * 为什么复用 ChargeForm 而不是直接调 BalanceLogHelper 之类:
     * ChargeForm 除了业务流水,还会写 ChargeLog(带 admin_id / before_num / after_num),
     * 那是后台资产变更的审计台账。绕过它发出去的钱在台账里查无此项,
     * 事后对账时就成了"凭空多出来的余额"。
     *
     * 单笔失败不中断:剩下两种资产、以及后面的用户都照常继续,失败项记进 $failed 上报。
     */
    private function chargeAssets(int $userId, string $mobile, array &$failed): void
    {
        // [ChargeForm 的 type, 金额, 中文名] —— type 口径见 ChargeForm:0=余额 1=积分 2=佣金
        $items = [
            [0, (float) $this->init_money,    '余额'],
            [1, (float) $this->init_integral, '积分'],
            [2, (float) $this->init_price,    '佣金'],
        ];

        foreach ($items as [$type, $num, $label]) {
            if ($num <= 0) {
                continue;
            }
            try {
                $charge = new ChargeForm();
                $charge->user_id = $userId;
                $charge->type    = $type;
                $charge->way     = 1;                 // 1=增加
                $charge->num     = $num;
                $charge->remarks = $this->remarks;    // 与用户备注同一个标记,便于事后一起筛出来清理
                $res = $charge->save();

                if (!is_array($res) || (int) ($res['code'] ?? -1) !== ApiCode::CODE_SUCCESS) {
                    $failed[] = [
                        'mobile' => $mobile,
                        'asset'  => $label,
                        'reason' => is_array($res) ? ($res['msg'] ?? '未知错误') : '返回结构异常',
                    ];
                }
            } catch (\Throwable $e) {
                $failed[] = ['mobile' => $mobile, 'asset' => $label, 'reason' => $e->getMessage()];
            }
        }
    }

    /**
     * 把「起始号 → 起始号+扫描上限」这段区间里已被占用的号一次性捞出来。
     *
     * 用区间查询而不是 IN(500 个号):撞号跳过后游标会往后跑,
     * 实际用到的号可能远超 count 个,IN 列表框不住。
     */
    private function loadUsedMobiles(int $start, int $count): array
    {
        $end = $start + self::MAX_SCAN;

        $rows = User::find()
            ->select(['mobile', 'openid'])
            ->andWhere(['mall_id' => $this->mall_id, 'is_delete' => 0])
            ->andWhere(['or',
                ['between', 'mobile', (string) $start, (string) $end],
                ['between', 'openid', (string) $start, (string) $end],
            ])
            ->asArray()
            ->all();

        $used = [];
        foreach ($rows as $r) {
            if (!empty($r['mobile'])) {
                $used[$r['mobile']] = true;
            }
            if (!empty($r['openid'])) {
                $used[$r['openid']] = true;
            }
        }
        return $used;
    }
}
