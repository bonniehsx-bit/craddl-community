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

namespace app\jobs\order;

use app\core\Job;
use app\forms\mall\order\OrderFilter;
use app\helpers\CacheHelper;
use app\helpers\ServerHelper;
use app\models\Order;
use app\models\User;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Yii;
use yii\helpers\FileHelper;
use yii\helpers\Json;

class OrderExportJob extends Job
{
    public $params;      // 前端传来的搜索参数
    public $token;       // 导出的唯一标识 Key
    public $mall_id;     // 商城ID

    /**
     * 定义所有支持的字段及其名称映射
     * (保持你原来的字段映射不变)
     */
    /** 推荐人昵称缓存 [user_id => nickname],每批订单一次查完,别一单查一次 */
    private $referrerNames = [];

    private $fieldMap = [
        'id' => '订单ID',
        'order_no' => '订单号',
        'transaction_id' => '交易单号',
        'mch_name' => '所属商家',
        'user_info' => '买家信息',
        'referrer_info' => '推荐人',
        'name' => '收货人',
        'mobile' => '电话',
        'address' => '收货地址',
        'status_text' => '订单状态',
        'pay_type' => '支付方式',
        'created_at' => '下单时间',
        'pay_at' => '支付时间',
        'remarks' => '订单备注',
        'pay_price' => '实付金额',
        'express_price' => '运费',
        'goods_name' => '商品名称',
        'goods_short_name' => '商品简称',
        'attr' => '规格',
        'factory' => '生产厂家',
        'price' => '单价',
        'num' => '数量',
        'total_price' => '商品总价',
        'is_send' => '发货状态',
    ];

