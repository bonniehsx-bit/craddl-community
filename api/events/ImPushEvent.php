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

namespace app\events;

use yii\base\Event;

/**
 * IM 推送事件
 *
 * 任何插件或业务代码只需触发此事件，即可向指定用户发送 IM 站内消息推送。
 * IM 插件未安装时，触发此事件是安全的（无任何副作用）。
 *
 * 用法示例：
 * ```php
 * use app\events\ImPushEvent;
 * use app\plugins\im\helpers\ImHelper;
 *
 * // 1. 发送纯文本通知
 * $event = new ImPushEvent([
 *     'mallId'  => $mallId,
 *     'toId'    => $userId,
 *     'content' => ImHelper::buildTextNotice('您有一条新消息', '/pages/xxx'),
 *     'msgType' => 'text',
 * ]);
 * Yii::$app->trigger(ImPushEvent::EVENT_PUSH, $event);
 *
 * // 2. 发送订单状态通知
 * $event = new ImPushEvent([
 *     'mallId'  => $mallId,
 *     'toId'    => $userId,
 *     'content' => ImHelper::buildOrderNotice($order, '您的订单已发货'),
 *     'msgType' => 'order',
 * ]);
 * Yii::$app->trigger(ImPushEvent::EVENT_PUSH, $event);
 * ```
 */
class ImPushEvent extends Event
{
    /**
     * 事件名称，触发时使用此常量
     */
    const EVENT_PUSH = 'im.push';

    /**
     * @var int 商城 ID（必填）
     */
    public $mallId;

    /**
     * @var int|string 接收方用户 ID（必填）
     */
    public $toId;

    /**
     * @var int|string 发送方用户 ID，0 表示系统发送
     */
    public $fromId = 0;

    /**
     * @var string|array 消息内容
     *   - 传字符串: 将作为纯文本 content 字段
     *   - 传数组: 建议使用 ImHelper::buildXxxNotice() 构建标准 Payload
     */
    public $content;

    /**
     * @var string 消息类型
     *   可选值: text | image | order | goods | account | coupon | system
     */
    public $msgType = 'text';
}
