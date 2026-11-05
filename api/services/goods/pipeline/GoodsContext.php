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
namespace app\services\goods\pipeline;

use app\forms\goods\BaseGoodsEditForm;
use app\models\Goods;

class GoodsContext
{
    /**
     * 表单实例（基类 BaseGoodsEditForm 的任意子类）
     * @var BaseGoodsEditForm|\app\models\BaseModel
     */
    public $form;

    /** @var Goods */
    public $goods;

    /**
     * 当前商户实例（由 mch 插件在请求生命周期中设置 Yii::$app->mch 注入）
     * 故意不写死类型，避免核心 Pipeline 反向依赖 plugins/mch 模型。
     * @var mixed
     */
    public $mch;

    public $isEditing = false;
    public $needsRecheck = false;
    public $isPlatformGoods = false;

    // [新增] 插件自定义参数支持
    public $isAttr = 1; // 默认常规商品为多规格(1)，插件可覆写为单规格(0)
    public $goodsType = ''; // 默认为常规商品或从之前继承，插件可覆写为 'group_buy' / 'voucher' 等

    /**
     * 通用插件挂载点：
     * 插件可以通过 $context->pluginContext['xxx'] = $model 向下游 Pipe / 回传 API 传递自己的模型或 ID，
     * 避免在核心 Context 里写死插件名（以前这里有 integralGoods/luckyGoods/yudaGoods 三个专属字段，已废弃）。
     *
     * 约定 key 使用插件名小写，例如：
     *   $context->pluginContext['lucky'] = $luckyGoods;
     *   $luckyId = $context->pluginContext['lucky']->id ?? 0;
     *
     * @var array<string, mixed>
     */
    public $pluginContext = [];

    /** @var array|null 编辑库存时抢到的一次性令牌(EditOnceHelper::claim),保存失败时要放回 */
    public $stockEditClaim = null;
}
