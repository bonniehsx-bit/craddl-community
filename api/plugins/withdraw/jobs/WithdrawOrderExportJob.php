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

namespace app\plugins\withdraw\jobs;

use app\core\Job;
use app\funds\BaseFund;
use app\helpers\CacheHelper;
use app\helpers\ServerHelper;
use app\models\User;
use app\plugins\withdraw\models\WithdrawOrder;
use app\plugins\withdraw\services\AssetService;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Yii;
use yii\helpers\FileHelper;

/**
 * 提现记录异步导出（对齐老 cash 导出：token + 队列 Job + cache 进度 + Excel）。
 */
class WithdrawOrderExportJob extends Job
{
    public $params;   // 搜索参数（同 OrderListForm 筛选）
    public $token;    // 任务标识
    public $mall_id;  // 商城ID

    const CACHE_PREFIX = 'withdraw_export_status_';

    private $fundNameCache = [];

    public function execute($params = null)
    {
        $cacheKey = self::CACHE_PREFIX . $this->token;
        $this->updateStatus($cacheKey, 'processing', '正在准备数据...', 0);
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            $query = $this->buildQuery();
            $total = (int)$query->count();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('提现记录');

            $headers = [
                'A' => 'ID', 'B' => '提现单号', 'C' => '用户昵称', 'D' => '用户手机', 'E' => '用户ID',
                'F' => '用户类型', 'G' => '提现渠道', 'H' => '打款网关', 'I' => '资金来源',
                'J' => '收款信息', 'K' => '申请金额', 'L' => '手续费', 'M' => '税费', 'N' => '实际到账',
                'O' => '状态', 'P' => '打款单号', 'Q' => '打款凭证', 'R' => '审核备注', 'S' => '失败原因',
                'T' => '申请时间', 'U' => '完成时间',
            ];
            foreach ($headers as $col => $text) {
                $sheet->setCellValue($col . '1', $text);
                $sheet->getColumnDimension($col)->setWidth(16);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
                $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            $sheet->getColumnDimension('B')->setWidth(26);
            $sheet->getColumnDimension('J')->setWidth(40);
            $sheet->getColumnDimension('Q')->setWidth(30);

            if ($total > 0) {
                $row = 2;
                $processed = 0;
                foreach ($query->batch(100) as $items) {
                    // 批量取用户，避免 N+1
                    $uids = array_unique(array_map(function ($o) { return $o->user_id; }, $items));
                    $users = $uids ? User::find()->select(['id', 'nickname', 'mobile'])->where(['id' => $uids])->indexBy('id')->asArray()->all() : [];

                    foreach ($items as $item) {
                        /** @var WithdrawOrder $item */
                        $u = $users[$item->user_id] ?? [];
                        $sheet->setCellValue('A' . $row, $item->id);
                        $sheet->setCellValueExplicit('B' . $row, $item->order_no, DataType::TYPE_STRING);
                        $sheet->setCellValue('C' . $row, $u['nickname'] ?? '');
                        $sheet->setCellValueExplicit('D' . $row, (string)($u['mobile'] ?? ''), DataType::TYPE_STRING);
                        $sheet->setCellValue('E' . $row, $item->user_id);
                        $sheet->setCellValue('F' . $row, $item->user_type === 'merchant' ? '商户' : '普通用户');
                        $sheet->setCellValue('G' . $row, $item->channel_name);
                        $sheet->setCellValue('H' . $row, $this->fundName($item->fund_class));
                        $sheet->setCellValue('I' . $row, AssetService::label($item->asset_type));
                        $sheet->setCellValue('J' . $row, $this->payoutStr($item));
                        $sheet->setCellValue('K' . $row, $item->amount);
                        $sheet->setCellValue('L' . $row, $item->fee);
                        $sheet->setCellValue('M' . $row, $item->tax);
                        $sheet->setCellValue('N' . $row, $item->actual_amount);
                        $sheet->setCellValue('O' . $row, $item->getStatusText());
                        $sheet->setCellValueExplicit('P' . $row, (string)$item->payout_no, DataType::TYPE_STRING);
                        $sheet->setCellValue('Q' . $row, $item->receipt);
                        $sheet->setCellValue('R' . $row, $item->audit_remark);
                        $sheet->setCellValue('S' . $row, $item->fail_reason);
                        $sheet->setCellValue('T' . $row, $item->created_at ? date('Y-m-d H:i:s', (int)$item->created_at) : '');
                        $sheet->setCellValue('U' . $row, $item->finish_at ? date('Y-m-d H:i:s', (int)$item->finish_at) : '');
                        $sheet->getStyle('J' . $row)->getAlignment()->setWrapText(true);
                        $row++;
                        $processed++;
                    }
                    $percent = min(99, (int)round(($processed / max(1, $total)) * 100));
                    $this->updateStatus($cacheKey, 'processing', "已处理 {$processed}/{$total} 条", $percent);
                    unset($items, $users);
                }
            }

            $this->updateStatus($cacheKey, 'processing', '正在保存文件...', 99);
            $relativeDir = '/temp/export/' . date('Ym/d');
            $absoluteDir = ServerHelper::getAbsFilePath() . $relativeDir;
            if (!is_dir($absoluteDir)) {
                FileHelper::createDirectory($absoluteDir);
            }
            $fileName = '提现记录_' . date('YmdHis') . '_' . rand(1000, 9999) . '.xlsx';
            IOFactory::createWriter($spreadsheet, 'Xlsx')->save($absoluteDir . '/' . $fileName);
            $fileUrl = ServerHelper::getHost() . $relativeDir . '/' . $fileName;

            $this->updateStatus($cacheKey, 'success', '导出成功', 100, $fileUrl);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        } catch (\Throwable $e) {
            Yii::error('[withdraw] 提现记录导出失败: ' . $e->getMessage(), 'withdraw');
            $this->updateStatus($cacheKey, 'failed', '导出失败: ' . $e->getMessage(), 0);
        }
    }

