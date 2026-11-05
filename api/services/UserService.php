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

use app\events\UserEvent;
use app\helpers\CacheHelper;
use app\helpers\JobHelper;
use app\helpers\OptionHelper;
use app\models\AccessToken;
use app\models\Order;
use app\models\User;
use app\models\UserPath;
use app\services\TeamStatUpdater;
use Yii;
use yii\db\Exception;

/**
 * Class UserService
 * 用户全栈服务层 (已修复 Path 缺失导致的断链问题)
 */
class UserService
{
    // =========================================================================
    // Part 1: 用户生命周期与副作用管理
    // =========================================================================

    /**
     * 在【当前事务内】对 user 行加行锁并读出真实当前等级，供所有"等级写入"路径统一加锁、取准确 old。
     *
     * 用法（所有写 user.level_id 的路径都应遵循）：
     *   $tx = beginTransaction();
     *   $cur = UserService::lockUserLevelRow($userId);   // FOR UPDATE，串行化并发写者
     *   ...按 $cur 判定守卫(只升/匹配/跳过) + 写 user.level/level_id + 写 UserUpgradeLog...
     *   $tx->commit();
     *   TeamStatUpdater::onLevelChanged($userId, $mallId, $cur['level_id'], $newLevelId); // commit 后
     *
     * 这样并发写者互相阻塞、各自取锁内真实 old，(old,new) 链正确，onLevelChanged 即便锁外并发也能按祖先行原子收敛。
     *
     * @return array|null ['level'=>int, 'level_id'=>int]；null=用户不存在/已删除
     */
    public static function lockUserLevelRow(int $userId): ?array
    {
        // 防呆:FOR UPDATE 仅在事务内生效。不在事务内调用会静默退化为普通读(锁不住)，
        // 失去串行化保护却不报错。这里显式告警，提醒调用方必须先 beginTransaction。
        if (Yii::$app->db->getTransaction() === null) {
            Yii::error("[lockUserLevelRow] 未在事务内调用，FOR UPDATE 不生效、并发保护失效 (user={$userId})。等级写入路径必须先 beginTransaction。");
        }

        $row = Yii::$app->db->createCommand(
            "SELECT level, level_id FROM {{%user}} WHERE id = :id AND is_delete = 0 FOR UPDATE",
            [':id' => $userId]
        )->queryOne();
        if (!$row) {
            return null;
        }
        return ['level' => (int)$row['level'], 'level_id' => (int)$row['level_id']];
    }

    public static function handleAfterSave(User $user, bool $insert, array $changedAttributes)
    {
        // 1. 确保 UserPath 存在 (新增或未生成时自动修补)
        if ($insert) {
            self::ensurePath($user->id);
            // 广播「用户注册」事件(全局基础设施,无监听即空操作;插件可据此即时发新人礼包等)
            // try/catch 隔离:任何监听异常都不得影响用户注册主流程
            try {
                $event = new UserEvent();
                $event->user_id = $user->id;
                Yii::$app->trigger(UserEvent::REGISTER, $event);
            } catch (\Throwable $e) {
                Yii::error('UserService: UserEvent::REGISTER 触发异常 uid=' . $user->id . ' - ' . $e->getMessage());
            }
        }

        self::processCacheUpdate($user, $insert, $changedAttributes);
        self::handleParentChangeTrigger($user, $changedAttributes);
        self::logUserAction($user, $insert, $changedAttributes);
    }

    private static function processCacheUpdate(User $user, bool $insert, array $changedAttributes)
    {
        try {
            $shouldRefresh = $insert;
            if (!$insert) {
                $identityFields = ['nickname', 'avatar_url', 'mobile', 'status', 'level_id', 'is_clerk', 'is_mch', 'is_delete', 'openid', 'union_id', 'level'];
                foreach ($changedAttributes as $name => $oldValue) {
                    if (in_array($name, $identityFields)) {
                        $shouldRefresh = true;
                        break;
                    }
                }
            }
            if ($shouldRefresh)
                self::refreshIdentityCache($user);
            if ($user->status == User::STATUS_DELETE)
                CacheHelper::remove(User::CACHE_KEY_IDENTITY . $user->id);
        } catch (\Exception $e) {
            Yii::warning("UserService: 缓存刷新过程异常 " . $e->getMessage());
        }
    }

    private static function handleParentChangeTrigger(User $user, array $changedAttributes)
    {
        // 关系链维护已由 bindParent → updatePathTree 同步完成，无需额外操作
    }

    private static function logUserAction(User $user, bool $insert, array $changedAttributes)
    {
        try {
            if (!$changedAttributes) {
                return;
            }

            $arr = ['created_at', 'updated_at', 'deleted_at'];
            $afterUpdate = $user->attributes;
            $newBeforeUpdate = [];
            $newAfterUpdate = [];

            if ($insert) {
                $remark = '数据插入';
            } else {
                $remark = '数据更新';
            }
            if (isset($afterUpdate['is_delete']) && $afterUpdate['is_delete'] == 1) {
                $remark = '数据删除';
            }

            foreach ($changedAttributes as $key => $item) {
                if (in_array($key, $arr)) {
                    continue;
                }
                if ($item != $afterUpdate[$key]) {
                    try {
                        $newBeforeUpdate[$key] = \yii\helpers\Json::decode($item);
                    } catch (\Exception $e) {
                        $newBeforeUpdate[$key] = $item;
                    }

                    try {
                        $newAfterUpdate[$key] = \yii\helpers\Json::decode($afterUpdate[$key]);
                    } catch (\Exception $e) {
                        $newAfterUpdate[$key] = $afterUpdate[$key];
                    }
                }
            }

            if (count($newAfterUpdate)) {
                $logPayload = [
                    'user_id'       => $user->id,
                    'mall_id'       => $user->mall_id,
                    'before_update' => \yii\helpers\Json::encode($newBeforeUpdate),
                    'after_update'  => \yii\helpers\Json::encode($newAfterUpdate),
                    'remarks'       => $remark,
                    'created_at'    => time(),
                    'updated_at'    => time(),
                    'created_time'  => date('Y-m-d H:i:s'),
                    'updated_time'  => date('Y-m-d H:i:s'),
                ];

                $logKey = env('REDIS_KEY_USER_LOG', 'user_action_logs');
                \app\helpers\CacheHelper::lpush($logKey, $logPayload);
            }
        } catch (\Exception $e) {
            \Yii::warning("Failed to push user action log to redis: " . $e->getMessage());
        }
    }

    // =========================================================================
    // Part 2: 前端业务 - 绑定/变更上级
    // =========================================================================

