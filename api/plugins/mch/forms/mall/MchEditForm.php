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

namespace app\plugins\mch\forms\mall;

use app\core\ApiCode;
use app\events\ImPushEvent;
use app\events\WxNotifyEvent;
use app\helpers\MessageHelper;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\models\User;
// ⚠ 不要 `use app\plugins\im\helpers\ImHelper;`
//   mch 是基础设施，反向 use 业务插件 im 会导致 im 卸载后 mch 整个崩
//   下面 IM 推送语句用 FQN + class_exists 守卫，im 没装时跳过 IM 渠道
use app\plugins\mch\events\MchEvent;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Yii;
use yii\helpers\Json;

/**
 * Class MchEditForm
 * @package app\plugins\mch\forms\mall
 *
 * [最终版] 负责后台对商户的创建、编辑、详情获取和审核。
 * [优化] 重构了店长(管理员)的设置逻辑，消除冗余，提升健壮性。
 */
class MchEditForm extends BaseModel
{

    // --- 接收前端 form 对象的所有字段 ---
    public $id;
    public $user_id;

    // 基础信息
    public $mch_name;
    public $logo;
    public $tel;
    public $mobile;
    public $type;
    public $opening_hours;
    public $banner_images;
    public $description;
    public $cat_id;

    // 地址信息
    public $address;
    public $province;
    public $city;
    public $county;
    public $town;
    public $province_code;
    public $city_code;
    public $county_code;
    public $town_code;
    public $latitude;
    public $longitude;

    // 分润与业务


    public $delivery_options;
    public $pickup_discount_rate;
    public $pickup_discount_max_amount;
    public $concession_rate; // 平台抽点/让利比例(商户级通用费率);下单时商品无独立设置则用它
    public $city_delivery_distance;
    public $city_delivery_config;
    
    // 招商分销
    
    public $floor;


    // 资质信息
    public $business_license;
    public $legal_person_name;
    public $id_card_front;
    public $id_card_back;
    // 审核专用字段
    public $status;
    public $remarks;
    public $is_first_order_discount_enabled;
    public $first_order_discount_amount;
    public $first_order_discount_total_stock;
    /**
     * 打开编辑页时的首单剩余名额(getDetail 下发,前端表单原样带回)。
     * 保存时名额按「表单值 − 它」的差额原子加减,不再整值写回 ——
     * 名额是下单时实时扣的(FirstOrderQuotaService),打开页面到点保存之间扣掉的会被加回来。
     */
    public $first_order_stock_snapshot;
    /** 这个编辑页的一次性令牌(和快照一起下发、带回):同一页面重复提交时名额差额只生效一次 */
    public $first_order_edit_token;
    public $first_order_discount_start_time;
    public $first_order_discount_end_time;

    public $application_id;
    public $other_platform_user_id;
    public $wechat_mchid;
    public $wechat_shop_number;

