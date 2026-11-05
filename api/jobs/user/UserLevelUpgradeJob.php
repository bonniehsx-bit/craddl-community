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

namespace app\jobs\user;

use app\helpers\OptionHelper;
use app\core\Job;
use app\models\OrderDetail;
use app\models\Level;
use app\models\Order;
use app\models\User;
use app\models\UserUpgradeLog;
use app\models\UserPath;
use app\services\EffectiveSpend;
use app\services\UserService;
use Yii;

class UserLevelUpgradeJob extends Job
{
    public $user_id;

    /** 货量实时查询记忆化(连升过程中货量不变,按 团队/个人 × 已支付/已结算 × goodsIds 缓存) */
    private $goodsQtyCache = [];
    private $goodsAmountCache = [];

    public function execute($params = null)
    {

        $user = User::findOne($this->user_id);
        if (!$user || $user->is_delete) {
            Yii::warning("用户 #{$this->user_id} 不存在或已删除，任务终止。");
            return;
        }

        // 被拉黑的用户不参与【自动】升级 —— 与 getParents / bindParent 的口径一致
        // (那两处都是 is_delete=0 + status=STATUS_NORMAL)。
        // 守卫放在 execute 而不是 dispatchUplineCheck:除了派发口,还有
        // UserLevelCheckListener 直接 new 本 Job 的旁路入口,只堵派发会漏。
        // 后台手动改等级走 UserLevelChangeForm,不经过这里,不受影响。
        if ((int)$user->status !== User::STATUS_NORMAL) {
            Yii::warning("用户 #{$this->user_id} 状态异常(status={$user->status}),跳过自动升级。");
            return;
        }

        // 后台标记「禁止自动升级」的用户，跳过自动升级（仍可后台手动改等级）
        // 不记日志:这是后台主动配置的预期行为,且「升级检测」弹窗会直接提示该用户被钉住。
        if (!empty($user->disable_auto_upgrade)) {
            return;
        }

        // 升级条件统一按【全量累计】判定（不再区分手动/自动起点）：
        // "消费满X / 团队业绩满X" 等门槛 = 终身累计，口径一致；
        // 不想让某用户自动升级，用 User.disable_auto_upgrade 钉住（上方已拦截）。
        // 升级本身不改变消费/团队规模，故 userStats 在连升过程中只取一次。
        $userStats = $this->getUserStats($user->id, $user->mall_id, null);

        // 一次 Job 内【连续爬升】：升一级后就地刷新 user.level/level_id，再判更高等级。
        // 解决阶梯条件(type2/7/8 依赖「自身等级=前一级」)原先每次触发只升一级、
        // 必须靠外部再次触发才能连升的缺陷。level 单调递增 + 候选 andWhere(>level) 必然收敛，
        // 另设 50 级安全上限做防御。
        $anyUpgraded = false;
        for ($guard = 0; $guard < 50; $guard++) {
            // 比当前等级高、启用且开了自动升级的等级，从高到低优先匹配最高等级
            $candidateLevels = Level::find()
                ->where(['is_delete' => 0, 'mall_id' => $user->mall_id, 'enabled' => 1, 'is_auto' => 1])
                ->andWhere(['>', 'level', $user->level])
                ->orderBy(['level' => SORT_DESC])
                ->all();
            if (empty($candidateLevels)) {
                break;
            }

            $upgradedThisRound = false;
            foreach ($candidateLevels as $level) {
                /** @var Level $level */
                $upgradeConfig = OptionHelper::get('level_upgrade_' . $level->id, $user->mall_id);
                if (!$upgradeConfig || $level->is_auto != 1) {
                    continue;
                }

                // 检查条件（条件关系由 level.upgrade_logic 决定：0=全部满足AND 1=满足任一OR）
                list($canUpgrade, $reason, $reasonType) = $this->checkConditions($upgradeConfig, $user, $level, $userStats, (int)$level->upgrade_logic);
                if (!$canUpgrade) {
                    continue;
                }

                if ($this->performUpgrade($user, $level, $reason, $reasonType)) {
                    // performUpgrade 已就地更新 $user->level/level_id，下一轮以新等级继续向更高匹配
                    // 升级成功留一行即可(权威流水在 UserLevelLog 表);走 info 不进告警管道 ——
                    // warning 会被 RedisLogTarget 推给 Go 落库当监控事件,升级成功不是异常。
                    Yii::info("[升级] 用户#{$user->id} → [{$level->name}](ID:{$level->id}):{$reason}", 'user.upgrade');
                    $anyUpgraded = true;
                    $upgradedThisRound = true;
                    break;
                }
                Yii::error("[升级] 用户#{$user->id} 升到 [{$level->name}] 失败", 'user.upgrade');
            }

            if (!$upgradedThisRound) {
                break;
            }
        }

        if ($anyUpgraded) {
            // 连升到位后，向整条上级链派发一次重检（下级升级可能拉高上级「伞下某等级人数」等条件）
            $this->triggerCascadingUpgrade($user);
        }
        // 没升上去是绝大多数情况的常态,不再留日志 —— 要看"为什么没升、还差多少",
        // 走后台用户列表的「升级检测」(dryRun),那里有逐等级逐条件的实时明细。
    }

    /**
     * 【只读试算】后台「升级检测」用:跑一遍与真实升级完全相同的条件判定,但**不执行任何升级**,
     * 返回逐等级、逐条件的达标明细,供后台弹窗展示"到底卡在哪、还差多少"。
     *
     * 为什么复用本 Job 而不另写一套:判定口径必须与实际升级 100% 一致,
     * 另写一套迟早会与真实逻辑漂移(货量/金额口径、buy_type、活人过滤等)。
     *
     * 只读保证:全程只调 getUserStats / checkConditions(纯查询),不碰 performUpgrade,
     * 不写库、不发事件、不触发级联。
     *
     * @return array {ok, msg, current_level, can_upgrade, target_level, levels:[{name,pass,logic,reason,items:[...]}]}
     */
    public function dryRun(): array
    {
        $user = User::findOne(['id' => $this->user_id, 'is_delete' => 0]);
        if (!$user) {
            return ['ok' => false, 'msg' => '用户不存在'];
        }
        $curLevel = Level::findOne($user->level_id);
        $out = [
            'ok'            => true,
            'msg'           => '',
            'current_level' => $curLevel ? $curLevel->name : ('等级ID:' . $user->level_id),
            'can_upgrade'   => false,
            'target_level'  => '',
            'levels'        => [],
        ];
        // 与 execute() 同序同口径的两道前置守卫 —— 少任何一道,后台都会对着一个
        // 实际根本不会被升级的用户算出"可升级到 X",让人以为是升级引擎出了问题。
        if ((int)$user->status !== User::STATUS_NORMAL) {
            $statusText = [
                User::STATUS_BLACK  => '已拉黑',
                User::STATUS_DELETE => '已注销',
            ][(int)$user->status] ?? ('异常(status=' . (int)$user->status . ')');
            $out['msg'] = "该用户{$statusText},不参与自动升级";
            return $out;
        }
        if (!empty($user->disable_auto_upgrade)) {
            $out['msg'] = '该用户已设置「禁止自动升级」,升级检测不会生效';
            return $out;
        }

        $userStats = $this->getUserStats($user->id, $user->mall_id, null);
        // 展示按等级从低到高(易读);而"可升到哪一级"仍与实际一致 —— 取达标中的最高级
        $levels = Level::find()
            ->where(['is_delete' => 0, 'mall_id' => $user->mall_id, 'enabled' => 1])
            ->andWhere(['>', 'level', $user->level])
            ->orderBy(['level' => SORT_ASC])
            ->all();
        if (empty($levels)) {
            $out['msg'] = '已是最高等级,无更高等级可升';
            return $out;
        }

        foreach ($levels as $level) {
            /** @var Level $level */
            $row = ['level_id' => (int)$level->id, 'name' => $level->name, 'pass' => false, 'logic' => (int)$level->upgrade_logic, 'reason' => '', 'items' => []];
            if ((int)$level->is_auto !== 1) {
                $row['reason'] = '该等级未开启自动升级';
                $out['levels'][] = $row;
                continue;
            }
            $cfg = OptionHelper::get('level_upgrade_' . $level->id, $user->mall_id);
            if (empty($cfg)) {
                $row['reason'] = '该等级未配置升级条件';
                $out['levels'][] = $row;
                continue;
            }
            $trace = [];
            list($can, $reason) = $this->checkConditions($cfg, $user, $level, $userStats, (int)$level->upgrade_logic, $trace);
            $row['pass']   = (bool)$can;
            $row['reason'] = $can ? $reason : '';
            $row['items']  = $trace;
            if (empty($trace)) {
                $row['reason'] = $row['reason'] ?: '该等级未勾选任何升级条件';
            }
            $out['levels'][] = $row;
            if ($can) {   // 取最高达标等级(levels 已按 level ASC,后面的更高)
                $out['can_upgrade']  = true;
                $out['target_level'] = $level->name;
            }
        }
        return $out;
    }

