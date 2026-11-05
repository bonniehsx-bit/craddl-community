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

namespace app\cron;

use app\core\Task;
use app\models\Order;
use app\services\TeamStatOutbox;
use app\services\TeamStatUpdater;
use Yii;
use yii\helpers\Json;

/**
 * 可靠事件外发箱消费者(团队统计增量的可靠路径)。
 *
 * 每分钟领取本商城待办 → 按 type 派发到 TeamStatUpdater →「处理 + ack」同一事务:
 *   - 成功:一起提交,exactly-once;
 *   - 崩溃/异常:整体回滚,该条仍 pending,退避后重试(崩溃丢事件被彻底堵住)。
 *
 * 未启用(team_stat_outbox_disabled=1)直接空转:老 TeamStatListener 走直接增量。
 */
class EventOutboxTask extends Task
{
    const BATCH = 200;

    public function run($params = null)
    {
        $mallId = (int)Yii::$app->mall->id;
        if (!TeamStatOutbox::enabled($mallId)) {
            return; // 未启用,worker 空转
        }

        $token = uniqid('outbox_', true);
        $rows = TeamStatOutbox::claim($mallId, self::BATCH, $token);

        $ok = 0; $failed = 0;
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $tx = Yii::$app->db->beginTransaction();
            try {
                $recheck = self::apply($row);
                TeamStatOutbox::ack($id);   // 处理 + ack 同事务 → exactly-once
                $tx->commit();
                $ok++;
                // [关键] 统计落库后补发升级检测:升级 job 延迟仅 5s、本 worker 每 60s 一轮,
                // 不补发的话升级判定会抢在增量写入前读到旧聚合值 → 自动完成订单永远升不了级。
                // dispatchLevelRecheck 内部已吞异常,绝不冒泡到下面 catch(否则已 ack 的行会被误判失败重试)。
                if ($recheck !== null) {
                    self::dispatchLevelRecheck($recheck['type'], (int)$recheck['user_id']);
                }
            } catch (\Throwable $e) {
                $tx->rollBack();
                TeamStatOutbox::fail($id, $e->getMessage()); // 退避重试 / 死信(独立事务)
                $failed++;
                Yii::error("[EventOutbox][Mall:{$mallId}] 处理失败 id={$id} type={$row['type']}: " . $e->getMessage(), 'crontab');
            }
        }

        TeamStatOutbox::cleanup($mallId);
        if ($ok || $failed) {
            Yii::warning("[EventOutbox][Mall:{$mallId}] 成功 {$ok} / 失败 {$failed}", 'crontab');
        }
    }

    /**
     * 按 type 派发到对应增量方法(与 TeamStatListener 口径一致)。
     * 返回值:需要在【提交后】补发升级检测的描述 ['type'=>'order'|'level', 'user_id'=>int];无需补发返回 null。
     */
    private static function apply(array $row): ?array
    {
        $mallId = (int)$row['mall_id'];
        $p = Json::decode($row['payload'] ?: '[]') ?: [];
        switch ($row['type']) {
            case 'order_paid':
                $o = Order::findOne(['id' => (int)($p['order_id'] ?? 0)]);
                if ($o) {
                    TeamStatUpdater::onOrderPaid($o);
                    return ['type' => 'order', 'user_id' => (int)$o->user_id];
                }
                return null;
            case 'order_settled':
                $o = Order::findOne(['id' => (int)($p['order_id'] ?? 0)]);
                if ($o) {
                    TeamStatUpdater::onOrderSettled($o);
                    return ['type' => 'order', 'user_id' => (int)$o->user_id];
                }
                return null;
            case 'commission_revert':
                TeamStatUpdater::onCommissionRevert(
                    $mallId,
                    (int)($p['order_id'] ?? 0),
                    (int)($p['order_detail_id'] ?? 0),
                    (string)($p['source'] ?? '')
                );
                return null; // 冲减只减不增,不会促成升级(系统只升不降)
            case 'level_changed':
                $uid = (int)($p['user_id'] ?? 0);
                TeamStatUpdater::onLevelChanged(
                    $uid,
                    $mallId,
                    (int)($p['before_level_id'] ?? 0),
                    (int)($p['after_level_id'] ?? 0)
                );
                // 本人已是新等级(不必再查本人);其上级的「伞下某等级人数」等条件可能因此达标 → 只补上级
                return $uid > 0 ? ['type' => 'level', 'user_id' => $uid] : null;
            default:
                Yii::warning("[EventOutbox] 未知事件类型: {$row['type']}", 'crontab');
                return null;
        }
    }

    /**
     * 统计已落库后补发升级检测 —— 堵住「升级 job(延迟 5s)抢在 outbox worker(每 60s)之前读到旧聚合值」的竞态。
     *   · order  :买家本人可能达标(个人消费 / 购买指定商品等)+ 上级可能达标(团队业绩)
     *   · level  :本人已升级,只重检上级(伞下某等级人数等级联条件)
     * 全程自吞异常:本方法在事务提交且 ack 之后调用,绝不能让异常冒泡导致已完成的行被误判为失败重试。
     * 说明:买家本人走 UserLevelCheckNeededEvent → 队列 unique_key 去重(不受上级 60s 防抖锁影响);
     *      上级走 dispatchUplineCheck,自带 Redis 60s 防抖,重复调用天然收敛。
     */
    private static function dispatchLevelRecheck(string $type, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            if ($type === 'order') {
                Yii::$app->trigger(
                    \app\events\UserLevelCheckNeededEvent::NAME,
                    new \app\events\UserLevelCheckNeededEvent(['user_id' => $userId, 'trigger' => 'outbox_applied'])
                );
            }
            \app\jobs\user\UserLevelUpgradeJob::dispatchUplineCheck($userId, 'outbox_applied_upline');
        } catch (\Throwable $e) {
            Yii::error("[EventOutbox] 补发升级检测失败 user={$userId} type={$type}: " . $e->getMessage(), 'crontab');
        }
    }

    public function rule()
    {
        return "* * * * *"; // 每分钟消费;需更低延迟可改由 Go 常驻 worker 拉取
    }

    public function description()
    {
        return "【团队统计】可靠事件外发箱消费者(outbox worker)";
    }
}
