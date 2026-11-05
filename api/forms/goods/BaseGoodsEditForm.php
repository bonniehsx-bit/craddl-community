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
use app\helpers\JsonHelper;
use app\helpers\LockHelper;
use app\models\BaseModel;
use app\models\Goods;
use app\models\MchGoodsCatRel;
use app\services\GoodsService;
use app\services\order\OrderBehaviorRegistry;
use app\services\goods\pipeline\GoodsBaseSavePipe;
use app\services\goods\pipeline\GoodsContext;
use app\services\goods\pipeline\GoodsEventDispatcherPipe;
use app\services\goods\pipeline\GoodsInfoSavePipe;
use app\services\goods\pipeline\GoodsSkuSyncPipe;
use app\services\goods\pipeline\GoodsValidatorPipe;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Json;

/**
 * 商品编辑核心表单基类
 *
 * 所有商品类型（普通/拼团/酒店/遇搭/券包/幸运拼...）的编辑表单都应该继承此类，
 * 而不是继承某个插件的 GoodsEditForm，以避免插件间的隐式强依赖。
 *
 * ## 设计
 * 核心负责：
 *  - 字段定义、基础 rules
 *  - getDetail 回显与老数据兼容升级
 *  - save 的 Pipeline 组装与事务管理
 *  - delete / updateStatus 的通用流程
 *  - mch_cats 商户专属分类持久化
 *
 * 子类通过覆盖以下钩子方法来定制行为：
 *  - {@see getFormGoodsType()}    查询过滤类型（可返回数组用于 IN 子句）
 *  - {@see getSaveGoodsType()}    写入类型（必须单一字符串）
 *  - {@see getPipes()}            自定义 Pipeline 组成（可插入插件 Pipe）
 *  - {@see getLockKey()}          并发锁 key（null 表示禁用）
 *  - {@see enrichDetailHook()}    getDetail 查询后追加插件数据
 *  - {@see buildContextHook()}    构造 Pipeline Context 时追加数据
 *  - {@see afterSaveHook()}       save 成功后插件附表持久化
 *
 * ## 运行时依赖
 * 本类假设 `Yii::$app->mch` 已由 mch 插件在请求生命周期中注入。所有商品都归属某个商户
 * （含平台自营商户），这是 JX Mall 的基础设定，不算插件依赖。
 */
class BaseGoodsEditForm extends BaseModel
{
    // === 接收前端的公共字段 ===
    public $id;
    public $name;
    public $price;
    public $origin_price;
    public $cost_price;
    public $purchase_price;
    public $cover_pic;
    public $pic_list;
    public $detail;
    public $cat_id;
    public $unit;
    public $is_attr;
    public $stock;
    public $virtual_sales;
    public $specs_data;
    public $mch_cats; // 商户店铺专属分类 ID 集合
    public $status;
    public $freight_id;
    public $express_type;
    public $freight_price;
    public $service_tags; // 详情页服务标签:标签 ID 数组(平台/商家自己维护的标签库,见 GoodsServiceTag)
    /**
     * 打开编辑页那一刻各规格的库存 {attr_id: stock}。getDetail 下发,前端原样带回。
     * 保存时库存按「新值 − 这里的旧值」的差额原子加减,不再整值写回 ——
     * 否则打开页面到点保存之间下单扣掉的库存会被加回来(见 GoodsService::syncSkus)。
     */
    public $stock_snapshot;
    /** 这个编辑页的一次性令牌(和 stock_snapshot 一起下发、带回),同一页面重复提交时差额只生效一次 */
    public $stock_edit_token;
    /** 「发布相似商品」:非 0 时 getDetail 下发的是一份可直接当新商品提交的拷贝(见 applySimilarReset) */
    public $similar;

    /**
     * 查询过滤类型（可返回数组用于 IN 子句，兼容历史空字符串老数据）
     * @return array|string
     */
    protected function getFormGoodsType()
    {
        return [Goods::TYPE_NORMAL, ''];
    }

    /**
     * 写入类型（必须返回单一字符串）
     * - 如果 getFormGoodsType() 返回数组，取第一个非空值
     * - 全部为空则回退到 TYPE_NORMAL
     * - 子类可独立覆盖此方法以指定写入类型
     */
    protected function getSaveGoodsType(): string
    {
        $type = $this->getFormGoodsType();
        if (is_array($type)) {
            foreach ($type as $t) {
                if ($t !== '' && $t !== null) {
                    return (string)$t;
                }
            }
            return Goods::TYPE_NORMAL;
        }
        return (string)$type;
    }

