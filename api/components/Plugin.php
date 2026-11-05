<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-29
 * Time: 0:36
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

namespace app\components;

use app\listeners\BaseListener;
use app\models\BaseModel;
use app\models\MallPlugin;
use yii\base\Component;
use app\models\Plugin as PluginModel;
use yii\base\UnknownClassException;

class Plugin extends Component
{
    public $currentPlugin;
    public $plugins;
    private $list;
    private $install_type = 0;

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-29
     * @Time: 11:50
     * @Note:获取数据库标记的已安装的插件列表 (返回模型)
     * @return PluginModel[]
     */
    public function getList()
    {
        $this->list = PluginModel::find()->where([
            'is_delete' => 0,
        ])->all();
        return $this->list;
    }

    /**
     * [新增] 获取所有已安装插件的【实例对象】
     * 用于责任链调用，如：商家端统一扫码核销
     * @return \app\plugins\Plugin[]
     */
    public function getAllPlugins()
    {
        // 1. 查出所有已安装的插件记录
        $list = PluginModel::find()->where(['is_delete' => 0])->all();
        $instances = [];

        // 2. 商城授权过滤（与 registerListeners 同源逻辑）
        $authorized = $this->resolveAuthorizedPluginNames();

        foreach ($list as $item) {
            if ($authorized !== null && !in_array($item->name, $authorized, true)) {
                continue;
            }
            try {
                // 3. 复用 getPlugin 方法实例化插件类 (自带单例缓存)
                $plugin = $this->getPlugin($item->name);
                if ($plugin) {
                    $instances[] = $plugin;
                }
            } catch (\Exception $e) {
                // 容错处理：防止某个插件文件丢失导致整个遍历中断
                \Yii::warning("加载插件实例失败 [{$item->name}]: " . $e->getMessage());
            }
        }

        return $instances;
    }

    /**
     * 注册所有"已安装 + 已授权给当前商城"的插件 listener。
     *
     * 过滤维度：
     *   1. jxmall_plugin (is_delete=0) —— 平台维度"已安装"
     *   2. jxmall_mall_plugin (mall_id=当前商城, is_delete=0) —— 商城维度"已授权"
     *
     * 兜底：
     *   · 当前请求未携带 x-mall-id（如健康检查、安装阶段）—— 注册所有已安装的插件
     *     listener（沿用旧行为，避免回归）。
     *   · 控制台模式下 AppBootstrap 已默认 mall_id=1，所以也走授权过滤。
     */
    public function registerListeners()
    {
        $list = PluginModel::find()->where([
            'is_delete' => 0,
        ])->all();

        $authorized = $this->resolveAuthorizedPluginNames();

        /**
         * @var PluginModel[] $list
         */
        foreach ($list as $item) {
            // 已知有 mall 但插件未在该商城授权 → 该插件「有副作用的业务监听器」跳过；
            // 但「与商城无关的纯元数据监听器」（CommissionTypeListener）仍要注册，
            // 否则财务后台无法显示历史分润记录的类型中文名（详见 isMallAgnosticListener）。
            $isAuthorized = ($authorized === null || in_array($item->name, $authorized, true));

            $Class = '\\app\\plugins\\' . $item->name . '\\Plugin';
            if (class_exists($Class)) {
                /** @var \app\plugins\Plugin $plugin */
                $plugin = new $Class();
                $listeners = $plugin->getListeners();
                foreach ($listeners as $listenerClass) {
                    if (!$isAuthorized && !$this->isMallAgnosticListener($listenerClass)) {
                        continue;
                    }
                    /**
                     * @var BaseListener $listener
                     */
                    $listener = new $listenerClass();
                    if ($listener instanceof BaseListener) {
                        $listener->register();
                    }
                }
            }
        }
    }

    /**
     * 判断 listener 是否「与商城授权无关」——即便插件未授权给当前商城也应注册。
     *
     * 目前仅 CommissionTypeListener：它只向分佣中台登记佣金类型的展示元数据
     * （中文名 / 标签颜色 / 费率口径），纯展示、零副作用。财务后台的分润日志页
     * 需要它来正确显示历史记录的类型名 —— 一个商城可能有历史 dealer_diff 等分润
     * 记录，但当前已不再授权该插件，此时仍需展示「经销商级差」而非裸字符串。
     *
     * @param string $listenerClass listener 完整类名
     */
    private function isMallAgnosticListener(string $listenerClass): bool
    {
        $pos  = strrpos($listenerClass, '\\');
        $base = $pos !== false ? substr($listenerClass, $pos + 1) : $listenerClass;
        return $base === 'CommissionTypeListener';
    }