    /**
     * 尝试给用户绑定推荐人 —— 关系链的唯一入口。
     *
     * 注册(同步,想立刻建立关系)与 UserLoginJob(异步兜底)都调这里,校验只此一份。
     *
     * parent_id 语义(勿与 header x-parent-id 的 -1 混淆,那是「本次请求无邀请人」的哨兵):
     *   0  = 未绑定,可绑        >0 = 已绑推荐人
     *   -1 = 平台直属(锁定)     -2 = 收银台共享游客(锁定)
     *
     * 绑定时机由后台 relation_setting.child_type 决定:
     *   0 = 无条件(注册即绑)  1 = 首次下单才绑  2 = 首次付款才绑
     * 非 0 时只暂存 maybe_parent_id,等条件满足后由后续触发点完成绑定。
     *
     * @param int $candidateParentId 本次带来的候选推荐人;<=0 表示没带,回退 user.maybe_parent_id
     * @return bool 是否真的完成了绑定
     */
    public static function tryBindParent(User $user, int $candidateParentId = 0, string $source = ''): bool
    {
        $tag = "[tryBindParent{$source}]";

        // 只有 parent_id == 0 才是「未定」。-1 平台直属 / -2 游客 / >0 已绑,都不再动。
        if ($user->parent_id != 0) {
            Yii::warning("{$tag} 用户 #{$user->id} 的 parent_id={$user->parent_id}(非 0,已定),跳过绑定。");
            return false;
        }

        Yii::warning("{$tag} 开始检查用户 #{$user->id} 的上级绑定。" .
            " candidate={$candidateParentId}" .
            " db.maybe_parent_id={$user->maybe_parent_id}" .
            " is_inviter={$user->is_inviter}" .
            " parent_at={$user->parent_at}" .
            " inviter_at={$user->inviter_at}");

        // 候选只认 >0。header 里的 -1 表示「本次无邀请人」,不能顶掉库里正常的 maybe_parent_id。
        $potentialParentId = $candidateParentId > 0 ? $candidateParentId : (int)$user->maybe_parent_id;
        if ($potentialParentId <= 0 || $potentialParentId == $user->id) {
            Yii::warning("{$tag} 用户 #{$user->id} 无有效推荐人候选（potentialParentId={$potentialParentId}），跳过。");
            return false;
        }

        if ($user->is_inviter && $user->maybe_parent_id && $user->parent_at > $user->inviter_at) {
            Yii::warning("{$tag} 用户 #{$user->id} 已是推广员且 parent_at({$user->parent_at}) > inviter_at({$user->inviter_at})，清除 maybe_parent_id 并跳过。");
            User::updateAll(['maybe_parent_id' => 0], ['id' => $user->id]);
            return false;
        }

        // 循环引用:bindParent → checkCircularReference 内置兜底,这里做快速前置检查
        $targetPath = self::getPath($potentialParentId);
        if ($targetPath && strpos($targetPath, "-{$user->id}-") !== false) {
            Yii::warning("{$tag} 用户 #{$user->id} 检测到循环引用（推荐人 #{$potentialParentId} 的 path 包含自己），清除 maybe_parent_id 并跳过。");
            User::updateAll(['maybe_parent_id' => 0], ['id' => $user->id]);
            return false;
        }

        // 与 bindParent 同口径:mall_id + is_delete=0 + status=NORMAL + is_inviter=1。
        // 此前这里漏了 status,拉黑的上级会在这一关放行、到 bindParent 才抛异常,
        // 而调用方(注册流程 / UserLoginJob)把异常 try-catch 吞掉 → 用户看到「注册成功」
        // 但 parent_id 停在 0,拉新方和新人双向无感。现在直接 return false + 明确日志。
        $parent = User::findOne([
            'id'         => $potentialParentId,
            'mall_id'    => $user->mall_id,
            'is_inviter' => 1,
            'is_delete'  => 0,
            'status'     => User::STATUS_NORMAL,
        ]);
        if (!$parent) {
            Yii::warning("{$tag} 推荐人 #{$potentialParentId} 不可用(不存在 / 非本商城 / 已注销 / 被拉黑 / 不具备推广资格),绑定跳过。");
            return false;
        }

        $setting = OptionHelper::get('relation_setting', $user->mall_id, []);
        $childType = $setting['child_type'] ?? 0;
        Yii::warning("{$tag} 用户 #{$user->id} 绑定条件类型 child_type={$childType}（0=无条件 1=首次下单 2=首次付款）。");

        if ($childType != 0) {
            if ($user->maybe_parent_id != $potentialParentId) {
                Yii::warning("{$tag} 用户 #{$user->id} 暂存推荐人意向 maybe_parent_id={$potentialParentId}。");
                $user->maybe_parent_id = $potentialParentId;
                $user->save(false);
            }
            if (!self::hasMetOrderCondition($user, $childType)) {
                Yii::warning("{$tag} 用户 #{$user->id} 尚未满足 child_type={$childType} 的绑定条件，等待下次触发。");
                return false;
            }
            Yii::warning("{$tag} 用户 #{$user->id} 已满足 child_type={$childType} 的绑定条件，继续执行绑定。");
        }

        if (!self::bindParent($user, $potentialParentId)) {
            Yii::warning("{$tag} 用户 #{$user->id} 绑定推荐人 #{$potentialParentId} 被拒绝(已有上级 / 已有下级团队 / 平台直属),见 bindParent 日志。");
            return false;
        }
        Yii::warning("{$tag} 用户 #{$user->id} 已成功绑定推荐人 #{$potentialParentId}。");
        return true;
    }

    /** child_type=1「首次下单」/ =2「首次付款」的条件判定 */
    private static function hasMetOrderCondition(User $user, $conditionType): bool
    {
        if ($conditionType == 1) {
            $met = Order::find()->where(['user_id' => $user->id, 'is_delete' => 0])->exists();
            Yii::warning("[tryBindParent] 用户 #{$user->id} [首次下单] 条件检查：" . ($met ? '满足' : '不满足（尚无任何订单）'));
            return $met;
        }
        if ($conditionType == 2) {
            $met = Order::find()->where(['user_id' => $user->id, 'is_delete' => 0, 'pay_status' => Order::PAY_STATUS_PAID])->exists();
            Yii::warning("[tryBindParent] 用户 #{$user->id} [首次付款] 条件检查：" . ($met ? '满足' : '不满足（尚无已支付订单）'));
            return $met;
        }
        Yii::warning("[tryBindParent] 用户 #{$user->id} 未知 conditionType={$conditionType}，视为条件不满足。");
        return false;
    }