    /**
     * 自定义 Pipeline 管线
     * 子类可以覆盖此方法，在默认 Pipe 列表里插入插件 Pipe
     * 例如：array_splice($pipes, 2, 0, [MyPluginPipe::class]);
     */
    protected function getPipes(): array
    {
        return [
            GoodsValidatorPipe::class,
            GoodsBaseSavePipe::class,
            GoodsInfoSavePipe::class,
            GoodsSkuSyncPipe::class,
            GoodsEventDispatcherPipe::class,
        ];
    }

    /**
     * 并发创建锁 key；返回 null 表示禁用锁
     */
    protected function getLockKey(): ?string
    {
        $mch = $this->resolveMch();
        return $mch ? "create_goods_{$mch->id}" : null;
    }

    /**
     * 解析当前操作的商户实例
     *
     * 默认从 Yii::$app->mch 取（商户登录场景）。
     * 平台后台代理操作的子类可以覆盖此方法，从 form 字段（如 mch_id）查找指定商户。
     *
     * @return mixed Mch 模型实例或 null
     */
    protected function resolveMch()
    {
        return Yii::$app->mch ?? null;
    }

    /**
     * save / delete / updateStatus 时按所有权查找 goods 的 where 条件
     *
     * 默认按 resolveMch() 返回的商户 + getFormGoodsType() 限定。
     * 子类可覆盖以放宽或收紧条件。
     */
    protected function buildOwnedGoodsConditions(): array
    {
        $mch = $this->resolveMch();
        return [
            'id' => $this->id,
            'mch_id' => $mch ? $mch->id : 0,
            'is_delete' => 0,
            'type' => $this->getFormGoodsType(),
        ];
    }

    /**
     * getDetail 时构造的查询。
     *
     * 默认按 resolveMch() 商户 + 类型限定。子类可覆盖以完全自定义查询逻辑。
     */
    protected function buildDetailQuery(): ActiveQuery
    {
        $mch = $this->resolveMch();
        return Goods::find()->with([
            'info',
            'cat',
            'attrs' => function ($query) {
                $query->andWhere(['is_delete' => 0]);
            }
        ])->where([
            'id' => $this->id,
            'mch_id' => $mch ? $mch->id : 0,
            'is_delete' => 0,
            'type' => $this->getFormGoodsType(),
        ]);
    }

    /**
     * 新建 goods 时设置归属字段（mch_id / mall_id）
     */
    protected function applyCreateAttributes(Goods $goods): void
    {
        $mch = $this->resolveMch();
        $goods->mch_id = $mch ? $mch->id : 0;
        $goods->mall_id = Yii::$app->mall->id;
    }

    /**
     * getDetail 接口响应里 detail 数据的外层 key
     *
     * 默认 'info'。商家端独立前端历史上用 'detail' 的，可以覆盖此方法兼容前端。
     */
    protected function getDetailResponseKey(): string
    {
        return 'info';
    }

    /**
     * 编辑已审核商品时是否触发重新审核
     *
     * 默认 true：商家自己修改已审核的商品 → 退回待审核状态。
     * 子类可覆盖：平台运营代为编辑不需要退回审核（平台是受信任的）。
     */
    protected function shouldTriggerRecheckOnEdit(): bool
    {
        return true;
    }

    /**
     * 子类钩子：getDetail 后追加数据（例如插件附表字段、触发插件事件）
     *
     * 注意：此钩子在「编辑」和「新建」两种场景都会被触发，新建模式下 $goods 为 null。
     * 子类必须做 null check，如：if ($goods && $goods->info) { ... }
     */
    protected function enrichDetailHook(array &$detail, ?Goods $goods): void
    {
    }

    /**
     * 子类钩子：构造 Pipeline Context 时追加数据
     */
    protected function buildContextHook(GoodsContext $context): void
    {
    }

    /**
     * 子类钩子：save 事务内、在 Pipeline 执行完成之后追加附表持久化
     * 抛异常会导致整个事务回滚
     */
    protected function afterSaveHook(GoodsContext $context, Goods $goods): void
    {
    }

