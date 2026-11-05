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

namespace app\forms\mall\user;

use app\core\ApiCode;
use app\helpers\CacheHelper; // 1. 【新增】引入 CacheHelper
use app\helpers\MallSettingHelper;
use app\models\BaseModel;
use app\models\Level;
use app\models\Order;
use app\models\User;
use app\models\UserPath;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use Yii;

class UserListForm extends BaseModel
{
    // ... (属性定义和 rules 保持不变) ...
    public $page;
    public $limit;
    public $keyword;
    public $user_id;
    public $mobile;
    public $nickname;
    public $parent_id;
    public $level_id;
    public $top_parent_id;
    public $is_export;
    public $search_date;
    public $status; // [新增] 状态筛选
    public $sort_field; // 点表头排序:字段名(只认白名单)
    public $sort_order; // 点表头排序:asc / desc

    public function rules()
    {
        return [
            [['keyword', 'nickname', 'mobile', 'sort_field', 'sort_order'], 'string'],
            [['user_id', 'status'], 'integer'], // [新增] status 验证
            [['search_date'], 'safe'],
            [['page', 'limit', 'parent_id', 'level_id', 'top_parent_id', 'is_export'], 'integer'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    /**
     * 列表排序。默认按 ID 倒序(= 最新注册在前);前端点表头传 sort_field / sort_order。
     *
     * ⛔ 字段必须走白名单:orderBy() 的值是拼进 SQL 的,直接把前端传的字段名接进去 = 注入口子。
     * 注册时间按 u.id 排:created_at 是秒级整数,同一秒注册的多条顺序会飘,翻页时会重复/漏人;
     * 自增 ID 与注册先后完全同序,且是主键,排序不用回表。
     */
    private function orderBy(): array
    {
        $fields = [
            'created_at' => 'u.id',
            'id' => 'u.id',
        ];
        $column = $fields[(string)$this->sort_field] ?? 'u.id';
        $direction = strtolower((string)$this->sort_order) === 'asc' ? SORT_ASC : SORT_DESC;
        return [$column => $direction];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = Yii::$app->mall->id;

        // --- 1. 构建主查询 ---
        $query = User::find()->alias('u')->where([
            'u.mall_id' => $mallId,
        ]);

        // [修改] 如果是查询注销用户(status=2)，则查找 is_delete=1 的记录；否则查找 is_delete=0
        if ($this->status == User::STATUS_DELETE) {
            $query->andWhere(['u.is_delete' => 1]);
        } else {
            $query->andWhere(['u.is_delete' => 0]);
        }

        $query->andFilterWhere(['u.id' => $this->user_id])
            ->andFilterWhere(['u.parent_id' => $this->parent_id])
            ->andFilterWhere(['u.level_id' => $this->level_id])
            ->andFilterWhere(['u.status' => $this->status])
            ->andFilterWhere(['like', 'u.mobile', $this->mobile]);

        $query->andFilterWhere(['like', 'u.nickname', $this->nickname]);

        if ($this->keyword) {
            $query->andWhere([
                'or',
                ['like', 'u.nickname', $this->keyword],
                ['like', 'u.mobile', $this->keyword]
            ]);
        }

        if (!empty($this->top_parent_id)) {
            $subQuery = UserPath::find()
                ->where(['like', 'path', \app\services\UserService::subtreeLike($this->top_parent_id), false])
                ->andWhere('user_id = u.id')
                ->andWhere(['!=', 'user_id', $this->top_parent_id]);
            $query->andWhere(['exists', $subQuery]);
        }

        if ($this->search_date && is_array($this->search_date) && count($this->search_date) == 2) {
            $begin = strtotime($this->search_date[0]);
            $end = strtotime($this->search_date[1] . ' 23:59:59');
            $query->andFilterWhere(['between', 'u.created_at', $begin, $end]);
        }

        // --- 2. 预加载与分页 (保持不变) ---
        $query->with(['inviter' => function ($q) {
            $q->select(['id', 'nickname', 'avatar_url']);
        }]);

        $pagination = null;
        if (!$this->is_export) {
            $query->page($pagination, $this->limit, $this->page);
        }

        $list = $query->orderBy($this->orderBy())
            ->asArray()
            ->all();

        if (empty($list)) {
            return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => [], 'pagination' => $pagination]);
        }

        // --- 3. 批量获取辅助数据 ---
        $userIds = ArrayHelper::getColumn($list, 'id');

        // A. 订单统计 (保持不变)
        $orderStats = Order::find()
            ->select([
                'user_id',
                'total_money' => new Expression('SUM(pay_price - refund_price)'),
                'total_count' => new Expression('COUNT(*)')
            ])
            ->where([
                'mall_id' => $mallId,
                'user_id' => $userIds,
                'is_delete' => 0,
                'status' => Order::STATUS_COMPLETED,
            ])
            ->groupBy('user_id')
            ->asArray()
            ->indexBy('user_id')
            ->all();

        // =====================================================================
        // B. [修改] 等级数据缓存 (使用 CacheHelper)
        // =====================================================================
        $levelCacheKey = "mall_levels_{$mallId}";

        // 1. 尝试从 Redis 读取 (JSON 格式)
        $levelsMap = CacheHelper::get($levelCacheKey);

        // 2. 如果未命中 (null)，则查询数据库并写入
        // 注意：这里用 === null，因为如果查询结果为空数组 []，我们也要缓存，避免缓存穿透
        if ($levelsMap === null) {
            $levelsMap = Level::find()
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->select(['id', 'name'])
                ->indexBy('id')
                ->asArray()
                ->all();

            // 写入 Redis，有效期 1 小时
            CacheHelper::set($levelCacheKey, $levelsMap, 3600);
        }
        // =====================================================================

        $defaultAvatar = MallSettingHelper::getLogo($mallId);

        // --- 4. 循环组装最终数据 (保持不变) ---
        foreach ($list as &$item) {
            $item['created_at'] = isset($item['created_at']) ? date('Y-m-d H:i:s', $item['created_at']) : '';
            $item['parent_at_fmt'] = !empty($item['parent_at']) ? date('Y-m-d H:i:s', $item['parent_at']) : '';

            // 这里不用改，JSON decode 出来的数组 key 如果是数字，PHP 也能正确匹配
            $item['level'] = $levelsMap[$item['level_id']]['name'] ?? '默认等级';

            $stats = $orderStats[$item['id']] ?? [];
            $item['total_order_price'] = $stats['total_money'] ?? '0.00';
            $item['order_num'] = (int)($stats['total_count'] ?? 0);

            if (empty($item['parent_id']) || $item['parent_id'] == 0) {
                $item['parent_avatar'] = $defaultAvatar;
                $item['parent_name'] = '无绑定';
            } elseif ($item['parent_id'] == -1) {
                $item['parent_avatar'] = $defaultAvatar;
                $item['parent_name'] = '总店/平台';
            } else {
                if (!empty($item['inviter'])) {
                    $item['parent_avatar'] = $item['inviter']['avatar_url'];
                    $item['parent_name'] = $item['inviter']['nickname'];
                } else {
                    $item['parent_avatar'] = $defaultAvatar;
                    $item['parent_name'] = '未知上级(ID:' . $item['parent_id'] . ')';
                }
            }
            unset($item['inviter']);
        }
        unset($item);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '', ['list' => $list, 'pagination' => $pagination]);
    }
}
