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

namespace app\forms\admin;

use app\core\ApiCode;
use app\helpers\ResponseHelper;
use Yii;

/**
 * 系统配置信息（超级管理员只读面板）
 *
 * 只读：仅采集与展示运行环境信息，不修改任何配置、不执行部署操作。
 */
class SystemInfoForm
{
    public function getInfo()
    {
        $groups = [
            $this->appGroup(),
            $this->runtimeGroup(),
            $this->phpConfigGroup(),
            $this->extensionGroup(),
            $this->databaseGroup(),
            $this->redisGroup(),
            $this->storageGroup(),
            $this->dependencyGroup(),
            $this->miniprogramGroup(),
        ];

        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '', ['groups' => $groups]);
    }

    /** 组装单项 */
    private function item($label, $value, $status = 'none')
    {
        // status: ok | warn | error | none
        return ['label' => $label, 'value' => (string)$value, 'status' => $status];
    }

    private function env($key, $default = '')
    {
        $v = $_ENV[$key] ?? getenv($key);
        return ($v === false || $v === null) ? $default : (string)$v;
    }

    /** 应用信息 */
    private function appGroup()
    {
        $debug = $this->env('YII_DEBUG');
        $isDebug = ($debug === 'true' || $debug === '1');
        $env = $this->env('YII_ENV', 'prod');

        return [
            'title' => '应用信息',
            'items' => [
                $this->item('应用名称', $this->env('APP_NAME', 'JXMALL')),
                $this->item('站点地址 APP_URL', $this->env('APP_URL', '(未配置)'),
                    $this->env('APP_URL') ? 'ok' : 'warn'),
                $this->item('运行环境', $env === 'prod' ? '生产环境 (prod)' : "开发环境 ({$env})",
                    $env === 'prod' ? 'ok' : 'warn'),
                $this->item('调试模式 YII_DEBUG', $isDebug ? '已开启' : '已关闭',
                    $isDebug ? 'warn' : 'ok'),
                $this->item('时区', $this->env('APP_TIMEZONE', date_default_timezone_get())),
            ],
        ];
    }

    /** 运行环境 */
    private function runtimeGroup()
    {
        return [
            'title' => '运行环境',
            'items' => [
                $this->item('PHP 版本', PHP_VERSION,
                    version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'error'),
                $this->item('操作系统', php_uname('s') . ' ' . php_uname('r')),
                $this->item('服务器软件', $_SERVER['SERVER_SOFTWARE'] ?? '(CLI)'),
                $this->item('服务器时间', date('Y-m-d H:i:s')),
            ],
        ];
    }

    /** PHP 扩展 */
    private function extensionGroup()
    {
        $critical = ['pdo_mysql', 'mbstring', 'curl', 'openssl', 'gd', 'json', 'zip'];
        $optional = ['redis', 'imagick'];
        $items = [];
        foreach ($critical as $ext) {
            $loaded = extension_loaded($ext);
            $items[] = $this->item($ext, $loaded ? '已加载' : '缺失', $loaded ? 'ok' : 'error');
        }
        foreach ($optional as $ext) {
            $loaded = extension_loaded($ext);
            $items[] = $this->item($ext . '（可选）', $loaded ? '已加载' : '未加载', $loaded ? 'ok' : 'warn');
        }
        return ['title' => 'PHP 扩展', 'items' => $items];
    }

    /** 数据库 */
    private function databaseGroup()
    {
        $items = [];
        try {
            $db = Yii::$app->db;
            $version = $db->createCommand('SELECT VERSION()')->queryScalar();
            $dbName  = $db->createCommand('SELECT DATABASE()')->queryScalar();
            $items[] = $this->item('连接状态', '已连通', 'ok');
            $items[] = $this->item('数据库版本', $version);
            $items[] = $this->item('数据库名', $dbName);

            try {
                $migCount = (int)$db->createCommand('SELECT COUNT(*) FROM {{%migration}}')->queryScalar();
                $items[] = $this->item('已执行迁移数', $migCount . ' 条',
                    $migCount > 0 ? 'ok' : 'warn');
            } catch (\Throwable $e) {
                $items[] = $this->item('迁移状态', '迁移表不存在，请执行部署', 'error');
            }
        } catch (\Throwable $e) {
            $items[] = $this->item('连接状态', '无法连接：' . $e->getMessage(), 'error');
        }
        return ['title' => '数据库', 'items' => $items];
    }

    /** Redis */
    private function redisGroup()
    {
        $items = [];
        try {
            if (!Yii::$app->has('redis')) {
                $items[] = $this->item('Redis', '未配置 redis 组件', 'warn');
            } else {
                $redis = Yii::$app->redis;
                $pong = $redis->executeCommand('PING');
                $connected = ($pong === true || $pong === 'PONG' || $pong === '+PONG');
                $items[] = $this->item('连接状态', $connected ? '已连通' : '无连接',
                    $connected ? 'ok' : 'error');
                if ($connected) {
                    $info = (string)$redis->executeCommand('INFO', ['server']);
                    if (preg_match('/redis_version:([^\r\n]+)/', $info, $m)) {
                        $items[] = $this->item('Redis 版本', trim($m[1]));
                    }
                }
            }
        } catch (\Throwable $e) {
            $items[] = $this->item('连接状态', '无法连接：' . $e->getMessage(), 'error');
        }
        return ['title' => 'Redis', 'items' => $items];
    }

    /** 存储目录 */
    private function storageGroup()
    {
        $paths = [
            'runtime 运行目录' => Yii::getAlias('@app') . '/runtime',
            'uploads 上传目录' => Yii::getAlias('@webroot') . '/uploads',
        ];
        $items = [];
        foreach ($paths as $label => $dir) {
            $writable = (is_dir($dir) && is_writable($dir))
                || (!is_dir($dir) && is_writable(dirname($dir)));
            $items[] = $this->item($label, $writable ? '可写' : '不可写，请修正权限',
                $writable ? 'ok' : 'error');
        }
        // 磁盘剩余空间
        $free = @disk_free_space(Yii::getAlias('@app'));
        if ($free !== false) {
            $items[] = $this->item('磁盘剩余空间', $this->humanSize($free),
                $free < 1024 * 1024 * 1024 ? 'warn' : 'ok');
        }
        return ['title' => '存储', 'items' => $items];
    }

    /** 系统级依赖 */
    private function dependencyGroup()
    {
        $items = [];
        if (function_exists('shell_exec')) {
            $gsPath = trim((string)@shell_exec('command -v gs 2>/dev/null'));
            if ($gsPath !== '') {
                $ver = trim((string)@shell_exec(escapeshellarg($gsPath) . ' --version 2>/dev/null'));
                $items[] = $this->item('Ghostscript', '已安装 v' . $ver, 'ok');
            } else {
                $items[] = $this->item('Ghostscript', '未安装（高版本 PDF 无法自动转换）', 'warn');
            }
        } else {
            $items[] = $this->item('Ghostscript', 'shell_exec 被禁用，无法检测', 'warn');
        }
        return ['title' => '系统依赖', 'items' => $items];
    }

    /** 小程序上传环境（后台「上传代码」依赖项体检） */
    private function miniprogramGroup()
    {
        $items = [];

        // 1. PHP exec()：上传强依赖（被 disable_functions 禁用则 function_exists 返回 false）
        $execOk = function_exists('exec');
        $items[] = $this->item('PHP exec()',
            $execOk ? '可用' : '已被禁用（上传不可用，请从 disable_functions 移除 exec）',
            $execOk ? 'ok' : 'error');

        // 探测命令（仅用于读版本，不影响上传本身）
        $probe = function ($cmd) {
            if (function_exists('shell_exec')) {
                return trim((string)@shell_exec($cmd . ' 2>&1'));
            }
            return null;
        };

        // 2. Node 环境
        $nodeBin = ($_ENV['MINIPROGRAM_CI_NODE'] ?? '') ?: 'node';
        $nodeVer = $probe(escapeshellarg($nodeBin) . ' --version');
        if ($nodeVer !== null && preg_match('/v?\d+\.\d+/', $nodeVer)) {
            $items[] = $this->item('Node 环境', '已安装 ' . $nodeVer, 'ok');
        } else {
            $items[] = $this->item('Node 环境', '未检测到（请装 Node 或配置 .env MINIPROGRAM_CI_NODE 全路径）', 'error');
        }

        // 3. miniprogram-ci CLI（跑 --version 自检，能抓出 cnpm 装坏 / less 不兼容）
        $ciBin = ($_ENV['MINIPROGRAM_CI_BIN'] ?? '')
            ?: (dirname(Yii::getAlias('@webroot')) . '/uploadserver/node_modules/.bin/miniprogram-ci');
        if (!file_exists($ciBin)) {
            $items[] = $this->item('小程序上传 CLI', '未安装（部署选 5，或 cd api/uploadserver && npm install）', 'error');
        } else {
            $ciVer = $probe(escapeshellarg($nodeBin) . ' ' . escapeshellarg($ciBin) . ' --version');
            if ($ciVer !== null && preg_match('/^\d+\.\d+\.\d+/', $ciVer)) {
                $items[] = $this->item('小程序上传 CLI', '已安装 v' . $ciVer . ' 且自检通过', 'ok');
            } else {
                $items[] = $this->item('小程序上传 CLI', '已安装但自检失败（多为 cnpm 装坏，请用真 npm 重装）', 'error');
            }
        }

        // 4. 标准代码包 + 占位符
        $tpl = Yii::getAlias('@webroot') . '/mp-weixin';
        $vendor = $tpl . '/common/vendor.js';
        if (!is_dir($tpl) || !file_exists($vendor)) {
            $items[] = $this->item('小程序标准包', '缺失 web/mp-weixin（请编译 uni-app 产物）', 'error');
        } else {
            $hasPlaceholder = strpos((string)@file_get_contents($vendor), '__JX_MALL_ID__') !== false;
            $items[] = $this->item('小程序标准包',
                $hasPlaceholder ? '就绪（含占位符）' : '缺占位符，请用带占位符的版本重新编译',
                $hasPlaceholder ? 'ok' : 'warn');
        }

        return ['title' => '小程序上传环境', 'items' => $items];
    }

    /**
     * PHP 配置 —— 只列**会真的把功能卡住**的那几项,不做 phpinfo 的搬运工。
     *
     * 选这几项的理由(都是实际会踩到的):
     *   · upload_max_filesize / post_max_size:上传营业执照、商品图、装修素材失败最常见的原因,
     *     而且 post_max_size 必须 ≥ upload_max_filesize,否则前者形同虚设 —— 这个坑光看 phpinfo 看不出来。
     *   · max_file_uploads:批量传 banner / 多图时一次能传几张。
     *   · memory_limit:导出订单、生成合同 PDF 这类批处理的天花板。
     *   · max_execution_time:同上,长任务会被它掐断(CLI 模式下为 0=不限,属正常)。
     *
     * 阈值按本项目实际需要定,不是通用建议值。
     */
    private function phpConfigGroup()
    {
        $upload = $this->iniBytes('upload_max_filesize');
        $post   = $this->iniBytes('post_max_size');
        $memory = $this->iniBytes('memory_limit');
        $execTime = (int)ini_get('max_execution_time');
        $maxFiles = (int)ini_get('max_file_uploads');

        // post_max_size 卡住 upload_max_filesize 是最隐蔽的一种:改了上传上限却不生效,
        // 因为整个请求体先被 post_max_size 拦下。这里显式点出来。
        $postStatus = 'ok';
        $postValue  = ini_get('post_max_size');
        if ($post > 0 && $upload > 0 && $post < $upload) {
            $postStatus = 'error';
            $postValue .= ' (小于上传上限,实际可传 ' . $this->humanSize($post) . ')';
        }

        return [
            'title' => 'PHP 配置',
            'items' => [
                $this->item('单文件上传上限', ini_get('upload_max_filesize') ?: '未设置',
                    $upload >= 8 * 1024 * 1024 ? 'ok' : 'warn'),
                $this->item('POST 请求体上限', $postValue ?: '未设置', $postStatus),
                $this->item('单次最多上传文件数', $maxFiles > 0 ? $maxFiles . ' 个' : '未设置',
                    $maxFiles >= 10 ? 'ok' : 'warn'),
                $this->item('内存上限', ini_get('memory_limit') ?: '未设置',
                    ($memory < 0 || $memory >= 128 * 1024 * 1024) ? 'ok' : 'warn'),
                $this->item('脚本超时', $execTime === 0 ? '不限制' : $execTime . ' 秒',
                    ($execTime === 0 || $execTime >= 30) ? 'ok' : 'warn'),
                $this->item('配置文件', php_ini_loaded_file() ?: '(未加载 php.ini)',
                    php_ini_loaded_file() ? 'none' : 'warn'),
            ],
        ];
    }

    /**
     * 把 php.ini 的尺寸写法(128M / 2G / 8192)换算成字节。
     * memory_limit=-1 表示不限制,原样返回 -1 交给调用方判断。
     */
    private function iniBytes($key)
    {
        $raw = trim((string)ini_get($key));
        if ($raw === '') {
            return 0;
        }
        if ($raw === '-1') {
            return -1;
        }
        $unit = strtolower(substr($raw, -1));
        $num = (float)$raw;
        switch ($unit) {
            case 'g': return (int)($num * 1024 * 1024 * 1024);
            case 'm': return (int)($num * 1024 * 1024);
            case 'k': return (int)($num * 1024);
            default:  return (int)$num;
        }
    }

    private function humanSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