    /** 与 OrderListForm 同款筛选 */
    private function buildQuery()
    {
        $p = $this->params;
        $query = WithdrawOrder::find()->alias('o')
            ->where(['o.mall_id' => $this->mall_id, 'o.is_delete' => 0]);

        if (!empty($p['order_no'])) {
            $query->andWhere(['like', 'o.order_no', $p['order_no']]);
        }
        if (isset($p['status']) && $p['status'] !== '' && $p['status'] !== null) {
            $query->andWhere(['o.status' => (int)$p['status']]);
        }
        if (!empty($p['asset_type'])) {
            $query->andWhere(['o.asset_type' => $p['asset_type']]);
        }
        // 与列表页同步:按提现渠道筛选(用 channel_id,渠道改名后历史单仍能筛到)
        if (!empty($p['channel_id'])) {
            $query->andWhere(['o.channel_id' => (int)$p['channel_id']]);
        }
        if (!empty($p['user_type'])) {
            $query->andWhere(['o.user_type' => $p['user_type']]);
        }
        if (!empty($p['user_keyword'])) {
            $uids = User::find()->select('id')
                ->where(['mall_id' => $this->mall_id])
                ->andWhere(['or', ['like', 'nickname', $p['user_keyword']], ['like', 'mobile', $p['user_keyword']]])
                ->column();
            $query->andWhere(['o.user_id' => $uids ?: [0]]);
        }
        return $query->orderBy('o.id DESC');
    }

    private function payoutStr(WithdrawOrder $item): string
    {
        $parts = [];
        foreach ($item->getPayoutInfoList() as $info) {
            if (!empty($info['value'])) {
                $parts[] = ($info['label'] ?? '') . '：' . $info['value'];
            }
        }
        return implode("\n", $parts);
    }

    private function fundName(string $fundClass): string
    {
        if (isset($this->fundNameCache[$fundClass])) {
            return $this->fundNameCache[$fundClass];
        }
        $name = '';
        if (class_exists($fundClass) && is_subclass_of($fundClass, BaseFund::class)) {
            try {
                $fund = Yii::createObject($fundClass, [(int)$this->mall_id]);
                $d = $fund->getDisplay();
                $name = is_array($d) ? ($d['name'] ?? '') : '';
            } catch (\Throwable $e) {
                $name = '';
            }
        }
        return $this->fundNameCache[$fundClass] = $name;
    }

    private function updateStatus($key, $status, $msg, $percent, $url = '')
    {
        CacheHelper::set($key, [
            'status' => $status, 'msg' => $msg, 'percentage' => $percent, 'url' => $url,
        ], 3600);
    }
}
