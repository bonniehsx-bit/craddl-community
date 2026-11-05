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
use app\models\User;
use app\services\CouponService;

// [新增] 引入服务层

class CouponSendForm extends BaseModel
{
    public $page;
    public $limit;

    // 搜索条件
    public $keyword;
    public $user_id;
    public $search_date;
    public $sign_time;
    public $year;
    public $month;
    public $day;

    // 发券参数
    public $uids;
    public $coupon_id;

    public function rules()
    {
        return [
            [['page', 'limit', 'user_id', 'year', 'month', 'day', 'coupon_id'], 'integer'],
            [['keyword'], 'string'],
            [['search_date', 'sign_time', 'uids'], 'safe'],
        ];
    }

    /**
     * 搜索用户
     * @return array
     */
    public function searchUser()
    {
        $query = User::find()->where([
            'mall_id' => \Yii::$app->mall->id,
            'is_delete' => 0,
        ]);

        if ($this->keyword) {
            $query->andWhere(['like', 'nickname', $this->keyword]);
        }
        if ($this->user_id) {
            $query->andWhere(['id' => $this->user_id]);
        }
        // 按生日范围搜索(只看月日,不看年份 —— 找的是"这段日子过生日的人")
        //
        // 改用 month/day 整数列比较,不再对 birthday 做 DATE_FORMAT:
        // 函数运算用不上索引,8.9 万用户实测要扫 4.2 万行再逐行算;
        // month/day 由 User::beforeSave 写入时自动拆好,与 birthday 恒一致,
        // 且有 idx-user-mall_id_is_delete_month_day 可用(m260726_170000)。
        //
        // 区间按月拆开,每一段 month 都是等值条件(见 birthdayRangeCondition)。
        // 不要写成 month*100+day BETWEEN ...:那又是对列做运算,索引只能用到前两列,
        // 优化器会退回 (mall_id, is_delete),把整个商城的用户逐行回表过滤(9 万行实测扫 4.5 万行)。
        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $s = strtotime($this->search_date[0]);
            $e = strtotime($this->search_date[1]);
            if ($s !== false && $e !== false) {
                $query->andWhere(self::birthdayRangeCondition(
                    (int) date('n', $s), (int) date('j', $s),
                    (int) date('n', $e), (int) date('j', $e)
                ));
            }
        }
        // 按注册时间搜索
        if ($this->sign_time && is_array($this->sign_time) && count($this->sign_time) == 2) {
            $query->andWhere(['between', 'created_at', strtotime($this->sign_time[0]), strtotime($this->sign_time[1])]);
        }
        if ($this->year) {
            $query->andWhere(['year' => $this->year]);
        }
        if ($this->month) {
            $query->andWhere(['month' => $this->month]);
        }
        if ($this->day) {
            $query->andWhere(['day' => $this->day]);
        }

        $list = $query->select('id, nickname, avatar_url, birthday')
            ->page($pagination, 20, $this->page)
            ->orderBy('id DESC')
            ->asArray()
            ->all();

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }

    /**
     * 生日「月日区间」→ 按月拆开的查询条件,只看月日、不看年份。
     *
     * 3/1~3/15   → month=3 且 day 在 1~15
     * 3/20~5/10  → (month=3 且 day≥20) 或 month=4 或 (month=5 且 day≤10)
     * 12/20~1/10 → (month=12 且 day≥20) 或 (month=1 且 day≤10),跨年靠 $m % 12 + 1 自然回到 1 月
     * 3/20~3/10  → 起始日在结束日之后,按跨了一整圈处理:3 月 20 号起,绕到明年 3 月 10 号
     *
     * 每段的 month 都是等值、day 是范围,符合「等值在前、范围在后」,
     * 能用上 idx-user-mall_id_is_delete_month_day(m260726_170000)。
     * 每段都指定了具体月份,month=0(没填生日)天然落不进任何一段。
     */
    public static function birthdayRangeCondition(int $sm, int $sd, int $em, int $ed): array
    {
        if ($sm === $em && $sd <= $ed) {
            return ['and', ['month' => $sm], ['between', 'day', $sd, $ed]];
        }
        $or = ['or', ['and', ['month' => $sm], ['>=', 'day', $sd]]];   // 起始月:起始日到月底
        for ($m = $sm % 12 + 1; $m !== $em; $m = $m % 12 + 1) {
            $or[] = ['month' => $m];                                     // 中间的整月
        }
        $or[] = ['and', ['month' => $em], ['<=', 'day', $ed]];          // 结束月:1 号到结束日
        return $or;
    }

    /**
     * 执行发券
     * @return array
     */
    public function send()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        if (empty($this->uids) || !is_array($this->uids)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择要发放的用户');
        }

        try {
            $coupon = Coupon::findOne(['id' => $this->coupon_id, 'is_delete' => 0, 'mall_id' => \Yii::$app->mall->id]);
            if (!$coupon) {
                throw new \Exception('优惠券不存在');
            }

            // [核心] 调用服务层来处理发券逻辑
            $couponService = new CouponService();
            $result = $couponService->sendCoupons($coupon, $this->uids);

            $successCount = $result['success_count'];
            $errorCount = $result['error_count'];

            $msg = "发放完成。成功 {$successCount} 人";
            if ($errorCount > 0) {
                $msg .= "，失败 {$errorCount} 人。";
                // 可以将具体的失败原因记录在 $result['errors'] 中返回给前端
            }

            return $this->apiResponse(ApiCode::CODE_SUCCESS, $msg);

        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }
}