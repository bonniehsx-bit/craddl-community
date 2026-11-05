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

namespace app\models;

use Yii;

/**
 * 公众号模板消息发送记录（平台核心，所有插件共用）
 *
 * 只有 `app\listeners\WxNotifyListener` 写它——那是全仓库唯一调微信模板接口的地方。
 * 三种结局都记：成功、失败（含微信 errcode/errmsg）、跳过（没绑公众号 / 场景没配模板）。
 * 「跳过」也必须记：运营问「为什么没收到」，十有八九就是这两种，日志文件里翻不出来。
 *
 * @property int    $id
 * @property int    $mall_id
 * @property string $plugin       来源插件（scene 的第一段）
 * @property string $scene
 * @property int    $user_id      接收人
 * @property string $openid       接收人公众号 openid，未绑定为空
 * @property string $template_id
 * @property int    $status       1=成功 2=失败 3=跳过
 * @property string $reason       失败 / 跳过原因
 * @property string $page_url
 * @property string $payload      发给微信的模板数据 JSON
 * @property int    $created_at
 */
class WxNotifyLog extends BaseActiveRecord
{
    const STATUS_SUCCESS = 1;
    const STATUS_FAILED  = 2;
    const STATUS_SKIPPED = 3;

    const STATUS_TEXT = [
        self::STATUS_SUCCESS => '已发送',
        self::STATUS_FAILED  => '发送失败',
        self::STATUS_SKIPPED => '已跳过',
    ];

    /** 跳过原因(固定几种,便于后台按原因筛) */
    const REASON_NO_OPENID   = '用户未绑定公众号';
    const REASON_NO_TEMPLATE = '该场景未配置模板 ID';

    public static function tableName()
    {
        return '{{%wx_notify_log}}';
    }

    /**
     * 记一条。**绝不能抛**：通知本身是尽力而为的旁路，日志更不该把它带崩。
     *
     * @param array $data mall_id / scene / user_id / openid / template_id / status / reason / page_url / payload
     */
    public static function write(array $data): void
    {
        try {
            $scene = (string)($data['scene'] ?? '');
            $log = new self();
            $log->mall_id     = (int)($data['mall_id'] ?? 0);
            $log->scene       = mb_substr($scene, 0, 100);
            // 插件名取 scene 的第一段(plaza.audit.todo → plaza),没有点就整串当插件名
            $log->plugin      = mb_substr(strtok($scene, '.') ?: '', 0, 50);
            $log->user_id     = (int)($data['user_id'] ?? 0);
            $log->openid      = mb_substr((string)($data['openid'] ?? ''), 0, 128);
            $log->template_id = mb_substr((string)($data['template_id'] ?? ''), 0, 100);
            $status = (int)($data['status'] ?? self::STATUS_FAILED);
            // 非法值一律记成失败:留下 status=0 这种行,后台筛选时既不算成功也不算失败,等于没记
            $log->status      = isset(self::STATUS_TEXT[$status]) ? $status : self::STATUS_FAILED;
            $log->reason      = mb_substr((string)($data['reason'] ?? ''), 0, 255);
            $log->page_url    = mb_substr((string)($data['page_url'] ?? ''), 0, 255);
            $payload = $data['payload'] ?? null;
            $log->payload     = $payload === null ? null : (is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE));
            $log->save(false);
        } catch (\Throwable $e) {
            Yii::warning('[WxNotifyLog] 写发送记录失败：' . $e->getMessage());
        }
    }

    /**
     * 清理过期记录（默认留 90 天）。量不大，但没人清就会一直长。
     *
     * @return int 删除行数
     */
    public static function purgeBefore(int $days = 90, int $mallId = 0): int
    {
        $days = max(1, $days);
        $cond = ['<', 'created_at', time() - $days * 86400];
        return (int)self::deleteAll($mallId > 0 ? ['and', ['mall_id' => $mallId], $cond] : $cond);
    }
}
