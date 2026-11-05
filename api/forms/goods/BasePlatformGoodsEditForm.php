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

namespace app\forms\goods;

use app\core\ApiCode;
use app\core\Pipeline;
use app\models\BaseModel;
use app\models\Goods;
use app\services\goods\pipeline\GoodsBaseSavePipe;
use app\services\goods\pipeline\GoodsContext;
use app\services\goods\pipeline\GoodsEventDispatcherPipe;
use app\services\goods\pipeline\GoodsInfoSavePipe;
use Yii;

/**
 * 平台级商品编辑表单基类
 *
 * 适用于：积分商品、幸运拼商品、礼券套餐 等"非常规商户商品"
 * 这些商品的共同特点：
 *   - 不使用多规格 (isAttr = 0)
 *   - 不需要平台分类校验
 *   - 不需要 normalizeSpecs 兜底
 *   - 不归属普通商户场景（mch_id 通常 = 0，或来自特殊路径）
 *   - 通过自己的「插件 ID 反查」找 Goods，而不是直接用 goods.id
 *
 * 与 {@see BaseGoodsEditForm} 的区别：
 *   BaseGoodsEditForm 是给走完整商户商品流程的（含分类、规格、运费、审核）
 *   BasePlatformGoodsEditForm 是简化版，只跑必要的 4 个 Pipe (Base/Info/Plugin/Event)
 *
 * 子类必须实现：
 *   - {@see findExistingGoods()}    根据 form->id 找已存在的 Goods（找不到返回 null 表示新建）
 *   - {@see makeNewGoods()}         新建 Goods 时的初始化字段
 *   - {@see getPluginSavePipe()}    返回插件专属 Save Pipe 的类名
 *   - {@see getGoodsType()}         返回此插件商品类型字符串（'lucky' / 'integral' / 'voucher'）
 *
 * 子类可选覆盖：
 *   - {@see buildContext()}         自定义 Context 字段（例如 voucher 需要 mch=Yii::$app->mch）
 *   - {@see buildSuccessResponse()} 自定义保存成功的返回 data 字段
 *   - {@see getPipes()}             调整 Pipeline 顺序（默认 Base → Info → Plugin → Event）
 */
abstract class BasePlatformGoodsEditForm extends BaseModel
{
    public $id;

    /**
     * 子类提供：商品类型字符串
     * 例如 'lucky' / 'integral' / Goods::TYPE_VOUCHER
     */
    abstract protected function getGoodsType(): string;

    /**
     * 子类提供：插件专属的 Save Pipe class name
     */
    abstract protected function getPluginSavePipe(): string;

    /**
     * 子类提供：根据 form->id 找已存在的 Goods
     * 通常做法是先用 form->id 查插件附表，再用附表的 goods_id 反查 Goods
     * 找不到返回 null 表示新建商品
     */
    abstract protected function findExistingGoods(): ?Goods;

    /**
     * 子类提供：新建 Goods 时的初始化字段（mall_id / mch_id / stock 等）
     */
    abstract protected function makeNewGoods(): Goods;

    /**
     * 构造 Pipeline Context
     * 默认设置：mch=null（平台级），isPlatformGoods=true，isAttr=0，needsRecheck=false
     * 子类可覆盖：例如 voucher 需要 mch=Yii::$app->mch、isPlatformGoods=false
     */
    protected function buildContext(GoodsContext $context, Goods $goods, bool $isEditing): void
    {
        $context->form = $this;
        $context->goods = $goods;
        $context->mch = null;
        $context->isEditing = $isEditing;
        $context->needsRecheck = false;
        $context->isPlatformGoods = true;
        $context->isAttr = 0;
        $context->goodsType = $this->getGoodsType();
    }

    /**
     * 自定义 Pipeline 组成。默认：Base → Info → 插件 → Event
     * 注意：故意省去了 GoodsValidatorPipe（不需要 cat 校验）和 GoodsSkuSyncPipe（无规格）
     */
    protected function getPipes(): array
    {
        return [
            GoodsBaseSavePipe::class,
            GoodsInfoSavePipe::class,
            $this->getPluginSavePipe(),
            GoodsEventDispatcherPipe::class,
        ];
    }

    /**
     * 自定义保存成功的返回 data 字段
     * 默认返回 ['id' => $goods->id]，子类可覆盖（例如 lucky 想返回 LuckyGoods.id 而非 Goods.id）
     */
    protected function buildSuccessResponse(GoodsContext $context): array
    {
        return ['id' => $context->goods->id];
    }

    /**
     * 子类钩子：save 事务内、Pipeline 之后追加附表持久化（如果插件 Pipe 没覆盖完）
     * 抛异常会回滚整个事务
     */
    protected function afterSaveHook(GoodsContext $context): void
    {
    }

    /**
     * 共享的 save() 模板
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $goods = $this->findExistingGoods();
            $isEditing = $goods !== null;

            // form->id 给了但找不到 → 报错
            if ($this->id && !$isEditing) {
                throw new \Exception('商品不存在或无权操作');
            }

            if (!$isEditing) {
                $goods = $this->makeNewGoods();
            }

            $context = new GoodsContext();
            $this->buildContext($context, $goods, $isEditing);

            $pipeline = new Pipeline();
            $pipeline->send($context)->through($this->getPipes())->then(function ($ctx) {
                return true;
            });

            // 子类钩子
            $this->afterSaveHook($context);

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功', $this->buildSuccessResponse($context));
        } catch (\Exception $e) {
            Yii::warning($e->getMessage());
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}
