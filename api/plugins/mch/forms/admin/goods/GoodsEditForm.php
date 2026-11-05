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

namespace app\plugins\mch\forms\admin\goods;

use app\forms\goods\BaseGoodsEditForm;

/**
 * 商户端-标准商品编辑表单
 *
 * 本类已迁移到核心层 {@see BaseGoodsEditForm}。
 * 保留此空壳子类的目的：
 *  - 历史代码中（控制器/事件监听/命令行脚本）仍在直接引用此类名
 *  - 日后如果要加"仅适用于普通商户商品"的特殊逻辑，可以在这里扩展
 *
 * 新的插件商品类型 **不要** 继承此类，请直接继承 {@see BaseGoodsEditForm}，
 * 以避免插件间产生隐式的强依赖（mch 插件被禁用时其他插件就崩溃）。
 */
class GoodsEditForm extends BaseGoodsEditForm
{
    /**
     * 在核心规则上追加「运费跨字段校验」——仅普通商户商品适用:
     *  · 选「统一运费」(express_type=1) 必须填金额且 > 0;
     *  · 选「运费模板」(express_type=2) 必须选到模板(freight_id > 0)。
     * 否则会静默落成运费 0 = 变相包邮(资损)。前端已拦一道,这里做服务端兜底。
     */
    public function rules()
    {
        return array_merge(parent::rules(), [
            ['freight_price', 'required', 'when' => fn($m) => (int)$m->express_type === 1, 'message' => '请输入统一运费金额'],
            ['freight_price', 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number',
                'when' => fn($m) => (int)$m->express_type === 1, 'message' => '统一运费金额需大于 0'],
            ['freight_id', 'required', 'when' => fn($m) => (int)$m->express_type === 2, 'message' => '请选择运费模板'],
            ['freight_id', 'compare', 'compareValue' => 0, 'operator' => '>', 'type' => 'number',
                'when' => fn($m) => (int)$m->express_type === 2, 'message' => '请选择运费模板'],
        ]);
    }
}
