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

namespace app\helpers;

/**
 * 插件控制台命令扫描器
 *
 * ── 设计目标 ──
 * 让 `php yii` 命令直接发现 `api/plugins/{plugin}/commands/` 下的 Controller，
 * 避免每次都把测试/工具命令塞到 `app/commands/`。
 *
 * ── 约定 ──
 *   1. 插件命令文件位置：api/plugins/{plugin}/commands/{Xxx}Controller.php
 *   2. 命名空间：app\plugins\{plugin}\commands
 *   3. 类名以 Controller 结尾
 *
 * ── 命令 ID 生成规则 ──
 *   类名（去掉 Controller 后缀）转 kebab-case，前面加 "{plugin}-"
 *   例：
 *     app\plugins\aicare\commands\TestController        → php yii aicare-test/<action>
 *     app\plugins\aicare\commands\RewardCheckController → php yii aicare-reward-check/<action>
 *     app\plugins\mch\commands\SettleController         → php yii mch-settle/<action>
 *
 * ── 注意 ──
 *   - 不读 DB（避免 console 启动期 DB 未就绪）
 *   - 仅按目录是否存在判断，不检查插件是否"已安装"
 *   - 失败静默跳过（容错，单个插件文件损坏不影响其它命令）
 */
class PluginConsoleHelper
{
    /**
     * 扫描所有插件下的 commands/ 目录，返回 Yii console controllerMap 片段。
     *
     * @return array<string, string>  [commandId => class FQN]
     */
    public static function scanCommands(): array
    {
        $map = [];

        $pluginsDir = dirname(__DIR__) . '/plugins';
        if (!is_dir($pluginsDir)) {
            return $map;
        }

        $iterator = new \DirectoryIterator($pluginsDir);
        foreach ($iterator as $pluginDir) {
            if ($pluginDir->isDot() || !$pluginDir->isDir()) {
                continue;
            }

            $pluginName = $pluginDir->getFilename();
            // 插件名规范：必须小写字母开头，仅含 [a-z0-9_]
            // 过滤掉 Plugin.php 等抽象基类
            if (!preg_match('/^[a-z][a-z0-9_]*$/', $pluginName)) {
                continue;
            }

            $commandsDir = $pluginDir->getPathname() . '/commands';
            if (!is_dir($commandsDir)) {
                continue;
            }

            foreach (new \DirectoryIterator($commandsDir) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $base = $file->getBasename('.php');
                // 必须以 Controller 结尾
                if (!preg_match('/^([A-Z][A-Za-z0-9]*)Controller$/', $base, $m)) {
                    continue;
                }

                $shortName = $m[1];                    // e.g. "TestAicare" / "Test"
                $kebab     = self::toKebabCase($shortName);
                $commandId = "{$pluginName}-{$kebab}"; // e.g. "aicare-test"
                $fqn       = "app\\plugins\\{$pluginName}\\commands\\{$base}";

                $map[$commandId] = $fqn;
            }
        }

        return $map;
    }

    /**
     * PascalCase / camelCase → kebab-case
     * "RewardCheck" → "reward-check"
     * "Test"        → "test"
     * "API2Status"  → "api2-status"
     */
    private static function toKebabCase(string $name): string
    {
        // 在大写字母前插破折号，再整体转小写
        $result = preg_replace('/(?<!^)([A-Z])/', '-$1', $name);
        return strtolower($result);
    }
}
