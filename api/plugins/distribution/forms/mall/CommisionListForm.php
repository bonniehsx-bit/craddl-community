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

namespace app\plugins\distribution\forms\mall;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\CommissionLog;
use app\plugins\distribution\models\DistributionCommissionType;
use app\models\Order;
use app\models\User;

class CommisionListForm extends BaseModel
{
    // --- 接收前端的搜索参数 ---
    public $keyword;
    public $status;
    public $date_start;
    public $date_end;
    public $page;
    public $limit = 20;

    public function rules()
    {
        return [
            [['keyword', 'status', 'date_start', 'date_end'], 'trim'],
            [['keyword', 'status', 'date_start', 'date_end'], 'string'],
            [['page', 'limit'], 'integer'],
            ['page', 'default', 'value' => 1],
        ];
    }

    public function getList()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $query = $this->buildQuery();

        // --- 分页处理:走统一的 page()(返回 total_count 等前端分页组件认的字段);
        //     原来直接返回 yii\data\Pagination,字段是 totalCount,列表页一直显示「共 0 条」、翻不了页 ---
        $pagination = null;
        $list = $query
            ->orderBy(['cl.id' => SORT_DESC])
            ->page($pagination, (int)$this->limit, (int)$this->page)
            ->asArray()
            ->all();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'list' => $this->formatRows($list),
                'pagination' => $pagination,
                'status_map' => CommissionLog::getStatusMap() // [附加] 将状态选项返回给前端
            ]
        ];
    }

    /**
     * 导出当前筛选结果为 CSV（同步下载，前端 window.open 直接触发）。
     *
     * 用 batch() 分批取数，避免一次性把几万行拉进内存；超上限不静默截断，
     * 最后一行明确写出「已达上限」，让运营知道要缩小筛选范围再分批导。
     */
    public function exportCsv(int $limit = 50000)
    {
        $this->validate();

        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM，否则 Excel 打开中文乱码
        fputcsv($fh, ['分润ID', '来源订单号', '商品', '买家', '买家ID', '分销员', '分销员ID',
                      '会员等级', '分销层级', '分销比例(%)', '计算基数', '佣金金额', '状态', '计提时间', '结算时间']);

        $count = 0;
        $truncated = false;
        $query = $this->buildQuery()->orderBy(['cl.id' => SORT_DESC])->asArray();
        foreach ($query->batch(500) as $rows) {
            foreach ($this->formatRows($rows) as $r) {
                if ($count >= $limit) {
                    $truncated = true;
                    break 2;
                }
                fputcsv($fh, [
                    $r['id'],
                    // 前面加制表符防止 Excel 把长订单号显示成科学计数法
                    "\t" . ($r['order_no'] ?? ''),
                    $r['goods_name'],
                    $r['buy_user_nickname'],
                    $r['buy_user_id'],
                    $r['user_nickname'],
                    $r['user_id'],
                    $r['dist_level_name'],
                    $r['tier_name'],
                    $r['commission_rate'],
                    $r['calculation_price'],
                    $r['commission_amount'],
                    $r['status_text'],
                    $r['created_at'],
                    $r['settled_at'],
                ]);
                $count++;
            }
        }

        if ($truncated) {
            fputcsv($fh, ["已达单次导出上限 {$limit} 条，其余未导出，请缩小筛选范围后分批导出"]);
        }

        rewind($fh);
        $content = stream_get_contents($fh);
        fclose($fh);

        return [
            'filename' => '分销佣金明细_' . date('YmdHis') . '.csv',
            'content'  => $content,
        ];
    }

    /**
     * 列表与导出共用的查询条件（含关联与筛选）
     */
    private function buildQuery()
    {
        $query = CommissionLog::find()->alias('cl')->where([
            'cl.mall_id' => \Yii::$app->mall->id,
            'cl.is_delete' => 0,
            'cl.type' => DistributionCommissionType::DISTRIBUTION, // 限定为分销佣金
        ]);

        // --- 关联查询 ---
        // 关联获得佣金的用户
        $query->leftJoin(['u' => User::tableName()], 'u.id = cl.user_id');
        // 关联购买者
        $query->leftJoin(['bu' => User::tableName()], 'bu.id = cl.buy_user_id');
        // 关联订单
        $query->leftJoin(['o' => Order::tableName()], 'o.id = cl.order_id');

        // --- 条件搜索 ---
        // 关键词搜索
        if ($this->keyword) {
            $query->andWhere(['or',
                ['like', 'u.nickname', $this->keyword],
                ['=', 'cl.user_id', $this->keyword],
                ['like', 'bu.nickname', $this->keyword],
                ['=', 'cl.buy_user_id', $this->keyword],
                ['like', 'o.order_no', $this->keyword]
            ]);
        }

        // 状态筛选
        if ($this->status !== '' && $this->status !== null) {
            $query->andWhere(['cl.status' => $this->status]);
        }

        // 日期范围筛选
        if ($this->date_start && $this->date_end) {
            $query->andWhere(['>=', 'cl.created_at', strtotime($this->date_start . ' 00:00:00')]);
            $query->andWhere(['<=', 'cl.created_at', strtotime($this->date_end . ' 23:59:59')]);
        }

        return $query->select('cl.*, u.nickname as user_nickname, u.avatar_url as user_avatar, bu.nickname as buy_user_nickname, o.order_no');
    }

    /**
     * 列表与导出共用的格式化
     */
    private function formatRows(array $list): array
    {
        foreach ($list as &$item) {
            $item['status_text'] = (new CommissionLog(['status' => $item['status']]))->getStatusText();
            $item['created_at'] = date('Y-m-d H:i:s', (int)$item['created_at']);
            $item['settled_at'] = $item['settled_at'] ? date('Y-m-d H:i:s', (int)$item['settled_at']) : '';
            $item['commission_amount'] = price_format($item['commission_amount']);

            // 解析 extra_data，提取会员等级快照和商品名
            $extra = json_decode($item['extra_data'] ?? '', true) ?: [];
            // 收款人会员等级（新）；兼容旧记录的 dist_level_name
            $item['dist_level_name'] = $extra['member_level_name'] ?? ($extra['dist_level_name'] ?? '');
            $item['goods_name']      = $extra['goods_name']      ?? '';
            $item['tier_name']       = $extra['level_name']      ?? (($item['level'] ?? 0) . '级分销'); // 分销层级（1级/2级）

            // 计算基数 & 分销比例（已存于 CommissionLog 字段，格式化后输出）
            $item['calculation_price'] = $item['calculation_price'] !== null
                ? number_format((float)$item['calculation_price'], 2, '.', '') : '0.00';
            $item['commission_rate'] = $item['commission_rate'] !== null
                ? (float)$item['commission_rate'] : 0;
        }
        unset($item);

        return $list;
    }
}
