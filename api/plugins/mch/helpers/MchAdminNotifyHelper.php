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

namespace app\plugins\mch\helpers;

use app\events\ImPushEvent;
use app\events\WxNotifyEvent;
use app\helpers\ImNoticeBuilder;
use app\helpers\OptionHelper;
use app\helpers\SmsHelper;
use app\models\User;
use app\plugins\mch\models\Mch;
use Yii;

/**
 * 商家插件 —— 平台管理员通知助手（站内IM + 微信公众号模板 + 短信 三通道）
 *
 * 用途：商家提交入驻申请时，按后台「消息通知 → 入驻通知管理员」配置，
 *       向指定管理员用户多通道触达，提醒尽快审核。
 *
 * 配置存于 mch_setting.admin_notify：
 *   [
 *     'enabled'        => 0/1,           // 总开关
 *     'user_ids'       => [int...],      // 接收通知的管理员用户ID
 *     'users'          => [...],         // 前端展示快照(本助手忽略)
 *     'channels'       => ['im','wx','sms'], // 启用的通道
 *     'sms_dxb_text'   => '...{mch_name}...{mch_type}...', // 短信宝原文(含签名)
 *     'sms_aliyun_tpl' => 'SMS_xxx',     // 阿里云模板ID(变量 mch_name/mch_type)
 *     'sms_tx_tpl'     => '12345',       // 腾讯云模板ID
 *   ]
 *
 * 设计同 MassageNotifyHelper：三通道各自独立 try/catch，绝不因通知失败影响主流程。
 */
class MchAdminNotifyHelper
{
    const SCENE_SUBMITTED = 'mch.entry.submitted';

    /** 商家类型 → 中文名（与入驻页一致） */
    private static function mchTypeName(int $type): string
    {
        $map = [
            Mch::TYPE_UP_STORE     => '线上商家',
            Mch::TYPE_DOWN_STORE   => '门店商家',
            Mch::TYPE_HOTEL        => '酒店民宿',
            Mch::TYPE_INDIVIDUAL   => '个人租户',
        ];
        return $map[$type] ?? '商家';
    }

    /**
     * 商家入驻申请提交 —— 通知所有配置的管理员。
     */
    public static function notifyApplySubmitted(Mch $mch): void
    {
        $mallId = (int) $mch->mall_id;
        $conf   = OptionHelper::get('mch_setting', $mallId, []);
        $conf   = is_array($conf) ? ($conf['admin_notify'] ?? []) : [];

        if (empty($conf['enabled'])) {
            return; // 未开启
        }

        $userIds  = array_values(array_unique(array_map('intval', (array) ($conf['user_ids'] ?? []))));
        $channels = (array) ($conf['channels'] ?? []);
        if (!$userIds || !$channels) {
            return; // 没选管理员 / 没选通道
        }

        $mchName = (string) $mch->mch_name;
        $mchType = self::mchTypeName((int) $mch->type);
        $tip     = '请尽快登录后台审核';

        foreach ($userIds as $uid) {
            if ($uid <= 0) {
                continue;
            }

            // ─── 通道 1：站内 IM（走 ImPushEvent 事件，IM 插件未启用时自动无副作用） ───
            if (in_array('im', $channels, true)) {
                try {
                    $content = ImNoticeBuilder::buildBizNotice(
                        'biz',
                        '📋 新的商家入驻申请',
                        "「{$mchName}」（{$mchType}）已提交入驻申请，{$tip}",
                        '',
                        '',
                        'mch.entry.submitted'
                    );
                    Yii::$app->trigger(ImPushEvent::EVENT_PUSH, new ImPushEvent([
                        'mallId'  => $mallId,
                        'toId'    => $uid,
                        'fromId'  => 0,
                        'content' => $content,
                        // 富业务卡片(buildBizNotice)→ system-list 富卡片渲染,msgType 用 'system'
                        // (与 PlazaNotifyHelper / HrmNotifyService 同口径,不要写 'text')
                        'msgType' => 'system',
                    ]));
                } catch (\Throwable $e) {
                    Yii::warning('[MchAdminNotify] IM 推送失败 user=' . $uid . ' err=' . $e->getMessage());
                }
            }

            // ─── 通道 2：微信公众号模板 ────────────────────────
            if (in_array('wx', $channels, true)) {
                try {
                    Yii::$app->trigger(WxNotifyEvent::EVENT_NOTIFY, new WxNotifyEvent([
                        'mallId'       => $mallId,
                        'userId'       => $uid,
                        'scene'        => self::SCENE_SUBMITTED,
                        'templateData' => [
                            'mch_name' => $mchName,
                            'mch_type' => $mchType,
                            'tip'      => $tip,
                        ],
                        'pageUrl'      => '',
                    ]));
                } catch (\Throwable $e) {
                    Yii::warning('[MchAdminNotify] 微信模板推送失败 user=' . $uid . ' err=' . $e->getMessage());
                }
            }

            // ─── 通道 3：短信 ──────────────────────────────────
            if (in_array('sms', $channels, true)) {
                self::pushSms($mallId, $uid, $conf, $mchName, $mchType);
            }
        }
    }

    /** 短信通道：dxb 原文 + aliyun/tx 模板（变量 mch_name / mch_type） */
    private static function pushSms(int $mallId, int $userId, array $conf, string $mchName, string $mchType): void
    {
        try {
            $user  = User::findOne($userId);
            $phone = ($user && !empty($user->mobile)) ? (string) $user->mobile : '';
            if ($phone === '') {
                Yii::warning("[MchAdminNotify] 管理员无手机号，跳过短信 user={$userId}");
                return;
            }

            $vars = ['mch_name' => $mchName, 'mch_type' => $mchType];

            // dxb 原文（管理员在后台自填，含签名）
            $dxbText = (string) ($conf['sms_dxb_text'] ?? '');
            if ($dxbText === '') {
                $dxbText = "您有新的商家入驻申请：{mch_name}（{mch_type}），请尽快登录后台审核";
            }
            foreach ($vars as $k => $v) {
                $dxbText = str_replace('{' . $k . '}', $v, $dxbText);
            }

            // aliyun / tx 模板 ID（未配置则对应服务商内部降级跳过）
            $tpl = [];
            if (!empty($conf['sms_aliyun_tpl'])) {
                $tpl['aliyun_tpl']  = (string) $conf['sms_aliyun_tpl'];
                $tpl['aliyun_vars'] = $vars;
            }
            if (!empty($conf['sms_tx_tpl'])) {
                $tpl['tx_tpl']  = $conf['sms_tx_tpl'];
                $tpl['tx_vars'] = array_values($vars);
            }

            $res = SmsHelper::sendNotify($mallId, $phone, $dxbText, $tpl);
            if (isset($res['code']) && $res['code'] !== 0) {
                Yii::warning('[MchAdminNotify] 短信推送失败 user=' . $userId
                    . ' phone=' . $phone
                    . ' provider=' . ($res['provider'] ?? '-')
                    . ' err=' . ($res['msg'] ?? ''));
            }
        } catch (\Throwable $e) {
            Yii::warning('[MchAdminNotify] 短信推送异常 user=' . $userId . ' err=' . $e->getMessage());
        }
    }
}
