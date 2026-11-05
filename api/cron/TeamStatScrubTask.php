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
use app\events\UserLevelCheckNeededEvent;
use app\helpers\OptionHelper;
use app\models\UserTeamStat;
use app\services\TeamStatUpdater;
use Yii;
use yii\helpers\Json;

/**
 * 团队统计【反熵】滚动重建(安全网)
 *
 * 游标循环遍历所有有 stat 记录的用户,每批 fullRebuild 重算 map/业绩,自愈任何来源的统计漂移 ——
 * 尤其 onLevelChanged 在 setUserLevel 的 commit 之后偶发丢失(进程崩溃 / 异常)导致的
 * 祖先 direct_level_map / team_level_map / team_legs_map 不一致:增量不会自愈、DirtyCheckTask 只重判不重建、
 * 每日 FullRebuildTask 只补"缺记录"用户。本任务是兜住这类漂移的唯一自愈路径。
 *
 * 设计要点:
 *  - 只扫 dirty=0 的用户:dirty 用户是"在途"的,交给 DirtyCheckTask;且 fullRebuild 会清 dirty,
 *    若扫了 dirty 用户会吞掉其待处理的升级重检信号。崩溃漂移的祖先恰恰是 dirty=0(丢失的 onLevelChanged
 *    本应给它标脏却没标),所以正好被本任务覆盖。
 *  - 只对"重建后 level 相关统计真的变了"的用户触发升级重检(漂移被纠正),其余(绝大多数无漂移)只重算不发事件,控量。
 *  - 吞吐/开关:分页循环 fullRebuild 直到 20s 时间盒用满(必 < 调度器 30s FastCGI 超时);
 *    'team_stat_scrub_batch'(默认 200)只是翻页大小、设 0 = 关闭;游标 'team_stat_scrub_cursor'
 *    每人重建前即时前移(claim-forward,进程被掐不回退)、到尾自动回绕。
 */
class TeamStatScrubTask extends Task
{
    /** 分页翻页大小(不是单 run 吞吐上限——上限由 MAX_RUN_SECONDS 时间盒决定);设 0 = 关闭反熵重建。 */
    const DEFAULT_BATCH = 200;

    /**
     * 单次运行时间盒(秒)。必须 < 调度器 scheduler/worker/dispatcher.go 的 30s FastCGI 派发硬超时
     * (到点它会 fcgi.Close 强关连接,PHP 侧读操作报「use of closed network connection / 派发网络异常」),
     * 留足收尾 + 响应回传余量。到点即停、靠 claim-forward 游标下一轮续跑,杜绝单批 fullRebuild 超 30s。
     */
    const MAX_RUN_SECONDS = 20;

    public function run($params = null)
    {
        $mallId = (int)Yii::$app->mall->id;

        $batchCfg = OptionHelper::get('team_stat_scrub_batch', $mallId);
        $batch = ($batchCfg === null || $batchCfg === '') ? self::DEFAULT_BATCH : (int)$batchCfg;
        if ($batch <= 0) {
            return; // 设为 0 = 关闭反熵重建
        }

        $start = microtime(true);
        $cur = (int)(OptionHelper::get('team_stat_scrub_cursor', $mallId) ?: 0);
        $rebuilt = 0;
        $healed = 0;

        // 分页循环,直到 20s 时间盒用满(必 < 调度器 30s FastCGI 硬超时):
        // batch 只是「翻页大小」、不再是单 run 吞吐上限 → run 始终吃满预算,与总用户数无关地安全滚动。
        while (microtime(true) - $start < self::MAX_RUN_SECONDS) {
            $ids = UserTeamStat::find()
                ->select('user_id')
                ->where(['mall_id' => $mallId, 'dirty' => 0])
                ->andWhere(['>', 'user_id', $cur])
                ->orderBy(['user_id' => SORT_ASC])
                ->limit($batch)
                ->column();

            if (empty($ids)) {
                // 扫到尾 → 游标归 0,本轮到此为止(不在同一 run 内立刻从头重扫,避免小数据集空转)
                OptionHelper::set('team_stat_scrub_cursor', 0, $mallId);
                break;
            }

            foreach ($ids as $uid) {
                // 时间盒:到点即停,剩余留给下一轮(已处理的靠下面「先推游标」保住进度)
                if (microtime(true) - $start >= self::MAX_RUN_SECONDS) {
                    break 2;
                }
                $uid = (int)$uid;

                // claim-forward:先把游标即时写库到本人再重建。即便某个超大树用户 fullRebuild 巨慢、
                // 被调度器 30s 掐断进程,游标也已落库 → 下一轮从下一个继续,绝不会永远卡死在同一个用户上
                // (这正是过去「派发网络异常」每 30 分钟复现、游标永不前进的根因)。
                OptionHelper::set('team_stat_scrub_cursor', $uid, $mallId);
                $cur = $uid;

                try {
                    $before = UserTeamStat::findOne(['user_id' => $uid]);
                    $sigBefore = $before ? $this->levelSig($before) : null;

                    TeamStatUpdater::fullRebuild($uid, $mallId);
                    $rebuilt++;

                    $after = UserTeamStat::findOne(['user_id' => $uid]);
                    $sigAfter = $after ? $this->levelSig($after) : null;

                    // level 相关统计被纠正 → 该用户可能"该升未升",触发一次升级重检(读新 stat 重新判定)
                    if ($sigBefore !== $sigAfter) {
                        $healed++;
                        Yii::$app->trigger(
                            UserLevelCheckNeededEvent::NAME,
                            new UserLevelCheckNeededEvent(['user_id' => $uid, 'trigger' => 'stat_scrub'])
                        );
                    }
                } catch (\Throwable $e) {
                    Yii::error("[TeamStatScrub] 重建用户 {$uid} 失败: " . $e->getMessage());
                }
            }
        }

        if ($healed > 0) {
            Yii::warning("[TeamStatScrub][Mall:{$mallId}] 重建 {$rebuilt} 人,纠正漂移 {$healed} 人,游标→{$cur}");
        }
    }

    /**
     * level 相关统计的归一化签名:用于判断 fullRebuild 是否真的纠正了漂移(map 按 key 排序消除顺序差异)。
     */
    private function levelSig(UserTeamStat $stat): string
    {
        $norm = function ($json): string {
            if (empty($json)) {
                return '{}';
            }
            $arr = (array)Json::decode($json);
            ksort($arr);
            return Json::encode($arr);
        };
        return implode('#', [
            $norm($stat->direct_level_map),
            $norm($stat->team_level_map),
            $norm($stat->team_legs_map),
            $norm($stat->purchased_goods_map),
            (string)(float)$stat->team_spend_paid,
            (string)(float)$stat->team_spend_settled,
            (string)(float)$stat->personal_spend_paid,
            (string)(float)$stat->personal_spend_settled,
            (string)(int)$stat->team_count,
            (string)(int)$stat->direct_count,
        ]);
    }

    public function rule()
    {
        return "*/15 * * * *"; // 每 15 分钟一批,游标循环遍历全量(intra-day 覆盖更快;单轮仍受 20s 时间盒约束)
    }

    public function description()
    {
        return "【团队统计】反熵滚动重建(自愈 map 漂移 / onLevelChanged 丢失)";
    }
}
