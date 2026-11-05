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

namespace app\plugins\marketing\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\Coupon;
use app\models\Goods;
use app\models\UserCoupon;
use yii\helpers\Json;

class CouponForm extends BaseModel
{
    public $id;
    public $page;
    public $limit;

    // --- 对应前端表单字段 ---
    public $name;
    public $is_enabled;
    public $type; // 0=满减, 1=折扣
    public $min_price;
    public $sub_price;
    public $discount;
    public $time_type; // 0=领取后N天, 1=固定日期
    public $day;
    public $date_range;
    public $total_count;
    public $can_be_claimed;
    public $limit_per_user;
    public $appoint_type; // all, goods, cat
    public $appoint_ids;  // 前端传来的商品/分类ID数组

    public $is_transferable;

    // [新增] 接收前端传来的商户ID
    public $mch_id;

    // 平台券(mch_id=0)是否可用于商家商品(1=可,优惠平台买单;0=仅自营)。商家券此字段忽略。
    public $apply_to_mch;

    // --- 列表筛选(仅 getList 用) ---
    public $keyword;   // 券名称 / 券ID
    public $owner;     // platform=平台券 mch=商家券
    public $validity;  // valid=未过期 expired=已过期

    public function rules()
    {
        return [
            // [修改] mch_id 加入整数验证
            [['id', 'page', 'limit', 'is_enabled', 'type', 'time_type', 'day', 'total_count', 'can_be_claimed', 'limit_per_user', 'is_transferable', 'mch_id', 'apply_to_mch'], 'integer'],
            [['min_price', 'sub_price', 'discount'], 'number'],
            [['name'], 'string', 'max' => 255],
            [['keyword'], 'trim'],
            [['keyword'], 'string', 'max' => 60],
            [['owner'], 'in', 'range' => ['platform', 'mch']],
            [['validity'], 'in', 'range' => ['valid', 'expired']],
            [['date_range', 'appoint_ids'], 'safe'],
            [['appoint_type'], 'in', 'range' => ['all', 'goods', 'cat']],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    /**
     * 保存 (新建/编辑) 优惠券
     * @return array
     */
    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $transaction = \Yii::$app->db->beginTransaction();
        try {
            $coupon = null;
            if ($this->id) {
                // 编辑模式：mch_id 不允许修改，只能改其他信息
                $coupon = Coupon::findOne(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id]);
                if (!$coupon) {
                    return ['code' => ApiCode::CODE_FAIL, 'msg' => '优惠券不存在'];
                }
            } else {
                // 新建模式
                $coupon = new Coupon();
                $coupon->mall_id = \Yii::$app->mall->id;

                // [核心修改] 如果前端传了 mch_id 且大于0，则认为是帮商家创建；否则默认为0（平台自营）
                // 注意：这里需要确保操作员有权限创建商家券（通常默认后台管理员有此权限）
                $coupon->mch_id = ($this->mch_id && $this->mch_id > 0) ? $this->mch_id : 0;
            }

            // --- 数据赋值 ---
            $coupon->name = $this->name;
            $coupon->is_enabled = $this->is_enabled ? 1 : 0;
            $coupon->type = $this->type;
            $coupon->min_price = $this->min_price ?: 0;
            $coupon->sub_price = ($this->type == 0) ? ($this->sub_price ?: 0) : 0;
            $coupon->discount = ($this->type == 1) ? ($this->discount ?: 10) : 10;
            $coupon->time_type = $this->time_type;
            $coupon->is_transferable = $this->is_transferable ?? 0;

            // 「可用于商家(平台买单)」仅平台券(mch_id=0)有意义;商家券恒为 0。
            // 编辑模式 mch_id 不可改,直接读 $coupon->mch_id(新建/编辑都已是最终值)。
            $coupon->apply_to_mch = ((int)$coupon->mch_id === 0 && (int)$this->apply_to_mch === 1) ? 1 : 0;

            if ($this->time_type == 1) { // 固定日期
                if (!$this->date_range || count($this->date_range) < 2) {
                    throw new \Exception('请选择日期范围');
                }
                $coupon->start_at = strtotime($this->date_range[0] . ' 00:00:00');
                $coupon->end_at = strtotime($this->date_range[1] . ' 23:59:59');
                $coupon->day = 0;
            } else { // 领取后有效
                $coupon->day = $this->day > 0 ? $this->day : 0;
                $coupon->start_at = 0;
                $coupon->end_at = 0;
            }

            $coupon->total_count = ($this->total_count === '' || $this->total_count === null) ? -1 : (int)$this->total_count;
            $coupon->can_be_claimed = $this->can_be_claimed ? 1 : 0;
            $coupon->limit_per_user = $this->limit_per_user >= 0 ? (int)$this->limit_per_user : 0;
            $coupon->appoint_type = $this->appoint_type ?: 'all';

            // 将关联ID数组存入 appoint_relation
            if (in_array($this->appoint_type, ['goods', 'cat']) && is_array($this->appoint_ids)) {
                // 通常前端传来的 appoint_ids 是 [{id: 1}, {id: 2}] 的格式
                $ids = array_column($this->appoint_ids, 'id');
                $coupon->appoint_relation = Json::encode($ids);
            } else {
                $coupon->appoint_relation = null;
            }

            if (!$coupon->save()) {
                throw new \Exception($this->getErrorMsg($coupon));
            }

            $transaction->commit();
            return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '保存成功'];

        } catch (\Exception $e) {
            $transaction->rollBack();
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $e->getMessage()];
        }
    }

    /**
     * 获取优惠券详情
     * @return array
     */
    public function search()
    {
        if (!$this->id) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请选择优惠券'];
        }

        // [核心修改] 关联查询 mch 信息，以便前端回显
        $coupon = Coupon::find()
            ->with(['mch'])
            ->where(['is_delete' => 0, 'id' => $this->id, 'mall_id' => \Yii::$app->mall->id])
            ->one();

        if (!$coupon) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '优惠券不存在'];
        }

        $data = $coupon->attributes;

        // [核心修改] 回显商户信息
        $data['mch_id'] = $coupon->mch_id;
        $data['mch_name'] = ($coupon->mch_id > 0 && $coupon->mch) ? $coupon->mch->mch_name : '';

        // --- 兼容旧字段，确保旧前端不出错 ---
        $data['is_goods_limit'] = $coupon->appoint_type == 'all' ? 0 : 1;
        $data['price'] = $coupon->min_price;
        $data['total'] = $coupon->total_count;
        $data['user_total'] = $coupon->limit_per_user;

        if ($coupon->time_type == 1) {
            $data['date_range'] = [date('Y-m-d', $coupon->start_at), date('Y-m-d', $coupon->end_at)];
        }

        // --- 查询关联的商品/分类信息 ---
        $data['appoint_ids'] = []; // 对应前端的 appoint_ids
        $data['goods_list'] = [];  // 兼容旧的 goods_list
        $relationIds = $coupon->getAppointRelationIds();

        if (!empty($relationIds)) {
            if ($coupon->appoint_type === 'goods') {
                $goodsList = Goods::find()->where(['id' => $relationIds, 'is_delete' => 0])
                    ->select('id, name, cover_pic')
                    ->asArray()
                    ->all();
                $data['appoint_ids'] = $goodsList;
                $data['goods_list'] = $goodsList;
            } elseif ($coupon->appoint_type === 'cat') {
                // 如果后续支持分类选择，这里补充分类查询逻辑
                // 目前暂留空
            }
        }

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '获取成功', 'data' => ['coupon' => $data]];
    }

    /**
     * 删除优惠券
     * @return array
     */
    public function delete()
    {
        if (!$this->id) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '请选择优惠券'];
        }

        $coupon = Coupon::findOne([
            'id' => $this->id,
            'mall_id' => \Yii::$app->mall->id,
            // 注意：后台管理员可以删除任何人的券，所以这里不强制 mch_id=0
            'is_delete' => 0
        ]);

        if (!$coupon) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '优惠券不存在'];
        }

        $coupon->is_delete = 1;
        if (!$coupon->save()) {
            return $this->responseErrorInfo($coupon);
        }

        // 软删除所有已发放的未使用优惠券 (严谨策略)
        UserCoupon::updateAll(['is_delete' => 1], ['coupon_id' => $this->id]);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '删除成功'];
    }

    /**
     * 获取优惠券列表
     * @return array
     */
    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = Coupon::find()
            ->with(['mch'])
            ->where([
                'mall_id' => \Yii::$app->mall->id,
                'is_delete' => 0
            ]);

        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere(ctype_digit($this->keyword)
                ? ['or', ['id' => (int)$this->keyword], ['like', 'name', $this->keyword]]
                : ['like', 'name', $this->keyword]);
        }
        foreach (['type', 'is_enabled', 'can_be_claimed'] as $field) {
            if ($this->$field !== null && $this->$field !== '') {
                $query->andWhere([$field => (int)$this->$field]);
            }
        }
        if ($this->owner === 'platform') {
            $query->andWhere(['mch_id' => 0]);
        } elseif ($this->owner === 'mch') {
            $query->andWhere(['>', 'mch_id', 0]);
        }
        // 只有固定日期券会过期;领取后 N 天的券模板本身一直有效
        if ($this->validity === 'expired') {
            $query->andWhere(['time_type' => 1])->andWhere(['<', 'end_at', time()]);
        } elseif ($this->validity === 'valid') {
            $query->andWhere(['or', ['time_type' => 0], ['>=', 'end_at', time()]]);
        }

        $list = $query->page($pagination, $this->limit, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        foreach ($list as &$item) {
            if ($item['time_type'] == 1) {
                $item['start_at_text'] = date('Y-m-d H:i:s', $item['start_at']);
                $item['end_at_text'] = date('Y-m-d H:i:s', $item['end_at']);
            } else {
                $item['start_at_text'] = '';
                $item['end_at_text'] = '';
            }

            // 确保前端 mch 对象存在，避免 null 报错
            if (!isset($item['mch'])) {
                $item['mch'] = null;
            }
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}