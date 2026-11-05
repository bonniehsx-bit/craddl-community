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

namespace app\models;

use app\helpers\CacheHelper;
use app\helpers\JobHelper;
use app\plugins\mch\models\Mch;
use Yii;
use yii\helpers\Json;

/**
 * This is the model class for table "{{%goods}}".
 *
 * @property int $id
 * @property string $name 商品名称
 * @property string $factory 生产厂家
 * @property float $price 商品售价
 * @property float $origin_price 商品划线原价
 * @property string|null $no 商品编号
 * @property int $status 状态: 1=上架, 0=下架
 * @property string $cover_pic 商品缩略图
 * @property string $unit 商品单位 (如:件,个)
 * @property int $is_limit 是否开启限购: 1=开启, 0=关闭
 * @property float $weight 商品重量(克)
 * @property int $is_delete 是否删除: 1=已删除, 0=未删除
 * @property int $deleted_at 删除时间戳
 * @property int $updated_at 更新时间戳
 * @property int $created_at 创建时间戳
 * @property float $cost_price 成本价
 * @property float $purchase_price 采购价（商家拿货价）
 * @property int $mall_id 商城ID
 * @property int $is_attr 是否启用多规格: 1=开启, 0=关闭
 * @property int $mch_id 商户ID (0=平台自营)
 * @property int|null $stock 商品总库存
 * @property int $is_area_limit 是否开启区域限购
 * @property int $sales 实际销量
 * @property int $virtual_sales 虚拟销量
 * @property string $share_title 分享标题
 * @property int $sort 排序 (数字越小越靠前)
 * @property int $is_recommend 是否推荐: 1=是, 0=否
 * @property int $freight_id 运费模板ID
 * @property int $express_type 运费计算规则
 * @property float $freight_price 统一运费金额
 * @property GoodsInfo $info 商品详情关联表
 * @property int $use_integral_type 积分抵扣类型: 0=固定抵扣, 1=按比例抵扣
 * @property float $use_integral 需要消耗的积分数量(或抵扣比例)
 * @property float $integral_price 积分可抵扣的金额
 * @property int $is_integral 是否开启积分抵扣: 1=开启, 0=关闭
 * @property int $is_negotiable 是否面议: 1=是, 0=否
 * @property string $subtitle 商品副标题
 * @property integer $limit_num 限购数量 (0为不限制)
 * @property integer $is_member_price 是否开启会员价: 1=开启, 0=关闭
 * @property integer $total_stock 总库存缓存
 * @property integer $warning_stock 预警库存
 * @property integer $is_special_price 是否特价
 * @property string $property 商品属性参数(JSON字符串)
 * @property integer $cat_id 商品分类ID
 * @property integer $is_check 审核状态
 * @property string $attr_group_name 规格组名称
 * @property integer $is_auto_complete 是否自动完成订单
 * @property float $send_integral 赠送积分数量
 * @property int $is_refund 是否支持退款: 1=支持, 0=不支持
 * @property int $platform_goods_id 关联的平台商品库ID
 * @property string $type 商品类型 (normal, hotel, etc.)
 * @property int $is_purchase 是否加入采购池
 * @property int $is_from_supply_chain 是否来自供应链
 * @property float $concession_rate 让利比例
 */
class Goods extends BaseActiveRecord
{
    /**
     * 库存预警阈值 —— 低于这个数算「缺货」。
     *
     * 必须只有这一个来源:后台首页的「库存预警」卡片和商品列表的低库存筛选
     * 各写一个 10 的话,点卡片进列表数量对不上,而且没人会想到去比这两个数。
     */
    const LOW_STOCK_THRESHOLD = 10;

    const TYPE_CASHIER = 'cashier';
    const TYPE_NORMAL = 'normal';
    const TYPE_HOTEL = 'hotel';
    const TYPE_VOUCHER = 'voucher';
    const TYPE_BS = 'bs';
    const EVENT_EDIT = 'goods.edit';

    const STATUS_UP = 1;
    const STATUS_DOWN = 0;
    const STATUS_CHECK = 2;

