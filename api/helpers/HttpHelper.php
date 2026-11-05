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

use app\jobs\common\UnlinkJob;
use app\models\File;
use Yii;
use yii\base\Exception;
use yii\httpclient\Client;
use yii\helpers\FileHelper as YiiFileHelper;
use yii\helpers\Json;

class HttpHelper
{

    public static function get($url, $data = [], $header = [])
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);

        try {
            $response = $client->createRequest()
                ->setHeaders($header)
                ->setMethod('get')->setOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false])
                ->setUrl($url)
                ->setData($data)
                ->send();
        } catch (\yii\base\InvalidConfigException $e) {
            return false;
        }
        return Json::decode($response->content);
    }

    public static function post($url, $data = null, $header = ['Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8'])
    {
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        try {
            $response = $client->createRequest()
                ->setUrl($url)
                ->setHeaders($header)
                ->setOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false])
                ->setMethod('post')
                ->setData($data)
                ->send();
        } catch (\yii\base\InvalidConfigException $e) {
            return false;
        }
        try {
            return SerializeHelper::decode($response->content);
        } catch (\Exception $exception) {
            return $response->content;
        }
    }

    public static function postRaw($url, $data = null, $header = ['Content-Type' => 'application/json'])
    {
        if ($data && is_array($data)) {
            $data = Json::encode($data);
        }
        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);
        try {
            $response = $client->createRequest()
                ->setUrl($url)
                ->setHeaders($header)
                ->setOptions([CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false])
                ->setMethod('post')
                ->setContent($data)
                ->send();
        } catch (\yii\base\InvalidConfigException $e) {
            return false;
        }
        return Json::decode($response->content);
    }

    /**
     * 底层下载工具：将一个 URL 的内容保存到指定的本地路径。
     * @param string $url 要下载的 URL
     * @param string $savePath 包含文件名的完整本地保存路径
     * @return string 成功则返回保存路径
     * @throws \Exception
     */
    public static function download($url, $savePath)
    {
        if (empty($url)) {
            throw new \Exception('下载的URL不能为空');
        }

        $dir = dirname($savePath);

        // [优化1] 创建目录时强制给 0777 权限，防止因权限不足创建后无法写入
        try {
            if (!is_dir($dir)) {
                YiiFileHelper::createDirectory($dir, 0777, true);
            }
        } catch (Exception $e) {
            Yii::warning("创建目录失败: " . $e->getMessage());
            throw $e;
        }

        // [优化2] 写入前检查权限，不仅靠 try-catch，还能记录具体是谁没权限
        if (!is_writable($dir)) {
            $currentUser = get_current_user(); // 获取当前运行 PHP 的用户名
            $owner = fileowner($dir); // 获取文件夹所有者ID
            Yii::error("目录无写入权限! 路径: {$dir}, 当前运行用户: {$currentUser}, 文件夹所有者ID: {$owner}");
            // 尝试通过 PHP 修复权限 (仅当当前用户是 owner 或 root 时有效)
            @chmod($dir, 0777);
        }

        // 步骤一：打开文件句柄
        $fileHandle = @fopen($savePath, 'w+');
        if ($fileHandle === false) {
            // [优化3] 抛出更具体的错误信息
            $error = error_get_last();
            $phpError = isset($error['message']) ? $error['message'] : '未知错误';
            throw new \Exception("fopen失败，无法写入文件: {$savePath}。PHP系统错误: {$phpError}");
        }

        $client = new Client(['transport' => 'yii\httpclient\CurlTransport']);

        try {
            // 步骤二：将文件句柄传递给 http 客户端
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions([
                    CURLOPT_TIMEOUT => 60,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_FOLLOWLOCATION => true,
                ])
                ->setOutputFile($fileHandle)
                ->send();

            if (!$response->isOk) {
                if (is_resource($fileHandle)) @fclose($fileHandle);
                if (file_exists($savePath)) @unlink($savePath);
                Yii::error("下载文件失败。 URL: {$url}, StatusCode: {$response->statusCode}");
                throw new \Exception("文件下载失败 (HTTP Code: {$response->statusCode})");
            }

            // 关闭句柄，确保数据完全 flush 到磁盘再做后续校验
            if (is_resource($fileHandle)) @fclose($fileHandle);

            // 校验文件：空文件或内容是 HTML 错误页（CDN/OSS 偶发返回 200+HTML）
            $fileSize = file_exists($savePath) ? filesize($savePath) : 0;
            if ($fileSize === 0) {
                @unlink($savePath);
                throw new \Exception("下载完成但文件为空（网络中断或服务端无内容）: {$url}");
            }
            $header4 = file_get_contents($savePath, false, null, 0, 4);
            if ($header4 === false || ltrim($header4)[0] === '<') {
                // 内容以 '<' 开头，是 HTML 错误页，不是图片
                @unlink($savePath);
                Yii::error("下载的文件是 HTML 而非图片，URL: {$url}");
                throw new \Exception("下载内容非图片文件（服务端返回了 HTML 页面）: {$url}");
            }
        } catch (\Exception $e) {
            if (is_resource($fileHandle)) @fclose($fileHandle);
            if (file_exists($savePath)) @unlink($savePath);
            throw $e;
        }

        return $savePath;
    }
    /**
     * [新方法] 获取一个 URL 对应的本地永久文件路径。
     * 业务场景: 需要将外部图片长久保存在自己服务器上时使用。
     * 逻辑: 检查数据库，如果不存在，则下载、保存，并在 File 表中创建记录。
     *
     * @param string $url
     * @return string 永久文件的绝对路径
     * @throws \Exception
     */
    public static function getLocalPath(string $url): string
    {
        if (empty($url)) {
            throw new \Exception('URL 不能为空');
        }

        // 1. 优先从数据库查找
        $file = File::findOne(['url' => $url, 'is_delete' => 0]);
        if ($file && file_exists($file->path)) {
            return $file->path;
        }
        $webRoot = Yii::getAlias('@webroot');
        if (Yii::$app instanceof \yii\console\Application || !is_dir($webRoot)) {
            // 获取项目基础路径 (通常是 /www/wwwroot/xxxx)
            $basePath = Yii::$app->basePath;
            // 拼接 web 目录
            $webRoot = $basePath . '/web';
        }

        // 2. 如果不存在，则下载到永久目录
        $saveDir = $webRoot . '/uploads/images/' . date('Ymd');
        YiiFileHelper::createDirectory($saveDir);
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';
        $saveName = uniqid() . '.' . $extension;
        $savePath = $saveDir . $saveName;
        // 调用底层下载
        self::download($url, $savePath);
        $paths = explode($webRoot, $savePath);
        $url = ServerHelper::getHost() . $paths[1];

        // 3. 在 File 表中创建或更新记录
        if (!$file) {
            $file = new File();
        }
        $file->url = $url;
        $file->thumb_url = $url;
        $file->path = $savePath;
        // 可以在这里补充其他字段
        $file->mall_id = 0;
        if (!$file->save()) {
            Yii::error('下载文件后，在 File 表中创建记录失败: ' . json_encode($file->errors));
        }
        return $savePath;
    }

    /**
     * [新方法] 下载一个文件到临时目录，并自动计划在未来删除它。
     * 业务场景: 海报生成等需要临时使用网络图片的场景。
     * 逻辑: 总是执行下载操作，并推送一个异步删除任务。
     *
     * @param string $url
     * @return string 临时文件的绝对路径
     * @throws \Exception
     */
    public static function getTempPath(string $url): string
    {
        if (empty($url)) {
            throw new \Exception('URL 不能为空');
        }

        if (file_exists($url)) {
            return $url;
        }
        $saveDir = Yii::getAlias('@runtime/temp/');
        YiiFileHelper::createDirectory($saveDir);
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'tmp';
        $savePath = $saveDir . uniqid() . '.' . $extension;

        self::download($url, $savePath);

        // 计划在 5 分钟后删除这个临时文件
        JobHelper::push(new UnlinkJob(['path' => $savePath]), 5 * 60);

        return $savePath;
    }

    /**
     * 下载文件到临时目录 (为了兼容旧代码)
     * @param string $url
     * @param string $format (此参数已无用)
     * @return string 临时文件的绝对路径
     * @throws \Exception
     * @deprecated 请根据业务需要，明确调用 getTempPath() (用于临时文件) 或 getLocalPath() (用于永久文件)
     */
    public static function downloadTempFile($url, $format = 'tmp')
    {
        return self::getTempPath($url);
    }

    /**
     * [核心方法] 下载远程图片到本地，并返回完整的网络 URL
     * 场景：将 AI (如火山引擎/Midjourney) 返回的临时链接转存为本地永久链接
     *
     * @param string $url 远程图片地址
     * @return string 本地完整的 URL (e.g. https://domain.com/uploads/images/2025/...)
     */
    /**
     * [核心方法] 下载远程图片到本地，并返回完整的网络 URL
     * 修复版：兼容 Console/Queue 环境下的 webroot 路径问题
     *
     * @param string $url 远程图片地址
     * @return string 本地完整的 URL
     */
    public static function saveToLocalAndGetUrl($url)
    {
        try {

            $localFilePath = self::getLocalPath($url);
            $webRoot = Yii::getAlias('@webroot');

            if (Yii::$app instanceof \yii\console\Application || !is_dir($webRoot)) {
                $basePath = Yii::$app->basePath;
                // 拼接 web 目录
                $webRoot = $basePath . '/web';
            }

            $relativePath = str_replace($webRoot, '', $localFilePath);


            // 兼容 Windows 反斜杠
            $relativePath = str_replace('\\', '/', $relativePath);

            // 确保开头有斜杠
            if (strpos($relativePath, '/') !== 0) {
                $relativePath = '/' . $relativePath;
            }

            // 4. 获取域名 (Console 环境下 ServerHelper::getHost 可能也需要配置，但通常是从 option 表读的)
            $host = ServerHelper::getHost();

            return rtrim($host, '/') . $relativePath;
        } catch (\Exception $e) {
            Yii::error("图片转存失败 URL:{$url}。错误: " . $e->getMessage());
            return $url;
        }
    }
}