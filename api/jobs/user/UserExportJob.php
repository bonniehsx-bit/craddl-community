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

namespace app\jobs\user;

use app\core\Job;
use app\helpers\CacheHelper;
use app\helpers\ServerHelper;
use app\models\Level;
use app\models\Order;
use app\models\User;
use app\models\UserPath;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Yii;
use yii\db\Expression;
use yii\helpers\ArrayHelper;
use yii\helpers\FileHelper;
use yii\helpers\Json;

class UserExportJob extends Job
{
    public $params;      // 搜索参数 + export_fields
    public $token;       // 任务Token
    public $mall_id;     // 商城ID

    /**
     * 定义支持导出的字段映射
     */
    private $fieldMap = [
        'id'                => '用户ID',
        'nickname'          => '用户昵称',
        'mobile'            => '手机号',
        'platform_text'     => '注册平台',
        'level_name'        => '会员等级',
        'parent_name'       => '推荐人',
        'created_at'        => '注册时间',
        'money'             => '余额',
        'integral'          => '当前积分',
        'total_order_price' => '累计消费金额',
        'order_num'         => '累计订单数',
        'status_text'       => '账号状态',
        'remarks'           => '后台备注',
    ];

    public function execute($params = null)
    {
        $cacheKey = 'user_export_status_' . $this->token;

        // 导出任务取消时间限制，调大内存
        set_time_limit(0);
        ini_set('memory_limit', '2048M');

        try {
            // 1. 初始化状态
            $this->updateStatus($cacheKey, 'processing', '正在准备数据...', 0);

            // 2. 解析自定义字段
            $exportFields = [];
            if (!empty($this->params['export_fields'])) {
                $exportFields = Json::decode($this->params['export_fields']);
            }
            // 默认全选
            if (empty($exportFields)) {
                $exportFields = array_keys($this->fieldMap);
            }

            // 3. 准备文件路径
            $absRoot = ServerHelper::getAbsFilePath();
            $fileName = '用户列表_' . date('YmdHis') . '_' . rand(1000, 9999) . '.xlsx';
            $relativeDir = '/temp/export/user/' . date('Ym/d');
            $absoluteDir = $absRoot . $relativeDir;

            if (!is_dir($absoluteDir)) {
                FileHelper::createDirectory($absoluteDir, 0777);
            }
            $filePath = $absoluteDir . '/' . $fileName;
            $fileUrl = ServerHelper::getHost() . $relativeDir . '/' . $fileName;

            // 4. 创建 Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('用户列表');

            // --- 动态写入表头 ---
            $colHeader = 'A';
            foreach ($exportFields as $fieldKey) {
                $cell = $colHeader . '1';
                $sheet->setCellValue($cell, $this->fieldMap[$fieldKey] ?? $fieldKey);
                $sheet->getStyle($cell)->getFont()->setBold(true);
                $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

                // 简单调整列宽
                if (in_array($fieldKey, ['nickname', 'parent_name', 'created_at', 'mobile'])) {
                    $sheet->getColumnDimension($colHeader)->setWidth(20);
                } else {
                    $sheet->getColumnDimension($colHeader)->setWidth(15);
                }
                $colHeader++;
            }

            // 5. 准备基础数据 (等级Map)
            $levelsMap = Level::find()
                ->where(['mall_id' => $this->mall_id, 'is_delete' => 0])
                ->select(['id', 'name'])
                ->indexBy('id')
                ->asArray()
                ->all();

            // 6. 构建查询
            $query = $this->buildQuery();
            $total = (int)$query->count();

            if ($total > 0) {
                $batchSize = 100;
                $processed = 0;
                $currentRow = 2;

                foreach ($query->batch($batchSize) as $users) {
                    // --- [性能核心] 批量查询当前批次的订单统计，避免 N+1 ---
                    $userIds = ArrayHelper::getColumn($users, 'id');
                    $orderStats = $this->getBatchOrderStats($userIds);

                    foreach ($users as $user) {
                        /** @var User $user */
                        $stats = $orderStats[$user->id] ?? ['total_price' => 0, 'total_count' => 0];

                        // 写入行数据
                        $currentCol = 'A';
                        foreach ($exportFields as $fieldKey) {
                            $value = $this->getFieldValue($user, $stats, $levelsMap, $fieldKey);

                            // 长数字处理
                            if (in_array($fieldKey, ['mobile', 'id'])) {
                                $sheet->setCellValueExplicit($currentCol . $currentRow, $value, DataType::TYPE_STRING);
                            } else {
                                $sheet->setCellValue($currentCol . $currentRow, $value);
                            }
                            $currentCol++;
                        }
                        $currentRow++;
                        $processed++;
                    }

                    // 更新进度
                    $percent = min(99, round(($processed / $total) * 100));
                    $this->updateStatus($cacheKey, 'processing', "已导出 {$processed}/{$total}", $percent);

                    // 释放内存
                    unset($users, $orderStats);
                }
            }

            // 7. 保存文件
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save($filePath);

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            // 8. 完成
            $this->updateStatus($cacheKey, 'success', '导出成功', 100, $fileUrl);
        } catch (\Throwable $e) {
            $msg = "导出异常: " . $e->getMessage();
            Yii::error($msg);
            $this->updateStatus($cacheKey, 'failed', $msg, 0);
        }
    }

