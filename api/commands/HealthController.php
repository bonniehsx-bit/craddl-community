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

use app\models\User;
use app\models\UserPath;
use app\services\UserService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * 系统健康检查与修复工具
 */
class HealthController extends Controller
{
    /**
     * 检查并修复用户关系链 (基于 UserPath)
     *
     * 使用方法:
     * 1. 仅检查: php yii health/check-user-relation
     * 2. 检查并自动修复: php yii health/check-user-relation --fix=true
     */
    public function actionCheckUserRelation($fix = false)
    {
        $this->stdout("====================================================\n");
        $this->stdout("开始执行用户关系链健康检查 (UserPath)...\n");
        $this->stdout("====================================================\n\n");

        $db = \Yii::$app->db;

        // 步骤1: 找出有 parent_id 但没有 UserPath 记录的用户
        $this->stdout("步骤 1/4: 查找缺失 Path 的用户...\n");
        $missingPath = User::find()->alias('u')
            ->leftJoin(UserPath::tableName() . ' p', 'p.user_id = u.id')
            ->where(['>', 'u.parent_id', 0])
            ->andWhere(['u.is_delete' => 0])
            ->andWhere(['p.user_id' => null])
            ->select('u.id')->column();
        $this->stdout("  缺失 Path: " . count($missingPath) . " 个\n\n", count($missingPath) > 0 ? Console::FG_RED : Console::FG_GREEN);

        // 步骤2: 找出 Path 与 parent_id 不一致的用户
        $this->stdout("步骤 2/4: 查找 Path 不一致的用户 (抽样100)...\n");
        $inconsistent = [];
        $sampleUsers = User::find()
            ->where(['>', 'parent_id', 0])
            ->andWhere(['is_delete' => 0])
            ->orderBy(new \yii\db\Expression('RAND()'))
            ->limit(100)->all();

        foreach ($sampleUsers as $user) {
            $path = UserPath::findOne(['user_id' => $user->id]);
            if ($path && strpos($path->path, "-{$user->parent_id}-") === false) {
                $inconsistent[] = $user->id;
            }
        }
        $this->stdout("  不一致: " . count($inconsistent) . "/100\n\n", count($inconsistent) > 0 ? Console::FG_RED : Console::FG_GREEN);

        $allProblems = array_unique(array_merge($missingPath, $inconsistent));
        $problemCount = count($allProblems);

        if ($problemCount == 0) {
            $this->stdout("检查完成，所有用户关系链均正确！\n\n", Console::FG_GREEN);
            return ExitCode::OK;
        }

        $this->stdout("发现 {$problemCount} 个用户的 Path 存在问题。\n", Console::FG_RED);

        if (!$fix) {
            $this->stdout("如需修复，请添加 --fix=true 参数。\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // 步骤3: 修复
        $this->stdout("步骤 3/4: 执行 ensurePath 自愈修复...\n", Console::FG_CYAN);
        $fixed = 0;
        foreach ($allProblems as $userId) {
            UserService::ensurePath($userId);
            $fixed++;
            if ($fixed % 50 == 0) $this->stdout("  已修复 {$fixed}...\n");
        }

        $this->stdout("\n步骤 4/4: 修复完成，共修复 {$fixed} 个用户。\n", Console::FG_GREEN);
        return ExitCode::OK;
    }
}
