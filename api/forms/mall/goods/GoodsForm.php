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

namespace app\forms\mall\goods;

use app\core\ApiCode;
use app\events\GoodsEvent;
use app\forms\goods\BaseGoodsEditForm;
use app\models\Goods;
use app\models\GoodsInfo;
use app\services\goods\pipeline\GoodsContext;
use Yii;
use yii\db\ActiveQuery;
use yii\helpers\Json;

/**
 * 平台后台商品编辑表单
 *
 * @description 后台商品编辑表单处理模型，已彻底废弃单规格概念，全量强制启用统一SKU架构
 *
 * 本类继承核心 {@see BaseGoodsEditForm}，复用：
 *  - validate / Pipeline 流程
 *  - normalizeSpecs 规格归一化
 *  - mch_cats 商户专属分类持久化
 *  - 老数据 is_attr=0 兼容升级
 *  - delete / updateStatus 通用流程
 *
 * 平台后台特有的差异由钩子覆盖：
 *  - resolveMch()                  从 form->mch_id 取，可为 null（平台自营）
 *  - getLockKey()                  null（平台运营受信任，不需要锁）
 *  - shouldTriggerRecheckOnEdit()  false（平台编辑不退回审核）
 *  - buildOwnedGoodsConditions()   不限定 mch_id 和 type
 *  - buildDetailQuery()            with('freight') + 不限定 mch_id
 *  - applyCreateAttributes()       新建时只设 mall_id（mch_id 跟随 form）
 *  - getSaveGoodsType()            优先用 form->type（前端可指定 group/voucher 等）
 *  - enrichDetailHook()            触发 GoodsEvent 让插件挂载 plugins_data + 反序列化 mall 独有字段
 *  - buildContextHook()            处理 cat 对象→cat_id、新建时强制 is_check=1
 */
class GoodsForm extends BaseGoodsEditForm
{
    // === 完整的 Goods 字段集（继承父类的同名字段会被自动覆盖）===
    public $factory;
    public $no;
    public $mch_id;
    public $share_title;
    public $weight;
    public $sales;
    public $sort;
    public $is_recommend;
    public $remarks;
    public $limit_area_list;
    public $is_limit;
    public $limit_num;
    public $total_stock;
    public $warning_stock;

    // 积分营销
    public $is_integral;
    public $integral_price;
    public $use_integral;
    public $use_integral_type;
    public $send_integral;
    public $send_integral_type; // 赠送积分方式:0=固定积分(每件) 1=按实付金额比例(%)
    public $send_integral_timing; // 赠送时机:0=订单结算后赠送 1=支付成功即赠送

    // 权限/会员/价格
    public $permission;
    public $is_negotiable;
    public $subtitle;
    public $short_name; // 商品简称(客户定制 xwy-new:仅订单导出用)
    public $source_url; // null 表示旧客户端未传，不覆盖已录入的来源链接
    public $is_member_price;
    public $level_price;
    public $is_special_price;
    public $is_check;
    public $is_refund;

    // 分类对象（前端传 {id, name}，会在 buildContextHook 转成 cat_id）
    public $cat;

    public $concession_rate;
    public $property;

    /**
     * 插件动态数据：前端通过 plugins_data 把各插件 tab 的内容打包传给后端，
     * 各插件监听 GoodsEvent 自行消费
     * @var array
     */
    public $plugins_data;

    /**
     * 前端可指定商品类型，覆盖默认 'normal'
     * 例如 group 商品页传 type='group'，voucher 传 type='voucher'
     * @var string|null
     */
    public $type;

    // === 已废弃或保留兼容的属性 ===
    public $attr_list;
    public $attr_group_list;
    public $attr_group_name;
    public $is_auto_complete;
    public $is_from_supply_chain;
    public $is_purchase;