    /**
     * @return array
     * 重构后的验证规则
     */
    public function rules()
    {
        return [
            [['type', 'user_id', 'mch_name', 'mobile', 'legal_person_name', 'id_card_front', 'id_card_back'], 'required'],
            [['logo', 'cat_id', 'business_license'], 'required', 'when' => function ($model) {
                return $model->type != Mch::TYPE_INDIVIDUAL;
            }, 'message' => '{attribute}不能为空'],
            
            [['opening_hours', 'latitude', 'longitude', 'address', 'province', 'city', 'county'], 'required', 'when' => function ($model) {
                return $model->type == Mch::TYPE_DOWN_STORE;
            }, 'message' => '线下商家请补全{attribute}'],

            // --- 类型与格式验证 ---
            [['id', 'type', 'user_id', 'cat_id', 'status', 'province_code', 'city_code', 'county_code', 'town_code', 'first_order_discount_total_stock', 'first_order_stock_snapshot'], 'integer'],
            [['first_order_edit_token'], 'string', 'max' => 64],
            [['latitude', 'longitude', 'pickup_discount_rate', 'pickup_discount_max_amount', 'city_delivery_distance', 'first_order_discount_amount'], 'number'],
            [['mch_name', 'logo', 'tel', 'address', 'opening_hours', 'business_license', 'legal_person_name', 'id_card_front', 'id_card_back', 'remarks', 'floor'], 'string', 'max' => 255],
            [['province', 'city', 'county', 'town'], 'string', 'max' => 45],
            [['description'], 'string'],
            ['mobile', 'match', 'pattern' => '/^1[3456789]\d{9}$/', 'message' => '请输入有效的手机号码。'],
            [['first_order_discount_start_time', 'first_order_discount_end_time'], 'datetime', 'format' => 'php:Y-m-d H:i:s'],

            // --- 范围与值验证 ---
            [['user_id'], 'integer', 'min' => 1, 'tooSmall' => '{attribute} 无效，请重新选择。'],
            [['cat_id'], 'integer', 'min' => 1, 'when' => function ($model) {
                return $model->type != Mch::TYPE_INDIVIDUAL;
            }, 'tooSmall' => '{attribute} 无效，请重新选择。'],
            [['is_first_order_discount_enabled'], 'in', 'range' => [0, 1]],
            [['pickup_discount_rate'], 'number', 'min' => 0, 'max' => 100],
            [['concession_rate'], 'number', 'min' => 0, 'max' => 100], // 平台抽点率 0-100%
            [['pickup_discount_max_amount', 'first_order_discount_amount'], 'number', 'min' => 0],
            [['first_order_discount_total_stock'], 'integer', 'min' => 0],
            ['first_order_discount_end_time', 'compare', 'compareAttribute' => 'first_order_discount_start_time', 'operator' => '>=', 'message' => '结束时间必须大于或等于开始时间。', 'when' => function ($model) {
                return $model->first_order_discount_start_time && $model->first_order_discount_end_time;
            }],
            // --- 条件验证 (当开启首单优惠时，相关字段必填) ---
            [['first_order_discount_amount', 'first_order_discount_total_stock', 'first_order_discount_start_time', 'first_order_discount_end_time'], 'required', 'when' => function ($model) {
                return $model->is_first_order_discount_enabled == 1;
            }],

            // --- 安全与默认值 ---
            [['banner_images', 'delivery_options', 'city_delivery_config', 'application_id', 'other_platform_user_id', 'wechat_mchid', 'wechat_shop_number'], 'safe'],
            [['status', 'is_first_order_discount_enabled'], 'default', 'value' => 0],
            [['first_order_discount_total_stock', 'first_order_discount_amount', 'pickup_discount_max_amount', 'pickup_discount_rate', 'concession_rate'], 'default', 'value' => 0]
        ];
    }

    /**
     * @return array
     * 为字段设置更友好的中文名称，用于错误提示
     */
    public function attributeLabels()
    {
        return [
            'id' => '商户ID',
            'user_id' => '绑定店长',
            'mch_name' => '商户名称',
            'type' => '商家类型',
            'logo' => '门店Logo',
            'tel' => '联系电话',
            'mobile' => '联系手机',
            'opening_hours' => '营业时间',
            'banner_images' => '门店轮播图',
            'description' => '门店介绍',
            'cat_id' => '门店分类',
            'address' => '详细地址',
            'province' => '省份',
            'city' => '城市',
            'county' => '区/县',
            'town' => '乡镇',
            'latitude' => '经度',
            'longitude' => '纬度',

            'business_license' => '营业执照',
            'legal_person_name' => '法人姓名',
            'id_card_front' => '法人身份证(正面)',
            'id_card_back' => '法人身份证(反面)',
            'is_first_order_discount_enabled' => '开启首单优惠',
            'first_order_discount_amount' => '首单优惠金额',
            'first_order_discount_total_stock' => '首单优惠总库存',
            'first_order_discount_start_time' => '优惠开始时间',
            'first_order_discount_end_time' => '优惠结束时间',
            'application_id' => '第三方支付应用ID',
            'other_platform_user_id' => '第三方支付用户ID',
            'wechat_mchid' => '微信支付商户号(商圈)',
            'wechat_shop_number' => '微信商圈门店编号',
            'floor' => '楼层',
            'concession_rate' => '通用让利比例'
        ];
    }

