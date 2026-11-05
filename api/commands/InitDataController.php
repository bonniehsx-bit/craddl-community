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

namespace app\commands;

use app\helpers\AddressHelper;
use app\helpers\CacheHelper;
use app\models\Address;
use app\models\BranchBank;
use yii\console\Controller;
use yii\console\ExitCode;
use Yii;

/**
 * 基础数据全局导入控制器
 * 一键导入或更新系统中所有静态基础数据 (如行政区划、银行联行号等) 
 * 预留了良好的扩展性，未来如需导入物流公司、币种等亦可轻松添加 action
 */
class InitDataController extends Controller
{
    /**
     * 一键执行所有基础数据的导入与清洗
     */
    public function actionIndex()
    {
        // 覆盖默认 PHP 30 秒执行限制，允许跑长线任务
        set_time_limit(0);

        // 关闭数据库日志和性能分析缓存，防止长线任务吃爆内存
        Yii::$app->db->enableLogging = false;
        Yii::$app->db->enableProfiling = false;

        $this->stdout("========== 开始初始化系统基础数据 ==========\n");
        $this->stdout("正在执行各项数据重置与灌入，如果数据量大可能需要些时间...\n");

        $addressCode = $this->actionAddress();
        if ($addressCode !== ExitCode::OK) {
            $this->stderr("❌ [失败] 地址数据导入中断，错误码：{$addressCode}\n");
            return $addressCode;
        }

        $bankCode = $this->actionBranchBank();
        if ($bankCode !== ExitCode::OK) {
            $this->stderr("❌ [失败] 银行联行号数据导入中断，错误码：{$bankCode}\n");
            return $bankCode;
        }

        $this->stdout("========== 基础数据初始化全部完美竣工！ ==========\n");
        return ExitCode::OK;
    }

