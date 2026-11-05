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

/**
 * IM 站内通知 Payload 构建器（系统级，core）
 *
 * 只负责「拼装标准化的消息卡片数组」，是纯函数，无任何副作用、不依赖 IM 插件。
 * 发送由全站统一事件 {@see \app\events\ImPushEvent} 驱动：
 *
 * ```php
 * use app\helpers\ImNoticeBuilder;
 * use app\events\ImPushEvent;
 *
 * $content = ImNoticeBuilder::buildBizNotice('biz', '标题', '正文', '/pages/xxx', '', 'scene');
 * Yii::$app->trigger(ImPushEvent::EVENT_PUSH, new ImPushEvent([
 *     'mallId'  => $mallId,
 *     'fromId'  => 0,
 *     'toId'    => $userId,
 *     'content' => $content,
 *     'msgType' => 'system',
 * ]));
 * ```
 *
 * 设计要点：因为是核心 helper（不在 im 插件内），其它插件可直接引用而不产生对 im 插件的依赖。
 * im 插件的 ImHelper 保留同名 build* 方法作为兼容壳，内部转调本类。
 */
class ImNoticeBuilder
{
    /**
     * 构建纯文本系统通知 Payload
     * @param string $desc 通知内容
     * @param string $url 可选，点击跳转链接
     */
    public static function buildTextNotice($desc, $url = '')
    {
        $payload = [
            'type'  => 'text',
            'title' => '系统通知',
            'desc'  => $desc
        ];
        if ($url) {
            $payload['url'] = $url;
        }
        return $payload;
    }

    /**
     * 构建图片系统通知 Payload
     * @param string $title 标题
     * @param string $imageUrl 图片地址
     * @param string $desc 描述
     * @param string $url 可选，点击跳转链接
     */
    public static function buildImageNotice($title, $imageUrl, $desc = '', $url = '')
    {
        $payload = [
            'type'  => 'image',
            'title' => $title,
            'desc'  => $desc,
            'image' => $imageUrl
        ];
        if ($url) {
            $payload['url'] = $url;
        }
        return $payload;
    }

    /**
     * 构建订单状态变更通知 Payload
     * @param \app\models\Order $order 订单模型
     * @param string $title 标题
     * @param string $desc 描述
     * @param string $url 可选，点击跳转链接，默认跳订单详情
     */
    public static function buildOrderNotice(\app\models\Order $order, $title, $desc = '', $url = '')
    {
        return [
            'type'     => 'order',
            'title'    => $title,
            'desc'     => $desc ?: '您的订单状态已更新',
            'order_no' => $order->order_no,
            'amount'   => $order->total_price,
            'status'   => $order->getStatusText(),
            'url'      => $url ?: '/others/order-detail/order-detail?order_id=' . $order->id, // 深度链接
        ];
    }

    /**
     * 构建商品推荐/审核结果通知 Payload
     * @param \app\models\Goods $goods 商品模型
     * @param string $title 标题
     * @param string $desc 描述
     * @param string $url 可选，点击跳转链接，默认跳商品详情
     */
    public static function buildGoodsNotice(\app\models\Goods $goods, $title, $desc = '', $url = '')
    {
        return [
            'type'     => 'goods',
            'title'    => $title,
            'desc'     => $desc ?: $goods->name,
            'price'    => $goods->price,
            'cover'    => $goods->cover_pic,
            'url'      => $url ?: '/others/goods/goods?id=' . $goods->id, // 深度链接
        ];
    }

    /**
     * 构建账户资金/积分变动通知 Payload
     * @param string $title 标题
     * @param string|float $amount 变动金额
     * @param string $currencyType 货币类型(余额/积分等)
     * @param string $desc 描述
     * @param string $url 可选，点击跳转链接
     */
    public static function buildAccountNotice($title, $amount, $currencyType = '余额', $desc = '', $url = '')
    {
        $payload = [
            'type'     => 'account',
            'title'    => $title,
            'desc'     => $desc,
            'amount'   => ($amount > 0 ? '+' : '') . $amount . ' ' . $currencyType,
        ];
        if ($url) {
            $payload['url'] = $url;
        }
        return $payload;
    }

    /**
     * 构建优惠券发放/到期提醒 Payload
     * @param \app\models\UserCoupon $userCoupon 用户优惠券模型
     * @param string $title 标题 (例如："一张优惠券即将到期" 或 "恭喜获得新人专享券")
     * @param string $desc 补充描述 (例如："请尽快使用哦")
     * @param string $url 可选，点击跳转链接，默认跳我的卡券包
     */
    public static function buildCouponNotice(\app\models\UserCoupon $userCoupon, $title, $desc = '', $url = '')
    {
        // 提取卡券的面值字符串
        $valueStr = '';
        if ($userCoupon->type == 1) { // 折扣券
            $valueStr = floatval($userCoupon->discount) . '折';
        } else { // 满减券
            $valueStr = '¥' . floatval($userCoupon->sub_price);
        }

        // 门槛字符串
        $conditionStr = $userCoupon->min_price > 0 ? "满{$userCoupon->min_price}元可用" : '无门槛使用';

        // 有效期范围
        $validityStr = '';
        if ($userCoupon->start_at && $userCoupon->end_at) {
            $validityStr = date('Y.m.d', $userCoupon->start_at) . ' - ' . date('Y.m.d', $userCoupon->end_at);
        }

        return [
            'type'      => 'coupon',
            'title'     => $title,
            'desc'      => $desc ?: '点击查看卡券详情',
            'value'     => $valueStr,
            'condition' => $conditionStr,
            'validity'  => $validityStr,
            'url'       => $url ?: '/pages/coupon/list', // 假设默认跳卡券包
        ];
    }

    /**
     * 构建通用业务卡片通知 Payload（给 yuda / bs / membership 等业务用）
     *
     * 与 chat.vue / list.vue / system-list.vue 三端兼容：
     *   · 提供 content 字段（纯文本摘要）—— chat 气泡 + 会话列表最后一条展示
     *   · 提供 type/title/desc/url/icon —— system-list 富卡片渲染
     *
     * @param string $type     业务类型标识（如 yuda / bs / membership）决定 system-list 卡片样式
     * @param string $title    卡片标题
     * @param string $desc     正文描述 (同时作为 content 摘要 fallback)
     * @param string $url      点击跳转链接
     * @param string $icon     图标 emoji（可选）
     * @param string $scene    业务场景标识（可选，便于埋点/渠道定位）
     * @param array  $fields   结构化字段列表 [{label,value},...]（可选）
     */
    public static function buildBizNotice(
        string $type,
        string $title,
        string $desc,
        string $url = '',
        string $icon = '',
        string $scene = '',
        array $fields = []
    ): array {
        return array_filter([
            'type'    => $type,
            'title'   => $title,
            'desc'    => $desc,
            'content' => $desc,   // ← 关键：让 chat/list 能直接读到摘要
            'url'     => $url,
            'icon'    => $icon,
            'scene'   => $scene,
            'fields'  => $fields,
        ], fn($v) => $v !== '' && $v !== [] && $v !== null);
    }
}