    /**
     * 绑定 / 变更 / 解除上级。
     *
     * @param bool $byAdmin 后台手动改上级(ParentChangeForm)传 true。非后台调用(登录绑定、扫码、收银台、插件)只能
     *                      给「还没有上级」的人绑定,且有下级团队的人不能被绑 —— 见下方守卫
     * @return bool 真的绑上 / 已是该上级 返回 true;被守卫拒绝返回 false;校验失败抛异常
     */
    public static function bindParent(User $user, int $newParentId, bool $byAdmin = false)
    {
        if ($user->id == $newParentId)
            throw new \Exception("不能将自己设置为推荐人");

        $pathJob = null;
        $transaction = Yii::$app->db->beginTransaction();
        try {
            // ── ① 第一条语句就加锁读:锁住本人和新上级两行(按 id 升序,防两边交叉等待) ──
            // ⛔ 在它之前不能有任何普通读:InnoDB 的事务快照在第一次普通读时建立,建早了,
            //    锁拿到之后也看不到别人刚提交的改挂 —— 下面的「当前上级」「查环」就全是旧数据。
            //    原来这里不加锁、查环用事务外读的 path:A 挂 B 与 B 挂 A 同时发生时两边都能通过,
            //    互为上级成环,重建后 A 出现在自己的祖先里、下单给自己分润(2026-09 本地复现)。
            $lockIds = [(int)$user->id];
            if ($newParentId > 0) {
                $lockIds[] = $newParentId;
            }
            sort($lockIds);
            $locked = User::find()
                ->select(['id', 'parent_id', 'mall_id', 'is_delete', 'status'])
                ->where(['id' => $lockIds])
                ->orderBy(['id' => SORT_ASC])
                ->indexBy('id')
                ->asArray()
                ->forUpdate()
                ->all();
            if (!isset($locked[$user->id])) {
                throw new \Exception("用户不存在");
            }
            $curParent = (int)$locked[$user->id]['parent_id'];
            // 调用方手里的模型可能是旧的,以锁内读到的为准
            $user->setAttribute('parent_id', $curParent);
            $user->setOldAttribute('parent_id', $curParent);

            // ── ② 锁内判断:以当前值为准 ──
            if ($curParent === -2 || $curParent === $newParentId) {
                // -2 收银台共享游客锁定;已是该上级 —— 都不再动,也不重复算团队统计、发通知
                $transaction->commit();
                return true;
            }
            if (!$byAdmin && $newParentId > 0) {
                // 非后台操作只能给「还没有上级」的人绑定:-1 平台直属是锁定的,已有上级的也不能被登录 / 扫码 / 插件改掉。
                // 原来只有 tryBindParent 看 parent_id,插件(emotion 核销、mch 扫码、收银台)直接调这里、判断写成 <=0,
                // 平台直属用户会被改挂到发码人 / 店主名下
                if ($curParent !== 0) {
                    $transaction->commit();
                    Yii::warning("[bindParent] 用户 #{$user->id} 当前上级 {$curParent},非后台操作不改,拒绝绑到 #{$newParentId}");
                    return false;
                }
                // 已有下级团队的人不能被绑:否则整棵团队树被「嫁接」到别人名下,此后整队的分润都流向对方那条线。
                // BindParentJob 早就有这道检查(2026-04 加的,本意是禁止),但 tryBindParent / 插件直调这里时一直没挡住 ——
                // 配了默认推荐人的商城,老推广员登录一次整队就被挂走
                $hasTeam = User::find()->where(['parent_id' => $user->id, 'is_delete' => 0])->exists();
                if ($hasTeam) {
                    User::updateAll(['maybe_parent_id' => 0], ['id' => $user->id]);
                    $transaction->commit();
                    Yii::warning("[bindParent] 用户 #{$user->id} 已有下级团队,拒绝绑到 #{$newParentId}(防整队嫁接;后台手动改上级不受限)");
                    return false;
                }
            }

            // 改挂前的祖先链和本人 path:提交后要把整队的团队统计从这条旧链上减掉(TeamStatUpdater::onUserMoved)
            $oldSelfPath = self::getVerifiedPath((int)$user->id);
            $oldAncestors = self::parseAncestorIds($oldSelfPath);

            if ($newParentId <= 0) {
                $user->parent_id = $newParentId;
                $user->parent_at = time();
                $user->parent_date = date('Y-m-d');
                if (!$user->save())
                    throw new \Exception(self::getErrorMsg($user));

                // [修复] 解绑时也更新 UserPath，清除旧上级链
                $pathJob = self::updatePathTree($user->id, 0);
                $newSelfPath = (string)UserPath::find()->select('path')->where(['user_id' => $user->id])->scalar();

                $transaction->commit();
                self::dispatchPathTreeUpdate($user->id, $pathJob);
                // 团队统计:整队从旧链减掉(方法内已吞异常,不影响已提交的解绑)
                TeamStatUpdater::onUserMoved((int)$user->id, (int)$user->mall_id, $oldAncestors, $oldSelfPath, $newSelfPath);
                return true;
            }

            // 补 mall_id:防跨商城绑定(把本商城用户挂到别商城的人名下)。用锁内读到的新上级
            $p = $locked[$newParentId] ?? null;
            if (!$p || (int)$p['mall_id'] !== (int)$user->mall_id || (int)$p['is_delete'] !== 0 || (int)$p['status'] !== User::STATUS_NORMAL)
                throw new \Exception("推荐人不存在或已被禁用");

            // ── ③ 锁内重新查环:按 parent_id 真值上溯(不信存量 path),顺带自愈新上级那条链的 path ──
            // 这一步必须在锁之后:此时建立的快照才包含并发刚提交的改挂
            $warm = [];
            $freshParentPath = (string)self::ensurePath($newParentId, $warm, true);
            self::checkCircularReference($user->id, $newParentId, $freshParentPath);

            $user->parent_id = $newParentId;
            $user->parent_at = time();
            $user->parent_date = date('Y-m-d');
            if (!$user->save())
                throw new \Exception(self::getErrorMsg($user));

            // [修复] 同步更新 UserPath（不依赖异步Job，防止路径不一致）
            $pathJob = self::updatePathTree($user->id, $newParentId);
            // 改挂后的 path 在事务里取:团队统计按它认新链,提交后再读可能已被紧接着的又一次改挂改掉
            $newSelfPath = (string)UserPath::find()->select('path')->where(['user_id' => $user->id])->scalar();

            $transaction->commit();

            // 子孙 path 改写:必须在 commit 之后推 —— 事务里推,调度器可能在提交前就执行,读到旧数据改了个空
            self::dispatchPathTreeUpdate($user->id, $pathJob);

            // [接入] 团队统计:整队从旧链挪到新链(事务外;方法内已吞异常,不影响已提交的改挂)
            TeamStatUpdater::onUserMoved((int)$user->id, (int)$user->mall_id, $oldAncestors, $oldSelfPath, $newSelfPath);

            // 广播「绑定/变更上级」事件。
            //
            // 这个事件此前**从未被触发过**:唯一的触发方是 executeParentChange,而它全项目零调用
            // (已随死代码一并删除),真正在跑的绑定路径就是这里,却一直没发事件。
            // 于是两个监听器一次都没执行:
            //   · api/listeners/UserParentChangeListener —— 给上级发「团队新添一名成员」通知
            //   · integral 插件 ParentChangeForIntegralListener —— 推邀请积分 Job
            // 属于「设计好了但线一直没接上」,这里补上。
            //
            // 放在 commit 之后、且 try/catch 隔离:与上面 TeamStat、与 handleAfterSave 里
            // UserEvent::REGISTER 的处理方式一致 —— 监听器再怎么出错都不能回滚已经绑好的关系,
            // 更不能把异常抛给注册/登录流程(调用方多半会吞掉,用户会看到「注册成功但没绑上」)。
            //
            // 开销:trigger 本身微秒级;通知监听器是 2 次主键查询,消息落库要 message 插件启用
            // 才有人接;积分那条是 integral 插件的异步 Job。链路只出不回 —— 监听器不碰
            // bindParent/tryBindParent,积分走 updateAllCounters 绕开 AR 事件,
            // 且 handleAfterSave 只发 REGISTER 不发本事件,不存在事件自激。
            try {
                $event = new UserEvent();
                $event->user_id = $user->id;
                Yii::$app->trigger(UserEvent::PARENT_CHANGE, $event);
            } catch (\Throwable $e) {
                Yii::error("UserService: UserEvent::PARENT_CHANGE 触发异常 uid={$user->id} - " . $e->getMessage());
            }

            return true;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::error("绑定推荐人失败 UserID:{$user->id} -> ParentID:{$newParentId} 错误: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 基于 UserPath 路径检测循环引用(O(1) 字符串匹配)。
     *
     * ⚠ 这里**必须拿到可信的 path**,否则是安全洞:
     *   tryBindParent 会先调 getPath(父),把 DB 原值(可能是缺根部前缀的坏 path)
     *   无校验地灌进 $_pathCache;若这里用普通 ensurePath(),就会撞上缓存直接拿到坏值。
     *   而坏 path 缺的正是顶上几层 —— 如果 $userId 恰好在被削掉的那段里,
     *   strpos 找不到 "-{$userId}-",**环检测就漏判**,于是允许把自己的祖先设成下级,造出真环。
     *   已实测复现:写坏 C 的 path 削掉根 A,再污染缓存,checkCircularReference(A, C) 直接放行。
     *
     * 所以:调用方若刚用 $force 重建过,把结果作为 $trustedPath 传进来(省一次上溯);
     * 没传就自己强制重建。**环检测是安全边界,不能依赖调用方的执行顺序。**
     *
     * @param int    $userId        待绑定的用户
     * @param int    $targetParentId 目标上级
     * @param string $trustedPath   调用方已 force 重建过的目标上级 path;空则本方法自行重建
     */
    private static function checkCircularReference($userId, $targetParentId, string $trustedPath = '')
    {
        $targetPath = $trustedPath;
        if ($targetPath === '') {
            $visited = [];
            $targetPath = self::ensurePath($targetParentId, $visited, true);
        }
        if ($targetPath && strpos($targetPath, "-{$userId}-") !== false) {
            throw new \Exception("检测到循环引用：不能将团队下级设置为推荐人");
        }
    }

    // =========================================================================
    // Part 3: 后台任务 - 关系链重组
    // =========================================================================


    // =========================================================================
    // Part 4: 路径管理与统计 (包含自愈逻辑)
    // =========================================================================

    /**
     * [性能优化] 变更上级时更新 Path
     * 同步更新自己的 Path(毫秒级);子孙 Path 的改写参数返回给调用方,由它在 commit 之后 dispatchPathTreeUpdate。
     * 兜底:getParents 读链时自检「上级-本人」是否与 parent_id 对得上,不一致当场重建。
     * (没有定时任务校正 path,TeamStatFullRebuildTask 只重算统计)
     */
    private static function updatePathTree($userId, $newParentId): ?array
    {
        // 1. 获取(或创建)当前 Path
        $userPathModel = UserPath::findOne(['user_id' => $userId]);
        if (!$userPathModel) {
            $userPathModel = new UserPath();
            $userPathModel->user_id = $userId;
            $userPathModel->mall_id = User::findOne($userId)->mall_id;
            $userPathModel->path = "-" . $userId . "-";
        }

        $oldPath = $userPathModel->path;

        // 2. 计算新路径前缀
        //
        // ⚠ 这里原本是「父只要有 user_path 行,就直接拿它的 path 当前缀」。那是坏数据的繁殖通道:
        //   父的 path 若已损坏(历史上被 ensurePath 的 100 层保险削掉过根部前缀),新下级就
        //   原样继承一份,PathTreeUpdateJob 还会把它复制给整棵子树 —— 一条坏 path 就这么越长越多。
        //   改成走 ensurePath:它只读 User.parent_id 上溯到真根,不采信任何存量 path。
        //   调用方(bindParent)已在锁内用 $force=true 重建过新上级的 path,这里命中缓存即可,不重复上溯;
        //   若缓存未命中(其它调用方),ensurePath 也会完整重建,同样安全。
        $newPathPrefix = "-";
        if ($newParentId > 0) {
            $parentPathStr = self::ensurePath($newParentId);
            if ($parentPathStr) {
                $newPathPrefix = $parentPathStr;
            }
        }

        $newPath = $newPathPrefix . $userId . '-';

        // 3. 同步更新自己（毫秒级）
        $userPathModel->path = $newPath;
        if (!$userPathModel->save()) {
            throw new Exception("UserPath save failed: " . json_encode($userPathModel->getErrors()));
        }
        self::clearPathCache($userId);

        // 4. 子孙 Path 交给调用方在 commit 之后推 Job(见 dispatchPathTreeUpdate),这里只返回要不要推
        if ($oldPath !== $newPath) {
            return ['mallId' => (int)$userPathModel->mall_id, 'oldPath' => $oldPath, 'newPath' => $newPath];
        }
        return null;
    }

    /**
     * 改完某人的 path 后,把他的子孙 path 改写到新前缀下(PathTreeUpdateJob)。**必须在事务 commit 之后调用**。
     * 有下级的当场执行,没下级的推 Job(见方法内注释)。
     *
     * 入队失败(Redis 故障等)时当场同步执行 —— JobHelper::push 失败只返回 false、不抛异常,
     * 原来的「catch 里降级同步」永远走不到,整棵子树就停在旧链上、分润一直发给旧上线(2026-09 复现)。
     * 同步执行也失败就只记 error:下级读链时 getParents 会自检、发现对不上当场按 parent_id 重建。
     */
    private static function dispatchPathTreeUpdate(int $userId, ?array $job): void
    {
        if (!$job) {
            return;
        }
        $params = ['userId' => $userId, 'mallId' => $job['mallId'], 'oldPath' => $job['oldPath'], 'newPath' => $job['newPath']];
        // 有下级的当场改写:紧接着 TeamStatUpdater::onUserMoved 要按 path 圈出这一队挪团队统计,子孙还挂在旧前缀下就圈不全。
        // 调度器积压、或上次改挂的改写 Job 还没跑时又改挂,前后两次叠在一起,团队人数就算错(2026-09 随机压测复现)。
        // 没下级的(注册绑定、普通会员改挂)照旧只推 Job,不在请求里多扫一遍 user_path;当场改写失败的也退回推 Job,由调度器重试。
        try {
            if (User::find()->where(['parent_id' => $userId])->exists()) {
                (new \app\jobs\user\PathTreeUpdateJob($params))->execute();
                // 子孙的 path 都换了前缀:本请求里缓存着的旧 path 全部作废。不清的话,同一请求里接着处理他的下级
                // (后台批量改上级、巡检)会按旧前缀拼链 / 圈子树(2026-09 随机压测:巡检把团队人数算成 0 写回库)
                self::clearPathCache();
                return;
            }
        } catch (\Throwable $e) {
            // 已在 commit 之后:这里抛出去会让调用方以为改挂失败,只记日志、退回推 Job
            Yii::warning("[PathTree] 当场改写子孙 path 失败,改推 Job 重试 user={$userId}: " . $e->getMessage());
        }
        $pushed = false;
        try {
            $pushed = (bool)\app\helpers\JobHelper::push(new \app\jobs\user\PathTreeUpdateJob($params));
        } catch (\Throwable $e) {
            Yii::warning("[PathTree] Job 推送异常 user={$userId}: " . $e->getMessage());
        }
        if ($pushed) {
            return;
        }
        Yii::warning("[PathTree] Job 推送失败,改为同步改写子孙 path user={$userId}");
        try {
            (new \app\jobs\user\PathTreeUpdateJob($params))->execute();
            self::clearPathCache();   // 同上:子孙 path 换了前缀,缓存着的旧 path 作废
        } catch (\Throwable $e) {
            Yii::error("[PathTree] 同步改写子孙 path 也失败 user={$userId}: " . $e->getMessage() . "(下级读链时会自检重建)");
        }
    }

    /**
     * [核心修复] 确保并获取用户的 Path
     * 如果用户没有 Path，会去查 User 表并递归修补上级 Path，保证链条完整
     *
     * @param int $userId
     * @return string Path字符串 (例如 "-1-5-10-")
     */
    /**
     * [修复] 确保并获取用户的 Path
     * 如果不存在则创建，如果存在但路径与 parent_id 不一致则修复
     */
    /**
     * 确保并获取用户的 Path（自愈式）
     * 如果不存在则创建，如果存在但路径与 parent_id 不一致则修复
     * 内置循环检测 + 深度上限，防止数据库出现环形 parent 导致栈溢出
     */
    /** @var array 请求级缓存，避免同一请求内重复查 DB */
    private static $_pathCache = [];

    /**
     * 请求级 path 缓存的容量上限。
     * PHP-FPM 请求结束即销毁,无害;但队列 worker / cli 长批处理是常驻进程,
     * 静态数组只增不淘汰会持续吃内存(几万用户 × path ≈ 数十 MB 永不释放)。
     * 超过上限就整清 —— 简单、无锁、对命中率的影响可忽略(热点用户会立刻重新填入)。
     */
    private const PATH_CACHE_MAX = 20000;

    /**
     * 同时按字节封顶。只按条数不够:path 长度随层数线性增长,千层链一条就 5~10KB,
     * 2 万条能到 100~200MB,常驻进程(队列 / cron)连续处理深层用户会被撑爆内存。
     */
    private const PATH_CACHE_MAX_BYTES = 33554432;   // 32MB

    /** $_pathCache 里 path 的总字节数,与缓存同步增减 */
    private static $_pathCacheBytes = 0;

    /** 单段写入的字节预算:段内 SQL 大致不超过它,避开 PHP 内存与 max_allowed_packet 双重上限 */
    private const PATH_WRITE_BUDGET = 4194304;   // 4MB

    /** 写 path 缓存,条数或字节超上限先整清,防常驻进程内存无限膨胀 */
    private static function cachePath(int $userId, string $path): void
    {
        if (isset(self::$_pathCache[$userId])) {
            self::$_pathCacheBytes -= strlen(self::$_pathCache[$userId]);
            unset(self::$_pathCache[$userId]);
        }
        if (count(self::$_pathCache) >= self::PATH_CACHE_MAX
            || self::$_pathCacheBytes + strlen($path) > self::PATH_CACHE_MAX_BYTES) {
            self::$_pathCache = [];
            self::$_pathCacheBytes = 0;
        }
        self::$_pathCache[$userId] = $path;
        self::$_pathCacheBytes += strlen($path);
    }

    /**
     * getParents 的请求级结果缓存。只能请求级 —— 它缓存「祖先状态视图」,
     * 某上级被拉黑/注销/恢复会影响【他所有子孙】的结果(跨用户失效,单 uid 清不掉)。
     * FPM 一个请求内状态不变,安全;与 $_pathCache 同挂 clearPathCache() 全清。
     * 命中场景:商品列表/详情逐 SKU 定价对同一 buyer 反复调 getParents。
     */
    private static $_parentsCache = [];

    /**
     * 快速读取 path（优先走缓存，不触发自愈）
     * 适用于只读场景（getParents、团队查询等），性能最优
     */
    public static function getPath(int $userId): ?string
    {
        if (isset(self::$_pathCache[$userId])) {
            return self::$_pathCache[$userId];
        }

        $path = UserPath::find()->select('path')->where(['user_id' => $userId])->scalar();
        if ($path) {
            self::cachePath($userId, $path);
            return $path;
        }

        // 缓存未命中且无记录，走 ensurePath 自愈
        return self::ensurePath($userId);
    }

    /**
     * 子树查询用的 LIKE 匹配值(团队查询统一入口)。
     *
     * 取本人自身 path 作前缀(如 '-1-2-5-%'),命中 idx-user_path-path(255) 索引,
     * 替代 "%-{id}-%" 前导通配符全表扫 —— 单棵树里每个用户只有一条 path,'-' 分隔天然防 5/55 混淆,
     * 两者返回完全同一批(本人 + 全部子孙)。本人 path 缺失(极少数据不一致)→回退包含匹配,保证不漏。
     *
     * 用法:->where(['like', 'path', UserService::subtreeLike($userId), false])
     *       (第 4 参 false 表示值已含通配、Yii 不再自动两侧加 %)
     */
    public static function subtreeLike(int $userId): string
    {
        $ownPath = (string)self::getPath($userId);
        return $ownPath !== '' ? $ownPath . '%' : "%-{$userId}-%";
    }

    /**
     * 确保并获取用户的 Path(自愈式,带请求级缓存)。
     * 不存在则创建;存在但与 parent_id 链不一致则修复。
     *
     * ## 为什么是循环而不是递归
     *
     * 这里原本是自底向上递归,并且带一道「深度 >= 100 就返回 -{userId}- 」的保险。
     * 那道保险本身就是 bug 制造机:被拦下的那一层会伪装成根返回,**它下面的每一层**
     * 都拿这个假前缀算出 correctPath 并写库,一次调用就能把 100 层的 path 写坏。
     *
     * 实测(2026-08-31,馨薇妍生产库):最深关系链 173 层、超 100 层的有 709 人;
     * 造一条 105 层链只调一次本方法,就有 100 层 path 被截断、叶子从 105 层被钳到 101 层。
     * 线上 56 条坏 path 就是这么来的 —— 缺失的永远是**从根开始的连续前缀**,
     * 截断点正好落在从根数第 (链深 − 100 + 1) 层。
     *
     * 业务上关系链没有层数上限,所以这里也不能有。把上限调大只是把撞墙时间往后推
     * (链还在长),而递归再深也会先爆 PHP 调用栈 —— 只有改成循环才是真的没有上限。
     *
     * 分两步:
     *   ① 自底向上**循环**收集待重建的节点(遇到缓存命中/环/User 不存在就停,那里就是可信前缀);
     *   ② 自顶向下拼 path,只写与现值不同的行,批量 upsert。
     *
     * upsert 而非 check-then-insert:本方法会被 TeamStatUpdater(多处)、TeamStatScrubTask
     * (无锁、时间盒到点续跑)、队列 Job、迁移脚本并发调用,多个进程重建不同用户时会撞上同一个
     * **共同祖先**,check-then-insert 会报 Duplicate entry '<ancestor_id>' for key 'user_path.PRIMARY'
     * (现场:重建用户 37831 时插入其祖先 26646 失败)。user_path 主键就是 user_id,天然适合 upsert。
     *
     * ## $force:什么时候必须绕开缓存
     *
     * 本方法**能自愈坏 path** —— 它只读 User.parent_id 一路上溯到真根,完全不采信任何存量 path。
     * 但请求级缓存会把这份能力废掉:getPath() 会把 DB 原值(可能是坏的)无校验地灌进 $_pathCache,
     * 之后 ensurePath 撞上缓存直接返回,重建根本不发生。注册链上就是这个顺序:
     *     tryBindParent → getPath(父)污染缓存 → checkCircularReference → ensurePath(父) 被短路
     *     → updatePathTree 拿库里的坏值当前缀 → 新用户原样继承 → PathTreeUpdateJob 再复制给整棵子树
     * 于是一条坏 path 会随着新注册不断繁殖。
     *
     * 所以**写入路径**(要拿别人的 path 当前缀往下拼)必须传 $force=true,宁可多走一趟上溯,
     * 也不能把没校验过的值焊进新数据。读取路径保持用缓存,不受影响。
     *
     * @param int   $userId
     * @param array $_visited 兼容旧签名的已访问集合;仅用于环检测的初始状态,调用方一般不传
     * @param bool  $force    true=无视缓存强制重建(写入路径专用)
     * @return string
     */
    public static function ensurePath($userId, array &$_visited = [], bool $force = false)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return '';
        }
        if (!$force && isset(self::$_pathCache[$userId])) {
            return self::$_pathCache[$userId];
        }

        // ── ① 自底向上收集待重建链(纯循环,无深度上限)──
        $chain  = [];     // 待重建节点,自底向上
        $mallOf = [];     // 顺带记 mall_id,②里 upsert 要用
        $seen   = $_visited;
        $prefix = '-';    // 链顶之上已确定的 path
        $cur    = $userId;

        while ($cur > 0) {
            if (!$force && isset(self::$_pathCache[$cur])) {
                $prefix = self::$_pathCache[$cur];   // 命中缓存:它上面的祖先不必再走
                break;
            }
            if (isset($seen[$cur])) {
                Yii::error("[ensurePath] 检测到循环引用! UserID:{$cur}, 链路:" . implode('→', array_keys($seen)));
                // 前缀用 '-'(在环上断开),不能用 "-{$cur}-":那会写出 -A-B-A-,A 出现在自己的祖先里,下单给自己分润
                $prefix = '-';
                break;
            }
            $seen[$cur] = true;

            $row = (new \yii\db\Query())
                ->select(['id', 'parent_id', 'mall_id'])
                ->from(User::tableName())
                ->where(['id' => $cur])
                ->one();

            if (!$row) {
                // User 行不存在(被物理删除)。但只要它自己的 UserPath 行还在,就用那条现成的 path ——
                // 它记录了这个节点及其之上的完整祖先链。直接用 "-{cur}-" 会把它锚成新根,
                // 导致【下级重建 path 时丢掉它上面的所有祖先】(getParents 返回空 = 下级变孤儿,已复现)。
                // 仅当 path 也不存在时,才真的无从上溯,退化为单节点根。
                $ownPath = UserPath::find()->select('path')->where(['user_id' => $cur])->scalar();
                $prefix  = $ownPath ?: "-{$cur}-";
                self::cachePath($cur, $prefix);
                break;
            }

            $chain[]      = $cur;
            $mallOf[$cur] = (int)$row['mall_id'];
            $cur          = (int)$row['parent_id'];
        }

        $_visited = $seen;   // 回写,兼容旧签名的引用语义

        if (!$chain) {
            return $prefix;  // 目标节点本身就是"User 不存在"或环起点
        }

        // ── ② 自顶向下拼 path,分段处理、只写有变化的行 ──
        //
        // 为什么要分段而不是一把梭:物化路径的总量是 O(深度²) —— 第 n 层的 path 串里有 n 个 id,
        // 一条万层链光 path 文本加起来就 ~450MB。所以现值查询、待写缓冲、批量 SQL 都必须
        // 按段滚动、用完即弃,否则深链必 OOM(实测一次性处理 10000 层直接撑爆 128M)。
        $db      = Yii::$app->db;
        $now     = time();
        $path    = $prefix;
        $ordered = array_reverse($chain);

        // 请求级缓存对超深链要收着点:那个缓存是给"同一请求里反复查同一个人"用的,
        // 万层链上的中间节点几乎不会被复查,全塞进去只会把内存吃光。
        $cacheAll = count($ordered) <= 500;

        // 段大小按**字节**自适应,不能写死行数:path 长度随深度线性增长,
        // 固定 200 行在浅链只有几百 KB,到万层就是几十 MB 的单条 SQL —— 先撑爆
        // PHP 内存,再撞 max_allowed_packet。这里按段首的 path 长度反推每段放多少行,
        // 浅链(线上最深 173 层)一段走完,深链自动切碎。
        $total = count($ordered);
        $cursor = 0;
        while ($cursor < $total) {
            $per  = strlen($path) + 32;   // 本段每行大约这么长(段内只增不减,取段首值偏保守)
            $size = (int) (self::PATH_WRITE_BUDGET / max($per, 1));
            // 下限取 1 而不是某个"看起来合理"的数:极深链上单个 path 本身就能超过预算,
            // 这时只能一行一段。写死下限会让那种链的单条 SQL 重新涨回超包大小。
            $size = max(1, min(500, $size));
            $seg  = array_slice($ordered, $cursor, $size);
            $cursor += $size;

            $existing = [];
            foreach (UserPath::find()->select(['user_id', 'path'])
                         ->where(['user_id' => $seg])->asArray()->all() as $r) {
                $existing[(int)$r['user_id']] = (string)$r['path'];
            }

            $toWrite = [];
            foreach ($seg as $id) {
                $path .= $id . '-';
                if (!array_key_exists($id, $existing) || $existing[$id] !== $path) {
                    $toWrite[] = [$id, $mallOf[$id] ?? 0, $path, $now, $now];
                }
                if ($cacheAll) {
                    self::cachePath($id, $path);
                }
            }
            unset($existing);

            if ($toWrite) {
                $sql = $db->getQueryBuilder()->batchInsert(
                    UserPath::tableName(),
                    ['user_id', 'mall_id', 'path', 'created_at', 'updated_at'],
                    $toWrite
                );
                if ($sql !== '') {
                    // 已存在则只纠正 path/updated_at(自愈本就是为了修正错误的 path),不动 mall_id/created_at
                    $sql .= ' ON DUPLICATE KEY UPDATE `path`=VALUES(`path`), `updated_at`=VALUES(`updated_at`)';
                    $db->createCommand($sql)->execute();
                }
                unset($toWrite, $sql);
            }
        }
        unset($ordered);

        if (!$cacheAll) {
            self::cachePath($userId, $path);   // 深链只缓存目标本人
        }

        return $path;
    }

    /**
     * 清除 path 缓存（bindParent 变更后调用）
     */
    public static function clearPathCache(?int $userId = null): void
    {
        if ($userId !== null) {
            if (isset(self::$_pathCache[$userId])) {
                self::$_pathCacheBytes -= strlen(self::$_pathCache[$userId]);
                unset(self::$_pathCache[$userId]);
            }
            self::$_parentsCache = [];   // 按祖先状态,单 uid 清不干净,整清最稳
        } else {
            self::$_pathCache = [];
            self::$_pathCacheBytes = 0;
            self::$_parentsCache = [];
        }
    }

    /**
     * 极速统计团队人数
     */
    public static function getTeamCount($userId)
    {
        $path = self::getPath($userId);
        if (!$path)
            return 0;

        $prefix = $path . '%';
        return UserPath::find()
            ->where(['like', 'path', $prefix, false])
            ->andWhere(['!=', 'user_id', $userId])
            ->count();
    }

    /**
     * [新增] 极速获取某人的【下级团队列表】 (O(1)级前缀检索)
     * @param int $userId 目标用户ID
     * @param int $limit 查询数量 (防止团队几十万人撑爆内存，默认查询前100)
     * @param int|null $maxLevel 最大查询深度 (例如只查直推传 1，查2级传 2，查无限代传 null)
     * @return array
     */
    public static function getDescendantTeam($userId, $limit = 100, $maxLevel = null)
    {
        $path = self::getPath($userId);
        if (!$path) return [];
        $prefix = $path . '%';

        $query = User::find()->alias('u')
            ->select(['u.id', 'u.nickname', 'u.avatar_url', 'u.mobile', 'u.level', 'up.path'])
            ->leftJoin(['up' => UserPath::tableName()], 'u.id = up.user_id')
            ->where(['like', 'up.path', $prefix, false])
            ->andWhere(['!=', 'u.id', $userId])
            ->andWhere(['u.is_delete' => 0]);

        // 层数限制必须下推到 SQL,不能先 limit 取完再在 PHP 里筛。
        // 原来是「按 u.id DESC 取 N 条 → 再过滤层数」,而深层成员往往 id 更大、排在前面,
        // 于是想查直推(maxLevel=1)时,N 个名额可能全被深层成员占满,直推一个都返不回来。
        // 实测:5 层链、limit=2 或 3 时返回 0 条,limit=10 才拿到那个直推。
        // 相对层数 = 该成员 path 的分隔符数 − 本人 path 的分隔符数,直接交给 MySQL 算。
        if ($maxLevel !== null) {
            $baseDepth = substr_count($path, '-');
            $query->andWhere(new \yii\db\Expression(
                '(CHAR_LENGTH(up.path) - CHAR_LENGTH(REPLACE(up.path, :dash, :empty))) - :base <= :maxLv',
                [':dash' => '-', ':empty' => '', ':base' => $baseDepth, ':maxLv' => (int)$maxLevel]
            ));
        }

        $results = $query->orderBy(['u.id' => SORT_DESC])->limit($limit)->asArray()->all();

        foreach ($results as &$item) {
            $item['relative_level'] = substr_count($item['path'], '-') - substr_count($path, '-');
        }
        return $results;
    }

    /**
     * 内部助手：快速获取 Path 字符串 (旧方法，仅作简单查询用，建议优先用 ensurePath)
     */
    public static function getPathStrByUserId($userId)
    {
        $model = UserPath::find()->select(['path'])->where(['user_id' => $userId])->asArray()->one();
        return $model ? $model['path'] : null;
    }



    public static function parseAncestorIds($path)
    {
        if (empty($path))
            return [];
        $ids = array_map('intval', explode('-', trim($path, '-')));
        $self = array_pop($ids);
        // 剔除本人、去重(保留首次出现的顺序)。正常 path 不会触发;坏数据(环)里本人若出现在祖先中,
        // 按链分润的插件会把买家当成自己的上级给他发钱
        $out = [];
        $seen = [];
        foreach ($ids as $id) {
            if ($id <= 0 || $id === $self || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $id;
        }
        return $out;
    }

    /**
     * 核对 path 与 parent_id 是否一致:[根 … 父, 本人] 每一段「上级-下级」都要对得上,根不能还有上级,不能有重复。
     *
     * @param array $parentOf [user_id => parent_id],至少含 path 上出现的 id;查不到的行(物理删除)跳过核对
     */
    private static function pathMatchesParents(int $userId, string $path, array $parentOf): bool
    {
        $ids = array_map('intval', explode('-', trim($path, '-')));
        if (!$ids || end($ids) !== $userId || count($ids) !== count(array_unique($ids))) {
            return false;
        }
        foreach ($ids as $i => $id) {
            if (!array_key_exists($id, $parentOf)) {
                continue;
            }
            $actual = (int)$parentOf[$id];
            if ($i === 0) {
                if ($actual > 0) {
                    return false;   // 根还有上级 = path 缺了靠根的那一段
                }
            } elseif ($actual !== $ids[$i - 1]) {
                return false;       // 这一段上下级对不上 = path 过期(改挂后没改写到)或损坏
            }
        }
        return true;
    }

    /**
     * 读 path 并核对它和 parent_id 是否一致,不一致当场按 parent_id 重建(按链算钱 / 统计的地方用这个,别用裸 getPath)。
     *
     * 为什么需要:path 是异步改写的冗余数据,改挂后 PathTreeUpdateJob 失败 / 乱序 / 还没跑,子孙的 path 就是旧的,
     * 按旧 path 分润就是把钱发给旧上线。多一条 SQL(按 id 批量查 parent_id),换来读的时候就能发现并修好。
     */
    public static function getVerifiedPath(int $userId): string
    {
        $path = (string)self::getPath($userId);
        if ($path === '') {
            return '';
        }
        $ids = array_values(array_unique(array_map('intval', explode('-', trim($path, '-')))));
        $parentOf = User::find()->select(['parent_id', 'id'])->where(['id' => $ids])->indexBy('id')->column();
        if (self::pathMatchesParents($userId, $path, $parentOf)) {
            return $path;
        }
        return self::rebuildMismatchedPath($userId, $path);
    }

    /** path 与 parent_id 对不上:记日志,强制按 parent_id 重建本人这条链 */
    private static function rebuildMismatchedPath(int $userId, string $badPath): string
    {
        self::clearPathCache($userId);
        $v = [];
        $rebuilt = (string)self::ensurePath($userId, $v, true);
        Yii::warning("[UserPath] 用户 #{$userId} 的 path 与 parent_id 不一致,已按 parent_id 重建:{$badPath} → {$rebuilt}");
        return $rebuilt;
    }

    // =========================================================================
    // Part 5: 缓存、鉴权与工具
    // =========================================================================

    /** 令牌缓存上限 7 天(与 AppBootstrap 同口径);令牌本身的有效期另由 tokenTtl() 决定 */
    const TOKEN_CACHE_MAX_TTL = 7 * 24 * 60 * 60;

    /** 登录有效期默认天数(后台「商城设置 → 登录注册 → 登录有效期」未填时) */
    const LOGIN_EXPIRE_DAYS_DEFAULT = 30;

    /**
     * 登录有效期(秒)。按「连续多少天没打开就要重新登录」理解 —— 用户只要还在用,
     * AppBootstrap 每天会顺手续一次(见 renewToken),不会再出现「天天在用,第 7 天被踢下线」。
     */
    public static function tokenTtl(int $mallId): int
    {
        $days = 0;
        if ($mallId > 0) {
            try {
                $days = (int)\app\helpers\MallSettingHelper::get('login_expire_days', $mallId, 0);
            } catch (\Throwable $e) {
                $days = 0;
            }
        }
        if ($days < 1) {
            $days = self::LOGIN_EXPIRE_DAYS_DEFAULT;
        }
        return min($days, 365) * 86400;
    }

    /**
     * 活跃续期:令牌被使用时,把到期时间往后推到「现在 + 有效期」。
     *
     * 为了不每个请求都写库,距上次续期不足一个步长(1 天,有效期很短时取 1/4)就跳过,
     * 所以实际效果是「最后一次打开后再过 有效期 ±1 天 才过期」。
     * 有效期也记在令牌缓存里(ttl):平时只比对缓存,不读商城配置(mall_setting 带着协议富文本,整包不小);
     * 真要续期时才读一次最新配置,所以后台改了天数,最迟一天内生效。
     *
     * @param string   $tokenString 原始令牌
     * @param array    $cached      令牌缓存里的 expire_at / ttl(旧格式缓存没有,传空数组,会回库查一次到期时间)
     * @param array    $cacheData   令牌缓存内容(user_id/openid/ip),续期后连同新 expire_at/ttl 一起重写
     * @return int|null|false 续期后的到期时间;null = 不需要续;false = 令牌在库里已失效(退出/强制下线/过期),本次应按未登录处理
     */
    public static function renewToken(string $tokenString, array $cached, int $mallId, array $cacheData)
    {
        $hash = hash('sha256', $tokenString);
        $cacheKey = User::CACHE_KEY_TOKEN_MAP . md5($tokenString);
        $now = time();

        $expireAt = isset($cached['expire_at']) ? (int)$cached['expire_at'] : 0;
        $ttl = isset($cached['ttl']) ? (int)$cached['ttl'] : 0;
        $patchCache = !$expireAt || !$ttl;
        if (!$expireAt) {
            $expireAt = (int)AccessToken::find()->select('expire_at')
                ->where(['token' => $hash, 'model' => User::class])
                ->andWhere(['>', 'expire_at', $now])
                ->scalar();
            if (!$expireAt) {
                CacheHelper::remove($cacheKey);
                return false;
            }
        }
        if (!$ttl) {
            $ttl = self::tokenTtl($mallId);
        }

        // 还新鲜,或后台把有效期调短了(新到期比现有的还早)—— 不动库;旧缓存补上 expire_at/ttl,下次就不用回库了
        if ($now + $ttl - $expireAt < min(86400, intdiv($ttl, 4))) {
            if ($patchCache) {
                CacheHelper::set($cacheKey, ['expire_at' => $expireAt, 'ttl' => $ttl] + $cacheData, max(60, min(self::TOKEN_CACHE_MAX_TTL, $expireAt - $now)));
            }
            return null;
        }

        // 要续了:按最新配置算(缓存里的 ttl 可能是改配置之前的)
        $ttl = self::tokenTtl($mallId);
        $newExpire = $now + $ttl;
        if ($newExpire <= $expireAt) {
            // 后台调短了天数:已发的长令牌不缩短,但缓存里的 ttl 换成新值,免得之后每个请求都判「要续」再读一遍配置
            CacheHelper::set($cacheKey, ['expire_at' => $expireAt, 'ttl' => $ttl] + $cacheData, max(60, min(self::TOKEN_CACHE_MAX_TTL, $expireAt - $now)));
            return null;
        }

        // updateAll 不走 afterSave,不会再排一次过期清理任务;条件里带 expire_at>now:
        // 令牌刚被退出登录/强制下线删掉的话影响 0 行,就别再把它写回缓存「复活」
        $affected = AccessToken::updateAll(
            ['expire_at' => $newExpire],
            ['and', ['token' => $hash, 'model' => User::class], ['>', 'expire_at', $now]]
        );
        if (!$affected) {
            CacheHelper::remove($cacheKey);
            return false;
        }

        CacheHelper::set($cacheKey, ['expire_at' => $newExpire, 'ttl' => $ttl] + $cacheData, min(self::TOKEN_CACHE_MAX_TTL, $ttl));
        return $newExpire;
    }

    public static function saveToken(User $user, $wx_openid = null)
    {
        $token = new AccessToken();
        $token->token = hash('sha256', $user->access_token); // 哈希存储，DB泄露不暴露原始Token
        $token->model_id = $user->id;
        $token->model = User::class;
        $token->expire_at = time() + self::tokenTtl((int)$user->mall_id);
        $token->wx_openid = $wx_openid ?: $user->openid;
        $token->platform = Yii::$app->platform;
        $token->ip = Yii::$app->request->remoteIP;
        if (!$token->save()) {
            Yii::error("AccessToken保存失败 UserID:{$user->id} Error:" . json_encode($token->getErrors()));
            return false;
        }

        // 把本次登录的 openid 持久化到用户对应平台列（wechat_openid / mpwx_openid），
        // 让推送(WxNotifyListener 读 wechat_openid)等用上「登录那一刻的当前号」openid。
        $loginOpenid = $token->wx_openid;
        if ($loginOpenid) {
            $platform = strtolower((string)Yii::$app->platform);
            $col = '';
            if ($platform === 'wechat' || $platform === 'official') {
                $col = 'wechat_openid';
            } elseif ($platform === 'mpwx' || $platform === 'wxapp') {
                $col = 'mpwx_openid';
            }
            if ($col && $user->hasAttribute($col) && $user->$col !== $loginOpenid) {
                $user->updateAttributes([$col => $loginOpenid]);
            }
        }

        $tokenCacheData = [
            'user_id' => $user->id,
            'openid' => $token->wx_openid,
            'ip' => $token->ip,
            'expire_at' => (int)$token->expire_at, // 续期判断用(见 renewToken),省得每次回库查
            'ttl' => (int)$token->expire_at - time(),
        ];
        $tokenCacheKey = User::CACHE_KEY_TOKEN_MAP . md5($user->access_token);
        CacheHelper::set($tokenCacheKey, $tokenCacheData, min(self::TOKEN_CACHE_MAX_TTL, (int)$token->expire_at - time()));

        // 新登录成功，清除该用户的强制登出标记
        CacheHelper::remove(User::CACHE_KEY_FORCE_LOGOUT . $user->id);

        self::refreshIdentityCache($user);
        return true;
    }

    public static function refreshIdentityCache(User $user)
    {
        $cacheKey = User::CACHE_KEY_IDENTITY . $user->id;
        $identityData = [
            'id' => $user->id,
            'mall_id' => $user->mall_id,
            'nickname' => $user->nickname,
            'avatar_url' => $user->avatar_url,
            'mobile' => $user->mobile,
            'openid' => $user->openid,
            'union_id' => $user->union_id,
            'status' => $user->status,
            'level_id' => $user->level_id,
            'is_clerk' => $user->is_clerk,
            'is_mch' => $user->is_mch,
            'is_delete' => $user->is_delete,
            'level' => $user->level,
        ];
        CacheHelper::set($cacheKey, $identityData, 3600);
    }

    public static function logout(User $user)
    {
        $tokenString = $user->access_token;
        if ($tokenString) {
            // DB 中存的是哈希，用哈希匹配删除
            AccessToken::deleteAll(['token' => hash('sha256', $tokenString), 'model' => User::class]);
            $tokenCacheKey = User::CACHE_KEY_TOKEN_MAP . md5($tokenString);
            CacheHelper::remove($tokenCacheKey);
        }
        try {
            Yii::$app->user->logout();
        } catch (\Exception $e) {
            // API stateless environment: ignore session destroy errors
        }
        return true;
    }

    /**
     * 强制失效指定用户的所有会话（封禁/改密/踢出时调用）
     *
     * 原理：
     * 1. 清除身份缓存 → 下次请求必须回源DB → 封禁状态立即生效
     * 2. 删除所有DB Token → Redis Token缓存回源时找不到记录
     * 3. 设置强制登出标记 → 拦截Redis Token缓存的7天残留窗口
     */
    public static function invalidateUserAuth($userId)
    {
        // 1. 清除身份缓存
        CacheHelper::remove(User::CACHE_KEY_IDENTITY . $userId);

        // 2. 删除该用户所有 AccessToken
        AccessToken::deleteAll(['model_id' => $userId, 'model' => User::class]);

        // 3. 设置强制登出标记（TTL=8天，覆盖Token缓存7天窗口）
        CacheHelper::set(User::CACHE_KEY_FORCE_LOGOUT . $userId, time(), 8 * 24 * 60 * 60);

        Yii::warning("[UserService] 已强制失效用户 #{$userId} 的所有会话");
    }

    /**
     * 检查用户封禁状态, 必要时 lazy 自动解封.
     *
     * - 用户 status != STATUS_BLACK → 返回 null (放行)
     * - 用户 status == STATUS_BLACK 且 banned_until 已过 → 自动解封 + 写 auto_unban 审计, 返回 null
     * - 用户 status == STATUS_BLACK 仍在封禁期 → 返回 ['msg' => ..., 'reason' => ..., 'banned_until' => ...]
     *
     * 登录入口统一调用本方法, 保证封禁语义一处定义. 之前 LoginForm / MobileSignInForm / LoginByMpwxPhoneForm
     * 分别硬写"该账号已被禁用", 没有原因也没有到期时间, 体验差, 且到期解封要等后台手动操作.
     *
     * @param User $user 入参必须是已经 save 过的持久化对象
     * @return array|null null = 放行, 数组 = 拦截
     */
    public static function checkBanStatus(User $user)
    {
        if ((int)$user->status !== User::STATUS_BLACK) {
            return null;
        }

        // 已到解封时间 → lazy 自动解封
        $until = $user->banned_until ? (int)$user->banned_until : null;
        if ($until !== null && $until > 0 && $until <= time()) {
            $snapshot = [
                'status'       => (int)$user->status,
                'ban_reason'   => (string)$user->ban_reason,
                'banned_until' => $user->banned_until,
            ];

            $user->status       = User::STATUS_NORMAL;
            $user->ban_reason   = '';
            $user->banned_by    = 0;
            $user->banned_at    = 0;
            $user->banned_until = null;
            $user->save(false);

            // 写 auto_unban 审计 (operator_id = 0 表示系统)
            try {
                $log = new \app\models\UserBanLog();
                $log->user_id       = $user->id;
                $log->action        = \app\models\UserBanLog::ACTION_AUTO_UNBAN;
                $log->reason        = '封禁到期自动解封';
                $log->operator_id   = 0;
                $log->operator_name = '系统';
                $log->snapshot      = \yii\helpers\Json::encode($snapshot);
                $log->save(false);
            } catch (\Throwable $e) {
                Yii::warning('[UserService] 自动解封审计写入失败: ' . $e->getMessage());
            }

            return null;
        }

        // 仍在封禁期
        $msg = '该账号已被禁用';
        if (!empty($user->ban_reason)) {
            $msg .= ':' . $user->ban_reason;
        }
        if ($until !== null && $until > 0) {
            $msg .= ' (解封时间:' . date('Y-m-d H:i', $until) . ')';
        }
        return [
            'msg'          => $msg,
            'reason'       => (string)$user->ban_reason,
            'banned_until' => $until,
        ];
    }

    /**
     * 获取用户的祖先链（基于 UserPath，O(1) 路径解析）
     * @param int $userId 用户ID
     * @param int $level 最多取几层上级（0=全部）
     * @param bool $includeSelf 是否包含自己
     * @return array [{id, parent_id, nickname, mobile, level}, ...] level=1 是直接上级
     */
    /**
     * 该用户当前是否有资格【收取新的分润】。
     *
     * 单跳直推奖(massage/youwei/mch/recruit 的 partner)只读 buyer.parent_id 一跳,
     * 天然不经过 getParents 的过滤 —— 必须在发钱前显式调用本方法把关。
     *
     * 语义与 getParents / bindParent 一致:is_delete=0 且 status=STATUS_NORMAL。
     *
     * ⚠ 直推奖失去资格时【烧掉,不穿透】:直推奖奖励的是「那个具体的人的推荐行为」,
     *   他失去资格这份奖就不存在了,不该转给他的上级 —— 那会改变分润归属。
     *   多跳分润链的穿透语义只适用于 getParents,不适用于单跳。
     *
     * ⚠ 这是「新分润的准入」,不是「已计提佣金的发放」。
     *   已计提的 PENDING 佣金由 CommissionService::settle 把关:
     *   已注销 → 作废(终态);被拉黑 → 照发到冻结账户(既得利益,解封后可用)。
     *
     * 用户行不存在时返回 false(宁可不发,也不发给一个查不到的人)。
     */
    public static function isCommissionEligible($userId): bool
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return false;
        }
        $row = User::find()
            ->select(['id', 'is_delete', 'status'])
            ->where(['id' => $userId])
            ->asArray()
            ->one();
        if (!$row) {
            return false;
        }
        return (int)$row['is_delete'] === 0 && (int)$row['status'] === User::STATUS_NORMAL;
    }

