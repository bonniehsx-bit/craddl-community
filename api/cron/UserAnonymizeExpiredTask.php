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
use app\models\User;
use app\models\UserBanLog;
use Yii;
use yii\helpers\Json;

/**
 * 注销账户 PII 匿名化任务
 *
 * 每日凌晨跑一次, 扫描已过 RE_REGISTER_COOL_DOWN_DAYS (默认 120 天) 冷却期
 * 的注销账户, 清空敏感字段:
 *  - backup_mobile / nickname / avatar_url
 *  - openid / union_id / wechat_openid / mpwx_openid (第三方标识, 防止反向定位)
 *
 * user.id 和历史订单保留不动 (财务/审计需要), 但所有 PII 被抹掉.
 *
 * 合规背景: 个保法 / GDPR 的"可遗忘权"要求冷却期过后真实删除 PII.
 * 每条匿名化操作都会写一条 user_ban_log 审计日志 (action=anonymize, operator_id=0=系统).
 */
class UserAnonymizeExpiredTask extends Task
{
    public function rule()
    {
        // 每天凌晨 3:10 执行一次
        return "10 3 * * *";
    }

    public function description()
    {
        return "注销账户冷却期满后 PII 匿名化";
    }

    public function run($params = null)
    {
        $coolDownDays = defined(User::class . '::RE_REGISTER_COOL_DOWN_DAYS')
            ? User::RE_REGISTER_COOL_DOWN_DAYS
            : 120;
        $threshold = date('Y-m-d H:i:s', time() - $coolDownDays * 86400);

        Yii::warning("[UserAnonymize] 冷却阈值: {$coolDownDays} 天 (<= {$threshold})");

        // 已注销 + 过冷却期 + 仍有可匿名化的 PII (backup_mobile 非空 或 昵称不是已注销模板)
        $query = User::find()
            ->where(['mall_id' => Yii::$app->mall->id, 'status' => User::STATUS_DELETE, 'is_delete' => 1])
            ->andWhere(['<', 'account_del_time', $threshold])
            ->andWhere(['or',
                ['!=', 'backup_mobile', ''],
                ['not like', 'nickname', '已注销\_%', false],
            ]);

        $total = (clone $query)->count();
        Yii::warning("[UserAnonymize] 待处理用户数: {$total}");
        if ($total == 0) {
            return;
        }

        $success = 0;
        $fail = 0;

        foreach ($query->each(200) as $user) {
            /** @var User $user */
            $snapshot = [
                'backup_mobile' => $user->backup_mobile,
                'nickname'      => $user->nickname,
                'avatar_url'    => $user->avatar_url,
                'union_id'      => $user->hasAttribute('union_id') ? $user->union_id : null,
                'openid'        => $user->hasAttribute('openid') ? $user->openid : null,
                'wechat_openid' => $user->hasAttribute('wechat_openid') ? $user->wechat_openid : null,
                'mpwx_openid'   => $user->hasAttribute('mpwx_openid') ? $user->mpwx_openid : null,
            ];

            try {
                $user->backup_mobile = '';
                $user->nickname      = '已注销_' . $user->id;
                $user->avatar_url    = '';
                // 第三方标识一并抹掉, 避免再通过微信号反向定位到原用户
                // hasAttribute() 才是检查字段存在的正确方式, isset 对 Yii AR 的魔术属性永远返回 true
                if ($user->hasAttribute('openid'))        $user->openid        = '';
                if ($user->hasAttribute('union_id'))      $user->union_id      = '';
                if ($user->hasAttribute('wechat_openid')) $user->wechat_openid = '';
                if ($user->hasAttribute('mpwx_openid'))   $user->mpwx_openid   = '';

                if (!$user->save(false)) {
                    throw new \Exception('user save failed');
                }

                // 审计
                $log = new UserBanLog();
                $log->user_id       = $user->id;
                $log->action        = UserBanLog::ACTION_ANONYMIZE;
                $log->reason        = "冷却期满 ({$coolDownDays} 天), 自动匿名化";
                $log->operator_id   = 0;
                $log->operator_name = '系统';
                $log->snapshot      = Json::encode($snapshot);
                $log->save(false);

                $success++;
            } catch (\Throwable $e) {
                Yii::error("[UserAnonymize] 失败 user_id={$user->id}: " . $e->getMessage());
                $fail++;
            }
        }

        Yii::warning("[UserAnonymize] 处理完成: 成功 {$success}, 失败 {$fail}");
    }
}