    public function execute($params = null)
    {
        $cacheKey = 'order_export_status_' . $this->token;

        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        try {
            // 1. 更新状态：初始化
            CacheHelper::set($cacheKey, [
                'status' => 'processing',
                'msg' => '正在准备数据...',
                'percentage' => 0
            ], 3600);

            // 2. 解析导出的字段顺序
            $exportFields = [];
            if (!empty($this->params['export_fields'])) {
                $exportFields = is_string($this->params['export_fields']) ? Json::decode($this->params['export_fields']) : $this->params['export_fields'];
            }
            if (empty($exportFields)) {
                $exportFields = array_keys($this->fieldMap);
            }

            // 3. 准备文件保存路径
            $absRoot = ServerHelper::getAbsFilePath();
            $fileName = '订单导出_' . date('YmdHis') . '_' . rand(1000, 9999) . '.xlsx';
            $relativeDir = '/temp/export/' . date('Ym/d');
            $absoluteDir = $absRoot . $relativeDir;

            if (!is_dir($absoluteDir)) {
                FileHelper::createDirectory($absoluteDir, 0777);
            }
            $filePath = $absoluteDir . '/' . $fileName;
            $fileUrl = ServerHelper::getHost() . $relativeDir . '/' . $fileName;

            // 4. 创建 Excel 对象
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('订单明细');

            // --- 动态设置表头 ---
            $colHeader = 'A';
            foreach ($exportFields as $fieldKey) {
                $cell = $colHeader . '1';
                $sheet->setCellValue($cell, $this->fieldMap[$fieldKey] ?? $fieldKey);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                if (in_array($fieldKey, ['order_no', 'transaction_id', 'address', 'goods_name', 'attr'])) {
                    $sheet->getColumnDimension($colHeader)->setWidth(30);
                } else {
                    $sheet->getColumnDimension($colHeader)->setWidth(15);
                }
                $colHeader++;
            }

            // 5. 构建查询 (此处调用了优化后的 buildQuery)
            $query = $this->buildQuery();
            $total = (int)$query->count();

            // 6. 分批处理数据 (保持你原来的循环逻辑)
            if ($total > 0) {
                $batchSize = 100;
                $currentRow = 2;
                $processed = 0;

                foreach ($query->batch($batchSize) as $orders) {
                    if (in_array('referrer_info', $exportFields, true)) {
                        $this->loadReferrerNames($orders);
                    }
                    foreach ($orders as $order) {
                        /** @var Order $order */
                        $details = $order->orderDetails;
                        $detailCount = count($details) ?: 1;

                        $startRow = $currentRow;
                        $endRow = $currentRow + $detailCount - 1;

                        // 循环订单下的每一个商品（作为行）
                        for ($i = 0; $i < $detailCount; $i++) {
                            $row = $startRow + $i;
                            $detail = $details[$i] ?? null;

                            $currentCol = 'A';
                            foreach ($exportFields as $fieldKey) {
                                // 调用原有的取值逻辑
                                $value = $this->getFieldValue($order, $detail, $fieldKey);

                                if (in_array($fieldKey, ['order_no', 'transaction_id', 'mobile', 'id'])) {
                                    $sheet->setCellValueExplicit($currentCol . $row, $value, DataType::TYPE_STRING);
                                } else {
                                    $sheet->setCellValue($currentCol . $row, $value);
                                }
                                $currentCol++;
                            }
                        }

                        // 合并订单级字段的单元格
                        if ($detailCount > 1) {
                            $mergeColLetter = 'A';
                            foreach ($exportFields as $fieldKey) {
                                if ($this->isOrderLevelField($fieldKey)) {
                                    $sheet->mergeCells("{$mergeColLetter}{$startRow}:{$mergeColLetter}{$endRow}");
                                }
                                $mergeColLetter++;
                            }
                        }

                        $sheet->getStyle("A{$startRow}:" . $sheet->getHighestColumn() . $endRow)
                            ->getAlignment()
                            ->setVertical(Alignment::VERTICAL_CENTER);

                        $currentRow += $detailCount;
                        $processed++;
                    }

                    $percentage = round(($processed / $total) * 98);
                    CacheHelper::set($cacheKey, [
                        'status' => 'processing',
                        'msg' => "正在生成Excel: {$processed}/{$total}",
                        'percentage' => $percentage
                    ], 3600);
                }
            }

            // 7. 保存文件
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            $this->finish($cacheKey, $fileUrl, '导出成功');

        } catch (\Throwable $e) {
            Yii::error("Excel导出任务失败: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            CacheHelper::set($cacheKey, [
                'status' => 'failed',
                'msg' => '导出失败: ' . $e->getMessage(),
                'percentage' => 0
            ], 3600);
        }
    }

    /**
     * [核心修改] 构建查询语句
     * 优化点：移除 keyword 复杂逻辑，统一使用 andFilterWhere，同步 OrderListForm 逻辑
     */
    private function buildQuery()
    {
        $query = Order::find()->alias('o')
            ->where(['o.mall_id' => $this->mall_id, 'o.is_delete' => 0]);

        $p = $this->params; // 简写参数

        // 商家条件必须在「勾选导出」提前返回之前:商户端导出(MchOrderExportForm)把 mch_id 强制成当前店,
        // 原来放在后面,勾选分支先 return 了 —— 传一串别人的订单号就能导出别家/自营单(含买家姓名电话地址)
        OrderFilter::applyMch($query, $p);

        // 勾选了订单就只导出勾选的,忽略其它筛选
        if (!empty($p['order_ids'])) {
            $ids = is_array($p['order_ids']) ? $p['order_ids'] : Json::decode($p['order_ids']);
            if (is_array($ids) && count($ids) > 0) {
                $query->andWhere(['o.id' => $ids]);
                return $query->with(['orderDetails.goods', 'user', 'mch', 'commonPayOrder'])
                    ->orderBy('o.id DESC');
            }
        }

        // 页面上的筛选条件:和列表共用一份,加筛选去 OrderFilter 里加
        OrderFilter::apply($query, $p, false);

        // 导出专有:没指定订单类型时,只导出「普通订单 + 各插件声明 exportable 的类型」。
        // 正向白名单,与 OrderBehaviorRegistry 的其它特征(排除式)语义相反:
        // 未声明的业态订单(拼团/酒店/遇搭…)不进常规导出,字段对不上。选了不在白名单里的插件类型,导出就是空的
        if (empty($p['type'])) {
            $exportable = array_values(array_unique(array_merge(
                [Order::TYPE_NORMAL],
                \app\services\order\OrderBehaviorRegistry::keysWith('exportable')
            )));
            $query->andWhere(['o.type' => $exportable]);
        }

        // 10. 预加载
        $query->with(['orderDetails.goods', 'user', 'mch', 'commonPayOrder']);

        return $query->orderBy('o.id DESC');
    }

    /**
     * 根据 Key 获取数据值 (保持原逻辑)
     */
    private function getFieldValue($order, $detail, $key)
    {
        switch ($key) {
            case 'id':
                return $order->id;
            case 'order_no':
                return $order->order_no;
            case 'transaction_id':
                return $order->commonPayOrder->transaction_id ?? '';
            case 'mch_name':
                return $order->mch->mch_name ?? '平台自营';
            case 'user_info':
                return ($order->user->nickname ?? '未知') . "(ID:{$order->user_id})";
            case 'referrer_info':
                // 订单上的推荐人快照(下单时的直属上级),昵称显示最新的;与列表一致
                $rid = (int)$order->referrer_id;
                return $rid > 0 ? (($this->referrerNames[$rid] ?? '') ?: '未命名') . "(ID:{$rid})" : '';
            case 'name':
                return $order->name;
            case 'mobile':
                return $order->mobile;
            case 'address':
                return $order->address;
            case 'status_text':
                // 这里如果原代码有 $order->getStatusText() 方法则直接调用，或者使用你提供的逻辑
                if (method_exists($order, 'getStatusText')) {
                    return $order->getStatusText();
                }
                // 简单的状态回落逻辑
                $statusMap = [
                    Order::STATUS_CLOSED => '已关闭',
                    Order::STATUS_UNPAID => '待付款',
                    Order::STATUS_TO_BE_SHIPPED => '待发货',
                    Order::STATUS_SHIPPED => '待收货',
                    Order::STATUS_COMPLETED => '已完成',
                ];
                return $statusMap[$order->status] ?? '未知';
            case 'pay_type':
                return $order->payment_name ?: '未支付';
            case 'created_at':
                return date('Y-m-d H:i:s', $order->created_at);
            case 'pay_at':
                return $order->pay_at ? date('Y-m-d H:i:s', $order->pay_at) : '-';
            case 'remarks':
                return $order->remarks;
            case 'pay_price':
                return $order->pay_price;
            case 'express_price':
                return $order->express_price;
            // --- 商品级字段 (从 $detail 获取) ---
            case 'goods_name':
                // 下单时的商品名(快照),老数据回退商品表;$detail 可能为空(订单没有明细时也占一行)
                return ($detail ? $detail->snapshotName() : '') ?: '商品已失效';
            case 'goods_short_name':
                return $detail->goods->short_name ?? '';
            case 'attr':
                return $this->formatAttr($detail->attr ?? '');
            case 'factory':
                return $detail->goods->factory ?? '';
            case 'price':
                return $detail->price ?? '0.00';
            case 'num':
                return $detail->num ?? 0;
            case 'total_price':
                return $detail->total_price ?? '0.00';
            case 'is_send':
                if (!$detail) return '';
                $isSent = $detail->is_send ? '已发货' : '待发货';
                if ($order->status == Order::STATUS_UNPAID) $isSent = '未付款';
                if ($order->refund_status != Order::REFUND_STATUS_NO_REFUND) $isSent = '售后中';
                return $isSent;
            default:
                return '';
        }
    }

    /** @param Order[] $orders */
    private function loadReferrerNames(array $orders)
    {
        $ids = [];
        foreach ($orders as $order) {
            $rid = (int)$order->referrer_id;
            if ($rid > 0 && !isset($this->referrerNames[$rid])) {
                $ids[$rid] = $rid;
            }
        }
        if ($ids) {
            $names = User::find()->select(['nickname', 'id'])->where(['id' => array_values($ids)])->indexBy('id')->column();
            foreach ($ids as $rid) {
                $this->referrerNames[$rid] = (string)($names[$rid] ?? '');
            }
        }
    }

    /**
     * 判断是否为订单基础信息字段（用于合并单元格，保持原逻辑）
     */
    private function isOrderLevelField($key)
    {
        // 这些字段是不合并的（每行商品独立显示），其他字段都合并
        $detailLevelFields = ['goods_name', 'goods_short_name', 'attr', 'factory', 'price', 'num', 'total_price', 'is_send'];
        return !in_array($key, $detailLevelFields);
    }

    private function formatAttr($attrJson)
    {
        if (empty($attrJson)) return '';
        try {
            $attrs = is_string($attrJson) ? Json::decode($attrJson) : $attrJson;
            if (!is_array($attrs)) return '';
            $parts = [];
            foreach ($attrs as $a) {
                $group = $a['attr_group_name'] ?? ($a['group'] ?? '');
                $value = $a['attr_name'] ?? ($a['value'] ?? '');
                if ($group || $value) $parts[] = "{$group}:{$value}";
            }
            return implode('; ', $parts);
        } catch (\Exception $e) {
            return '';
        }
    }

    private function finish($key, $url, $msg)
    {
        CacheHelper::set($key, [
            'status' => 'success',
            'msg' => $msg,
            'url' => $url,
            'percentage' => 100
        ], 3600);
    }
}