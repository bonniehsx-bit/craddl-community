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

namespace app\services\verify;

use app\core\ApiCode;

/**
 * 核销执行结果 DTO(commit 阶段返回)。核销治理阶段4。
 * 采新契约 VerifiablePlugin 的插件产出本 DTO;存量老钩子返回裸数组,由 Gateway 兼容。
 */
class VerifyResult
{
    /** @var bool 是否核销成功 */
    public bool $ok = true;

    /** @var string 结果文案 */
    public string $message = '';

    /** @var string 业务类型显示名 */
    public string $typeName = '';

    /**
     * 下面三个是**统一台账 ClerkLog 要的字段**,不是可有可无的装饰。
     *
     * 原先本 DTO 只有 code/msg/verify_type_name,与多数老钩子的返回值一样窄 —— 平台核销台
     * 据此写台账时 order_no/products/biz_type 全空,后台核销记录按订单号一条都搜不到
     * (dev 实测:source=platform 的行三列皆空,source=mch 的行反而完整)。
     * 平台侧现已改为核销前预取快照兜底,但插件自报的最准;新契约插件请务必填这三项。
     *
     * @var string 业务类型标识(如 integral / brand / hotel),对应 ClerkLog.biz_type
     */
    public string $bizType = '';

    /** @var string 关联单号,对应 ClerkLog.order_no —— 运营就靠它反查 */
    public string $orderNo = '';

    /** @var array 商品快照,字段强约定 [{name, image, spec, num}],对应 ClerkLog.products_snapshot */
    public array $products = [];

    public function __construct(bool $ok = true, string $message = '', string $typeName = '', string $bizType = '', string $orderNo = '', array $products = [])
    {
        $this->ok = $ok;
        $this->message = $message;
        $this->typeName = $typeName;
        $this->bizType = $bizType;
        $this->orderNo = $orderNo;
        $this->products = $products;
    }

    /** 转成与既有钩子一致的数组结构 */
    public function toArray(): array
    {
        return [
            'code' => $this->ok ? ApiCode::CODE_SUCCESS : ApiCode::CODE_FAIL,
            'msg' => $this->message,
            'verify_type_name' => $this->typeName,
            // 台账字段:调用方(平台/商家核销台)按 biz_type/order_no/products 取,取不到才回退自己的快照
            'biz_type' => $this->bizType,
            'order_no' => $this->orderNo,
            'products' => $this->products,
        ];
    }
}
