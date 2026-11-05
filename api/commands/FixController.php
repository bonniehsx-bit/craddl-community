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

use app\helpers\PluginHelper;
use app\models\Plugin;
use app\models\User;
use app\models\UserPath;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use Yii;

/**
 * 运维修复控制器
 *
 * php yii fix/relation            全量重建 UserPath
 * php yii fix/reset-passwords     重置开发环境所有账号密码
 * php yii fix/plugin-logos        修复插件 logo URL（旧格式 → statics 懒加载格式）
 * php yii fix/plugin-logos 1      预览模式，不实际写库
 */
class FixController extends Controller
{
    public function actionRelation()
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        $db = Yii::$app->db;

        $this->stdout(">>> 开始全量重建 UserPath <<<\n", Console::FG_YELLOW);

        // Step 1: 预载入全量数据到内存
        $this->stdout("1. 正在加载用户索引到内存...\n");
        $allUsers = $db->createCommand("SELECT id, parent_id, mall_id FROM " . User::tableName())->queryAll();

        $parentMap = [];
        $mallMap = [];
        foreach ($allUsers as $u) {
            $id = (int)$u['id'];
            $parentMap[$id] = (int)$u['parent_id'];
            $mallMap[$id] = (int)$u['mall_id'];
        }
        $countUsers = count($parentMap);
        unset($allUsers);
        $this->stdout("  加载 {$countUsers} 个用户\n\n");

        $batchSize = 5000;
        $pathBuffer = [];
        $processed = 0;
        $errorCount = 0;
        $now = time();

        // Step 2: 先构建全部关系链,并据此统计每个用户的 team_count
        // team_count 定义对齐 UserService::getTeamCount —— 以该用户为祖先的后代数(不含自己、含间推全层)。
        // 做法:遍历每个用户的 path,path 去掉末位(自己)后余下都是其祖先,给每个祖先 +1。
        $this->stdout("2. 正在构建关系链 + 统计 team_count...\n");
        $pathMap = [];
        $teamCount = [];
        foreach ($parentMap as $userId => $parentId) {
            $pathStr = $this->buildPathString($userId, $parentMap, $errorCount);
            $pathMap[$userId] = $pathStr;

            $chain = explode('-', trim($pathStr, '-'));
            array_pop($chain); // 去掉末位 = 自己
            foreach ($chain as $anc) {
                $anc = (int)$anc;
                if ($anc > 0) {
                    $teamCount[$anc] = ($teamCount[$anc] ?? 0) + 1;
                }
            }
        }

        // Step 3: 重置并写入(path + team_count 一次落库,upsert 抗前台并发补建)
        $this->stdout("3. 正在重置并写入 UserPath(含 team_count)...\n");
        $db->createCommand()->truncateTable(UserPath::tableName())->execute();

        Console::startProgress(0, $countUsers);
        foreach ($pathMap as $userId => $pathStr) {
            $processed++;

            $pathBuffer[] = [
                $userId,
                $mallMap[$userId] ?? 0,
                $pathStr,
                $teamCount[$userId] ?? 0,
                $now,
                $now
            ];

            if (count($pathBuffer) >= $batchSize) {
                $this->batchInsertPath($pathBuffer);
                $pathBuffer = [];
            }

            if ($processed % 1000 == 0) Console::updateProgress($processed, $countUsers);
        }
        $this->batchInsertPath($pathBuffer);
        Console::endProgress();

        $this->stdout("\n\n>>> 全部修复完成! 共处理 {$countUsers} 个用户 <<<\n", Console::FG_GREEN, Console::BOLD);
        if ($errorCount > 0) {
            $this->stdout("注意: 发现并截断了 {$errorCount} 个死循环关系。\n", Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    /** @var int fix/user-path:限定商城,0=全部 */
    public $mallId = 0;
    /** @var int fix/user-path:1=只体检不写库(默认),0=真修 */
    public $dryRun = 1;
    /** @var int fix/user-path:打印几条差异样例 */
    public $show = 5;

    /**
     * 声明命令行选项。Yii 的 options() 绑的是控制器的**公共属性**(不是 action 的方法参数),
     * 不声明的话 --mallId 会直接报"未知的选项"。
     */
    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID === 'user-path') {
            $options = array_merge($options, ['mallId', 'dryRun', 'show']);
        }
        return $options;
    }

