<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-27
 * Time: 23:10
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

use Yii;
use yii\helpers\FileHelper;
use yii\web\Request;

class ServerHelper
{

    /**
     * 获取基础URL，仅在Web环境下有效
     * @return string|null 在Web环境下返回URL，在Console环境下返回null
     */
    public static function getBaseUrl()
    {
        // 判断当前应用是否是Web应用
        if (!Yii::$app instanceof \yii\web\Application) {
            return null; // 或者返回一个默认的配置值，或者抛出异常
        }

        $protocol = (Yii::$app->request->getIsSecureConnection()) ? 'https://' : 'http://';
        return $protocol . Yii::$app->request->hostInfo . Yii::$app->request->baseUrl;
    }

    /**
     * 获取站点域名（不含尾部斜杠）
     *
     * 优先级：
     *   1) .env 中的 APP_URL —— 部署时统一配置，Web / CLI 表现一致，最稳定
     *   2) Web 请求域名 hostInfo —— 未配置 APP_URL 时回退到当前请求
     *   3) option 表 domain 配置 —— 最后兜底
     *
     * @return string|null
     */
    public static function getHost()
    {
        // 1) .env APP_URL（phpdotenv 已将其载入 $_ENV / getenv）
        $appUrl = $_ENV['APP_URL'] ?? (getenv('APP_URL') ?: '');

        if (is_string($appUrl) && trim($appUrl) !== '') {
            return rtrim(trim($appUrl), '/');
        }

        // 2) Web 请求域名
        $domain = '';
        if (Yii::$app->has('request') && Yii::$app->request instanceof Request) {
            try {
                $domain = Yii::$app->request->hostInfo;
            } catch (\Exception $exception) {
                // 即使在Web环境下，某些特殊配置也可能导致hostInfo获取失败
                // 此时，继续执行下面的逻辑
            }
        }

        // 3) option 表配置兜底
        if (!$domain) {
            $domain = OptionHelper::get('domain');
        }
        return $domain ? rtrim($domain, '/') : $domain;
    }

    /**
     * 获取用户IP地址，仅在Web环境下有效
     * @return string|null
     */
    public static function getUserIp()
    {
        if (!Yii::$app->has('request') || !(Yii::$app->request instanceof Request)) {
            return null;
        }
        return Yii::$app->request->remoteIP;
    }

    /**
     * 获取Yii应用的基础路径，在Web和Console环境下均可使用
     * @return string
     */
    public static function getYiiBasePath()
    {
        return Yii::$app->basePath;
    }

    /**
     * [核心补全] 下载远程文件到本地
     * @param string $url 远程图片地址
     * @return string 本地文件的绝对物理路径
     * @throws \Exception
     */
    public static function getLocalPath($url)
    {
        if (empty($url)) {
            throw new \Exception('远程文件地址不能为空');
        }

        // 1. 确定保存目录 (web/uploads/images/YYYYmm/dd/)
        $webRoot = self::getAbsFilePath();
        $subDir = '/uploads/images/' . date('Ym/d');
        $saveDir = $webRoot . $subDir;

        // 如果目录不存在，则创建
        if (!is_dir($saveDir)) {
            if (!FileHelper::createDirectory($saveDir)) {
                throw new \Exception("无法创建目录: " . $saveDir);
            }
        }

        // 2. 使用 CURL 下载文件 (比 file_get_contents 更稳定)
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 忽略SSL证书验证
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);  // 跟随重定向
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);           // 超时设置
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode != 200 || empty($content)) {
            throw new \Exception("下载远程文件失败 [HTTP {$httpCode}]: " . ($curlError ?: $url));
        }

        // 3. 确定文件后缀
        $extension = 'jpg'; // 默认后缀
        // 尝试从 Content-Type 获取
        $mimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];
        if (isset($mimeTypes[$contentType])) {
            $extension = $mimeTypes[$contentType];
        } else {
            // 尝试从 URL 获取
            $pathInfo = pathinfo(parse_url($url, PHP_URL_PATH));
            if (isset($pathInfo['extension']) && !empty($pathInfo['extension'])) {
                $extension = strtolower($pathInfo['extension']);
                // 简单的安全过滤，防止 php 等后缀
                if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'pdf'])) {
                    $extension = 'jpg';
                }
            }
        }

        // 4. 生成唯一文件名
        $fileName = md5($url . microtime()) . '.' . $extension;
        $absolutePath = $saveDir . '/' . $fileName;

        // 5. 写入文件
        if (file_put_contents($absolutePath, $content) === false) {
            throw new \Exception("无法写入文件到: " . $absolutePath);
        }

        // 注意：如果你的系统需要记录文件到数据库（如 attachment 表），
        // 你需要在这里调用相关的 Model 进行 save 操作。
        // 例如： \app\models\Attachment::add($url, $absolutePath);

        return $absolutePath;
    }

    /**
     * [核心方法] 下载远程图片到本地，并返回完整的网络 URL
     * 场景：将 AI 返回的临时链接转存为本地永久链接
     *
     * @param string $url 远程图片地址
     * @return string 本地完整的 URL (e.g. https://domain.com/uploads/images/...)
     */
    public static function saveToLocalAndGetUrl($url)
    {
        try {
            // 1. 下载文件并获取本地物理绝对路径
            $localFilePath = self::getLocalPath($url);

            // 2. 获取 Web 根目录的物理路径 (e.g. /var/www/html/web)
            $webRoot = self::getAbsFilePath();

            // 3. 将物理路径转换为相对路径
            // 把 /var/www/html/web/uploads/images/a.jpg 变成 /uploads/images/a.jpg
            $relativePath = str_replace($webRoot, '', $localFilePath);

            // 兼容 Windows 路径反斜杠
            $relativePath = str_replace('\\', '/', $relativePath);

            // 4. 拼接域名，生成完整 URL
            $host = ServerHelper::getHost();

            // 确保 host 没有尾部斜杠，相对路径有头部斜杠
            return rtrim($host, '/') . '/' . ltrim($relativePath, '/');
        } catch (\Exception $e) {
            Yii::error("图片转存失败 URL:{$url}。错误: " . $e->getMessage());
            // 如果转存失败，为了不中断流程，暂时返回原链接（虽然会过期，但比空着好）
            return $url;
        }
    }

    public static function getAbsFilePath($path = null)
    {
        $webRoot = Yii::getAlias('@webroot');
        if (Yii::$app instanceof \yii\console\Application || !is_dir($webRoot)) {
            $basePath = Yii::$app->basePath;
            // 拼接 web 目录
            $webRoot = $basePath . '/web';
        }
        if ($path) {
            return $webRoot . $path;
        }
        return $webRoot;
    }
}