    public function rules()
    {
        return [
            // ⚠ 本类的 rules() 没有合并 parent::rules(),父类里声明的字段要在这儿再写一遍,
            //   漏了就是「前端传了、后端 attributes 赋值时被静默丢掉」——service_tags 踩过一次。
            [['cat', 'specs_data', 'plugins_data', 'service_tags', 'stock_snapshot', 'stock_edit_token'], 'safe'],
            [['similar'], 'integer'], // 发布相似商品:见 BaseGoodsEditForm::applySimilarReset
            [['type'], 'string', 'max' => 64],
            [['type'], 'default', 'value' => Goods::TYPE_NORMAL],
            [['price', 'concession_rate', 'is_purchase', 'send_integral', 'origin_price', 'weight', 'cost_price', 'purchase_price', 'freight_price', 'integral_price', 'use_integral'], 'number'],
            [
                [
                    'status', 'is_from_supply_chain', 'is_refund', 'is_auto_complete',
                    'is_special_price', 'total_stock', 'warning_stock', 'limit_num',
                    'is_check', 'is_member_price', 'is_limit', 'is_attr',
                    'mch_id', 'stock', 'id', 'sales', 'virtual_sales',
                    'sort', 'is_recommend', 'express_type', 'freight_id',
                    'use_integral_type', 'send_integral_type', 'send_integral_timing', 'is_integral', 'is_negotiable', 'cat_id'
                ],
                'integer'
            ],
            [['name', 'no', 'share_title', 'cover_pic', 'subtitle', 'short_name'], 'string', 'max' => 255],
            [['source_url'], 'trim', 'skipOnEmpty' => true],
            [['source_url'], 'string', 'max' => 2048],
            [['source_url'], 'validateSourceUrl'],
            [['remarks', 'factory'], 'string', 'max' => 128],
            [['detail'], 'string'],
            [['property', 'limit_area_list', 'pic_list', 'permission', 'level_price'], 'safe'],
            [['unit'], 'string', 'max' => 10],
            [['mch_id', 'use_integral', 'is_limit', 'status', 'weight', 'limit_num', 'origin_price', 'cost_price', 'purchase_price'], 'default', 'value' => 0],
            [['unit'], 'default', 'value' => '件'],
            [
                ['price'], 'default', 'value' => 0,
                'when' => function ($model) {
                    return $model->is_negotiable;
                }
            ],
            [['pic_list', 'detail', 'limit_area_list'], 'trim'],
            [['limit_area_list', 'detail', 'pic_list'], 'default', 'value' => null],
            [['cover_pic'], 'required', 'message' => '缩略图不能为空'],
            [['name'], 'required', 'message' => '商品名称不能为空'],
            [['price'], 'number', 'min' => 0, 'message' => '价格不能小于0'],
            [['pic_list'], 'required', 'message' => '请上传商品轮播图'],
            [['integral_price'], 'validateIntegralPrice'],
            [['specs_data'], 'validateSkuList'],
            [['is_from_supply_chain'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'cover_pic' => '缩略图',
            'name' => '名称',
            'source_url' => '来源链接',
            'is_limit' => '是否开启限购',
            'pic_list' => '商品轮播图',
            'is_recommend' => '是否推荐',
            'remarks' => '备注',
            'is_integral' => '开启积分抵扣',
            'integral_price' => '积分抵扣额度',
            'use_integral_type' => '积分抵扣类型',
            'send_integral_type' => '赠送积分方式',
            'send_integral_timing' => '赠送时机',
            'use_integral' => '使用积分',
            'is_negotiable' => '是否开启面议',
            'limit_num' => '限购数量',
            'send_integral' => '赠送积分',
            'is_purchase' => '是否支持采购',
            'is_refund' => '支持退款',
            'concession_rate' => '让利比例',
        ];
    }

    /**
     * 在 validate() 之前把 cat 对象转成 cat_id（前端 CatPicker 传 {id, name}），
     * 这样基类的 cat_id 校验就能通过
     */
    public function beforeValidate()
    {
        if ($this->cat && isset($this->cat['id'])) {
            $this->cat_id = $this->cat['id'];
        }
        return parent::beforeValidate();
    }

    public function validateSourceUrl($attribute, $params)
    {
        $scheme = strtolower((string)parse_url($this->$attribute, PHP_URL_SCHEME));
        if (!filter_var($this->$attribute, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            $this->addError($attribute, '来源链接请填写有效的 http:// 或 https:// 网址');
        }
    }

    public function validateSkuList($attribute, $params)
    {
        // 只有当前端开启多规格模式时才去强校验，否则交由 normalizeSpecs 兜底生成
        if (!empty($this->specs_data['spec_groups']) && empty($this->specs_data['sku_list'])) {
            $this->addError($attribute, '商品规格数据不完整，缺少具体的 SKU 组合列表');
        }
    }

    public function validateIntegralPrice($attribute, $params)
    {
        if ($this->is_integral && $this->use_integral_type == 0) {
            if ($this->integral_price > $this->price) {
                $this->addError($attribute, '抵扣金额不能超过商品售价');
            }
        }
    }

    // ==========================================
    // 钩子覆盖：BaseGoodsEditForm 的差异化点
    // ==========================================

    /**
     * 平台后台代填：mch 由前端传 mch_id 决定（可以为 0=平台自营）
     */
    protected function resolveMch()
    {
        // 平台后台：mch_id=0 表示平台自营商品，不需要 Mch 实例
        return null;
    }

    /**
     * 平台运营受信任，不需要并发锁
     */
    protected function getLockKey(): ?string
    {
        return null;
    }

    /**
     * 平台后台代为编辑不触发重新审核
     */
    protected function shouldTriggerRecheckOnEdit(): bool
    {
        return false;
    }

    /**
     * 平台后台可以编辑任意商户/任意类型的商品（不限 mch_id 和 type）
     */
    protected function buildOwnedGoodsConditions(): array
    {
        return [
            'id' => $this->id,
            'is_delete' => 0,
        ];
    }

    /**
     * getDetail 查询 with 'freight' 关联（mall 端独有）
     */
    protected function buildDetailQuery(): ActiveQuery
    {
        return Goods::find()->with([
            'cat',
            'info',
            'freight',
            'attrs' => function ($query) {
                $query->andWhere(['is_delete' => 0]);
            }
        ])->where([
            'id' => $this->id,
            'is_delete' => 0,
        ]);
    }

    /**
     * 新建商品时只设 mall_id；mch_id 跟随 form->mch_id（GoodsBaseSavePipe 会自动同步）
     */
    protected function applyCreateAttributes(Goods $goods): void
    {
        $goods->mall_id = Yii::$app->mall->id;
    }

    /**
     * 优先使用前端传入的 type 字段（例如 group/voucher/yuda 等插件商品类型）
     */
    protected function getSaveGoodsType(): string
    {
        if ($this->type) {
            return (string)$this->type;
        }
        return parent::getSaveGoodsType();
    }

    /**
     * 平台后台不限定查询类型，所有类型的商品都能编辑
     */
    protected function getFormGoodsType()
    {
        return [Goods::TYPE_NORMAL, ''];
    }

    /**
     * 构造 Pipeline Context 时的钩子：
     * - property 数组拼接成字符串
     * - 积分抵扣类型预处理
     * - 新建时强制 is_check=1（平台自动审核）
     * (cat 对象→cat_id 转换在 beforeValidate 中处理，时序更靠前)
     */
    protected function buildContextHook(GoodsContext $context): void
    {
        // 1. property 数组拼成字符串存入数据库
        if ($this->property && is_array($this->property)) {
            $this->property = implode(',', $this->property);
        }

        // 2. 积分抵扣类型 1 时强制 integral_price = 0
        if ($this->is_integral && $this->use_integral_type == 1) {
            $this->integral_price = 0;
        }

        // 3. 新建商品时平台自动审核通过
        if (!$context->isEditing) {
            $this->is_check = 1;
        }
    }

    /**
     * getDetail 钩子：触发插件事件 + 反序列化 mall 独有 JSON 字段 + 字段兜底
     *
     * 注意：$goods 可能为 null（新建商品场景），插件事件依然要触发——
     *      mch/lucky/integral 等插件靠这个事件挂载默认的 plugins_data tab。
     */
    protected function enrichDetailHook(array &$detail, ?Goods $goods): void
    {
        // 1. 触发插件事件，让各插件挂载 plugins_data
        //    新建商品时 $goods 为 null，插件按"默认值"挂载即可（例如 mch 选择器默认 mch_id=0）
        $event = new GoodsEvent();
        $event->goods = $goods;
        $event->pluginData = [];
        Yii::$app->trigger(GoodsEvent::GOODS_EDIT_FORM_LOAD, $event);
        $detail['plugins_data'] = $event->pluginData;

        // 2. 反序列化 mall 独有的 JSON 字段（存在 GoodsInfo 表）
        $info = $goods ? $goods->info : null;
        // 运营内部资料只在后台编辑接口显式返回，不随 GoodsInfo 序列化下发买家端。
        $detail['source_url'] = $info && $info->hasAttribute('source_url') ? (string)$info->source_url : '';
        $detail['limit_area_list'] = ($info && $info->limit_area_list) ? Json::decode($info->limit_area_list) : [];
        $detail['level_price'] = ($info && $info->level_price) ? Json::decode($info->level_price) : [];
        $detail['permission'] = ($info && $info->permission) ? Json::decode($info->permission) : [];

        // 默认 permission 结构兜底
        if (!isset($detail['permission']['explode_level_ids'])) {
            $detail['permission']['explode_level_ids'] = [];
        }
        if (!isset($detail['permission']['buy_level_ids'])) {
            $detail['permission']['buy_level_ids'] = [];
        }

        // 3. property 字符串拆数组（数据库存逗号分隔字符串）
        if (isset($detail['property']) && is_string($detail['property'])) {
            $detail['property'] = explode(',', $detail['property']);
        }
        $detail['property'] = (isset($detail['property']) && is_array($detail['property']))
            ? array_filter($detail['property'])
            : [];

        // 4. 积分字段默认值兜底
        if (!isset($detail['is_integral'])) {
            $detail['is_integral'] = 0;
        }
        if (!isset($detail['use_integral_type'])) {
            $detail['use_integral_type'] = 0;
        }
    }

    // ==========================================
    // 兼容老控制器的接口名：search() 是历史名字，新代码用 getDetail()
    // ==========================================

    /**
     * @deprecated 历史 controller 调用的方法名。新代码请直接调 getDetail()。
     */
    public function search()
    {
        return $this->getDetail();
    }

    /** 来源链接与商品编辑同事务保存；旧客户端未传时保留原值。 */
    protected function afterSaveHook(GoodsContext $context, Goods $goods): void
    {
        if ($this->source_url === null) return;
        $info = GoodsInfo::findOne(['mall_id' => $goods->mall_id, 'goods_id' => $goods->id, 'is_delete' => 0]);
        if (!$info) {
            throw new \RuntimeException('商品详情不存在或无权操作');
        }
        if (!$info->hasAttribute('source_url')) {
            if ($this->source_url === '') return;
            throw new \RuntimeException('请先执行商品来源链接数据库迁移');
        }
        $info->source_url = $this->source_url;
        if (!$info->save(false, ['source_url'])) {
            throw new \RuntimeException('来源链接保存失败');
        }
    }

    // ==========================================
    // mall 端独有的快捷动作（不在 BaseGoodsEditForm 接口里）
    // ==========================================

    public function delete()
    {
        $goods = Goods::findOne([
            'id' => $this->id,
            'is_delete' => 0,
            'mall_id' => Yii::$app->mall->id,
        ]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }

        $goods->is_delete = 1;
        $goods->deleted_at = time();

        if (!$goods->save(false)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '删除失败', ['error' => $goods->getErrors()]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '删除成功');
    }

    public function statusChange()
    {
        $goods = Goods::findOne([
            'id' => $this->id,
            'is_delete' => 0,
            'mall_id' => Yii::$app->mall->id,
        ]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }
        $goods->status = $this->status;
        if (!$goods->save(false)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '上架失败', ['error' => $goods->getErrors()]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '上架成功');
    }

    public function recommendChange()
    {
        $goods = Goods::findOne([
            'id' => $this->id,
            'is_delete' => 0,
            'mall_id' => Yii::$app->mall->id,
        ]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }
        $goods->is_recommend = $this->is_recommend;
        if (!$goods->save()) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '操作失败', ['error' => $goods->getErrors()]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
    }

    public function check()
    {
        $goods = Goods::findOne([
            'id' => $this->id,
            'is_delete' => 0,
            'mall_id' => Yii::$app->mall->id,
        ]);
        if (!$goods) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商品不存在或已被删除');
        }
        $goods->is_check = $goods->is_check ? 0 : 1;
        if (!$goods->save(false)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '操作失败', ['error' => $goods->getErrors()]);
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
    }
}