    /**
     * 增量校正 user_path —— 只改错的行,不清表。
     *
     *   php yii fix/user-path                 只体检,不写库(默认)
     *   php yii fix/user-path --dryRun=0      真修
     *   php yii fix/user-path --mallId=1      限定商城
     *   php yii fix/user-path --show=20       多打印几条样例
     *
     * 与 fix/relation 的区别、以及为什么生产上要用这个:
     * relation 会 TRUNCATE 整张 user_path 再重建,从清空到写完这几十秒里,
     * 所有读 path 的功能(分腿大区/小区、团队人数、DirectorService 后代判定)
     * 全都读到空 —— 业务在这段时间里是错的。本命令只 UPDATE 与正确值不符的那几行,
     * 没有空窗期,可反复跑,跑完再跑一次应该是 0 处待修。
     * 另外它不碰 team_count(那是 TeamStatFullRebuildTask 的活),只管 path。
     *
     * 已知损坏形态(2026-08 在馨薇妍生产库实测 216 处):某人的 path 丢了根部前缀,
     * 比如 `-2666-3301-…-21140-` 少了开头的 `-2-433-`。成因见 UserService::updatePathTree:
     * 祖先换绑时子孙走 `CONCAT(:newPath, SUBSTRING(path, CHAR_LENGTH(:oldPath)+1))` 批量替换,
     * 一旦那次 newPath 退化成 "-"(取不到父 path 时的兜底值),整棵子树的前缀就被削掉了。
     * path 缺前缀 → 分腿时来源归不到直推腿上,那笔业绩凭空蒸发。
     */
    public function actionUserPath()
    {
        ini_set('memory_limit', '2048M');
        set_time_limit(0);
        $db = Yii::$app->db;
        $mallId = (int)$this->mallId;
        $dryRun = (int)$this->dryRun !== 0;
        $show = max(0, (int)$this->show);

        $this->stdout(">>> user_path 体检" . ($dryRun ? "(dryRun,不写库)" : "(实修)") . " <<<\n", Console::FG_YELLOW);

        // 1. 载入用户索引。两条都不能省:
        //    · 全量载入(不按 mallId 过滤):父链可能跨商城,否则限定商城时会把跨商城的父亲
        //      当成"父不存在",把人错判成根、path 被截断。
        //    · **不过滤 is_delete**:软删用户仍留在关系链上,这是 fix/relation 的既有口径。
        //      加上 is_delete=0 会把已删的中间节点摘掉,于是 `-2-…-538-559-575-` 被算成
        //      `-559-575-`(538 已删)—— 那不是修复,是把 69 个人的 path 削断。踩过,别再加。
        $this->stdout("1. 载入用户索引...\n");
        $rows = $db->createCommand("SELECT id, parent_id, mall_id FROM " . User::tableName())->queryAll();
        $parentMap = [];
        $mallMap = [];
        foreach ($rows as $u) {
            $id = (int)$u['id'];
            $parentMap[$id] = (int)$u['parent_id'];
            $mallMap[$id] = (int)$u['mall_id'];
        }
        unset($rows);
        $this->stdout("   用户 " . count($parentMap) . " 个\n");

        // 2. 载入现有 path。
        //    **不按 mallId 过滤**:user_path 的主键是 user_id 单列(一人全局一行),写回时
        //    upsert 也是按 user_id 命中。若这里按 mall_id 过滤,某行 mall_id 与用户实际商城
        //    不一致(数据异常)时就会读不到它、被判成"缺 path 行",然后 upsert 覆盖掉一行
        //    我们根本没比对过的数据。限定商城的语义只作用在「处理哪些用户」上(见下面的 mallMap 判断)。
        $this->stdout("2. 载入现有 path...\n");
        $q = "SELECT user_id, path, mall_id FROM " . UserPath::tableName() . " WHERE is_delete = 0";
        $current = [];
        $currentMall = [];
        foreach ($db->createCommand($q)->query() as $r) {
            $current[(int)$r['user_id']] = (string)$r['path'];
            $currentMall[(int)$r['user_id']] = (int)$r['mall_id'];
        }
        $this->stdout("   path 行 " . count($current) . " 条\n\n");

        // 3. 逐人比对
        $errorCount = 0;
        $wrong = [];      // [user_id => 正确 path] —— 补前缀类,安全
        $shrink = [];     // [user_id => 正确 path] —— 会变短,祖先被硬删所致,需人工确认
        $missing = [];    // 有用户没有 path 行
        $orphan = [];     // 有 path 行但 user 表里根本没有这条 id(软删的不算)
        foreach ($parentMap as $userId => $_) {
            if ($mallId > 0 && ($mallMap[$userId] ?? 0) !== $mallId) {
                continue;
            }
            $correct = $this->buildPathString($userId, $parentMap, $errorCount);
            if (!array_key_exists($userId, $current)) {
                $missing[$userId] = $correct;
                continue;
            }
            if ($current[$userId] === $correct) {
                continue;
            }
            // 变长且现值是正确值的后缀 = 典型的"根部前缀被削掉",补回去即可,安全。
            // 变短 = 关系链上有祖先已被【硬删】(user 表里查无此人),按 parent_id 只能追到断点为止。
            // 后者会让这些人的 path 丢掉一段历史祖先,影响分腿归属与团队统计,不该跟前者混在一起
            // 无脑写下去 —— 单独列出来让人先确认那些祖先是不是真该消失。
            if (strlen($correct) > strlen($current[$userId])
                && substr($correct, -strlen($current[$userId])) === $current[$userId]) {
                $wrong[$userId] = $correct;
            } elseif (strlen($correct) < strlen($current[$userId])) {
                $shrink[$userId] = $correct;
            } else {
                $wrong[$userId] = $correct;   // 同长度但内容不同 = 链本身变了,按关系链为准
            }
        }
        foreach ($current as $uid => $_) {
            if (isset($parentMap[$uid])) {
                continue;
            }
            if ($mallId > 0 && ($currentMall[$uid] ?? 0) !== $mallId) {
                continue;   // 限定商城时,别把别家商城的孤儿算进来
            }
            $orphan[] = $uid;
        }

        $this->stdout("3. 体检结果\n", Console::FG_YELLOW);
        $this->stdout("   path 缺前缀/需重建 : " . count($wrong) . "   ← 本命令会修\n", count($wrong) ? Console::FG_RED : Console::FG_GREEN);
        $this->stdout("   缺 path 行         : " . count($missing) . "   ← 本命令会补\n", count($missing) ? Console::FG_RED : Console::FG_GREEN);
        $this->stdout("   孤儿 path(无此用户): " . count($orphan) . "   ← 只报告,不动\n");
        if ($shrink) {
            $this->stdout("   ⚠ path 会被截短     : " . count($shrink) . "   ← 【不自动修】\n", Console::FG_YELLOW);
            $this->stdout("     这些人的关系链上有祖先已被硬删(user 表查无此人),按 parent_id 只能追到断点。\n", Console::FG_GREY);
            $this->stdout("     照写会丢掉一段历史祖先,影响分腿归属与团队统计。请先确认那些祖先是否真该消失,\n", Console::FG_GREY);
            $this->stdout("     确认后再用 fix/relation 全量重建(它对这类数据的口径与此处一致)。\n", Console::FG_GREY);
            $i2 = 0;
            foreach ($shrink as $uid => $correct) {
                if ($i2++ >= max(1, $show)) break;
                $this->stdout("     #{$uid}\n       现: " . $current[$uid] . "\n       应: {$correct}\n", Console::FG_GREY);
            }
        }
        if ($errorCount > 0) {
            $this->stdout("   ⚠ 关系链成环并被截断: {$errorCount} 处,请人工核对 parent_id\n", Console::FG_RED);
        }

        $i = 0;
        foreach ($wrong as $uid => $correct) {
            if ($i++ >= $show) break;
            $this->stdout("     #{$uid}\n       现: " . $current[$uid] . "\n       应: {$correct}\n");
        }

        if (!$wrong && !$missing) {
            $this->stdout($shrink
                ? "\n>>> 本命令无需写入(上面的截短项按设计不自动修) <<<\n"
                : "\n>>> 无需修复 <<<\n", Console::FG_GREEN, Console::BOLD);
            return ExitCode::OK;
        }

        if ($dryRun) {
            $this->stdout("\n(dryRun 未写库。确认后加 --dryRun=0 执行)\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // 4. 只写这些行。upsert:抗前台 ensurePath 并发补建,且只碰 path/updated_at,不动 team_count
        $this->stdout("\n4. 写入...\n");
        $now = time();
        $buffer = [];
        $written = 0;
        foreach ($wrong + $missing as $uid => $correct) {
            $buffer[] = [(int)$uid, $mallMap[$uid] ?? 0, $correct, $now, $now];
            if (count($buffer) >= 2000) {
                $written += $this->upsertPathOnly($buffer);
                $buffer = [];
            }
        }
        $written += $this->upsertPathOnly($buffer);

        $this->stdout(">>> 完成,写入 {$written} 行。建议再跑一次 dryRun 确认归零 <<<\n", Console::FG_GREEN, Console::BOLD);
        return ExitCode::OK;
    }

    /** 只 upsert path 字段(不碰 team_count),返回本批行数 */
    private function upsertPathOnly($rows)
    {
        if (empty($rows)) return 0;
        $db = Yii::$app->db;
        $sql = $db->getQueryBuilder()->batchInsert(
            UserPath::tableName(),
            ['user_id', 'mall_id', 'path', 'created_at', 'updated_at'],
            $rows
        );
        if ($sql === '') return 0;
        $sql .= ' ON DUPLICATE KEY UPDATE `path`=VALUES(`path`), `updated_at`=VALUES(`updated_at`)';
        $db->createCommand($sql)->execute();
        return count($rows);
    }

    /**
     * 构建 Path 字符串（循环向上追溯，防环）
     */
    private function buildPathString($userId, &$parentMap, &$errorCount)
    {
        $stack = [];
        $current = $userId;
        $visited = [];

        while ($current > 0 && isset($parentMap[$current])) {
            if (isset($visited[$current])) {
                $errorCount++;
                break;
            }
            $visited[$current] = true;

            $parentId = $parentMap[$current];
            if ($parentId > 0) {
                $current = $parentId;
                $stack[] = $current;
            } else {
                break;
            }
        }

        $path = "-";
        while (!empty($stack)) {
            $path .= array_pop($stack) . "-";
        }
        $path .= $userId . "-";

        return $path;
    }

    private function batchInsertPath($rows)
    {
        if (empty($rows)) return;
        $db = Yii::$app->db;
        // 用 upsert(ON DUPLICATE KEY UPDATE)而非纯 INSERT:
        // 全量重建期间,前台 UserService::ensurePath 会随访问惰性补建 user_path 行,
        // 与本批量插入抢同一主键(user_id)→ 纯 INSERT 会报 1062 Duplicate。
        // 改 upsert 后,被并发补建的行直接被重建结果覆盖,既抗并发又幂等(可反复跑)。
        $sql = $db->getQueryBuilder()->batchInsert(
            UserPath::tableName(),
            ['user_id', 'mall_id', 'path', 'team_count', 'created_at', 'updated_at'],
            $rows
        );
        if ($sql === '') return;
        $sql .= ' ON DUPLICATE KEY UPDATE `mall_id`=VALUES(`mall_id`), `path`=VALUES(`path`), `team_count`=VALUES(`team_count`), `updated_at`=VALUES(`updated_at`)';
        $db->createCommand($sql)->execute();
    }

    /**
     * 修复插件 logo URL：将各插件目录下的 logo.png 复制到 web/statics/plugin_logos/
     * 并把可访问的 URL 写入数据库。
     *
     * 用法：
     *   php yii fix/plugin-logos https://www.example.com       # 指定域名（推荐）
     *   php yii fix/plugin-logos https://www.example.com 1     # 预览模式，不写库
     *   php yii fix/plugin-logos                               # 自动从配置读取域名
     */
    public function actionPluginLogos($domain = '', $dryRun = 0)
    {
        set_time_limit(0);
        $dryRun = (bool)$dryRun;

        // 解析域名：优先用命令行参数，其次读配置，再次尝试 APP_URL 环境变量
        if (empty($domain)) {
            $domain = \app\helpers\OptionHelper::get('domain');
        }
        if (empty($domain)) {
            $domain = getenv('APP_URL');
        }
        $domain = rtrim((string)$domain, '/');

        if (empty($domain)) {
            $this->stderr("错误：无法确定域名，请显式传入。\n示例: php yii fix/plugin-logos https://www.example.com\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        // web 根目录
        $webRoot = Yii::$app->basePath . '/web';
        $staticDir = $webRoot . '/statics/plugin_logos';
        $publicPrefix = $domain . '/statics/plugin_logos/';

        $this->stdout(">>> 插件 Logo 修复工具 <<<\n", Console::FG_YELLOW, Console::BOLD);
        $this->stdout("    域名:      {$domain}\n");
        $this->stdout("    存储目录:  {$staticDir}\n");
        $this->stdout("    URL 前缀:  {$publicPrefix}\n");
        if ($dryRun) {
            $this->stdout("    [预览模式] 不实际写库\n", Console::FG_CYAN);
        }
        $this->stdout("\n");

        // 确保目标目录存在
        if (!is_dir($staticDir)) {
            if (!mkdir($staticDir, 0755, true)) {
                $this->stderr("无法创建目录：{$staticDir}\n请检查写入权限。\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("  已创建目录：{$staticDir}\n\n");
        }

        $candidates = ['logo.png', 'icon.png', 'logo.jpg', 'icon.jpg'];

        $allPlugins = Plugin::find()->where(['is_delete' => 0])->all();
        $ok = 0; $fail = 0; $skip = 0; $copy = 0;

        foreach ($allPlugins as $plugin) {
            $name    = $plugin->name;
            $oldLogo = (string)$plugin->logo;

            // 找源文件
            $srcFile = null;
            $srcExt  = null;
            $pluginDir = Yii::$app->basePath . '/plugins/' . $name;
            foreach ($candidates as $c) {
                $f = $pluginDir . '/' . $c;
                if (file_exists($f)) {
                    $srcFile = $f;
                    $srcExt  = substr($c, strrpos($c, '.')); // .png / .jpg
                    break;
                }
            }

            if (!$srcFile) {
                $this->stdout(sprintf("  [SKIP] %-22s 插件目录内无 logo 文件\n", $name), Console::FG_YELLOW);
                $skip++;
                continue;
            }

            // 目标文件路径 & 最终 URL
            $destFile = $staticDir . '/' . $name . $srcExt;
            $newUrl   = $publicPrefix . $name . $srcExt;

            // 需要复制的条件：目标不存在 / 是空文件 / 源文件比目标新
            $destExists  = file_exists($destFile);
            $destIsEmpty = $destExists && filesize($destFile) === 0;
            $needCopy    = !$destExists || $destIsEmpty || filemtime($srcFile) > filemtime($destFile);

            if ($needCopy && !$dryRun) {
                $content = file_get_contents($srcFile);
                if ($content === false || strlen($content) === 0) {
                    $this->stdout(sprintf("  [FAIL] %-22s 源文件无法读取或本身为空: %s\n", $name, $srcFile), Console::FG_RED);
                    $fail++;
                    continue;
                }
                if (file_put_contents($destFile, $content) === false) {
                    $this->stdout(sprintf("  [FAIL] %-22s 写入目标文件失败: %s\n", $name, $destFile), Console::FG_RED);
                    $fail++;
                    continue;
                }
                $copy++;
                if ($destIsEmpty) {
                    $this->stdout(sprintf("  [RECP] %-22s 覆盖了之前的空文件\n", $name), Console::FG_CYAN);
                }
            }

            $this->stdout(sprintf(
                "  [%s] %-22s  %s → %s\n",
                $dryRun ? 'DRY ' : 'FIX ',
                $name,
                $oldLogo ?: '(空)',
                $newUrl
            ));

            if (!$dryRun) {
                $plugin->logo = $newUrl;
                if ($plugin->save(false)) {
                    $ok++;
                } else {
                    $this->stdout(
                        sprintf("        → DB 写入失败: %s\n", json_encode($plugin->getFirstErrors(), JSON_UNESCAPED_UNICODE)),
                        Console::FG_RED
                    );
                    $fail++;
                }
            } else {
                $ok++;
            }
        }

        $this->stdout("\n─────────────────────────────────────────────────\n");
        $this->stdout(
            sprintf("复制文件: %d   写库成功: %d   跳过: %d   失败: %d\n", $copy, $ok, $skip, $fail),
            Console::FG_GREEN, Console::BOLD
        );
        if ($dryRun) {
            $this->stdout("预览完毕，去掉第二个参数执行实际修复。\n", Console::FG_CYAN);
        } else {
            $this->stdout("完成！\n", Console::FG_GREEN);
        }
        return ExitCode::OK;
    }

    /**
     * 补发指定订单的分销佣金
     * php yii fix/retry-distribution C2604115410437325
     */
    public function actionRetryDistribution($orderNo = null)
    {
        if (!$orderNo) {
            $this->stderr("请传入 --order_no 参数\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $order = \app\models\Order::findOne(['order_no' => $orderNo]);
        if (!$order) {
            $this->stderr("订单不存在: {$orderNo}\n", Console::FG_RED);
            return ExitCode::DATAERR;
        }

        $this->stdout("订单: {$order->order_no}, ID: {$order->id}, 用户: {$order->user_id}, 状态: {$order->status}\n");

        // 初始化 mall 上下文
        $mall = \app\models\Mall::findOne($order->mall_id);
        if ($mall) {
            Yii::$app->set('mall', $mall);
        }

        // 检查幂等
        $existing = \app\models\CommissionLog::find()
            ->where(['order_id' => $order->id, 'type' => 'distribution', 'is_delete' => 0])
            ->count();

        if ($existing > 0) {
            $this->stdout("该订单已有 {$existing} 条分销记录，跳过\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // 触发分销逻辑
        $event = new \app\events\OrderEvent();
        $event->order = $order;

        $listener = new \app\plugins\distribution\listeners\DistributionListener();
        $listener->onOrderPaid($event);

        // 验证
        $newLogs = \app\models\CommissionLog::find()
            ->where(['order_id' => $order->id, 'type' => 'distribution', 'is_delete' => 0])
            ->asArray()->all();

        if (empty($newLogs)) {
            $this->stderr("未生成分销记录，请检查分销配置和用户关系链\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("补发成功，生成 " . count($newLogs) . " 条分销记录:\n", Console::FG_GREEN);
        foreach ($newLogs as $log) {
            $this->stdout("  → 用户: {$log['user_id']}, 金额: {$log['commission_amount']}, 状态: {$log['status']}, 备注: {$log['remark']}\n");
        }

        return ExitCode::OK;
    }
}
