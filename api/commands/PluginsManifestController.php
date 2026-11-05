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

/**
 * 生成插件清单 api/plugins-manifest.json
 *
 * 用法: php yii plugins-manifest/generate
 *
 * 扫描 api/plugins/ 下每个插件,实例化其 Plugin 类,取 getLabel()/getDsc()/getVersion(),
 * 写成 { "mch": {"label":"多商户","dsc":"...","version":"1.0.0"}, ... }。
 * 随整包一起下发,云端扫描时读它 → plugins 表拿到中文名与说明(而不是只有目录名)。
 * 打包脚本 build-package.sh 会在压包前自动跑本命令。
 */
class PluginsManifestController extends Controller
{
    public function actionGenerate()
    {
        $dir = Yii::$app->basePath . '/plugins';
        if (!is_dir($dir)) {
            $this->stdout("未找到 plugins 目录\n");
            return;
        }

        $manifest = [];
        foreach (scandir($dir) as $name) {
            if ($name === '.' || $name === '..' || !is_dir($dir . '/' . $name)) {
                continue;
            }
            $class = '\\app\\plugins\\' . $name . '\\Plugin';
            if (!class_exists($class)) {
                continue;
            }
            try {
                /** @var \app\plugins\Plugin $p */
                $p = new $class();
                $manifest[$name] = [
                    'label' => (string)$p->getLabel(),
                    'dsc' => (string)$p->getDsc(),
                    'version' => (string)$p->getVersion(),
                ];
            } catch (\Throwable $e) {
                // 某插件实例化失败不影响其它:退化为仅目录名
                $manifest[$name] = ['label' => $name, 'dsc' => '', 'version' => ''];
                $this->stdout("⚠ 插件 {$name} 取元数据失败: " . $e->getMessage() . "\n");
            }
        }

        $file = Yii::$app->basePath . '/plugins-manifest.json';
        file_put_contents($file, json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->stdout("✅ 生成 plugins-manifest.json:" . count($manifest) . " 个插件\n");
    }
}