    public function rules()
    {
        return [
            [['express_type', 'freight_id'], 'integer'],
            [['freight_price'], 'number', 'min' => 0],
            [['name', 'cover_pic', 'price', 'stock', 'cat_id'], 'required'],
            [['id', 'cat_id', 'is_attr', 'stock', 'virtual_sales', 'status', 'similar'], 'integer'],
            [['price', 'origin_price', 'cost_price', 'purchase_price'], 'number', 'min' => 0],
            [['name', 'unit', 'cover_pic'], 'string', 'max' => 255],
            [['pic_list', 'detail', 'specs_data', 'mch_cats', 'service_tags', 'stock_snapshot', 'stock_edit_token'], 'safe'],
            [['status', 'cost_price', 'purchase_price', 'origin_price', 'is_attr'], 'default', 'value' => 0],
        ];
    }

    /**
     * 获取单个商品详情，用于编辑回显
     * 【架构对齐】自动兼容并升级老旧的单规格数据
     */
    public function getDetail()
    {
        $detail = [];
        $goods = null;
        if ($this->id) {
            /** @var Goods|null $goods */
            $goods = $this->buildDetailQuery()->one();

            if (!$goods) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或无权访问');
            }

            $detail = $goods->toArray();
            // 服务标签库里存的是 ID 数组,前端勾选框要数组;老商品字段为空时给空数组
            $detail['service_tags'] = \app\models\GoodsServiceTag::decodeIds($goods->service_tags);
            // 各规格此刻的库存,保存时按差额改(见 $stock_snapshot)。转成对象形态(键是规格 ID)
            $detail['stock_snapshot'] = (object)array_map('intval', \yii\helpers\ArrayHelper::map($goods->attrs, 'id', 'stock'));
            $detail['stock_edit_token'] = \app\helpers\EditOnceHelper::issue();

            // 补充系统分类对象供前端 TabBasic 显示
            if ($goods->cat) {
                $detail['cat'] = ['id' => $goods->cat->id, 'name' => $goods->cat->name];
            } elseif ($goods->cat_id) {
                $detail['cat'] = ['id' => $goods->cat_id, 'name' => "已选(ID:{$goods->cat_id})"];
            }

            if ($goods->info) {
                $detail['pic_list'] = Json::decode($goods->info->pic_list) ?: [];
                $detail['detail'] = $goods->info->detail ?? '';
            } else {
                $detail['pic_list'] = [];
                $detail['detail'] = '';
            }

            // ==========================================
            // 旧数据自动兼容升级逻辑：
            // 发现旧版的无规格商品，自动补全严格符合前端数据结构的虚拟默认规格
            // ==========================================
            if (isset($detail['is_attr']) && $detail['is_attr'] == 0) {
                $detail['is_attr'] = 1;

                $defaultSkuId = 0;
                if (!empty($goods->attrs)) {
                    $defaultSkuId = $goods->attrs[0]->id;
                }

                $detail['specs_data'] = [
                    'spec_groups' => [
                        [
                            'id' => 'temp_group_0',
                            'name' => '规格',
                            'values' => [
                                ['id' => 'temp_value_0', 'name' => '默认']
                            ]
                        ]
                    ],
                    'sku_list' => [
                        [
                            'id' => (int)$defaultSkuId,
                            'specs' => [['group' => '规格', 'value' => '默认']],
                            'price' => (float)$goods->price,
                            'cost_price' => (float)$goods->cost_price,
                            'purchase_price' => (float)$goods->purchase_price,
                            'stock' => (int)$goods->stock,
                            'no' => $goods->no ?? '',
                            'weight' => (float)$goods->weight ?? 0,
                            'cover_pic' => $goods->cover_pic ?? '',
                        ]
                    ]
                ];
            } else {
                // 正常解析多规格数据
                $specTemplate = $goods->info ? $goods->info->spec_template : '';
                $specGroups = $specTemplate ? JsonHelper::decode($specTemplate) : [];
                $detail['specs_data'] = [
                    'spec_groups' => is_array($specGroups) ? $specGroups : [],
                    'sku_list' => []
                ];

                if ($goods->attrs) {
                    foreach ($goods->attrs as $attr) {
                        $specData = $attr->spec_data;
                        $specs = $specData ? JsonHelper::decode($specData) : [];
                        $detail['specs_data']['sku_list'][] = [
                            'id' => (int)$attr->id,
                            'price' => (float)$attr->price,
                            'cost_price' => (float)$attr->cost_price,
                            'purchase_price' => (float)$attr->purchase_price,
                            'stock' => (int)$attr->stock,
                            'no' => $attr->no ?? '',
                            'weight' => (float)($attr->weight ?? 0),
                            'cover_pic' => $attr->cover_pic ?? '',
                            'specs' => is_array($specs) ? $specs : [],
                        ];
                    }
                }
            }

            // [核心] 回显商户店铺专属分类挂载数据
            $detail['mch_cats'] = MchGoodsCatRel::find()
                ->where(['goods_id' => $goods->id, 'is_delete' => 0])
                ->select('cat_id')
                ->column() ?: [];

            unset($detail['attrs'], $detail['info']);
        }