    /**
     * [核心实现] 执行升级操作
     * 包含事务处理、用户字段更新、日志写入
     *
     * @param User $user
     * @param Level $level
     * @param string $reason
     * @param int $reasonType 对应 UserUpgradeLog 的 reason_by
     * @return bool
     */
    private function performUpgrade(User $user, Level $level, string $reason, int $reasonType): bool
    {
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 0. 行锁重读当前等级：防止与其它写者(会员/限时授予/后台改级/另一个升级 Job)并发，
            //    导致 beforeLevelId 取到脏值，或对同一用户重复升级、重复触发非幂等的 onLevelChanged。
            $cur = \app\services\UserService::lockUserLevelRow((int)$user->id);
            if ($cur === null) {
                $transaction->rollBack();
                return false;
            }
            // 已被并发写者升到 ≥ 目标等级：放弃本次升级（同步内存对象，供爬升循环以最新等级继续判定）
            if ($cur['level'] >= (int)$level->level) {
                $transaction->rollBack();
                $user->level = $cur['level'];
                $user->level_id = $cur['level_id'];
                Yii::warning("用户 #{$user->id} 已达 ≥ 目标等级 [{$level->name}]，跳过本次升级（并发去重）。");
                return false;
            }

            // 1. 记录升级前数据（锁内读到的真实当前等级）
            $beforeLevelId = $cur['level_id'];

            // 2. 更新用户模型
            $user->level = $level->level;      // 更新等级权重
            $user->level_id = $level->id;      // 更新等级ID
            $user->level_at = time();          // 更新升级时间
            $user->is_manual = 0;              // 自动升级标记为非手动

            // 注意：save() 会触发 afterSave 事件
            if (!$user->save()) {
                throw new \Exception('用户升级保存失败: ' . json_encode($user->getErrors()));
            }

            // 3. 写入升级日志
            $log = new UserUpgradeLog();
            $log->mall_id = $user->mall_id;
            $log->user_id = $user->id;
            $log->before_level_id = $beforeLevelId ?: 0;
            $log->after_level_id = $level->id;
            $log->reason_by = $reasonType; // 0:购物满(金额) 1:购买商品
            $log->marks = "自动升级: " . $reason; // marks 已加宽到 255(见 m260703 迁移),放得下完整原因;万一超长,下面日志保存失败也不回滚升级

            if (!$log->save()) {
                // 升级日志是审计记录、非升级的必要条件:保存失败只记错误,绝不因此回滚已完成的升级
                Yii::error("用户 #{$user->id} 升级日志保存失败(不影响升级): " . json_encode($log->getErrors()));
            }

            // 团队统计:开了 outbox 就在【本升级事务内】emit(与等级变更原子落库,崩溃不丢);未开则提交后直连增量(见下)
            if (\app\services\TeamStatOutbox::enabled((int)$user->mall_id)) {
                \app\services\TeamStatOutbox::emit((int)$user->mall_id, 'level_changed', [
                    'user_id' => (int)$user->id, 'before_level_id' => (int)$beforeLevelId, 'after_level_id' => (int)$level->id,
                ]);
            }

            $transaction->commit();

            // [接入] 团队统计 + 全局广播等级变化事件
            try {
                // 未开 outbox 才在此直连增量;开了则上面已在事务内 emit、交给 worker,避免双写
                if (!\app\services\TeamStatOutbox::enabled((int)$user->mall_id)) {
                    \app\services\TeamStatUpdater::onLevelChanged($user->id, $user->mall_id, $beforeLevelId, $level->id);
                }
                Yii::$app->trigger(\app\events\UserLevelChangedEvent::EVENT_NAME, new \app\events\UserLevelChangedEvent([
                    'user' => $user, 'oldLevelId' => $beforeLevelId, 'newLevelId' => $level->id, 'source' => 'auto_upgrade',
                ]));
            } catch (\Exception $e) {
                Yii::error("[TeamStat/LevelEvent] 失败: " . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("用户 #{$user->id} 升级异常: " . $e->getMessage());
            return false;
        }
    }

    /**
     * 级联升级触发器 (自底向上扫网)：升级成功后，触发整条上级链重检等级。
     */
    private function triggerCascadingUpgrade(User $user)
    {
        self::dispatchUplineCheck($user->id, 'level_cascaded');
    }

    /**
     * 【公共入口】触发某用户整条上级链的会员等级升级重检（异步、带防抖）。
     *
     * 适用场景：
     *   · 升级成功后级联(level_cascaded)
     *   · 订单支付后(order_paid_upline)——下级消费拉高上级团队业绩
     *   · 自身等级变化后(level_changed_upline)——下级等级变化影响上级"伞下某等级人数"等条件
     *
     * 利用 O(1) 物化路径取祖先链，逐个派发 UserLevelCheckNeededEvent；
     * 每个上级 60 秒防抖，避免重复入队。
     */
    public static function dispatchUplineCheck(int $userId, string $trigger = 'upline_check'): void
    {
        try {
            // 读库里现成的 path(核对过与 parent_id 一致),不用 ensurePath 按 parent_id 逐层上溯(千层链上千条 SQL)。
            // 与团队统计 TeamStatUpdater::ancestorsOf、分润 getParents 同口径
            $path = \app\services\UserService::getVerifiedPath($userId);
            $ancestorIds = \app\services\UserService::parseAncestorIds($path);
            if (empty($ancestorIds)) {
                return;
            }
            // 反转：直推上级优先
            $ancestorIds = array_reverse($ancestorIds);

            $redis = Yii::$app->redis;
            foreach ($ancestorIds as $parentId) {
                $parentId = (int)$parentId;
                if ($parentId <= 0) {
                    continue;
                }
                // 防抖锁：60 秒内不重复压入队列
                $isLocked = $redis->executeCommand('SET', ['upgrade_lock_' . $parentId, 1, 'NX', 'EX', 60]);
                if ($isLocked) {
                    Yii::$app->trigger(
                        \app\events\UserLevelCheckNeededEvent::NAME,
                        new \app\events\UserLevelCheckNeededEvent([
                            'user_id' => $parentId,
                            'trigger' => $trigger,
                        ])
                    );
                }
            }
        } catch (\Exception $e) {
            Yii::error("[UplineCheck] 触发上级链升级重检失败 user={$userId}: " . $e->getMessage());
        }
    }

    /**
     * 获取用户升级判定所需的统计数据
     * 优先从 TeamQueryService 预计算数据读取（O(1)），手动升级场景降级为实时查询
     */
    private function getUserStats($userId, $mallId, $startTime = null): array
    {
        if ($startTime) {
            return $this->getUserStatsRealtime($userId, $mallId, $startTime);
        }

        $query = new \app\services\TeamQueryService($userId, $mallId);
        $stat = $query->getStat();

        $goodsMap = $stat->getMapField('purchased_goods_map');
        $purchasedGoodsIds = array_map('intval', array_keys(array_filter($goodsMap, fn($qty) => $qty > 0)));

        return [
            'paid_amount' => $query->getPersonalSpend(false),
            'settled_amount' => $query->getPersonalSpend(true),
            'purchased_goods_ids_paid' => $purchasedGoodsIds,
            'purchased_goods_ids_settled' => $purchasedGoodsIds,
            // 只数状态正常的子孙:墓碑不该给活人凑升级(见 calcActiveTeamCount)
            'team_total_count' => $this->calcActiveTeamCount($userId, $mallId),
            // 直推有效 = 直推下级中【有下单(有效订单)】的人数(排除无效/已退款明细)
            'direct_valid_count' => $this->calcDirectValidCount($userId, $mallId),
            // 直推业绩：直推下级的累计消费额（UserTeamStat 无此字段，实时查询）
            'direct_spend_paid' => $this->calcDirectSpend($userId, $mallId, false, null),
            'direct_spend_settled' => $this->calcDirectSpend($userId, $mallId, true, null),
        ];
    }


    private function getUserStatsRealtime($userId, $mallId, $startTime): array
    {
        // 金额、已购商品按 EffectiveSpend 口径现算,与 getUserStats 读的统计同一套
        $mine = fn(bool $settled) => OrderDetail::find()->alias('od')
            ->innerJoin(['o' => Order::tableName()], 'o.id = od.order_id')
            ->where(['o.user_id' => $userId, 'o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::orders($settled))
            ->andWhere(EffectiveSpend::validDetails());
        $paidAmount = round((float)($mine(false)->sum('od.pay_price') ?: 0), 2);
        $settledAmount = round((float)($mine(true)->sum('od.pay_price') ?: 0), 2);
        $purchasedGoodsIdsPaid = $mine(false)->select('od.goods_id')->distinct()->column();
        $purchasedGoodsIdsSettled = $mine(true)->select('od.goods_id')->distinct()->column();

        $teamTotalCount = UserPath::find()
            ->where(['like', 'path', "-{$userId}-%", false])
            ->andWhere(['!=', 'user_id', $userId])->count();
        // 覆盖为「只数状态正常的子孙」:上面那句仍保留是为了别处可能的引用,
        // 但升级判定一律用活跃人数(见 calcActiveTeamCount)。
        $teamTotalCount = $this->calcActiveTeamCount($userId, $mallId);
        $directValidCount = $this->calcDirectValidCount($userId, $mallId);

        return [
            'paid_amount' => $paidAmount,
            'settled_amount' => $settledAmount,
            'purchased_goods_ids_paid' => array_map('intval', $purchasedGoodsIdsPaid),
            'purchased_goods_ids_settled' => array_map('intval', $purchasedGoodsIdsSettled),
            'team_total_count' => (int)$teamTotalCount,
            'direct_valid_count' => (int)$directValidCount,
            'direct_spend_paid' => $this->calcDirectSpend($userId, $mallId, false, $startTime),
            'direct_spend_settled' => $this->calcDirectSpend($userId, $mallId, true, $startTime),
        ];
    }

    /**
     * 直推【有效】下级数：直推下级(parent_id=本人)中，至少有一笔【有效订单】的人数。
     * 有效订单 = 付过款、没关闭、还有没退的明细(口径见 EffectiveSpend)。
     */
    /**
     * 活跃团队总人数 —— 升级条件 4 专用,只数【状态正常】的子孙。
     *
     * 不能直接用 UserTeamStat.team_count:它由 TeamStatUpdater 走原始链对称加减维护,
     * 含已注销/被拉黑的墓碑,且注销/拉黑时没有递减钩子(只增不减)。
     * 而升级条件是「等级 → 分润」的入口 —— 拉 49 个真人 + 1 个小号,小号注销后人头还在,
     * 上级照样达标升级、解锁更高分润。死账户不该给活人凑升级。
     *
     * 走 UserPath 子树前缀索引 + User 状态过滤,一次查询。升级检测是异步 job,不在热路径。
     * (team_count 本身不动:它算的是结构规模,展示与统计仍需要含墓碑的口径。)
     */
    private function calcActiveTeamCount(int $userId, int $mallId): int
    {
        return (int)\app\models\UserPath::find()->alias('up')
            ->innerJoin(['u' => User::tableName()], 'u.id = up.user_id')
            ->where(['like', 'up.path', \app\services\UserService::subtreeLike($userId), false])
            ->andWhere(['!=', 'up.user_id', $userId])
            ->andWhere(['u.mall_id' => $mallId, 'u.is_delete' => 0, 'u.status' => User::STATUS_NORMAL])
            ->count();
    }

    /**
     * 活跃直推人数(可选等级下限)—— 升级条件 6/13/14/15/16 专用,只数状态正常的直接下级。
     *
     * 不用 TeamQueryService 的 direct_level_map:那是预计算值,不过滤拉黑,注销也要等
     * fullRebuild 才剔除,且拉黑/注销时无人通知 TeamStatUpdater —— 「死账户凑升级」
     * 在按等级数人头的条件上同样成立(与 calcActiveTeamCount 修 case 4 是同一个洞)。
     * 升级检测是异步 job,不在热路径,实时查可承受;等级比较沿用「及以上」= 权重 >= 目标权重。
     *
     * @param int $targetLevelId 0 = 不限等级(对应原 getDirectCount 的「任意含普通」兜底)
     */
    /**
     * 「自身等级达到某等级」判定 —— 条件 2 / 7 / 8 共用。
     *
     * ⚠️ 必须按**等级权重**比,不能拿 level_id 比大小。
     *    level_id 是主键、没有顺序 —— 实测同一商城里 id=10 的权重是 5、id=5 的权重是 8。
     *    user.level 存的就是权重,由 performUpgrade 与会员体系同步维护(见 calcActiveDirectCount 同款口径)。
     *
     * 2026-09-11 之前这三处写的是 `level_id == 配置值`,即**精确相等**,是初始化代码的遗留:
     *   · 界面写「选择需要已达到的等级」、条件名叫「自身等级基础」,都是「及以上」的意思,唯独实现要求相等;
     *   · 后果是配「白银 + 买A商品」的规则,**黄金用户反而不满足**;
     *   · 还与界面宣传的「越级直达」互相冲突 —— 中间那一级若是手动等级,这条升级路径会永久断掉且不报错。
     * 全仓 dev + bonnie 的 21 条升级配置里这三个条件使用次数为 0,所以改动无存量影响。
     *
     * 等级不存在 / 权重非正 → **判不通过**(保守口径,与 calcActiveDirectCount 一致:
     * 宁可升不上去等人来问,也不能让一条坏配置把所有人放行)。
     */
    /**
     * 「自身等级达到某等级及以上」前置门 —— 条件 19/20/21 共用的可选门槛。
     *
     * 与 meetsBaseLevel 的区别只有一点:**等级留空(0)时视为不限,直接放行**,
     * 与这几条里「直推等级留空=任意」的语义保持一致,运营不填就是不设这道门。
     *
     * @return string|null 通过时返回给判定说明用的前缀(不限时为空串);不通过返回 null,
     *                     调用方据此 break,$failReason 已被填好。
     */
    private function baseLevelGate(User $user, int $baseLevelId, string &$failReason): ?string
    {
        if ($baseLevelId <= 0) {
            return '';   // 留空 = 不限等级
        }
        if (!$this->meetsBaseLevel($user, $baseLevelId, $failReason)) {
            return null;
        }
        $lm = Level::findOne($baseLevelId);
        return '自身[' . ($lm ? $lm->name : ('等级ID:' . $baseLevelId)) . ']及以上、';
    }

    private function meetsBaseLevel(User $user, int $baseLevelId, string &$failReason): bool
    {
        $lm = Level::findOne($baseLevelId);
        $w  = $lm ? (int)$lm->level : 0;
        if ($w <= 0) {
            $failReason = "自身等级要求无效(等级ID:{$baseLevelId} 不存在或权重非正)";
            return false;
        }
        if ((int)$user->level >= $w) {
            return true;
        }
        $failReason = "自身等级不足(需[{$lm->name}]及以上=权重{$w},当前权重{$user->level})";
        return false;
    }

    private function calcActiveDirectCount(int $userId, int $mallId, int $targetLevelId = 0): int
    {
        $q = User::find()->where([
            'parent_id' => $userId, 'mall_id' => $mallId,
            'is_delete' => 0, 'status' => User::STATUS_NORMAL,
        ]);
        if ($targetLevelId > 0) {
            $w = (int)Level::find()->select('level')->where(['id' => $targetLevelId])->scalar();
            if ($w <= 0) {
                return 0;   // 与 getDirectCountByLevel 一致:等级不存在/权重非正 → 0
            }
            $q->andWhere(['>=', 'level', $w]);   // user.level 存等级权重,performUpgrade/membership 均同步维护
        }
        return (int)$q->count();
    }

    /** 活跃伞下某等级及以上人数 —— 升级条件 8 专用,口径同上(排除注销/拉黑,不含本人) */
    private function calcActiveTeamCountByLevel(int $userId, int $mallId, int $targetLevelId): int
    {
        $w = (int)Level::find()->select('level')->where(['id' => $targetLevelId])->scalar();
        if ($w <= 0) {
            return 0;
        }
        return (int)\app\models\UserPath::find()->alias('up')
            ->innerJoin(['u' => User::tableName()], 'u.id = up.user_id')
            ->where(['like', 'up.path', \app\services\UserService::subtreeLike($userId), false])
            ->andWhere(['!=', 'up.user_id', $userId])
            ->andWhere(['u.mall_id' => $mallId, 'u.is_delete' => 0, 'u.status' => User::STATUS_NORMAL])
            ->andWhere(['>=', 'u.level', $w])
            ->count();
    }

    private function calcDirectValidCount(int $userId, int $mallId): int
    {
        // 有效订单口径见 EffectiveSpend:付过款没关闭、还有没退的明细
        $existsValidOrder = (new \yii\db\Query())
            ->from(['o' => Order::tableName()])
            ->innerJoin(['od' => OrderDetail::tableName()], 'od.order_id = o.id')
            ->where('o.user_id = u.id')
            ->andWhere(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::orders(false))
            ->andWhere(EffectiveSpend::validDetails());

        return (int)User::find()->alias('u')
            ->where([
                'u.parent_id' => $userId, 'u.mall_id' => $mallId,
                'u.status' => User::STATUS_NORMAL, 'u.is_delete' => 0,
            ])
            ->andWhere(['exists', $existsValidOrder])
            ->count();
    }

    /**
     * 计算指定用户的直推下级累计消费额（实时 JOIN 查询）
     *
     * 直推下级定义：User.parent_id = $userId（平台推荐人字段）
     *
     * @param int $userId 待检查用户
     * @param int $mallId
     * @param bool $settled true=已完成结算订单，false=已支付订单
     * @param int|null $startTime 起始时间戳，null=统计全部历史
     * @return float
     */
    private function calcDirectSpend(int $userId, int $mallId, bool $settled, ?int $startTime): float
    {
        // 直推下级的有效消费,口径见 EffectiveSpend:按商品实付(明细合计,运费不算),部分退款的单没退的照算,
        // 已关闭的、退掉的不算 —— 与团队消费、条件 18 的直推业绩同一套。
        // 原来是「订单实付 − 退掉的明细」:含运费,且部分退款的单(pay_status=2)整单漏掉。
        return round((float)(OrderDetail::find()->alias('od')
            ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id')
            ->innerJoin(['u' => User::tableName()], 'u.id = o.user_id')
            ->where(['u.parent_id' => $userId, 'u.mall_id' => $mallId, 'u.is_delete' => 0, 'o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::orders($settled))
            ->andWhere(EffectiveSpend::validDetails())
            ->sum('od.pay_price') ?: 0), 2);
    }

    /**
     * 累计购买指定商品(多选)的数量之和(实时统计),统一支持 个人/团队 × 已支付/已结算 四种口径。
     *
     *   $team=false → 个人(o.user_id=本人);$team=true → 整个团队(含本人,Order JOIN user_path 前缀直连子树);
     *   $settled=false → 已支付(pay_status=PAID);$settled=true → 已完成结算(COMPLETED 且 SETTLED),与金额条件的 buy_type 口径对齐;
     *   均【剔除退款】(od.status!=INVALID 且未被 commission.revert 回滚)。
     *   团队 subtreeLike 前缀命中 idx-user_path-path 索引,避开 '%-id-%' 前导通配全表扫与大 IN 占位符上限(1390)。
     * 连升过程中货量不变,按 (team,settled,goodsIds) 记忆化,避免候选等级循环内重复查询。
     */
    private function calcGoodsQty(int $userId, int $mallId, array $goodsIds, bool $settled, bool $team, bool $excludeSelf = false): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $goodsIds), fn($v) => $v > 0)));
        if (empty($ids)) {
            return 0;
        }
        sort($ids);
        $key = ($team ? 't' : 'p') . ($settled ? 's' : 'u') . ($excludeSelf ? 'x' : '') . ':' . implode(',', $ids);
        if (isset($this->goodsQtyCache[$key])) {
            return $this->goodsQtyCache[$key];
        }

        $q = OrderDetail::find()->alias('od')
            ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id');
        if ($team) {
            $q->innerJoin(UserPath::tableName() . ' tp', 'tp.user_id = o.user_id')
                ->where(['like', 'tp.path', UserService::subtreeLike($userId), false]);
            if ($excludeSelf) {
                $q->andWhere(['!=', 'o.user_id', $userId]);   // 团队组只算下级、不含本人(团队复购)
            }
        } else {
            $q->where(['o.user_id' => $userId]);
        }
        // 口径见 EffectiveSpend:已支付 vs 已完成结算(与金额条件 buy_type 对齐);部分退款的单没退的照算,
        // 已关闭的单不算(关单老路径 pay_status 仍是 PAID、明细不置无效也不进回滚台账,实测某商城 373 单 2850 件被误算)
        $q->andWhere(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(['od.goods_id' => $ids])
            ->andWhere(EffectiveSpend::orders($settled))
            ->andWhere(EffectiveSpend::validDetails());

        return $this->goodsQtyCache[$key] = (int)($q->sum('od.num') ?: 0);
    }

    /**
     * 指定商品的**业绩金额**(条件 18 专用)。
     *
     * 与 calcGoodsQty 是同一套统计,只有两处不同:
     *   · 汇总的是 od.pay_price(用户实付商品金额)而不是 od.num
     *   · 多了 SCOPE_DIRECT(只算直推下级),现有条件没有"按商品算直推业绩"的口径
     *
     * ⛔ 金额字段必须是 pay_price。全站业绩(团队消费 / 直推业绩)都按明细 pay_price 算
     * (口径见 EffectiveSpend),用 total_price 会把优惠券、积分抵扣前的原价算进来 ——
     * 同一个「业绩」在升级条件和团队报表里对不上,运营会拿着两个数字来问哪个是对的。
     *
     * 哪些单、哪些明细算数与 calcGoodsQty 同一套(EffectiveSpend),不另起一套。
     *
     * @param int   $scope self=仅本人 / direct=仅直推下级 / team=整棵子树不含本人
     * @param array $excludeGoodsIds 从统计中剔除的商品(条件 18 的"自购排除清单")
     */
    const AMOUNT_SCOPE_SELF   = 'self';
    const AMOUNT_SCOPE_DIRECT = 'direct';
    const AMOUNT_SCOPE_TEAM   = 'team';

    private function calcGoodsAmount(int $userId, int $mallId, array $goodsIds, bool $settled, string $scope, array $excludeGoodsIds = []): float
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $goodsIds), fn($v) => $v > 0)));
        $ex  = array_values(array_unique(array_filter(array_map('intval', $excludeGoodsIds), fn($v) => $v > 0)));
        if ($ex) {
            $ids = array_values(array_diff($ids, $ex));
        }
        if (empty($ids)) {
            return 0.0;
        }
        sort($ids);
        $key = 'amt:' . $scope . ($settled ? 's' : 'u') . ':' . implode(',', $ids);
        if (isset($this->goodsAmountCache[$key])) {
            return $this->goodsAmountCache[$key];
        }

        $q = OrderDetail::find()->alias('od')
            ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id');

        if ($scope === self::AMOUNT_SCOPE_DIRECT) {
            // 直推 = parent_id 直接挂在本人名下,且状态正常。
            // 与 calcActiveDirectCount 同口径:注销/拉黑的下级不算业绩 ——
            // 否则拉一批小号刷够业绩、再注销,门槛照样撑着。
            $q->innerJoin(User::tableName() . ' du', 'du.id = o.user_id')
                ->where([
                    'du.parent_id' => $userId, 'du.mall_id' => $mallId,
                    'du.is_delete' => 0, 'du.status' => User::STATUS_NORMAL,
                ]);
        } elseif ($scope === self::AMOUNT_SCOPE_TEAM) {
            $q->innerJoin(UserPath::tableName() . ' tp', 'tp.user_id = o.user_id')
                ->where(['like', 'tp.path', UserService::subtreeLike($userId), false])
                ->andWhere(['!=', 'o.user_id', $userId]);   // 团队组只算下级、不含本人
        } else {
            $q->where(['o.user_id' => $userId]);
        }

        $q->andWhere(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(['od.goods_id' => $ids])
            ->andWhere(EffectiveSpend::orders($settled))
            ->andWhere(EffectiveSpend::validDetails());

        return $this->goodsAmountCache[$key] = round((float)($q->sum('od.pay_price') ?: 0), 2);
    }

    /**
     * 本人【单笔订单】内的最高件数(条件 17「一次性购买 N 件」)。
     *
     * 与 calcGoodsQty 的差别只有一处:先按 order_id 分组求和,再取最大值 ——
     * 「一次性购买 5 瓶」要求同一笔订单内买够,分 5 单各买 1 瓶不算达标。
     * 退款/关闭/回滚的排除口径与 calcGoodsQty 完全一致,避免两套统计口径打架。
     *
     * @param array $goodsIds 限定商品;留空 = 不限商品,按订单总件数算
     */
    private function calcMaxSingleOrderQty(int $userId, int $mallId, array $goodsIds, bool $settled): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $goodsIds), fn($v) => $v > 0)));
        sort($ids);
        $key = 'single:' . ($settled ? 's' : 'u') . ':' . implode(',', $ids);
        if (isset($this->goodsQtyCache[$key])) {
            return $this->goodsQtyCache[$key];
        }

        $q = OrderDetail::find()->alias('od')
            ->innerJoin(Order::tableName() . ' o', 'o.id = od.order_id')
            ->where(['o.user_id' => $userId])
            ->andWhere(['o.mall_id' => $mallId, 'o.is_delete' => 0])
            ->andWhere(EffectiveSpend::orders($settled))
            ->andWhere(EffectiveSpend::validDetails());
        if (!empty($ids)) {
            $q->andWhere(['od.goods_id' => $ids]);
        }

        $max = (int)($q->select(['qty' => new \yii\db\Expression('SUM(od.num)')])
            ->groupBy('od.order_id')
            ->orderBy(new \yii\db\Expression('SUM(od.num) DESC'))
            ->limit(1)
            ->scalar() ?: 0);

        return $this->goodsQtyCache[$key] = $max;
    }

    /**
     * 检查是否满足所有勾选的条件 (AND 逻辑)
     *
     * 这 12 种条件是系统会员等级的"公共基线"，其他插件需要升级条件时可直接复制本方法后改造：
     *   · 不要依赖某个共享 Service（避免插件间耦合）
     *   · 按插件业务删减条件类型，新增独有条件直接加 case
     *
     * @param int $logic 条件关系: 0=全部满足(AND) 1=满足任一(OR)
     * @return array [bool $canUpgrade, string $reason, int $reasonType]  reasonType: 0=金额类 1=商品类
     */
    private function checkConditions($conditions, User $user, Level $level, array $userStats, int $logic = 0, ?array &$trace = null): array
    {
        $buyType = (int)($level->buy_type ?? 0);
        $validReasons = [];
        $finalReasonType = 0;
        $hasCheckedCondition = false;
        // $trace 非 null = 后台「升级检测」的试算模式:逐条收集达标明细,且 AND 不短路(把所有条件判完,
        // 便于运营一眼看清全部条件差多少)。正常升级路径 $trace 恒为 null → 行为与开销完全不变。
        $isDryRun = ($trace !== null);
        $andFailed = false;

        foreach ($conditions as $condition) {
            if (empty($condition['selected'])) continue;
            $hasCheckedCondition = true;
            $conditionMet = false;
            $currentReason = '';
            $failReason = '';   // 本条不满足时的说明（当前值 vs 目标值）
            $currentType = 0;

            switch ((int)$condition['type']) {
                case 0: // 消费金额满
                    $amount = ($buyType == 1) ? $userStats['settled_amount'] : $userStats['paid_amount'];
                    if ($amount >= (float)$condition['value']) {
                        $conditionMet = true;
                        $currentReason = "消费满{$condition['value']}元(当前{$amount})";
                    } else {
                        $failReason = "消费需满{$condition['value']}元(当前{$amount},差" . round((float)$condition['value'] - $amount, 2) . ")";
                    }
                    break;
                case 1: // 购买指定商品
                    $goodsIds = array_column($condition['value'], 'id');
                    $purchased = ($buyType == 1) ? $userStats['purchased_goods_ids_settled'] : $userStats['purchased_goods_ids_paid'];
                    if (!empty(array_intersect($goodsIds, $purchased))) {
                        $conditionMet = true;
                        $currentReason = "购买指定商品";
                        $currentType = 1;
                    } else {
                        $failReason = "未购买指定商品(商品ID:" . implode(',', $goodsIds) . ")";
                    }
                    break;
                case 2: // 自身等级(及以上) + 购买指定商品
                    if ($this->meetsBaseLevel($user, (int)$condition['value'], $failReason)) {
                        $rawGoods = $condition['value1'] ?? [];
                        $goodsIds = is_array($rawGoods) && isset($rawGoods[0]['id'])
                            ? array_column($rawGoods, 'id')
                            : array_map('intval', (array)$rawGoods);
                        $purchased = ($buyType == 1) ? $userStats['purchased_goods_ids_settled'] : $userStats['purchased_goods_ids_paid'];
                        if (!empty(array_intersect($goodsIds, $purchased))) {
                            $conditionMet = true;
                            $currentReason = "指定身份购买商品";
                            $currentType = 1;
                        } else {
                            $failReason = "指定身份未购买指定商品(商品ID:" . implode(',', $goodsIds) . ")";
                        }
                    }
                    break;
                case 3: // 无条件
                    $conditionMet = true;
                    $currentReason = "无条件";
                    break;
                case 4: // 团队总人数满
                    if ($userStats['team_total_count'] >= (int)$condition['value']) {
                        $conditionMet = true;
                        $currentReason = "团队总人数满{$condition['value']}人(当前{$userStats['team_total_count']})";
                    } else {
                        $failReason = "团队总人数需满{$condition['value']}人(当前{$userStats['team_total_count']}人)";
                    }
                    break;
                case 5: // 直推有效用户满（"有效"=有下单/有有效订单的直推下级，见 calcDirectValidCount）
                    if ($userStats['direct_valid_count'] >= (int)$condition['value']) {
                        $conditionMet = true;
                        $currentReason = "直推有效人数满{$condition['value']}人(当前{$userStats['direct_valid_count']})";
                    } else {
                        $failReason = "直推有效人数需满{$condition['value']}人(当前{$userStats['direct_valid_count']}人)";
                    }
                    break;
                case 6: // 直推某等级及以上满 N 人
                    $targetLevelId = (int)$condition['value'];
                    $targetCount = (int)$condition['value1'];
                    $_lm = Level::findOne($targetLevelId);
                    $levelName = $_lm ? $_lm->name : ("等级ID:" . $targetLevelId);
                    // 只数状态正常的直推:墓碑不给活人凑升级(见 calcActiveDirectCount)
                    $actualCount = $this->calcActiveDirectCount($user->id, $user->mall_id, $targetLevelId);
                    if ($actualCount >= $targetCount) {
                        $conditionMet = true;
                        $currentReason = "直推[{$levelName}]及以上满{$targetCount}人(当前{$actualCount}人)";
                    } else {
                        $failReason = "直推[{$levelName}]及以上需满{$targetCount}人(当前{$actualCount}人)";
                    }
                    break;
                case 7: // 自身等级 + 购买指定商品数量
                    $baseLevelId = (int)$condition['value'];
                    $goodsId = (int)$condition['value1'];
                    $targetBuyCount = (int)$condition['value2'];
                    if ($this->meetsBaseLevel($user, $baseLevelId, $failReason)) {
                        $tqs = new \app\services\TeamQueryService($user->id, $user->mall_id);
                        $actualBuyCount = $tqs->getGoodsPurchaseQty($goodsId);
                        if ($actualBuyCount >= $targetBuyCount) {
                            $conditionMet = true;
                            $currentType = 1;
                            $currentReason = "指定身份累购满{$targetBuyCount}件(已购{$actualBuyCount}件)";
                        } else {
                            $failReason = "指定身份累购商品[{$goodsId}]需满{$targetBuyCount}件(已购{$actualBuyCount}件)";
                        }
                    }
                    break;
                case 8: // 自身等级 + 伞下某等级人数
                    $baseLevelId = (int)$condition['value'];
                    $targetLevelId = (int)$condition['value1'];
                    $targetTeamCount = (int)$condition['value2'];
                    if ($this->meetsBaseLevel($user, $baseLevelId, $failReason)) {
                        $_lm = Level::findOne($targetLevelId);
                        $levelName = $_lm ? $_lm->name : ('等级ID:' . $targetLevelId);
                        // 只数状态正常的伞下成员(见 calcActiveTeamCountByLevel)
                        $actualTeamCount = $this->calcActiveTeamCountByLevel($user->id, $user->mall_id, $targetLevelId);
                        if ($actualTeamCount >= $targetTeamCount) {
                            $conditionMet = true;
                            $currentReason = "指定身份伞下[{$levelName}]及以上满{$targetTeamCount}人(当前{$actualTeamCount}人)";
                        } else {
                            $failReason = "指定身份伞下[{$levelName}]及以上需满{$targetTeamCount}人(当前{$actualTeamCount}人)";
                        }
                    }
                    break;
                case 9: // 团队总业绩满
                    $targetTeamAmount = (float)$condition['value'];
                    $tqs = new \app\services\TeamQueryService($user->id, $user->mall_id);
                    $actualTeamAmount = ($buyType == 1) ? $tqs->getTeamSpend(true) : $tqs->getTeamSpend(false);
                    if ($actualTeamAmount >= $targetTeamAmount) {
                        $conditionMet = true;
                        $currentReason = "团队总业绩满{$targetTeamAmount}元(当前约{$actualTeamAmount}元)";
                    } else {
                        $failReason = "团队总业绩需满{$targetTeamAmount}元(当前约{$actualTeamAmount}元)";
                    }
                    break;
                case 10: // 多线并行（N 条独立合格线）
                    $targetLegCount = (int)$condition['value'];
                    $targetLevelId = (int)$condition['value1'];
                    $_lm = Level::findOne($targetLevelId);
                    $levelName = $_lm ? $_lm->name : ('等级ID:' . $targetLevelId);
                    $tqs = new \app\services\TeamQueryService($user->id, $user->mall_id);
                    $qualifiedLegs = $tqs->getQualifiedLegs($targetLevelId);
                    if ($qualifiedLegs >= $targetLegCount) {
                        $conditionMet = true;
                        $currentReason = "拥有{$targetLegCount}条并行代理线包含[{$levelName}]及以上(当前{$qualifiedLegs}条)";
                    } else {
                        $failReason = "需{$targetLegCount}条并行代理线含[{$levelName}]及以上(当前{$qualifiedLegs}条)";
                    }
                    break;
                case 11: // 个人直推业绩满（直推下级累计消费额）
                    $directAmount = ($buyType == 1)
                        ? $userStats['direct_spend_settled']
                        : $userStats['direct_spend_paid'];
                    if ($directAmount >= (float)$condition['value']) {
                        $conditionMet = true;
                        $currentReason = "直推业绩满{$condition['value']}元(当前{$directAmount}元)";
                    } else {
                        $failReason = "直推业绩需满{$condition['value']}元(当前{$directAmount}元)";
                    }
                    break;
                case 12: // 累计购买指定商品(多选)满 N 件(个人,合计数量)
                    $goodsIds12 = array_map('intval', array_column((array)($condition['value'] ?? []), 'id'));
                    $target12 = (int)($condition['value1'] ?? 0);
                    $actual12 = $this->calcGoodsQty($user->id, $user->mall_id, $goodsIds12, $buyType == 1, false);
                    if ($actual12 >= $target12) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "累计购买指定商品满{$target12}件(已购{$actual12}件)";
                    } else {
                        $failReason = "累计购买指定商品需满{$target12}件(已购{$actual12}件)";
                    }
                    break;
                case 13: // 直推某等级及以上满 n 人 + 自购组(value4/5,本人) + 团队组(value2/3,下级不含本人);两组商品至少配一组
                    $lvId13 = (int)($condition['value'] ?? 0);
                    $needDirect13 = (int)($condition['value1'] ?? 0);
                    $teamGoods13 = array_map('intval', array_column((array)($condition['value2'] ?? []), 'id'));
                    $needTeamQty13 = (int)($condition['value3'] ?? 0);
                    $selfGoods13 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $needSelfQty13 = (int)($condition['value5'] ?? 0);
                    if ($lvId13 > 0) {
                        $_lm13 = Level::findOne($lvId13);
                        $lvName13 = $_lm13 ? $_lm13->name : ('等级ID:' . $lvId13);
                        $curDirect13 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvId13);
                    } else {
                        $lvName13 = '任意等级';   // 等级留空=任意含普通,按直推总数算
                        $curDirect13 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    // 自购组算本人;团队组算子树但排除本人(团队复购)。某组商品留空=不校验该组
                    $curSelfQty13 = !empty($selfGoods13) ? $this->calcGoodsQty($user->id, $user->mall_id, $selfGoods13, $buyType == 1, false) : 0;
                    $curTeamQty13 = !empty($teamGoods13) ? $this->calcGoodsQty($user->id, $user->mall_id, $teamGoods13, $buyType == 1, true, true) : 0;
                    $selfOk13 = empty($selfGoods13) || $curSelfQty13 >= $needSelfQty13;
                    $teamOk13 = empty($teamGoods13) || $curTeamQty13 >= $needTeamQty13;
                    $goodsDesc13 = [];
                    if (!empty($selfGoods13)) $goodsDesc13[] = "自购满{$needSelfQty13}件(当前{$curSelfQty13})";
                    if (!empty($teamGoods13)) $goodsDesc13[] = "团队(不含本人)复购满{$needTeamQty13}件(当前{$curTeamQty13})";
                    $goodsStr13 = empty($goodsDesc13) ? '' : ('、' . implode('、', $goodsDesc13));
                    if ($curDirect13 >= $needDirect13 && $selfOk13 && $teamOk13) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "直推[{$lvName13}]及以上满{$needDirect13}人(当前{$curDirect13}){$goodsStr13}";
                    } else {
                        $failReason = "需直推[{$lvName13}]及以上{$needDirect13}人(当前{$curDirect13}){$goodsStr13}";
                    }
                    break;
                case 14: // 两级直推 + 自购组(本人) + 团队组(仅下级、不含本人);两组商品至少配一组,各自独立达标
                    $lvBase14 = (int)($condition['value'] ?? 0);
                    $needBase14 = (int)($condition['value1'] ?? 0);
                    $lvHigh14 = (int)($condition['value2'] ?? 0);
                    $needHigh14 = (int)($condition['value3'] ?? 0);
                    $teamGoods14 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $needTeamQty14 = (int)($condition['value5'] ?? 0);
                    $selfGoods14 = array_map('intval', array_column((array)($condition['value6'] ?? []), 'id'));
                    $needSelfQty14 = (int)($condition['value7'] ?? 0);
                    // 基础等级不选(0)= 普通用户及以上 = 所有直推(不限等级),此时基础层按直推总人数算
                    if ($lvBase14 > 0) {
                        $_lmB14 = Level::findOne($lvBase14);
                        $lvBaseName14 = $_lmB14 ? $_lmB14->name : ('等级ID:' . $lvBase14);
                        $curBase14 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvBase14);
                    } else {
                        $lvBaseName14 = '普通及以上';
                        $curBase14 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    if ($lvHigh14 > 0) {
                        $_lmH14 = Level::findOne($lvHigh14);
                        $lvHighName14 = $_lmH14 ? $_lmH14->name : ('等级ID:' . $lvHigh14);
                        $curHigh14 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvHigh14);
                    } else {
                        $lvHighName14 = '任意等级';   // 更高等级留空=任意含普通
                        $curHigh14 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    // 自购组算本人;团队组算子树但排除本人(团队复购)。某组商品留空=不校验该组
                    $curSelfQty14 = !empty($selfGoods14) ? $this->calcGoodsQty($user->id, $user->mall_id, $selfGoods14, $buyType == 1, false) : 0;
                    $curTeamQty14 = !empty($teamGoods14) ? $this->calcGoodsQty($user->id, $user->mall_id, $teamGoods14, $buyType == 1, true, true) : 0;
                    $selfOk14 = empty($selfGoods14) || $curSelfQty14 >= $needSelfQty14;
                    $teamOk14 = empty($teamGoods14) || $curTeamQty14 >= $needTeamQty14;
                    $goodsDesc14 = [];
                    if (!empty($selfGoods14)) $goodsDesc14[] = "自购满{$needSelfQty14}件(当前{$curSelfQty14})";
                    if (!empty($teamGoods14)) $goodsDesc14[] = "团队(不含本人)复购满{$needTeamQty14}件(当前{$curTeamQty14})";
                    $goodsStr14 = empty($goodsDesc14) ? '' : ('、' . implode('、', $goodsDesc14));
                    if ($curBase14 >= $needBase14 && $curHigh14 >= $needHigh14 && $selfOk14 && $teamOk14) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "直推[{$lvBaseName14}]及以上{$needBase14}人(当前{$curBase14})、其中[{$lvHighName14}]及以上{$needHigh14}人(当前{$curHigh14}){$goodsStr14}";
                    } else {
                        $failReason = "需直推[{$lvBaseName14}]及以上{$needBase14}人(当前{$curBase14})、其中[{$lvHighName14}]及以上{$needHigh14}人(当前{$curHigh14}){$goodsStr14}";
                    }
                    break;
                case 15: // 直推某等级满 n 人 + (自购A + 团队B) 合计满 N 件(与 13 同,但两组货量合并计;团队组不含本人)
                    $lvId15 = (int)($condition['value'] ?? 0);
                    $needDirect15 = (int)($condition['value1'] ?? 0);
                    $teamGoods15 = array_map('intval', array_column((array)($condition['value2'] ?? []), 'id'));
                    $selfGoods15 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $needTotal15 = (int)($condition['value3'] ?? 0);
                    if ($lvId15 > 0) {
                        $_lm15 = Level::findOne($lvId15);
                        $lvName15 = $_lm15 ? $_lm15->name : ('等级ID:' . $lvId15);
                        $curDirect15 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvId15);
                    } else {
                        $lvName15 = '任意等级';   // 等级留空=任意含普通,按直推总数算
                        $curDirect15 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    $selfQ15 = !empty($selfGoods15) ? $this->calcGoodsQty($user->id, $user->mall_id, $selfGoods15, $buyType == 1, false) : 0;
                    $teamQ15 = !empty($teamGoods15) ? $this->calcGoodsQty($user->id, $user->mall_id, $teamGoods15, $buyType == 1, true, true) : 0;
                    $total15 = $selfQ15 + $teamQ15;
                    if ($curDirect15 >= $needDirect15 && $total15 >= $needTotal15) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "直推[{$lvName15}]及以上满{$needDirect15}人(当前{$curDirect15})、自购+团队合计满{$needTotal15}件(当前{$total15}=自{$selfQ15}+团{$teamQ15})";
                    } else {
                        $failReason = "需直推[{$lvName15}]及以上{$needDirect15}人(当前{$curDirect15})、自购+团队合计{$needTotal15}件(当前{$total15}=自{$selfQ15}+团{$teamQ15})";
                    }
                    break;
                case 16: // 两级直推 + (自购A + 团队B) 合计满 N 件(与 14 同,但两组货量合并计;团队组不含本人)
                    $lvBase16 = (int)($condition['value'] ?? 0);
                    $needBase16 = (int)($condition['value1'] ?? 0);
                    $lvHigh16 = (int)($condition['value2'] ?? 0);
                    $needHigh16 = (int)($condition['value3'] ?? 0);
                    $teamGoods16 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $selfGoods16 = array_map('intval', array_column((array)($condition['value6'] ?? []), 'id'));
                    $needTotal16 = (int)($condition['value5'] ?? 0);
                    if ($lvBase16 > 0) {
                        $_lmB16 = Level::findOne($lvBase16);
                        $lvBaseName16 = $_lmB16 ? $_lmB16->name : ('等级ID:' . $lvBase16);
                        $curBase16 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvBase16);
                    } else {
                        $lvBaseName16 = '普通及以上';
                        $curBase16 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    if ($lvHigh16 > 0) {
                        $_lmH16 = Level::findOne($lvHigh16);
                        $lvHighName16 = $_lmH16 ? $_lmH16->name : ('等级ID:' . $lvHigh16);
                        $curHigh16 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvHigh16);
                    } else {
                        $lvHighName16 = '任意等级';   // 更高等级留空=任意含普通
                        $curHigh16 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    $selfQ16 = !empty($selfGoods16) ? $this->calcGoodsQty($user->id, $user->mall_id, $selfGoods16, $buyType == 1, false) : 0;
                    $teamQ16 = !empty($teamGoods16) ? $this->calcGoodsQty($user->id, $user->mall_id, $teamGoods16, $buyType == 1, true, true) : 0;
                    $total16 = $selfQ16 + $teamQ16;
                    if ($curBase16 >= $needBase16 && $curHigh16 >= $needHigh16 && $total16 >= $needTotal16) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "直推[{$lvBaseName16}]及以上{$needBase16}人(当前{$curBase16})、其中[{$lvHighName16}]及以上{$needHigh16}人(当前{$curHigh16})、自购+团队合计满{$needTotal16}件(当前{$total16}=自{$selfQ16}+团{$teamQ16})";
                    } else {
                        $failReason = "需直推[{$lvBaseName16}]及以上{$needBase16}人(当前{$curBase16})、其中[{$lvHighName16}]及以上{$needHigh16}人(当前{$curHigh16})、自购+团队合计{$needTotal16}件(当前{$total16}=自{$selfQ16}+团{$teamQ16})";
                    }
                    break;
                case 17: // 单笔订单件数满 N 件(一次性购买;value=指定商品多选,留空=不限商品;value1=件数)
                    // 与条件 7/12「累计购买」的区别:这里要求【同一笔订单】内达标,
                    // 对应「一次性购买 5 瓶 / 20 瓶」这类进货门槛,累计买够不算。
                    $goodsIds17 = array_map('intval', array_column((array)($condition['value'] ?? []), 'id'));
                    $target17 = (int)($condition['value1'] ?? 0);
                    $actual17 = $this->calcMaxSingleOrderQty($user->id, $user->mall_id, $goodsIds17, $buyType == 1);
                    $scope17 = empty($goodsIds17) ? '' : '指定商品';
                    if ($target17 > 0 && $actual17 >= $target17) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "单笔订单{$scope17}满{$target17}件(最高单笔{$actual17}件)";
                    } else {
                        $failReason = "需单笔订单{$scope17}满{$target17}件(当前最高单笔{$actual17}件)";
                    }
                    break;
                case 18: // 三条业绩线各自达标:直推买A组 + 本人买B组 + 团队(不含本人)买C组;另有"自购排除清单"
                    // value  = 直推组商品(多选)      value1 = 直推组金额门槛
                    // value2 = 本人自购组商品(多选)  value3 = 自购组金额门槛
                    // value4 = 团队组商品(多选)      value5 = 团队组金额门槛
                    // value6 = 自购排除清单(多选) —— 这些商品本人买了一律不计入
                    $directGoods18 = array_map('intval', array_column((array)($condition['value'] ?? []), 'id'));
                    $needDirect18  = (float)($condition['value1'] ?? 0);
                    $selfGoods18   = array_map('intval', array_column((array)($condition['value2'] ?? []), 'id'));
                    $needSelf18    = (float)($condition['value3'] ?? 0);
                    $teamGoods18   = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $needTeam18    = (float)($condition['value5'] ?? 0);
                    $exclGoods18   = array_map('intval', array_column((array)($condition['value6'] ?? []), 'id'));

                    // 排除清单只对"本人自购"这条线生效 —— 另两条线本来就不含本人的订单,
                    // 在它们身上做排除是无意义的空转(直推组只看下级、团队组显式排除了本人)。
                    $curDirect18 = !empty($directGoods18)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $directGoods18, $buyType == 1, self::AMOUNT_SCOPE_DIRECT)
                        : 0;
                    $curSelf18 = !empty($selfGoods18)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $selfGoods18, $buyType == 1, self::AMOUNT_SCOPE_SELF, $exclGoods18)
                        : 0;
                    $curTeam18 = !empty($teamGoods18)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $teamGoods18, $buyType == 1, self::AMOUNT_SCOPE_TEAM)
                        : 0;

                    // 某组商品留空 = 不校验该组(与 case 13/14 一致),三组都留空则本条件形同虚设,
                    // 由后台配置时拦住;这里按"未配置即通过"处理,不让它意外卡死升级。
                    $directOk18 = empty($directGoods18) || $curDirect18 >= $needDirect18;
                    $selfOk18   = empty($selfGoods18)   || $curSelf18   >= $needSelf18;
                    $teamOk18   = empty($teamGoods18)   || $curTeam18   >= $needTeam18;

                    $desc18 = [];
                    if (!empty($directGoods18)) $desc18[] = "直推业绩满{$needDirect18}元(当前{$curDirect18}元)";
                    if (!empty($selfGoods18))   $desc18[] = "自购满{$needSelf18}元(当前{$curSelf18}元)";
                    if (!empty($teamGoods18))   $desc18[] = "团队(不含本人)满{$needTeam18}元(当前{$curTeam18}元)";
                    $descStr18 = empty($desc18) ? '未配置任何商品组' : implode('、', $desc18);

                    if ($directOk18 && $selfOk18 && $teamOk18) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = $descStr18;
                    } else {
                        $failReason = '需' . $descStr18;
                    }
                    break;

                // ═══════════ 19 / 20 / 21:12 / 15 / 16 的「金额版」 ═══════════
                // 三条与 12/15/16 一一对应,字段位置、人群范围、留空语义全部照搬,
                // **唯一差别是把件数换成金额**:calcGoodsQty(od.num) → calcGoodsAmount(od.pay_price)。
                //
                // ⛔ 金额一律取 od.pay_price(用户实付),不是 total_price ——
                //    全站业绩累加的就是实付额,用原价会让同一个「业绩」在升级条件和团队报表里对不上。
                //    退款/关闭/回滚三道排除口径由 calcGoodsAmount 与件数版共用,不另起一套。
                //
                // 与件数版的一处刻意不同:**金额门槛必须 > 0 才算达标**(照 case 17 的写法)。
                // 件数版沿用了「0 >= 0 为真」的老行为,门槛留空会让所有人通过;
                // 保存时 LevelForm 会拦住,但历史数据和直接改库绕得过去 —— 金额是钱,这里多兜一层。
                case 19: // 自身等级及以上 + 累计购买指定商品(多选)满 N 元(个人,合计金额)= 条件12 的金额版
                    $goodsIds19 = array_map('intval', array_column((array)($condition['value'] ?? []), 'id'));
                    $target19 = (float)($condition['value1'] ?? 0);
                    // ⓪ 自身等级前置门(value2,留空=不限)。走 meetsBaseLevel 按**权重**比,不是 level_id
                    $gate19 = $this->baseLevelGate($user, (int)($condition['value2'] ?? 0), $failReason);
                    if ($gate19 === null) {
                        break;   // 等级不达标,$failReason 已由 baseLevelGate 填好
                    }
                    $actual19 = $this->calcGoodsAmount(
                        $user->id, $user->mall_id, $goodsIds19, $buyType == 1, self::AMOUNT_SCOPE_SELF
                    );
                    if ($target19 > 0 && $actual19 >= $target19) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "{$gate19}累计购买指定商品满{$target19}元(已购{$actual19}元)";
                    } else {
                        $failReason = "{$gate19}累计购买指定商品需满{$target19}元(已购{$actual19}元)";
                    }
                    break;

                case 20: // 直推某等级满 n 人 + (自购A + 团队B) 合计满 N 元 = 条件15 的金额版
                    $lvId20 = (int)($condition['value'] ?? 0);
                    $needDirect20 = (int)($condition['value1'] ?? 0);
                    $teamGoods20 = array_map('intval', array_column((array)($condition['value2'] ?? []), 'id'));
                    $selfGoods20 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $needTotal20 = (float)($condition['value3'] ?? 0);
                    // ⓪ 自身等级前置门(value5,留空=不限)
                    $gate20 = $this->baseLevelGate($user, (int)($condition['value5'] ?? 0), $failReason);
                    if ($gate20 === null) {
                        break;
                    }
                    if ($lvId20 > 0) {
                        $_lm20 = Level::findOne($lvId20);
                        $lvName20 = $_lm20 ? $_lm20->name : ('等级ID:' . $lvId20);
                        $curDirect20 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvId20);
                    } else {
                        $lvName20 = '任意等级';   // 等级留空=任意含普通,按直推总数算
                        $curDirect20 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    $selfA20 = !empty($selfGoods20)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $selfGoods20, $buyType == 1, self::AMOUNT_SCOPE_SELF)
                        : 0.0;
                    $teamA20 = !empty($teamGoods20)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $teamGoods20, $buyType == 1, self::AMOUNT_SCOPE_TEAM)
                        : 0.0;
                    $total20 = round($selfA20 + $teamA20, 2);
                    if ($needTotal20 > 0 && $curDirect20 >= $needDirect20 && $total20 >= $needTotal20) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "{$gate20}直推[{$lvName20}]及以上满{$needDirect20}人(当前{$curDirect20})、自购+团队合计满{$needTotal20}元(当前{$total20}=自{$selfA20}+团{$teamA20})";
                    } else {
                        $failReason = "{$gate20}需直推[{$lvName20}]及以上{$needDirect20}人(当前{$curDirect20})、自购+团队合计{$needTotal20}元(当前{$total20}=自{$selfA20}+团{$teamA20})";
                    }
                    break;

                case 21: // 两级直推 + (自购A + 团队B) 合计满 N 元 = 条件16 的金额版
                    $lvBase21 = (int)($condition['value'] ?? 0);
                    $needBase21 = (int)($condition['value1'] ?? 0);
                    $lvHigh21 = (int)($condition['value2'] ?? 0);
                    $needHigh21 = (int)($condition['value3'] ?? 0);
                    $teamGoods21 = array_map('intval', array_column((array)($condition['value4'] ?? []), 'id'));
                    $selfGoods21 = array_map('intval', array_column((array)($condition['value6'] ?? []), 'id'));
                    $needTotal21 = (float)($condition['value5'] ?? 0);
                    // ⓪ 自身等级前置门(value7,留空=不限)
                    $gate21 = $this->baseLevelGate($user, (int)($condition['value7'] ?? 0), $failReason);
                    if ($gate21 === null) {
                        break;
                    }
                    if ($lvBase21 > 0) {
                        $_lmB21 = Level::findOne($lvBase21);
                        $lvBaseName21 = $_lmB21 ? $_lmB21->name : ('等级ID:' . $lvBase21);
                        $curBase21 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvBase21);
                    } else {
                        $lvBaseName21 = '普通及以上';
                        $curBase21 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    if ($lvHigh21 > 0) {
                        $_lmH21 = Level::findOne($lvHigh21);
                        $lvHighName21 = $_lmH21 ? $_lmH21->name : ('等级ID:' . $lvHigh21);
                        $curHigh21 = $this->calcActiveDirectCount($user->id, $user->mall_id, $lvHigh21);
                    } else {
                        $lvHighName21 = '任意等级';   // 更高等级留空=任意含普通
                        $curHigh21 = $this->calcActiveDirectCount($user->id, $user->mall_id, 0);
                    }
                    $selfA21 = !empty($selfGoods21)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $selfGoods21, $buyType == 1, self::AMOUNT_SCOPE_SELF)
                        : 0.0;
                    $teamA21 = !empty($teamGoods21)
                        ? $this->calcGoodsAmount($user->id, $user->mall_id, $teamGoods21, $buyType == 1, self::AMOUNT_SCOPE_TEAM)
                        : 0.0;
                    $total21 = round($selfA21 + $teamA21, 2);
                    if ($needTotal21 > 0 && $curBase21 >= $needBase21 && $curHigh21 >= $needHigh21 && $total21 >= $needTotal21) {
                        $conditionMet = true;
                        $currentType = 1;
                        $currentReason = "{$gate21}直推[{$lvBaseName21}]及以上{$needBase21}人(当前{$curBase21})、其中[{$lvHighName21}]及以上{$needHigh21}人(当前{$curHigh21})、自购+团队合计满{$needTotal21}元(当前{$total21}=自{$selfA21}+团{$teamA21})";
                    } else {
                        $failReason = "{$gate21}需直推[{$lvBaseName21}]及以上{$needBase21}人(当前{$curBase21})、其中[{$lvHighName21}]及以上{$needHigh21}人(当前{$curHigh21})、自购+团队合计{$needTotal21}元(当前{$total21}=自{$selfA21}+团{$teamA21})";
                    }
                    break;
            }

            // 逐条判定轨迹不再写日志:每个用户每次触发都会按 候选等级×条件数 刷出成片记录,
            // 而这些信息后台「升级检测」(dryRun)已能按需实时给出,且带当前值 vs 目标值。
            // 试算模式:逐条留痕(条件类型 / 是否达标 / 带当前值的说明),供后台「升级检测」弹窗展示
            if ($isDryRun) {
                $trace[] = [
                    'type' => (int)$condition['type'],
                    'name' => (string)($condition['name'] ?? ''),
                    'pass' => (bool)$conditionMet,
                    'desc' => $conditionMet ? $currentReason : ($failReason !== '' ? $failReason : '未满足'),
                ];
            }

            if ($logic === 1) {
                // OR（满足任一）：命中一个立即升级
                if ($conditionMet) {
                    // 试算模式要把所有条件判完再返回,故此处不提前 return
                    if (!$isDryRun) {
                        return [true, $currentReason, $currentType];
                    }
                    $validReasons[] = $currentReason;
                    if ($currentType === 1) $finalReasonType = 1;
                }
                // 未命中 → 继续看下一个($failReason 已进 $trace 供后台「升级检测」展示)
            } else {
                // AND（全部满足）：任一不满足即失败
                if (!$conditionMet) {
                    // 卡在哪一条、还差多少 → 看后台「升级检测」,那里连后续条件也一并判完
                    // (这里是短路的,本来也只能告诉你第一条)。
                    // 试算模式不短路:标记失败但把剩余条件也判完,让后台看到每条差多少
                    if ($isDryRun) {
                        $andFailed = true;
                        continue;
                    }
                    return [false, '', 0];
                }
                $validReasons[] = $currentReason;
                if ($currentType === 1) $finalReasonType = 1;
            }
        }

        if (!$hasCheckedCondition) return [false, '', 0];

        // 试算模式:上面没有提前 return,在此按逻辑给出最终结论
        if ($isDryRun) {
            if ($logic === 1) {
                // OR:只要有一条达标即通过
                return !empty($validReasons)
                    ? [true, implode(' 且 ', $validReasons), $finalReasonType]
                    : [false, '', 0];
            }
            return $andFailed ? [false, '', 0] : [true, implode(' 且 ', $validReasons), $finalReasonType];
        }

        // OR 走到这里说明勾选的条件一个都没满足(常态,不记日志;明细看后台「升级检测」)
        if ($logic === 1) {
            return [false, '', 0];
        }
        // AND 且全部满足
        return [true, implode(' 且 ', $validReasons), $finalReasonType];
    }
}
