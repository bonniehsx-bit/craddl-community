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

namespace app\helpers;

use app\events\MessageEvent;
use InvalidArgumentException;
use Yii;

class MessageHelper
{
    /**
     * [兼容方法] 创建一条消息。
     * 建议使用新的具名方法如 sendToUser() 以获得更好的代码提示和校验。
     *
     * @param array $data 消息数据数组
     * @param int|null $mall_id 商城ID，如果为null则尝试从当前应用实例获取
     * @return void
     */
    public static function addMsg($data, $mall_id = null)
    {
        // 保持对旧代码的完全兼容
        if (!is_array($data)) {
            return;
        }

        // 调用新的核心方法进行处理
        self::_create($data, $mall_id);
    }

    /**
     * [新方法] 发送一条消息给指定用户。
     * 这是推荐的调用方式。
     *
     * @param int $user_id 用户ID
     * @param string $title 消息标题
     * @param string $content 消息内容
     * @param string $msg_type 消息标识符，可选
     * @param int|null $mall_id 商城ID，如果为null则尝试从当前应用实例获取
     * @return void
     * @throws InvalidArgumentException 如果参数无效
     */
    public static function sendToUser(int $user_id, string $title, string $content, string $msg_type = '', ?int $mall_id = null)
    {
        $data = [
            'user_id'  => $user_id,
            'title'    => $title,
            'content'  => $content,
            'msg_type' => $msg_type,
        ];
        self::_create($data, $mall_id);
    }

    /**
     * [新方法] 创建一条系统级消息 (不指定用户)。
     *
     * @param string $title 消息标题
     * @param string $content 消息内容
     * @param string $msg_type 消息标识符，可选
     * @param int|null $mall_id 商城ID，如果为null则尝试从当前应用实例获取
     * @return void
     * @throws InvalidArgumentException 如果参数无效
     */
    public static function sendSystemNotification(string $title, string $content, string $msg_type = '', ?int $mall_id = null)
    {
        $data = [
            'user_id'  => 0, // 系统消息 user_id 为 0
            'title'    => $title,
            'content'  => $content,
            'msg_type' => $msg_type,
        ];
        self::_create($data, $mall_id);
    }


    /**
     * 内部核心方法：用于校验和触发消息创建事件。
     *
     * @param array $data 消息数据
     * @param int|null $mall_id 商城ID
     * @return void
     * @throws InvalidArgumentException
     */
    private static function _create(array $data, ?int $mall_id)
    {
        // 1. 增强校验：确保核心字段存在
        if (empty($data['title']) || empty($data['content'])) {
            // 抛出异常而不是静默失败，让调用者知道发生了错误
            throw new InvalidArgumentException('消息的标题和内容不能为空。');
        }

        // 确保 user_id 存在且是整数
        $data['user_id'] = $data['user_id'] ?? 0;
        if (!is_int($data['user_id'])) {
            throw new InvalidArgumentException('用户ID必须是一个整数。');
        }

        // 2. 优化 mall_id 的处理
        // 如果未传入 mall_id，则尝试从Yii应用实例中获取，使调用更便捷
        if (is_null($mall_id)) {
            $mall_id = isset(Yii::$app->mall) ? Yii::$app->mall->id : 1;
        }

        // 3. 触发事件 (逻辑不变)
        $data['mall_id'] = $mall_id;
        $event = new MessageEvent();
        $event->body = $data;
        Yii::$app->trigger(MessageEvent::MSG_CREATING, $event);
    }
}