        // 子类钩子：在「编辑」和「新建」两种场景都触发
        // 让插件 (例如 mch 选择器) 在新建商品时也能挂载默认 plugins_data
        $this->enrichDetailHook($detail, $goods);

        if ($goods && $this->similar) {
            $this->applySimilarReset($detail);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            $this->getDetailResponseKey() => $detail
        ]);
    }

    /**
     * 「发布相似商品」:把编辑回显的数据整理成一份能当新商品提交的拷贝。
     *
     * 内容(名称 / 图片 / 详情 / 分类 / 规格 / 价格 / 运费 / 会员价 / 限购 / 各插件配置)原样保留;
     * 身份和运行数据一律去掉,否则一保存就改到原商品、或带着原商品的销量 / 货号 / 规格 ID 过去:
     *  - 商品 id、各规格 id → 去掉(保存走新建);库存快照 / 编辑令牌 → 重新发,快照空(新规格没有「打开页面那一刻」)
     *  - 货号 no、规格货号 → 清空(货号要唯一);销量 sales → 0
     *  - 状态 → 下架(复制出来先不上架,运营改完自己上);平台商品库关联、创建 / 修改时间 → 去掉
     */
    protected function applySimilarReset(array &$detail): void
    {
        foreach (['id', 'created_at', 'updated_at', 'deleted_at', 'created_time', 'updated_time', 'deleted_time',
                     'platform_goods_id', 'auto_off_at'] as $k) {
            unset($detail[$k]);
        }
        $detail['no'] = '';
        $detail['sales'] = 0;
        $detail['status'] = 0;
        $detail['stock_snapshot'] = (object)[];
        $detail['stock_edit_token'] = \app\helpers\EditOnceHelper::issue();
        if (!empty($detail['specs_data']['sku_list']) && is_array($detail['specs_data']['sku_list'])) {
            foreach ($detail['specs_data']['sku_list'] as &$sku) {
                unset($sku['id']);
                $sku['no'] = '';
            }
            unset($sku);
        }
    }

    /**
     * 保存商品 (创建/编辑)
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mch = $this->resolveMch();

        // 并发创建锁
        if (!$this->id && !Yii::$app->request->isConsoleRequest) {
            $lockKey = $this->getLockKey();
            if ($lockKey && !LockHelper::lock($lockKey, 10)) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '操作过于频繁，请稍后再试');
            }
        }

        // 注：cat_id 校验由 GoodsValidatorPipe 接管（它会按 type 智能跳过 lucky/integral/voucher 等无分类商品）
        //     此处不再硬编码 required，避免对平台级商品造成误拦

        // ==========================================
        // 【核心兜底】彻底抛弃前端 is_attr 依赖！
        // 发现规格为空，自动补全严格符合前端数据结构的虚拟默认规格
        // ==========================================
        $normalized = GoodsService::normalizeSpecs(
            $this->id,
            $this->specs_data,
            $this->price,
            $this->cost_price,
            $this->stock,
            0, // 没有基础 weight
            '',
            $this->cover_pic,
            (float) $this->purchase_price
        );
        $this->specs_data = $normalized['specs_data'];
        $this->is_attr = $normalized['is_attr'];

        $transaction = Yii::$app->db->beginTransaction();
        $context = null;
        try {
            $goods = Goods::findOne($this->buildOwnedGoodsConditions());

            $isEditing = (bool)$goods;
            $needsRecheck = $isEditing && $goods->is_check == 1 && $this->shouldTriggerRecheckOnEdit();
            $isPlatformGoods = $isEditing && $goods->platform_goods_id > 0;

            if (!$goods) {
                $goods = new Goods();
                $this->applyCreateAttributes($goods);
            }

            // 初始化管道上下文载体
            $context = new GoodsContext();
            $context->form = $this;
            $context->goods = $goods;
            $context->mch = $mch;
            $context->isEditing = $isEditing;
            $context->needsRecheck = $needsRecheck;
            $context->isPlatformGoods = $isPlatformGoods;
            $context->goodsType = $this->getSaveGoodsType();

            // 子类钩子：追加 Context 字段
            $this->buildContextHook($context);

            // 执行 Pipeline (mch_cats 由 GoodsBaseSavePipe 自动持久化，无需在此重复处理)
            $pipeline = new Pipeline();
            $pipeline->send($context)->through($this->getPipes())->then(function ($ctx) {
                return true;
            });

            // 子类钩子：附表持久化（插件专属模型）
            $this->afterSaveHook($context, $goods);

            $transaction->commit();

            // 只有商品真的处在待审核时才让人「等审核」:平台后台改已审核的商品,原来也提示等审核,其实什么都没变
            if ($needsRecheck) {
                $successMessage = '修改成功，商品已进入待审核状态';
            } elseif ((int) $goods->is_check === 1) {
                $successMessage = '保存成功';
            } else {
                $successMessage = '保存成功，请等待平台审核';
            }
            return $this->apiResponse(ApiCode::CODE_SUCCESS, $successMessage);
        } catch (\Exception $e) {
            Yii::warning($e->getMessage());
            $transaction->rollBack();
            // 这次没存成:把库存编辑令牌放回去,用户改完重试时差额还能生效
            \app\helpers\EditOnceHelper::release($context ? $context->stockEditClaim : null);
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    public function delete()
    {
        $conditions = $this->buildOwnedGoodsConditions();
        unset($conditions['is_delete']); // delete 时允许查到已删除的也无所谓，按 id+mch+type 定位即可
        $goods = Goods::findOne($conditions);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或无权操作当前类型商品');
        }
        if (OrderBehaviorRegistry::isGoodsProtected((string)$goods->type)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '默认收银商品不可操作');
        }
        $goods->is_delete = 1;
        $goods->deleted_at = time();
        if (!$goods->save()) {
            return $this->responseErrorMsg($goods);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }

    public function updateStatus()
    {
        $goods = Goods::findOne($this->buildOwnedGoodsConditions());
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或操作类型不匹配');
        }
        if (OrderBehaviorRegistry::isGoodsProtected((string)$goods->type)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '默认收银商品不可操作');
        }

        // 移除 is_check 拦截机制，允许未审核商品先"标称上架"但客户端不显示
        $goods->status = $this->status;
        if (!$goods->save()) {
            return $this->responseErrorMsg($goods);
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
    }

    /**
     * 批量上/下架。
     * 与 updateStatus 同款所有权作用域(resolveMch() 当前商户 + getFormGoodsType())，
     * 只影响调用方名下、本类型、未删除且非默认收银的商品，绝不越权碰他人商品。
     *
     * @param array    $ids    商品 id 列表
     * @param int|bool $status 1=上架 0=下架
     */
    public function batchUpdateStatus(array $ids, $status)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请先选择商品');
        }
        $status = (int)$status ? 1 : 0;

        $mch = $this->resolveMch();
        $query = Goods::find()->where([
            'id' => $ids,
            'mch_id' => $mch ? $mch->id : 0,
            'is_delete' => 0,
            'type' => $this->getFormGoodsType(),
        ]);
        // 受保护商品类型(如默认收银商品)由插件 getOrderBehaviors().goods_protected 声明。
        // 空数组时必须跳过 NOT IN —— 不同 Yii 版本对 `not in []` 的处理不一致。
        $protectedTypes = OrderBehaviorRegistry::keysWith('goods_protected');
        if ($protectedTypes) {
            $query->andWhere(['not in', 'type', $protectedTypes]);
        }
        $goodsList = $query->all();

        if (empty($goodsList)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '没有可操作的商品');
        }

        $ok = 0;
        foreach ($goodsList as $goods) {
            $goods->status = $status;
            if ($goods->save()) {
                $ok++;
            }
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, ($status ? '上架' : '下架') . "成功 {$ok} 件");
    }
}
