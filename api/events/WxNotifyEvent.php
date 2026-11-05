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
 * 微信公众号模板消息推送事件
 *
 * 任何插件或业务代码触发此事件，即可向指定用户发送微信模板消息。
 * - 用户未绑定公众号时静默跳过，不报错
 * - 模板 ID 未配置时静默跳过，不报错
 *
 * 用法示例：
 * ```php
 * use app\events\WxNotifyEvent;
 *
 * Yii::$app->trigger(WxNotifyEvent::EVENT_NOTIFY, new WxNotifyEvent([
 *     'mallId'       => $mallId,
 *     'userId'       => $userId,
 *     'scene'        => 'hardware.bottle.feeding_end',
 *     'templateData' => [
 *         '喂养时间' => '2026-04-16 14:23',
 *         '奶量'     => '120ml',
 *         '用时'     => '8分钟',
 *         '平均温度' => '38.2°C',
 *     ],
 *     'pageUrl' => 'plugins1/hardware/bottle/index?device_id=1',
 * ]));
 * ```
 */
class WxNotifyEvent extends Event
{
    const EVENT_NOTIFY = 'wx.notify';

    /** @var int 商城 ID（必填） */
    public $mallId;

    /** @var int 接收用户 ID（必填） */
    public $userId;

    /**
     * @var string 场景键（必填）
     * 对应 wx_notify_template 表中的 scene 字段
     * 例：hardware.bottle.feeding_end
     */
    public $scene;

    /**
     * @var array 模板变量键值对
     * key 为模板申请时填写的字段名，value 为实际内容
     * 例：['喂养时间' => '14:23', '奶量' => '120ml']
     */
    public $templateData = [];

    /**
     * @var string 点击消息后跳转的小程序页面路径（可选）
     * 例：plugins1/hardware/bottle/index?device_id=1
     */
    public $pageUrl = '';
}