    /**
     * 取用户的上级关系链 —— 全平台查关系链的唯一权威入口。
     *
     * 【只返回状态正常的用户】is_delete=1(已注销)或 status != STATUS_NORMAL(拉黑)
     * 的祖先会被摘出链,后面的人层级依次前移(穿透)。
     * 例:A → B(已注销) → C,查 C 的上级链得到 [A],A 的 level = 1(不是 2)。
     *
     * 过滤发生在「取前 N 级」之前 —— 否则墓碑会占掉 level 名额,让真实上级少拿一层。
     *
     * ⚠ 插件严禁自己 while 循环走 parent_id 或直接读 UserPath 来拼关系链:
     *   那样绕开本过滤,已注销/被拉黑的用户会重新出现在分润链上。
     *   需要原始结构(含墓碑)的场景(如后台展示、路径自愈),走 getPath()/parseAncestorIds()。
     *
     * @param int  $userId
     * @param int  $level       取前 N 级(近→远);0=全部
     * @param bool $includeSelf 是否把自己放在结果第一位(level=0);自己不参与状态过滤
     * @return array 近→远排序,每项含 id/parent_id/nickname/mobile/level
     */
    public static function getParents($userId, $level = 0, $includeSelf = false)
    {
        $userId = (int)$userId;
        $ck = $userId . '_' . (int)$level . '_' . ($includeSelf ? 1 : 0);
        if (isset(self::$_parentsCache[$ck])) {
            return self::$_parentsCache[$ck];
        }
        $path = (string)self::getPath($userId); // 快速读取;下面顺带核对,对不上才重建

        // 一条 SQL 查出 path 上的所有人(祖先 + 本人,含 parent_id 与状态),顺带核对 path 与 parent_id:
        // 改挂后子孙 path 没改写到 / 坏数据时,按旧 path 分润就是把钱发给旧上线 —— 发现对不上当场重建再算
        $fetch = function (string $p): array {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode('-', trim($p, '-'))))));
            if (!$ids) {
                return [];
            }
            $rows = User::find()
                ->select(['id', 'parent_id', 'nickname', 'mobile', 'status', 'is_delete'])
                ->where(['id' => $ids])
                ->asArray()
                ->all();
            return array_column($rows, null, 'id');
        };
        $userMap = $path !== '' ? $fetch($path) : [];
        if ($path !== '' && !self::pathMatchesParents($userId, $path, array_column($userMap, 'parent_id', 'id'))) {
            $path = self::rebuildMismatchedPath($userId, $path);
            $userMap = $fetch($path);
        }

        // 从根到父 → 反转为从近到远（直接上级在前）
        $ancestorIds = array_reverse(self::parseAncestorIds($path));

        $result = [];

        if ($includeSelf) {
            $self = $userMap[$userId] ?? User::find()
                ->select(['id', 'parent_id', 'nickname', 'mobile'])
                ->where(['id' => $userId])->asArray()->one();
            if ($self) {
                unset($self['status'], $self['is_delete']);
                $self['level'] = 0;
                $result[] = $self;
            }
        }

        if (empty($ancestorIds)) {
            self::$_parentsCache[$ck] = $result;
            return $result;
        }

        $active = [];
        foreach ($ancestorIds as $id) {
            $row = $userMap[$id] ?? null;
            if (!$row) {
                continue;   // 用户行不存在(历史脏数据):跳过,不中断上溯
            }
            if ((int)$row['is_delete'] === 1 || (int)$row['status'] !== User::STATUS_NORMAL) {
                continue;   // 已注销 / 被拉黑:摘出链,后面的人层级前移
            }
            unset($row['status'], $row['is_delete']);   // 不外泄状态字段,保持原返回结构
            $active[] = $row;
        }

        // 先过滤再截断:墓碑不应占用 level 名额
        if ($level > 0) {
            $active = array_slice($active, 0, $level);
        }

        foreach ($active as $i => $row) {
            $row['level'] = $i + 1; // 1=直接上级, 2=上上级...(按过滤后的位置)
            $result[] = $row;
        }
        self::$_parentsCache[$ck] = $result;
        return $result;
    }

    /**
     * ⛔ 不是死代码:pb 月度业绩奖励 PerformanceBonusTask 在用(还有 pb-test)。2026-09-01 被当成零调用删过一次,
     *    pb 结算从此抛 Error、每月停在 pending 发不出钱(9/22 复现后恢复)。删之前 grep 全仓,含各插件的 cron 目录。
     *
     * getParents 的批量版 —— 批处理(cron/job)里给一批用户各求「过滤后的直接上级」。
     *
     * 语义与 getParents 完全一致:已注销(is_delete=1)/被拉黑(status!=STATUS_NORMAL)的祖先
     * 被摘出链,后面的人层级前移(穿透)。区别只是把 O(N) 次查询压成 3 次。
     *
     * 用途:级差类月度结算需要「谁是谁的直接下级」,而这张父子表必须和业绩累计
     * (走 getParents)用同一套拓扑 —— 否则墓碑节点两边不一致,会出现上级超发。
     *
     * @param int[] $userIds
     * @param int   $level 每人取前 N 级(近→远);0=全部
     * @return array<int, int[]> user_id => [近→远的祖先id...](已过滤)
     *         注意:传入的 0 会被剔除、不出现在结果里;负数会保留,对应空数组。
     *         调用方按 `$map[$uid] ?? []` 取用即可(与单个版对 id<=0 返回 [] 语义等价)。
     */
    public static function getParentsBatch(array $userIds, int $level = 0): array
    {
        $userIds = array_values(array_unique(array_map('intval', array_filter($userIds))));
        if (empty($userIds)) {
            return [];
        }

        // ① 一次拿所有人的 path
        $paths = UserPath::find()
            ->select(['user_id', 'path'])
            ->where(['user_id' => $userIds])
            ->asArray()
            ->all();
        $pathMap = array_column($paths, 'path', 'user_id');

        // ② 收集所有出现过的祖先,一次查状态
        //
        // ⚠ UserPath 记录缺失时必须回退到 getPath() —— 它会走 ensurePath 自愈并返回真实链。
        //   单个版 getParents 正是这么做的;这里若直接当成「无祖先」返回空数组,
        //   一个有上级、但 path 行丢失的用户会被当成顶级用户 ——
        //   在 pb 级差里就是「上级不扣减他的团队奖 → 上级超发」。
        //   缺失通常是少数脏数据,不会把批量退化成 N 次查询。
        $allAncestorIds = [];
        $ancestorsOf = [];
        foreach ($userIds as $uid) {
            $path = $pathMap[$uid] ?? null;
            if ($path === null) {
                $path = self::getPath($uid);   // 自愈,与 getParents 同行为
            }
            $ids = $path ? array_reverse(self::parseAncestorIds($path)) : []; // 近→远
            $ancestorsOf[$uid] = $ids;
            foreach ($ids as $id) {
                $allAncestorIds[$id] = true;
            }
        }
        // (不能在这里「没有任何祖先就直接返回」:path 被截成只剩本人、实际却有上级的,要走下面的核对重建)

        // 连同本人一起查 parent_id,顺带核对每人的 path(与 getParents 同口径):对不上的当场按 parent_id 重建
        $fetchState = function (array $ids): array {
            if (!$ids) {
                return [];
            }
            $rows = User::find()
                ->select(['id', 'parent_id', 'is_delete', 'status'])
                ->where(['id' => $ids])
                ->asArray()
                ->all();
            return array_column($rows, null, 'id');
        };
        $stateMap = $fetchState(array_values(array_unique(array_merge(array_keys($allAncestorIds), $userIds))));
        $parentOf = array_column($stateMap, 'parent_id', 'id');
        foreach ($userIds as $uid) {
            $path = $pathMap[$uid] ?? self::getPath($uid);
            if (!$path || self::pathMatchesParents($uid, (string)$path, $parentOf)) {
                continue;
            }
            $path = self::rebuildMismatchedPath($uid, (string)$path);
            $ids = array_reverse(self::parseAncestorIds($path));
            $ancestorsOf[$uid] = $ids;
            $missing = array_diff($ids, array_keys($stateMap));
            if ($missing) {
                $stateMap += $fetchState(array_values($missing));
            }
        }

        // ③ 按与 getParents 完全相同的规则过滤 + 穿透 + 截断
        $result = [];
        foreach ($userIds as $uid) {
            $active = [];
            foreach ($ancestorsOf[$uid] as $id) {
                $row = $stateMap[$id] ?? null;
                if (!$row) {
                    continue;   // 用户行不存在(历史脏数据):跳过,不中断上溯
                }
                if ((int)$row['is_delete'] === 1 || (int)$row['status'] !== User::STATUS_NORMAL) {
                    continue;   // 已注销 / 被拉黑:摘出链,后面的人层级前移
                }
                $active[] = (int)$id;
            }
            // 先过滤再截断:墓碑不应占用 level 名额(与 getParents 一致)
            $result[$uid] = $level > 0 ? array_slice($active, 0, $level) : $active;
        }
        return $result;
    }

    private static function getErrorMsg($model)
    {
        $errors = $model->getErrors();
        return !empty($errors) ? reset($errors)[0] : '保存失败';
    }}