    /**
     * 导入微信官方行政区划数据 (并自动修复直筒子市区县)
     */
    public function actionAddress()
    {
        // 1. 清空旧表
        $this->stdout("\n--- 阶段1/2: 重置并导入全国行政区划 (Address) ---\n");
        $this->stdout("清理残留旧表...\n");
        Yii::$app->db->createCommand()->truncateTable(Address::tableName())->execute();

        $csvFile = Yii::getAlias('@app/commands/address.csv');

        if (!file_exists($csvFile)) {
            $this->stderr("错误: 地址源 CSV 文件丢失! - {$csvFile}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // 2. 纯净的 CSV 流式解析逻辑
        $this->stdout("挂载 CSV 文件，开始极高速流式读取...\n");

        if (function_exists('exec') && DIRECTORY_SEPARATOR === '/') {
            $lineCount = trim(exec("wc -l " . escapeshellarg($csvFile)));
            $this->stdout("文件体量估计 (wc -l): {$lineCount} 行记录\n");
        }

        $handle = fopen($csvFile, 'r');
        $headerLine = fgetcsv($handle);

        $isGbk = false;
        if (!$this->isUtf8($headerLine[0])) {
            $isGbk = true;
            $this->stdout("⚠️ 检测到非主流的 GBK 字符集，自动激活 UTF-8 转码防御模块\n");
            foreach ($headerLine as &$v) $v = $this->convertEncoding($v);
        }

        $headerMap = [];
        foreach ($headerLine as $index => $colName) {
            $headerMap[trim($colName)] = $index;
        }

        $dbMap = [
            'province_code' => 'CODE_PROV',
            'province_name' => 'NAME_PROV',
            'city_code' => 'CODE_CITY',
            'city_name' => 'NAME_CITY',
            'county_code' => 'CODE_COUN',
            'county_name' => 'NAME_COUN',
            'town_code' => 'CODE_TOWN',
            'town_name' => 'NAME_TOWN',
        ];
        $dbColumns = array_keys($dbMap);
        $buffer = [];
        $totalInserted = 0;
        $currentRowNum = 1;
        $chunkSize = 2500;

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $currentRowNum++;

                if (empty($row) || (count($row) === 1 && $row[0] === null)) continue;

                if ($isGbk) {
                    foreach ($row as &$val) $val = $this->convertEncoding($val);
                }

                $newRow = [];
                foreach ($dbColumns as $dbCol) {
                    $csvColName = $dbMap[$dbCol];
                    if (isset($headerMap[$csvColName])) {
                        $index = $headerMap[$csvColName];
                        $newRow[] = isset($row[$index]) ? (string)trim($row[$index]) : '';
                    } else {
                        $newRow[] = '';
                    }
                }

                $buffer[] = $newRow;
                $totalInserted++;

                if (count($buffer) >= $chunkSize) {
                    $this->stdout("... 已极速入库 {$totalInserted} 块数据拼图...\r");
                    Yii::$app->db->createCommand()->batchInsert(Address::tableName(), $dbColumns, $buffer)->execute();
                    $buffer = [];
                    // 强制清空日志内存与GC
                    if (Yii::$app->log->getLogger()) {
                        Yii::$app->log->getLogger()->flush(true);
                    }
                    if (function_exists('gc_collect_cycles')) gc_collect_cycles();
                }
            }

            if (!empty($buffer)) {
                $this->stdout("... 已极速入库 {$totalInserted} 块数据拼图...\r");
                Yii::$app->db->createCommand()->batchInsert(Address::tableName(), $dbColumns, $buffer)->execute();
            }
            fclose($handle);

            $this->stdout("\n基础碎片载入完成，开始重组变异区划...\n");

            // =================================================================
            // [核心新增] 数据全部导入后，执行 SQL 修正直筒子市数据
            // =================================================================
            $tableName = Address::tableName();

            $alterSql = "ALTER TABLE {$tableName} MODIFY COLUMN `county_code` VARCHAR(20) DEFAULT '' COMMENT '区县编码'";
            Yii::$app->db->createCommand($alterSql)->execute();

            $updateSql = "UPDATE {$tableName}
                          SET 
                              `county_name` = `town_name`,
                              `county_code` = `town_code`,
                              `town_code`   = CONCAT(`town_code`, '001')
                          WHERE 
                              `city_code` IN ('441900', '442000', '460400', '620200') 
                              AND `town_code` != '' 
                              AND `town_code` IS NOT NULL";

            $affectedRows = Yii::$app->db->createCommand($updateSql)->execute();
            $this->stdout("-> 广东直筒子市修复！受影响行数: {$affectedRows}\n");

            $updateSql = "UPDATE {$tableName} SET `city_name` = '重庆市县' WHERE `city_code` IN ('500200')";
            $affectedRows = Yii::$app->db->createCommand($updateSql)->execute();

            $updateSql = "UPDATE {$tableName} SET `city_name` = '重庆市区' WHERE `city_code` IN ('500100')";
            $affectedRows += Yii::$app->db->createCommand($updateSql)->execute();
            $this->stdout("-> 重庆市县辖区修复！受影响行数: {$affectedRows}\n");
        } catch (\Exception $e) {
            $this->stderr("致命异常: " . $e->getMessage() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // 清空行政区划缓存，触发下一位请求重新拉取
        CacheHelper::remove(AddressHelper::ADDRESS_DATA);
        CacheHelper::remove(AddressHelper::AREA_DATA);

        $this->stdout("🎉 阶段1 完美完成！\n");
        return ExitCode::OK;
    }

    /**
     * 导入银联全网支行联行号数据
     */
    public function actionBranchBank()
    {
        $this->stdout("\n--- 阶段2/2: 重置并导入全量支行联行号 (Branch Bank) ---\n");
        Yii::$app->db->createCommand()->truncateTable(BranchBank::tableName())->execute();
        $csvFile = Yii::getAlias('@app/commands/branch_bank.csv');

        if (!file_exists($csvFile)) {
            $this->stderr("错误: 银行网点数据源不存在! - {$csvFile}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (function_exists('exec') && DIRECTORY_SEPARATOR === '/') {
            $lineCount = trim(exec("wc -l " . escapeshellarg($csvFile)));
            $this->stdout("文件体量估计 (wc -l): {$lineCount} 行记录\n");
        }

        $handle = fopen($csvFile, 'r');
        $headerLine = fgetcsv($handle);

        $isGbk = false;
        if (!$this->isUtf8($headerLine[0])) {
            $isGbk = true;
            $this->stdout("⚠️ 检测到非主流的 GBK 字符集，自动激活 UTF-8 转码防御模块\n");
            foreach ($headerLine as &$v) $v = $this->convertEncoding($v);
        }

        $headerMap = [];
        foreach ($headerLine as $index => $colName) {
            $headerMap[trim($colName)] = $index;
        }

        $dbMap = [
            'branch_name'   => '开户支行名称',
            'branch_code'   => '开户支行联行号',
            'province_code' => '省编码',
            'city_code'     => '市编码',
        ];

        $dbColumns = array_keys($dbMap);
        $extraColumns = ['is_delete', 'created_at', 'created_time', 'updated_at', 'updated_time'];
        $finalDbColumns = array_merge($dbColumns, $extraColumns);

        $buffer = [];
        $totalInserted = 0;
        $currentRowNum = 1;
        $skippedEmpty = 0;
        $skippedError = 0;
        $chunkSize = 2500;

        $this->stdout("数据流载入中，开始清洗并建立索引...\n");

        while (($row = fgetcsv($handle)) !== false) {
            $currentRowNum++;

            if (empty($row) || (count($row) === 1 && $row[0] === null)) {
                $skippedEmpty++;
                continue;
            }

            if ($isGbk) {
                foreach ($row as &$val) $val = $this->convertEncoding($val);
            }

            $newRow = [];
            $isValidRow = true;

            foreach ($dbColumns as $dbCol) {
                $csvColName = $dbMap[$dbCol];
                $index = $headerMap[$csvColName];
                $val = isset($row[$index]) ? trim($row[$index]) : '';

                if ($dbCol === 'branch_name' && $val === '') {
                    $isValidRow = false;
                    break;
                }
                $newRow[] = (string)$val;
            }

            if (!$isValidRow) {
                $skippedError++;
                continue;
            }

            // 填充审计字段
            $newRow[] = 0;
            $newRow[] = time();
            $newRow[] = date('Y-m-d H:i:s');
            $newRow[] = time();
            $newRow[] = date('Y-m-d H:i:s');

            $buffer[] = $newRow;
            $totalInserted++;

            if (count($buffer) >= $chunkSize) {
                $this->batchInsertDB($finalDbColumns, $buffer, $totalInserted);
                $buffer = [];
                // 强制清空日志内存与GC防止OOM
                if (Yii::$app->log->getLogger()) {
                    Yii::$app->log->getLogger()->flush(true);
                }
                if (function_exists('gc_collect_cycles')) gc_collect_cycles();
            }
        }

        if (!empty($buffer)) {
            $this->batchInsertDB($finalDbColumns, $buffer, $totalInserted);
        }

        fclose($handle);

        $this->stdout("\n------------------------------------------\n");
        $this->stdout("📊 [银行网点数据质量报告]\n");
        $this->stdout("原文件测录总行数: {$currentRowNum}\n");
        $this->stdout("成功清洗并入库数: {$totalInserted}\n");
        $this->stdout("过滤无用空行: {$skippedEmpty}\n");
        $this->stdout("抛弃问题脏据: {$skippedError}\n");

        $sum = $totalInserted + $skippedEmpty + $skippedError + 1;
        if ($sum != $currentRowNum) {
            $this->stdout("警告提示：行记录校验不匹，可能存在回车截断或未闭合引号。\n");
        }
        $this->stdout("------------------------------------------\n");

        $this->stdout("🎉 阶段2 完美完成！\n");
        return ExitCode::OK;
    }

    /**
     * 内部批量写入函数
     */
    private function batchInsertDB($columns, $data, $currentTotal)
    {
        $count = count($data);
        $this->stdout("... 批量吸入 {$count} 条记录 (目前累充: {$currentTotal})...\r");
        Yii::$app->db->createCommand()->batchInsert(BranchBank::tableName(), $columns, $data)->execute();
    }

    private function isUtf8($str)
    {
        return mb_check_encoding($str, 'UTF-8');
    }
    private function convertEncoding($str)
    {
        return iconv('GBK', 'UTF-8//IGNORE', $str);
    }
}