    /**
     * 获取单元格具体数值
     */
    private function getFieldValue(User $user, $stats, $levelsMap, $key)
    {
        switch ($key) {
            case 'id':
                return $user->id;
            case 'nickname':
                return $user->nickname;
            case 'mobile':
                return $user->mobile;
            case 'platform_text':
                return $this->getPlatformText($user->platform);
            case 'level_name':
                return $levelsMap[$user->level_id]['name'] ?? '默认等级';
            case 'parent_name':
                // 因为 query 预加载了 inviter，这里直接取
                return $user->inviter ? $user->inviter->nickname : ($user->parent_id ? "ID:{$user->parent_id}" : '无');
            case 'created_at':
                return date('Y-m-d H:i:s', $user->created_at);
            case 'money':
                return $user->money;
            case 'integral':
                return $user->integral;
            case 'total_order_price':
                return $stats['total_price'];
            case 'order_num':
                return $stats['total_count'];
            case 'status_text':
                $map = [0 => '正常', 1 => '拉黑', 2 => '注销'];
                return $map[$user->status] ?? '未知';
            case 'remarks':
                return $user->remarks;
            default:
                return '';
        }
    }

    /**
     * [性能优化] 批量获取订单统计
     */
    private function getBatchOrderStats($userIds)
    {
        if (empty($userIds)) return [];

        return Order::find()
            ->select([
                'user_id',
                'total_price' => new Expression('SUM(pay_price - refund_price)'),
                'total_count' => new Expression('COUNT(*)')
            ])
            ->where([
                'mall_id' => $this->mall_id,
                'user_id' => $userIds,
                'is_delete' => 0,
                'status' => Order::STATUS_COMPLETED, // 仅统计已完成
            ])
            ->groupBy('user_id')
            ->asArray()
            ->indexBy('user_id')
            ->all();
    }

    /**
     * 构建查询 (逻辑复用 UserListForm)
     */
    private function buildQuery()
    {
        $query = User::find()->alias('u')->where(['u.mall_id' => $this->mall_id]);

        $p = $this->params;

        // [修改] 状态筛选逻辑
        if (isset($p['status']) && $p['status'] == User::STATUS_DELETE) {
            $query->andWhere(['u.is_delete' => 1]);
        } else {
            $query->andWhere(['u.is_delete' => 0]);
        }

        // 1. 基础筛选 (自动过滤空值)
        $query->andFilterWhere(['u.id' => $p['user_id'] ?? null])
            ->andFilterWhere(['u.parent_id' => $p['parent_id'] ?? null])
            ->andFilterWhere(['u.level_id' => $p['level_id'] ?? null])
            ->andFilterWhere(['u.status' => $p['status'] ?? null]) // [新增]
            ->andFilterWhere(['like', 'u.mobile', $p['mobile'] ?? null]);

        // 2. 昵称筛选
        if (!empty($p['nickname'])) {
            $query->andWhere(['like', 'u.nickname', $p['nickname']]);
        }

        // 3. 团队长筛选
        if (!empty($p['top_parent_id'])) {
            $subQuery = UserPath::find()
                ->where(['like', 'path', \app\services\UserService::subtreeLike($p['top_parent_id']), false])
                ->andWhere('user_id = u.id')
                ->andWhere(['!=', 'user_id', $p['top_parent_id']]);
            $query->andWhere(['exists', $subQuery]);
        }

        // 4. 时间筛选
        if (!empty($p['search_date']) && is_array($p['search_date'])) {
            $begin = strtotime($p['search_date'][0]);
            $end = strtotime($p['search_date'][1] . ' 23:59:59');
            $query->andWhere(['between', 'u.created_at', $begin, $end]);
        }

        // 5. 预加载推荐人，防止循环查库
        $query->with(['inviter']);

        // 6. 排序
        $query->orderBy(['u.id' => SORT_DESC]);

        return $query;
    }

    private function getPlatformText($platform)
    {
        $map = [
            'wxapp' => '微信小程序',
            'wechat' => '微信公众号',
            'mobile' => '手机号',
            'aliapp' => '支付宝',
            'ttapp' => '头条/抖音',
            'bdapp' => '百度'
        ];
        return $map[$platform] ?? $platform;
    }

    private function updateStatus($key, $status, $msg, $percent, $url = '')
    {
        CacheHelper::set($key, [
            'status' => $status,
            'msg' => $msg,
            'percentage' => $percent,
            'url' => $url
        ], 3600);
    }
}
