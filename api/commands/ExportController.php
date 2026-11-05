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

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Connection;
use yii\helpers\Console;
use yii\helpers\FileHelper;

/**
 * 导出指定表结构和数据为迁移文件
 */
class ExportController extends Controller
{
    /**
     * @var string 迁移文件保存路径
     */
    public $migrationPath = '@app/migrations';

    /**
     * @var Connection
     */
    private $db;

    public function init()
    {
        parent::init();
        $this->db = Yii::$app->db;
    }

    /**
     * 导出指定表的结构和数据
     * 使用方法: php yii export/table table_name1,table_name2
     * 例如: php yii export/table user,menu
     *
     * @param string $tables 表名，多个表用逗号分隔
     */
    public function actionTable($tables)
    {
        $tableList = explode(',', $tables);
        $upCode = "";
        $downCode = "";

        $this->stdout("开始导出...\n", Console::FG_YELLOW);

        foreach ($tableList as $tableName) {
            $tableName = trim($tableName);
            $this->stdout("正在处理表: {$tableName} ...\n", Console::FG_GREEN);
            $dataCode = $this->getData($tableName);
            if ($dataCode) {
                $upCode .= $dataCode;
            }
        }

        if (empty($upCode)) {
            $this->stdout("没有生成任何内容。\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        // 3. 生成文件名和内容
        // 如果只有一个表，文件名带表名；如果多个，用 "multiple_tables"
        $namePart = count($tableList) === 1 ? $tableList[0] : 'multiple_tables';
        // 去除表前缀，让文件名更好看
        if ($this->db->tablePrefix) {
            $namePart = str_replace($this->db->tablePrefix, '', $namePart);
        }

        $className = 'm' . gmdate('ymd_His') . '_export_' . $namePart;
        $content = $this->renderFileTemplate($className, $upCode, $downCode);

        // 4. 写入文件
        $path = Yii::getAlias($this->migrationPath);
        if (!is_dir($path)) {
            FileHelper::createDirectory($path);
        }
        $file = $path . DIRECTORY_SEPARATOR . $className . '.php';

        if (file_put_contents($file, $content)) {
            $this->stdout("\n成功！已生成迁移文件：\n{$file}\n", Console::FG_GREEN);
            return ExitCode::OK;
        } else {
            $this->stdout("\n错误：无法写入文件。\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * 获取表结构代码
     */
    private function getStructure($tableName)
    {
        $schema = $this->db->getSchema();
        $tableSchema = $schema->getTableSchema($tableName);

        // 处理表名
        $rawTableName = $this->db->tablePrefix ? str_replace($this->db->tablePrefix, '', $tableName) : $tableName;
        $tableNameStr = "{{%{$rawTableName}}}";

        // 生成字段定义
        $columnsCode = [];
        foreach ($tableSchema->columns as $column) {
            $columnsCode[] = $this->generateColumnCode($column);
        }
        $colsString = implode(",\n            ", $columnsCode);

        // Up 逻辑
        $up = "        // 1. 创建表结构: {$tableName}\n";
        $up .= "        \$this->createTable('{$tableNameStr}', [\n            {$colsString}\n        ]);\n\n";

        // 处理索引
        try {
            $indexes = $schema->getTableIndexes($tableName);
            foreach ($indexes as $index) {
                // 兼容对象/数组访问
                $isPrimary = is_object($index) ? $index->isPrimary : ($index['isPrimary'] ?? false);
                if ($isPrimary) continue;

                $isUnique = is_object($index) ? $index->isUnique : ($index['isUnique'] ?? false);
                $unique = $isUnique ? 'true' : 'false';

                $columns = is_object($index) ? $index->columnNames : ($index['columns'] ?? []);
                $cols = "['" . implode("', '", $columns) . "']";

                $idxName = is_object($index) ? $index->name : ($index['name'] ?? '');

                $up .= "        \$this->createIndex('{$idxName}', '{$tableNameStr}', {$cols}, {$unique});\n";
            }
        } catch (\Exception $e) {
            // 忽略索引错误
        }
        $up .= "\n";

        // Down 逻辑
        $down = "        \$this->dropTable('{$tableNameStr}');\n";

        return ['up' => $up, 'down' => $down];
    }

    /**
     * 获取表数据代码 (BatchInsert)
     */
    private function getData($tableName)
    {
        $rows = (new \yii\db\Query())->from($tableName)->all();

        if (empty($rows)) {
            return "";
        }

        $rawTableName = $this->db->tablePrefix ? str_replace($this->db->tablePrefix, '', $tableName) : $tableName;
        $tableNameStr = "{{%{$rawTableName}}}";

        // 获取列名
        $columns = array_keys($rows[0]);
        $colsStr = "['" . implode("', '", $columns) . "']";

        // 构建数据行
        $dataRows = [];
        foreach ($rows as $row) {
            $rowValues = [];
            foreach ($row as $val) {
                // 使用 var_export 自动处理 null, string(转义), int, float
                $rowValues[] = var_export($val, true);
            }
            $dataRows[] = "            [" . implode(", ", $rowValues) . "]";
        }

        $dataStr = implode(",\n", $dataRows);

        $code = "        // 2. 插入数据: {$tableName}\n";
        $code .= "        \$this->batchInsert('{$tableNameStr}', {$colsStr}, [\n{$dataStr}\n        ]);\n\n";

        return $code;
    }

    /**
     * 生成列定义代码 (复用之前的逻辑)
     */
    private function generateColumnCode($column)
    {
        $type = $column->type;
        $size = $column->size;
        $scale = $column->scale;
        $method = $type;
        $args = [];

        if ($column->autoIncrement) {
            $method = $type === 'bigint' ? 'bigPrimaryKey' : 'primaryKey';
        } else {
            if (in_array($type, ['string', 'char', 'integer', 'smallint', 'tinyint', 'bigint']) && $size) {
                $args[] = $size;
            } elseif (in_array($type, ['decimal', 'float', 'double']) && $size !== null) {
                $args[] = $size;
                if ($scale !== null) $args[] = $scale;
            }
        }

        $argStr = implode(', ', $args);
        $line = "'{$column->name}' => \$this->{$method}({$argStr})";

        if (!$column->autoIncrement) {
            if ($column->unsigned) $line .= "->unsigned()";
            $line .= $column->allowNull ? "->null()" : "->notNull()";

            if ($column->defaultValue !== null) {
                $defVal = $column->defaultValue;
                if (is_string($defVal)) {
                    $defVal = str_replace("'", "\'", $defVal);
                    // 简单判断数据库表达式
                    if (strpos($defVal, '(') !== false && strtoupper($defVal) !== $defVal) {
                        $line .= "->defaultExpression('{$defVal}')";
                    } else {
                        $line .= "->defaultValue('{$defVal}')";
                    }
                } elseif (is_bool($defVal)) {
                    $line .= "->defaultValue(" . ($defVal ? 1 : 0) . ")";
                } else {
                    $line .= "->defaultValue({$defVal})";
                }
            }

            if (!empty($column->comment)) {
                $cleanComment = str_replace("'", "\'", $column->comment);
                $line .= "->comment('{$cleanComment}')";
            }
        }
        return $line;
    }

    private function renderFileTemplate($className, $up, $down)
    {
        return <<<PHP
<?php

use yii\db\Migration;

/**
 * Auto-generated Migration via Console Command
 */
class {$className} extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
{$up}
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
{$down}
    }
}
PHP;
    }
}