    /**
     * auto_off_at 的哨兵值:运营在系统自动下架后又手动上架过 → 豁免售罄自动下架。
     * 语义见 cron/GoodsDownTask(0=归自动管理 / >0=自动下架时间戳 / -1=豁免)。
     */
    const AUTO_OFF_EXEMPT = -1;

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%goods}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['name', 'cover_pic', 'deleted_at', 'updated_at', 'created_at', 'mall_id'], 'required'],
            [['price', 'send_integral', 'origin_price', 'weight', 'cost_price', 'purchase_price', 'freight_price', 'integral_price', 'use_integral', 'concession_rate'], 'number'],


            [[
                'status',
                'is_purchase',
                'is_from_supply_chain',
                'platform_goods_id',
                'is_refund',
                'is_auto_complete',
                'cat_id',
                'total_stock',
                'is_check',
                'is_special_price',
                'warning_stock',
                'is_member_price',
                'limit_num',
                'use_integral_type',
                'send_integral_type',
                'send_integral_timing',
                'is_negotiable',
                'is_integral',
                'is_recommend',
                'freight_id',
                'express_type',
                'is_limit',
                'is_delete',
                'deleted_at',
                'updated_at',
                'created_at',
                'mall_id',
                'is_attr',
                'mch_id',
                'stock',
                'is_area_limit',
                'sales',
                'virtual_sales',
                'sort',

            ], 'integer'],

            [['name', 'no', 'share_title', 'property', 'factory', 'short_name'], 'string', 'max' => 255],
            [['cover_pic', 'subtitle'], 'string', 'max' => 255],
            [['unit'], 'string', 'max' => 10],

            [['is_from_supply_chain'], 'default', 'value' => 0],
            // send_integral_type/timing 是 NOT NULL 列(默认0):新建商品未传时必须归0,否则插 NULL 触发约束违反。
            // send_integral 列可空,但一并兜底为0更干净。
            [['send_integral', 'send_integral_type', 'send_integral_timing'], 'default', 'value' => 0],
            [['attr_group_name', 'type'], 'string', 'max' => 45],

        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'platform_goods_id' => '平台商品ID',
            'name' => '商品名',
            'factory' => '生产厂家',
            'price' => '商品价格',
            'origin_price' => '商品原价',
            'no' => '商品编号',
            'status' => '状态',
            'cover_pic' => '缩略图',
            'unit' => '单位',
            'is_limit' => '是否开启区域限购',
            'weight' => '重量',
            'is_delete' => '是否删除',
            'deleted_at' => '删除时间',
            'updated_at' => '更新时间',
            'created_at' => '创建时间',
            'cost_price' => '原价',
            'purchase_price' => '采购价',
            'mall_id' => '商城ID',
            'is_attr' => '是否使用多规格',
            'mch_id' => '商户ID',
            'stock' => '库存',
            'is_area_limit' => '开启区域限购',
            'sales' => '销量',
            'share_title' => '分享标题',
            'sort' => '排序',
            'is_recommend' => '是否推荐',
            'express_type' => '快递类型',
            'freight_price' => '运费',
            'freight_id' => '运费模板',
            'integral_price' => '抵扣金额',
            'use_integral' => '使用积分',
            'is_integral' => '使用积分抵扣',
            'use_integral_type' => '抵扣类型',
            'is_negotiable' => '是否开启面议',
            'subtitle' => '商品小标题',
            'limit_num' => '限购数量',
            'is_member_price' => '是否启用会员价',
            'warning_stock' => '预警库存',
            'total_stock' => '总库存',
            'is_special_price' => '特价商品',
            'cat_id' => '分类ID',
            'is_refund' => '支持退款',
            'type' => '商品类型',
            'is_from_supply_chain' => '是否来自供应链',
            'is_purchase' => '是否支持采购',
            'concession_rate' => '让利比例'
        ];
    }

    public function getCatList()
    {
        // goods_cat商品分类存的是倒序 顶级分类的level最小
        return GoodsCat::find()->alias('gc')
            ->where(['gc.is_delete' => 0, 'gc.goods_id' => $this->id])
            ->leftJoin(['c' => Cat::tableName()], 'c.id=gc.cat_id')
            ->select('gc.cat_id as id,c.name')
            ->orderBy('gc.level DESC')
            ->asArray()
            ->all();
    }

    public function getInfo()
    {
        return $this->hasOne(GoodsInfo::class, ['goods_id' => 'id']);
    }

    public function getAttrs()
    {
        return $this->hasMany(Attr::class, ['goods_id' => 'id']);
    }

    public function getLevel()
    {
        return $this->hasOne(Level::class, ['id' => 'level_id']);
    }

    public static function getBaseInfo($id)
    {
        $goods = CacheHelper::get('mall_goods_base_' . $id);
        if ($goods) {
            return $goods;
        }
        $goods = self::find()->where(['is_delete' => 0, 'id' => $id])->select('id,cover_pic,name,price')->one();
        if (!$goods) {
            return null;
        }
        CacheHelper::set('mall_goods_base_' . $id, $goods);
        return $goods;
    }

    public function getLevelPrice($level_id = null)
    {
        return $this->applyLevelDiscountToPrice($this->price, $level_id);
    }

    public function getLevelPriceByAttr($level_id = null, $price = 0)
    {
        return $this->applyLevelDiscountToPrice($price, $level_id);
    }

    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        // 只有在 cat_id 真的发生变化时才执行
        if ($insert || (isset($changedAttributes['cat_id']) && $this->cat_id != $changedAttributes['cat_id'])) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                GoodsCat::deleteAll(['goods_id' => $this->id]);
                if ($this->cat_id) {
                    $level = 1;
                    $currentCatId = $this->cat_id;

                    while ($currentCatId > 0) {
                        $exists = GoodsCat::find()->where(['goods_id' => $this->id, 'cat_id' => $currentCatId])->exists();
                        if ($exists) {
                            Yii::warning("GoodsCat relation already exists for goods_id: {$this->id}, cat_id: {$currentCatId}. Breaking loop.");
                            break;
                        }

                        $goodsCat = new GoodsCat();
                        $goodsCat->mall_id = $this->mall_id;
                        $goodsCat->goods_id = $this->id;
                        $goodsCat->cat_id = $currentCatId;
                        $goodsCat->level = $level;

                        if (!$goodsCat->save()) {
                            throw new \Exception("GoodsCat save failed: " . $this->getErrorMsg($goodsCat));
                        }

                        // 向上查找父级
                        $parentCat = Cat::findOne($currentCatId);
                        if ($parentCat && $parentCat->parent_id > 0) {
                            $currentCatId = $parentCat->parent_id;
                            $level++;
                        } else {
                            $currentCatId = 0;
                        }
                    }
                }
                $transaction->commit();
            } catch (\Exception $e) {
                $transaction->rollBack();
                Yii::error("Failed to update GoodsCat relations for goods_id: {$this->id}. Error: " . $e->getMessage());
            }
        }
    }


    public function beforeSave($insert)
    {
        if ($insert) {
            $this->no = 'JG' . intval(bcmul(microtime(true), 10000)) . rand(1000, 9999);
        }

        // 库存为 0 还要上架 —— 预售、按需备货、不管理库存的服务类商品、供应链代发……
        // 这是运营的明确意图,记下豁免,别让 GoodsDownTask 两分钟后把它打回去。
        // 收在 beforeSave 而不是各个上下架表单里:走 AR 的入口(商品编辑、批量上下架、
        // 插件自建商品、GoodsUpDownJob 定时上架)一次覆盖完,将来加新入口也不会漏。
        //
        // ⚠ 必须限定「本次 save 真的动了 status 或 stock」,不能只看当前值。
        //   真实的卖光走 Goods::updateAllCounters(乐观锁),不经过 AR,库存归零后
        //   auto_off_at 还是 0，等着 cron 来下架。若这里不加改动判定，那么在
        //   「卖光」到「cron 跑」之间的两分钟窗口里，**任何**一次 save(后台改个
        //   商品名、插件监听 goods.edit 后回存……)都会把它误判成运营意图，
        //   商品就此永久豁免、再也不会自动下架。
        //
        // 三种该豁免的情形，都落在这个判定里:
        //   · 新建商品，库存 0 且直接上架(预售)
        //   · 上架动作本身(status 0→1)，而库存是 0
        //   · 运营手动把库存改成 0 却保持上架 —— 与「被订单卖光」的区别正在于此
        //
        // 只在 stock<=0 时标记:有库存的商品本就不会被下架,标了反而要等 cron 清回去,徒增写入。
        // 「动没动」要宽松比较(第二个参数 false):后台表单是 qs 编码提交的,status 传过来是字符串 "1",
        // 库里是整数 1,Yii 默认的严格比较会判成「改了」—— 被订单卖光的商品,运营只改个名字也会被记成豁免。
        if ((int)$this->status === self::STATUS_UP
            && (int)$this->stock <= 0
            && ($insert || $this->isAttributeChanged('status', false) || $this->isAttributeChanged('stock', false))
        ) {
            $this->auto_off_at = self::AUTO_OFF_EXEMPT;
        }

        return parent::beforeSave($insert);
    }

    public function getCat()
    {
        return $this->hasOne(Cat::class, ['id' => 'cat_id']);
    }

    public function getFreight()
    {
        return $this->hasOne(FreightTpl::class, ['id' => 'freight_id']);
    }

    /**
     * 统一的会员价计算逻辑
     * 架构升级：全量启用统一SKU折扣模式（已彻底废弃固定金额模式）
     * 优先级：商品独立配置的折扣 > 会员等级全局折扣 > 原价
     *
     * @param float|string $basePrice 基础价格 (通常传入具体 SKU 的售价)
     * @param int|null $level_id 用户当前的等级ID
     * @return string 计算后的最终价格 (保留2位小数)
     */
    public function applyLevelDiscountToPrice($basePrice, $level_id)
    {
        // 0. 插件定价钩子：允许插件（如品牌代理）覆盖价格计算
        // 插件通过 GoodsListener 注册 EVENT_CALC_PRICE 事件，返回自定义价格
        $pluginPrice = $this->applyPluginPrice($basePrice);
        if ($pluginPrice !== null) {
            return $pluginPrice;
        }

        // 1. 基础校验：未开启会员价或价格无效，直接返回原价
        if (!$this->is_member_price || (float)$basePrice <= 0) {
            return (string)$basePrice;
        }
        // 2. 获取用户等级信息(请求级缓存:同一等级在商品列表里被反复计算时,避免每商品各查一次 Level)
        $userLevel = $level_id ? self::resolveLevelCached($level_id) : null;
        if (!$userLevel) {
            return (string)$basePrice;
        }
        $levelPrices = $this->info && $this->info->level_price ? Json::decode($this->info->level_price) : [];
        if (!is_array($levelPrices)) {
            $levelPrices = [];
        }
        // 4. 【核心逻辑】优先匹配商品独立的等级折扣设置
        foreach ($levelPrices as $setting) {
            if (isset($setting['level_id']) && $setting['level_id'] == $userLevel->id) {
                if (isset($setting['discount']) && is_numeric($setting['discount'])) {
                    $discount = (float)$setting['discount'];
                    // 确保折扣在合法范围内 0.1 ~ 10 折
                    if ($discount > 0 && $discount <= 10) {
                        // ratio 取 6 位小数(原来是 4 位)。4 位挡不住常见的"想卖某个整数价"：
                        // 299 想卖 199 需要 ratio≈0.6655518，4 位只能取 0.6655(得 198.98)
                        // 或 0.6656(得 199.01)，199.00 卡在两者之间，**无论折扣填什么都到不了**。
                        // 6 位后可精确命中，且对 4 位以内的旧折扣(9.5/8.8 这类)结果完全不变。
                        $ratio = bcdiv((string)$discount, '10', 6);
                        return bcmul((string)$basePrice, $ratio, 2);
                    }
                }
                // 如果商品单独设置了该等级，但未填折扣或无效，则跳出寻找全局兜底
                break;
            }
        }

        // 5. 【兜底逻辑】使用当前会员等级自带的全局折扣
        if ($userLevel->discount > 0 && $userLevel->discount < 10) {
            $ratio = bcdiv((string)$userLevel->discount, '10', 6);   // 精度同上，两处必须一致
            return bcmul((string)$basePrice, $ratio, 2);
        }

        // 6. 均无有效折扣，返回原价
        return (string)$basePrice;
    }

    /**
     * 请求级 Level 缓存。会员价计算在一个商品列表里会对同一用户等级反复调用,
     * 每次 Level::findOne 都打一次库(典型 N+1)。此处按 level_id 缓存本请求已查过的 Level。
     * @param int $levelId
     * @return Level|null
     */
    private static $_levelCache = [];
    private static function resolveLevelCached($levelId)
    {
        $levelId = (int)$levelId;
        if (!array_key_exists($levelId, self::$_levelCache)) {
            self::$_levelCache[$levelId] = Level::findOne($levelId);
        }
        return self::$_levelCache[$levelId];
    }

    public function getGoodsCat()
    {
        return $this->hasOne(GoodsCat::class, ['goods_id' => 'id']);
    }

    public function getMch()
    {
        return $this->hasOne(Mch::class, ['id' => 'mch_id']);
    }

    /**
     * 插件定价钩子
     * 插件可通过注册 Goods::EVENT_CALC_PRICE 事件来覆盖商品价格
     * 返回 null 表示不干预，交给标准会员价逻辑处理
     * 返回 string 表示插件接管了定价
     */
    const EVENT_CALC_PRICE = 'goods.calcPrice';

    private $_pluginPriceResult = null;

    protected function applyPluginPrice($basePrice)
    {
        if (!Yii::$app->user || Yii::$app->user->isGuest) {
            return null;
        }

        $this->_pluginPriceResult = null;

        $event = new \app\events\GoodsPriceEvent([
            'goods' => $this,
            'basePrice' => $basePrice,
            'userId' => Yii::$app->user->id,
        ]);

        Yii::$app->trigger(self::EVENT_CALC_PRICE, $event);

        if ($event->finalPrice !== null) {
            return (string)number_format($event->finalPrice, 2, '.', '');
        }

        return null;
    }
}
