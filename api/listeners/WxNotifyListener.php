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

namespace app\listeners;

use app\events\WxNotifyEvent;
use app\helpers\OptionHelper;
use app\helpers\WechatHelper;
use app\models\User;
use app\models\WxNotifyLog;
use app\models\WxNotifyTemplate;
use Yii;

/**
 * 微信公众号模板消息推送监听器
 *
 * 监听全局 WxNotifyEvent::EVENT_NOTIFY，收到后：
 *   1. 查用户 wechat_openid（未绑定则静默跳过）
 *   2. 查该场景的 template_id（未配置则静默跳过）
 *   3. 通过公众号接口发送模板消息
 *
 * 四个结局（成功 / 失败 / 两种跳过）都记进 `wx_notify_log`：
 * 通知是尽力而为的旁路，失败只降级不打断业务 —— 但运营得能查「为什么没收到」，
 * 不能只留在服务器日志文件里。记日志本身再失败也不影响发送（WxNotifyLog::write 内部兜住）。
 *
 * 任何插件触发推送只需：
 *   Yii::$app->trigger(WxNotifyEvent::EVENT_NOTIFY, new WxNotifyEvent([...]))
 */
class WxNotifyListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(WxNotifyEvent::EVENT_NOTIFY, [$this, 'handle']);
    }

    public function handle(WxNotifyEvent $event)
    {
        if (empty($event->mallId) || empty($event->userId) || empty($event->scene)) {
            Yii::warning('WxNotifyListener: 事件参数不完整，已跳过。scene=' . $event->scene);
            return;
        }

        // ── 1. 查 wechat_openid ──
        $user = User::find()
            ->select(['id', 'wechat_openid'])
            ->where(['id' => $event->userId, 'mall_id' => $event->mallId, 'is_delete' => 0])
            ->asArray()
            ->one();

        if (empty($user['wechat_openid'])) {
            Yii::warning("WxNotifyListener: 用户 {$event->userId} 未绑定公众号，跳过推送。scene={$event->scene}");
            WxNotifyLog::write([
                'mall_id' => $event->mallId, 'scene' => $event->scene, 'user_id' => $event->userId,
                'status'  => WxNotifyLog::STATUS_SKIPPED, 'reason' => WxNotifyLog::REASON_NO_OPENID,
                'page_url' => $event->pageUrl, 'payload' => $event->templateData,
            ]);
            return;
        }

        // ── 2. 查 template_id + field_map ──
        $config = WxNotifyTemplate::getTemplateConfig($event->mallId, $event->scene);

        if (!$config) {
            Yii::warning("WxNotifyListener: 场景 {$event->scene} 未配置模板 ID，跳过推送。");
            WxNotifyLog::write([
                'mall_id' => $event->mallId, 'scene' => $event->scene, 'user_id' => $event->userId,
                'openid'  => $user['wechat_openid'], 'status' => WxNotifyLog::STATUS_SKIPPED,
                'reason'  => WxNotifyLog::REASON_NO_TEMPLATE,
                'page_url' => $event->pageUrl, 'payload' => $event->templateData,
            ]);
            return;
        }

        $templateId = $config['template_id'];
        $fieldMap   = $config['field_map'];   // ['device_name' => 'keyword1', ...]

        // ── 3. 发送公众号模板消息 ──
        try {
            WechatHelper::init($event->mallId);
            $app = Yii::$app->wechat->app;

            // 将 templateData 转为微信要求的格式：{'模板变量名': {'value': '内容'}}
            // 若配置了 field_map，则把内部字段名翻译成模板变量名；否则原样透传（兼容旧写法）
            $data = [];
            foreach ($event->templateData as $key => $value) {
                $wxKey        = $fieldMap[$key] ?? $key;   // 未配置映射则直接用原 key
                $data[$wxKey] = ['value' => (string)$value];
            }

            $payload = [
                'touser'      => $user['wechat_openid'],
                'template_id' => $templateId,
                'data'        => $data,
            ];

            // 配置了跳转页面则附加小程序跳转。
            // ⛔ 原来读的是 `Yii::$app->wechat->config`——组件上根本没有这个属性(它只有 userConfig /
            //    miniProgramConfig / ...),`?? ''` 又把「属性不存在」吞成空串,于是 appid 恒为空:
            //    带跳转页的模板消息(审批提醒、账单通知都带)全都发不出去。
            //    小程序 appid 的真实来源就是本商城的 `platform_mpwx` 配置(WechatHelper::init 也是读它)。
            //    这里按 $event->mallId 直接读,不走 params —— params 是进程级的,cron 一个进程轮多个商城时
            //    会把上一个商城的 appid 带给下一个;取不到就不带 miniprogram(空 appid 微信会整条拒收)。
            $mpwx = OptionHelper::get('platform_mpwx', (int)$event->mallId, []);
            $miniAppId = is_array($mpwx) ? trim((string)($mpwx['appid'] ?? '')) : '';
            if (!empty($event->pageUrl) && $miniAppId !== '') {
                $payload['miniprogram'] = [
                    'appid'    => $miniAppId,
                    'pagepath' => $event->pageUrl,
                ];
            } elseif (!empty($event->pageUrl)) {
                Yii::warning("WxNotifyListener: 商城 {$event->mallId} 没有配置小程序 appid，本条模板消息不带跳转。scene={$event->scene}");
            }

            $response = $app->getClient()->postJson('cgi-bin/message/template/send', $payload);
            $res = $response->toArray(false);

            $ok = ($res['errcode'] ?? -1) === 0;
            if (!$ok) {
                Yii::warning("WxNotifyListener: 发送失败。scene={$event->scene} errcode={$res['errcode']} errmsg={$res['errmsg']}");
            } else {
                // 成功走 info,别再污染 warning 日志
                Yii::info("WxNotifyListener: 推送成功。scene={$event->scene} userId={$event->userId}", __METHOD__);
            }
            WxNotifyLog::write([
                'mall_id' => $event->mallId, 'scene' => $event->scene, 'user_id' => $event->userId,
                'openid'  => $user['wechat_openid'], 'template_id' => $templateId,
                'status'  => $ok ? WxNotifyLog::STATUS_SUCCESS : WxNotifyLog::STATUS_FAILED,
                'reason'  => $ok ? '' : ('errcode=' . ($res['errcode'] ?? '-') . ' ' . ($res['errmsg'] ?? '')),
                'page_url' => $event->pageUrl, 'payload' => $data,
            ]);
        } catch (\Exception $e) {
            Yii::error("WxNotifyListener: 异常。scene={$event->scene} msg=" . $e->getMessage());
            WxNotifyLog::write([
                'mall_id' => $event->mallId, 'scene' => $event->scene, 'user_id' => $event->userId,
                'openid'  => $user['wechat_openid'], 'template_id' => $templateId,
                'status'  => WxNotifyLog::STATUS_FAILED, 'reason' => '异常：' . $e->getMessage(),
                'page_url' => $event->pageUrl, 'payload' => $event->templateData,
            ]);
        }
    }
}