    /**
     * 后台获取商户信息用于编辑
     */
    public function getDetail()
    {
        // 详情把商户的证照、联系方式、结算信息整个吐出来,只按 id 查等于任一商城都能看别家的
        $mch = Mch::findOne(['id' => $this->id, 'mall_id' => Yii::$app->mall->id]);
        if (!$mch) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '商户不存在');
        }
        $detail = $mch->toArray();

        // [核心] 解码所有JSON字段，并提供默认值
        $detail['banner_images'] = Json::decode($mch->banner_images) ?: [];
        $detail['delivery_options'] = Json::decode($mch->delivery_options) ?: [];

        $defaultDeliveryConfig = [
            'enable' => 0,
            'delivery_radius' => 5,
            'charge_rules' => ['start_distance' => 3, 'start_fee' => 5, 'extra_fee_per_km' => 2],
            'time_config' => ['schedule_limit_days' => 3, 'time_slot_interval' => 30, 'business_hours' => [['start' => '09:00', 'end' => '21:00']]],
            'order_amount_limit' => ['min_order_amount' => 0],
        ];
        $detail['city_delivery_config'] = !empty($mch->city_delivery_config) ? Json::decode($mch->city_delivery_config) : $defaultDeliveryConfig;
        if ($mch->opening_hours) {
            $hours = explode('-', $mch->opening_hours);
            if (count($hours) == 2) {
                $detail['city_delivery_config']['time_config']['business_hours'] = [['start' => $hours[0], 'end' => $hours[1]]];
            }
        }
        $user = User::findOne($mch->user_id);
        $detail['user_info'] = $user ? ['id' => $user->id, 'nickname' => $user->nickname, 'avatar_url' => $user->avatar_url] : null;
        $detail['application_id'] = $mch->other_application_id;
        $detail['other_platform_user_id'] = $mch->other_user_id;
        $detail['wechat_mchid'] = $mch->wechat_mchid;
        $detail['wechat_shop_number'] = $mch->wechat_shop_number;
        $detail['floor'] = $mch->floor;
        // 首单剩余名额的快照,保存时按差额改(见 $first_order_stock_snapshot)
        $detail['first_order_stock_snapshot'] = (int)$mch->first_order_discount_total_stock;
        $detail['first_order_edit_token'] = \app\helpers\EditOnceHelper::issue();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['detail' => $detail]);
    }

    /**
     * 后台保存商户信息 (创建/编辑)
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 这里不能简单地在 findOne 里加 mall_id 了事:那样传一个别家商城的 id 会「查不到」,
            // 于是落进下面的新建分支,拿着请求里的数据凭空建出一个商户 —— 比原来的越权改还糟。
            // 所以要把「没传 id(新建)」和「传了 id 但不是本商城的(越权)」分开处理。
            $mch = $this->id
                ? Mch::findOne(['id' => $this->id, 'mall_id' => Yii::$app->mall->id])
                : null;
            if ($this->id && !$mch) {
                throw new \Exception('商户不存在');
            }
            $isNew = !$mch;

            if (!$mch) {
                $mch = new Mch();
                $mch->mall_id = Yii::$app->mall->id;
            }
            // 记录旧的店长ID，用于后续判断是否变更
            $oldUserId = $isNew ? null : $mch->user_id;

            $mch->attributes = $this->attributes;

            // 首单剩余名额按差额改:带了快照的编辑,名额不跟着这次 save() 写(改回库里的原值),
            // 存完再原子加减;新建商户、没带快照的老页面照旧整值写
            $firstOrderStockDelta = 0;
            $editClaim = null;
            if (!$isNew && $this->first_order_stock_snapshot !== null && $this->first_order_stock_snapshot !== '') {
                $firstOrderStockDelta = (int)$this->first_order_discount_total_stock - (int)$this->first_order_stock_snapshot;
                $mch->first_order_discount_total_stock = $mch->getOldAttribute('first_order_discount_total_stock');
                // 差额不幂等:同一个编辑页提交两次会加两遍。只让这个页面的第一次保存生效
                [$firstSave, $editClaim] = \app\helpers\EditOnceHelper::claim('mch_first_order', (int)$mch->id, $this->first_order_edit_token);
                if (!$firstSave) {
                    $firstOrderStockDelta = 0;
                }
            }

            // 数据库强制约束容错
            if ($mch->floor === null) {
                $mch->floor = '';
            }
            // cat_id 是 NOT NULL 字段，个人租户(type=4)没有商户分类 → 兜底 0
            // 也能防止前端清空 cat_id 后变 null 导致 SQL 报错
            if ($mch->cat_id === null || $mch->cat_id === '') {
                $mch->cat_id = 0;
            }


            $mch->first_order_discount_start_time = empty($this->first_order_discount_start_time) ? null : $this->first_order_discount_start_time;
            $mch->first_order_discount_end_time = empty($this->first_order_discount_end_time) ? null : $this->first_order_discount_end_time;

            $mch->banner_images = json_encode($this->banner_images ?: []);
            $mch->delivery_options = json_encode($this->delivery_options ?: []);

            if (!empty($this->city_delivery_config) && is_array($this->city_delivery_config)) {
                $mch->city_delivery_config = json_encode($this->city_delivery_config);
                if (isset($this->city_delivery_config['enable']) && $this->city_delivery_config['enable'] == 1) {
                    $mch->city_delivery_distance = (float)($this->city_delivery_config['delivery_radius'] ?? 0);
                } else {
                    $mch->city_delivery_distance = 0;
                }
            } else {
                $mch->city_delivery_config = null;
                $mch->city_delivery_distance = 0;
            }
            if ($this->application_id) {
                $mch->other_application_id = $this->application_id;
                $mch->other_user_id = $this->other_platform_user_id;
                User::updateAll(['application_id' => $this->application_id, 'other_platform_user_id' => $this->other_platform_user_id], ['id' => $mch->user_id]);
            }
            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }
            if ($firstOrderStockDelta !== 0) {
                // 调减超过现有名额时归 0,不为负
                Mch::updateAll(
                    ['first_order_discount_total_stock' => new \yii\db\Expression(
                        'GREATEST(0, first_order_discount_total_stock + :delta)', [':delta' => $firstOrderStockDelta]
                    )],
                    ['id' => (int)$mch->id]
                );
            }

            // [优化] 调用统一的辅助方法处理店长关系，移除此处的冗余代码
            $this->syncManagerRelation($mch, $oldUserId, $this->user_id);


            // 根据商户审核状态，同步更新用户的 is_mch 身份
            $this->updateUserMchStatus($mch->user_id, $mch->status);

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            // 这次没存成:放回名额编辑令牌,改完重试时差额还能生效
            \app\helpers\EditOnceHelper::release($editClaim ?? null);
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * 后台审核商户
     */
    public function updateStatus()
    {
        if (!$this->validate(['id', 'status', 'remarks'])) {
            return $this->responseErrorInfo();
        }
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $mch = Mch::findOne(['id' => $this->id, 'mall_id' => Yii::$app->mall->id]);
            if (!$mch) {
                throw new \Exception('商户不存在');
            }

            if ($mch->status == $this->status) {
                throw new \Exception('请勿重复审核');
            }

            $mch->status = $this->status;
            $mch->remarks = $this->remarks ?? ''; // 确保 remarks 字段始终被更新

            if (!$mch->save()) {
                throw new \Exception($this->getErrorMsg($mch));
            }

            // 同步用户的商家身份标记 —— save()(编辑商户)一直有这一步,唯独审核这条路漏了:
            // 于是从审核抽屉点"通过"的商家,user.is_mch 仍是 0,而 UserService 的身份缓存会带上它,
            // 前端据此判断的商家身份全部失真。审核是 is_mch 最该翻转的时刻,不能只在编辑时同步。
            $this->updateUserMchStatus($mch->user_id, $mch->status);

            // 根据不同的审核状态发送不同的消息（IM 站内 + 微信公众号模板 双通道）
            if ($this->status == 2) { // 2=驳回
                $reason = $this->remarks ?: '暂无说明';
                $desc = sprintf('很抱歉，您的店铺「%s」入驻申请已被驳回。驳回原因：%s', $mch->mch_name, $reason);

                // 【MessageHelper 渠道】历史兼容保留
                MessageHelper::sendToUser(
                    $mch->user_id,
                    '店铺入驻申请被驳回',
                    $desc,
                    'mch_rejected'
                );

                // 【IM 站内】用 buildBizNotice 构造富结构，chat/list/system-list 三端可读
                //   im 插件未装时跳过 IM 渠道（MessageHelper / WxNotify 仍然走）
                $imHelperCls = '\app\plugins\im\helpers\ImHelper';
                if (class_exists($imHelperCls)) {
                    Yii::$app->trigger(ImPushEvent::EVENT_PUSH, new ImPushEvent([
                        'mallId'  => $mch->mall_id,
                        'toId'    => $mch->user_id,
                        'content' => $imHelperCls::buildBizNotice(
                            'biz', '店铺入驻审核未通过', $desc, '', '❌', 'mch.entry.rejected'
                        ),
                        'msgType' => 'system',
                    ]));
                }

                // 【微信公众号模板】离线触达
                Yii::$app->trigger(WxNotifyEvent::EVENT_NOTIFY, new WxNotifyEvent([
                    'mallId'       => $mch->mall_id,
                    'userId'       => $mch->user_id,
                    'scene'        => 'mch.entry.rejected',
                    'templateData' => [
                        'mch_name' => $mch->mch_name,
                        'reason'   => $reason,
                        'tip'      => '如有疑问请联系商城客服',
                    ],
                    'pageUrl'      => '/others/mch/entry',
                ]));
            } elseif ($this->status == 1) { // 1=通过
                // 触发审核通过事件
                Yii::$app->trigger(MchEvent::MCH_APPROVED, new MchEvent(['mch' => $mch]));
                // 同步设置店长管理员身份
                $this->syncManagerRelation($mch, null, $mch->user_id);

                $desc = sprintf('恭喜！您的店铺「%s」入驻申请已审核通过，快去开始经营吧。', $mch->mch_name);

                // 【IM 站内】
                $imHelperCls = '\app\plugins\im\helpers\ImHelper';
                if (class_exists($imHelperCls)) {
                    Yii::$app->trigger(ImPushEvent::EVENT_PUSH, new ImPushEvent([
                        'mallId'  => $mch->mall_id,
                        'toId'    => $mch->user_id,
                        'content' => $imHelperCls::buildBizNotice(
                            'biz', '店铺入驻审核通过', $desc, '/others/mch/home', '🎉', 'mch.entry.approved'
                        ),
                        'msgType' => 'system',
                    ]));
                }

                // 【微信公众号模板】
                Yii::$app->trigger(WxNotifyEvent::EVENT_NOTIFY, new WxNotifyEvent([
                    'mallId'       => $mch->mall_id,
                    'userId'       => $mch->user_id,
                    'scene'        => 'mch.entry.approved',
                    'templateData' => [
                        'mch_name' => $mch->mch_name,
                        'tip'      => '点击进入商家后台开始经营',
                    ],
                    'pageUrl'      => '/others/mch/home',
                ]));
            }

            $transaction->commit();
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '操作成功');
        } catch (\Exception $e) {
            $transaction->rollBack();
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /**
     * [重构] 辅助方法：同步维护商家和管理员(店长)的关系
     * @param Mch $mch 当前商家模型
     * @param int|null $oldUserId 旧的店长用户ID
     * @param int $newUserId 新的店长用户ID
     * @throws \Exception
     */
    private function syncManagerRelation(Mch $mch, $oldUserId, $newUserId)
    {
        // 只有在店长发生变更时才执行操作
        if ($newUserId == $oldUserId) {
            return;
        }

        // 1. 移除旧店长的管理员身份
        if ($oldUserId) {
            $oldClerkRelation = MchClerk::findOne(['mch_id' => $mch->id, 'user_id' => $oldUserId, 'is_delete' => 0]);
            if ($oldClerkRelation) {
                $oldClerkRelation->is_manager = 0;
                if (!$oldClerkRelation->save()) {
                    throw new \Exception('移除旧店长权限失败: ' . $this->getErrorMsg($oldClerkRelation));
                }
            }
        }

        // 2. 为新店长赋予管理员身份
        if ($newUserId) {
            $newClerkRelation = MchClerk::findOne(['mch_id' => $mch->id, 'user_id' => $newUserId, 'is_delete' => 0]);
            if (!$newClerkRelation) {
                // 如果新店长之前不是本店成员，则创建新记录
                $newClerkRelation = new MchClerk();
                $newClerkRelation->mall_id = $mch->mall_id;
                $newClerkRelation->mch_id = $mch->id;
                $newClerkRelation->user_id = $newUserId;
            }
            $newClerkRelation->is_manager = 1; // 设为管理员
            $newClerkRelation->status = 1; // 确保是启用状态
            if (!$newClerkRelation->save()) {
                throw new \Exception('赋予新店长权限失败: ' . $this->getErrorMsg($newClerkRelation));
            }
        }
    }

    /**
     * 辅助方法：根据商户审核状态，更新用户的 is_mch 字段
     */
    private function updateUserMchStatus($userId, $mchStatus)
    {
        $user = User::findOne($userId);
        if (!$user) return;

        $isMch = ($mchStatus == 1) ? 1 : 0;
        if ($user->is_mch != $isMch) {
            $user->is_mch = $isMch;
            if (!$user->save()) {
                throw new \Exception('更新用户商家身份失败: ' . $this->getErrorMsg($user));
            }
        }
    }
}