    /**
     * 解析当前商城已授权的插件名列表。
     * 返回 null 表示"未知/无 mall 上下文" → 调用方退化到注册全部。
     * (public:除 getAllPlugins/registerListeners 外,LinkForm 等挂载插件链接时也需按商城授权过滤)
     *
     * @return string[]|null
     */
    public function resolveAuthorizedPluginNames(): ?array
    {
        try {
            $mallId = (int) (\Yii::$app->mall->id ?? 0);
        } catch (\Throwable $e) {
            return null;
        }
        if ($mallId <= 0) {
            return null;
        }
        try {
            return MallPlugin::find()
                ->select('plugin')
                ->where(['mall_id' => $mallId, 'is_delete' => 0])
                ->column();
        } catch (\Throwable $e) {
            // 表不存在 / 数据库异常 → 退化到注册全部，避免阻断启动
            \Yii::warning('[Plugin::resolveAuthorizedPluginNames] 查询失败：' . $e->getMessage());
            return null;
        }
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-10-29
     * @Time: 11:50
     * @Note:扫描插件目录列表
     * @return array
     * @throws \Exception
     */
    public function scanPluginList()
    {
        $baseDir = \Yii::$app->basePath . '/plugins';
        if (!is_dir($baseDir)) {
            return [];
        }
        $handle = opendir($baseDir);
        if (!$handle) {
            throw new \Exception('无法访问目录`' . $baseDir . '`，请确认该目录是否有访问权限。');
        }
        $list = [];
        while (($file = readdir($handle)) !== false) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }
            if (!is_dir($baseDir . '/' . $file)) {
                continue;
            }
            // 插件目录下必须有 Plugin.php，否则视为无效目录直接跳过，
            // 不触发 autoloader、不打 warning，彻底避免空目录报错
            if (!file_exists($baseDir . '/' . $file . '/Plugin.php')) {
                continue;
            }
            try {
                $plugin = $this->getPlugin($file);
                if ($plugin) {
                    $list[] = $plugin;
                }
            } catch (\Exception $e) {
                \Yii::error('[Plugin::scanPluginList] 加载插件 `' . $file . '` 时异常：' . $e->getMessage());
            }
        }
        closedir($handle);
        return $list;
    }

    public function install($name)
    {
        $plugin = $this->getPlugin($name);
        try {
            $this->saveData($plugin);
        } catch (\Exception $e) {
            \Yii::warning($e->getMessage());
            throw new \Exception($e->getMessage());
        }
        return true;
    }

    /**
     * @param \app\plugins\Plugin $plugin
     * @return bool
     * @throws \Exception
     */
    private function saveData($plugin)
    {
        $pluginModel = PluginModel::findOne(['name' => $plugin->getName()]);
        $versionFile = \Yii::$app->basePath . '/plugins/' . $plugin->getName() . '/version';
        if (file_exists($versionFile)) {
            $version = file_get_contents($versionFile);
            if (!$version || !preg_match('/^\d*(\.\d*)*$/', $version)) {
                $version = '0.0.0';
            }
        } else {
            file_put_contents($versionFile, '0.0.0');
            $version = '0.0.0';
        }
        if (!$pluginModel) {
            $pluginModel = new PluginModel();
        }
        $pluginModel->name = $plugin->getName();
        $pluginModel->label = $plugin->getLabel();
        $pluginModel->version = $version;
        $pluginModel->is_delete = 0;
        $pluginModel->install_type = $this->install_type;
        $pluginModel->description = $plugin->getDsc();
        $pluginModel->logo = ''; // logo 由 PluginHelper::getPluginIconUrl() 懒加载生成，安装时不再写死 URL
        $pluginModel->type = $plugin->getType();

        if (!$pluginModel->save()) {
            \Yii::warning($pluginModel->getFirstErrors());
            throw new \Exception('插件安装失败');
        }
        return true;
    }

    /**
     * @param $name
     * @return \app\plugins\Plugin|false
     */
    public function getPlugin($name)
    {
        $Class = 'app\\plugins\\' . $name . '\\Plugin';
        if (!class_exists($Class)) {
            // 追踪调用链：定位是谁触发加载这个插件（如 getAllPlugins / registerListeners 遍历 DB 记录）
            $caller = '';
            foreach (array_slice(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 6), 1, 4) as $t) {
                $caller .= ' ← ' . ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '') . '()';
            }
            $pluginFile = \Yii::$app->basePath . '/plugins/' . $name . '/Plugin.php';
            if (!file_exists($pluginFile)) {
                // 文件根本不存在 = jxmall_plugin 表有「孤儿安装记录」（插件文件被删/未部署），不是代码错误。
                \Yii::warning("[Plugin::getPlugin] 插件 `{$name}` 的文件不存在（{$pluginFile}），却仍被加载 —— "
                    . "极可能是 jxmall_plugin / jxmall_mall_plugin 表残留了安装记录。"
                    . "排查：SELECT * FROM jxmall_plugin WHERE name='{$name}'; 清理：UPDATE jxmall_plugin SET is_delete=1 WHERE name='{$name}';"
                    . " 调用链：{$caller}");
            } else {
                // 文件存在但类加载失败 = 真正的命名空间 / 语法错误
                \Yii::error("[Plugin::getPlugin] 插件 `{$name}` 的 Plugin.php 存在但类 `{$Class}` 无法加载，请检查命名空间或语法。调用链：{$caller}");
            }
            return false;
        }
        if (!$this->plugins) {
            $this->plugins = [];
        }
        if (!empty($this->plugins[$name])) {
            return $this->plugins[$name];
        }
        $object = \Yii::createObject($Class);
        $this->plugins[$name] = $object;
        return $this->plugins[$name];
    }

    /**
     * @param \app\plugins\Plugin $plugin
     */
    public function setCurrentPlugin($plugin)
    {
        $this->currentPlugin = $plugin;
    }
}
