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

/**
 * 核销码详情 DTO(scan 预览阶段返回)。核销治理阶段4:统一渲染契约,
 * 消除既有钩子返回结构的漂移(hotel 用 list/can_verify 异构、products 图片键 cover_pic/image 不一)。
 *
 * 采新契约 VerifiablePlugin 的插件产出本 DTO;存量老钩子(返回裸数组)由 Gateway 兼容,不强制迁移。
 * 前端统一按本结构渲染:type/is_allow/msg/data.{name,order_no,user_name,info,products[]}。
 */
class VerifyDetail
{
    /** @var string 业务类型标识:normal/card/hotel/group/xwy/brand... */
    public string $type = '';

    /** @var string 业务类型显示名(词库解析后) */
    public string $typeName = '';

    /** @var bool 是否可核销(前端据此置灰确认键) */
    public bool $isAllow = false;

    /** @var string 状态/提示文案 */
    public string $msg = '';

    /** @var string 凭证持有人 */
    public string $userName = '';

    /** @var string 关联单号 */
    public string $orderNo = '';

    /** @var string 附加信息(如 实付/积分抵扣) */
    public string $info = '';

    /** @var array 商品明细,字段强约定:[{name, image, spec, num}] */
    public array $products = [];

    /** @var array 各插件私有透传(如 otv 外部平台信息),前端按需读 */
    public array $extra = [];

    /**
     * 转成与既有钩子一致的数组结构,供 Gateway/控制器 asJson 与前端复用。
     */
    public function toArray(): array
    {
        return array_merge([
            'type' => $this->type,
            'type_name' => $this->typeName,
            'is_allow' => $this->isAllow,
            'msg' => $this->msg,
            'data' => [
                'name' => $this->typeName,
                'order_no' => $this->orderNo,
                'user_name' => $this->userName,
                'info' => $this->info,
                'products' => $this->products,
            ],
        ], $this->extra ? ['extra' => $this->extra] : []);
    }
}
