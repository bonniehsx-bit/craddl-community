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

namespace app\services;

use app\helpers\FileHelper;
use app\helpers\OptionHelper;
use app\helpers\WechatHelper;
use Yii;
use EasyWeChat\Kernel\Http\StreamResponse;

class MiniProgramCodeService
{
    /** 常见微信错误码 → 能看懂的原因。其余原样透出 errcode 与 errmsg,便于排查 */
    const PATH_CODE_ERRORS = [
        40013 => 'AppID 无效,请核对「APPID」',
        40125 => '密钥不正确,请核对「密钥」(AppSecret)',
        40164 => '服务器 IP 不在小程序的 IP 白名单里,请到微信公众平台「开发管理 → 开发设置」添加',
        45029 => '这个小程序生成的码已达微信 10 万个的累计上限',
        41030 => '页面路径不存在,请确认该页面已在小程序正式版中发布',
    ];

    /**
     * 按「页面路径 + 查询参数」生成小程序码(微信 wxa/getwxacode)。
     *
     * 和下面的 getUnlimitedSceneCode 不是一回事,选错会让扫码进来的页面拿不到参数:
     *   · getUnlimitedSceneCode(getwxacodeunlimit):数量不限,但参数只能塞进 32 字符的 scene,
     *     要靠小程序页面自己解 scene。全站只有少数页面这么做了(商品详情等),
     *     其他页面扫进去会缺参数。适合海报这类「页面固定、确认会解 scene」的场景。
     *   · 本方法(getwxacode):参数原样放在 path 里,页面 onLoad(options) 直接收到,
     *     任何页面都能用。代价是每个小程序累计上限 10 万个码,所以只给后台手动生成用。
     *
     * 缓存按 appid + path 区分:不同商城是不同的小程序,同一路径生成的码并不相同;
     * 改了 appid 之后旧图也不能再用。同一链接重复获取直接走缓存,不重复消耗额度。
     *
     * @param string $path 不带开头斜杠,可带查询参数,例如 others/goods/goods?id=12
     * @param int $mallId
     * @return string 相对 Web 路径,例如 /uploads/mini_qrcode/path_xxx.jpg
     * @throws \Exception 失败原因可直接展示给后台用户
     */
    public function getPathCode(string $path, $mallId): string
    {
        $mpwx = OptionHelper::get('platform_mpwx', $mallId);
        $appid = is_array($mpwx) ? trim((string)($mpwx['appid'] ?? '')) : '';
        $secret = is_array($mpwx) ? trim((string)($mpwx['secret'] ?? '')) : '';
        if ($appid === '' || $secret === '') {
            throw new \Exception('请先填写小程序的 APPID 和密钥并保存');
        }
        WechatHelper::init($mallId);

        $webRoot = Yii::getAlias('@webroot');
        if (Yii::$app instanceof \yii\console\Application || !is_dir($webRoot)) {
            $webRoot = Yii::$app->basePath . '/web';
        }
        $webPath = '/uploads/mini_qrcode/';
        $directory = $webRoot . $webPath;
        $base = 'path_' . md5($appid . '|' . $path);

        // 扩展名跟着真实格式走,缓存命中时两种都要找(见下方保存处的说明)
        foreach (['jpg', 'png'] as $ext) {
            if (file_exists($directory . $base . '.' . $ext)) {
                return $webPath . $base . '.' . $ext;
            }
        }

        try {
            $client = Yii::$app->wechat->miniProgram->getClient();
            $response = $client->postJson('wxa/getwxacode', ['path' => $path]);
        } catch (\Exception $e) {
            // 后台专用:把真实原因带出去。拿 access_token 失败(密钥错、IP 白名单)都会落在这里,
            // 只报一句「配置或网络异常」的话,运营完全不知道该去改哪
            $safe = self::redact($e->getMessage());
            Yii::error('生成小程序码(getwxacode)异常: ' . $safe);
            throw new \Exception('调用微信接口失败:' . self::explainWechatError($safe));
        }

        if ($response->getStatusCode() !== 200) {
            Yii::error('生成小程序码(getwxacode) HTTP ' . $response->getStatusCode());
            throw new \Exception('调用微信接口失败:HTTP ' . $response->getStatusCode());
        }

        $content = $response->getContent();
        // 成功时微信直接返回图片二进制;失败时返回 JSON
        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['errcode']) && (int)$decoded['errcode'] !== 0) {
            Yii::error('生成小程序码(getwxacode)失败: ' . self::redact($content));
            throw new \Exception(self::explainWechatError(self::redact($content)));
        }

        // getwxacode 不传 is_hyaline 时返回的是 JPEG(实测),传了透明底才是 PNG。
        // 按文件头判断,不去假设微信给什么 —— 扩展名和内容对不上的话,
        // 用户下载下来是个 .png 实为 JPEG 的文件,部分看图、印刷软件会报错
        $ext = strncmp($content, "\x89PNG", 4) === 0 ? 'png' : 'jpg';
        $filePath = $directory . $base . '.' . $ext;

        FileHelper::createDirectory($directory);
        file_put_contents($filePath, $content);
        if (!file_exists($filePath)) {
            throw new \Exception('保存小程序码失败,请检查目录权限:' . $directory);
        }
        return $webPath . $base . '.' . $ext;
    }

    /**
     * 抹掉密钥和令牌。
     *
     * SDK 换 access_token 失败时,异常消息里带着完整请求地址
     * (…/cgi-bin/token?grant_type=client_credential&appid=…&secret=明文),
     * 不处理的话 AppSecret 会原样出现在接口返回和日志里。
     */
    private static function redact(string $text): string
    {
        return preg_replace('/((?:secret|access_token|component_access_token)=)[^&"\s]+/i', '$1***', $text);
    }

    /**
     * 从微信返回(或异常消息里夹带的 JSON)中取出 errcode,翻译成能看懂的原因。
     */
    private static function explainWechatError(string $raw): string
    {
        if (preg_match('/"errcode"\s*:\s*(\d+)/', $raw, $m)) {
            $code = (int)$m[1];
            $msg = preg_match('/"errmsg"\s*:\s*"([^"]*)"/', $raw, $mm) ? $mm[1] : '';
            $hint = self::PATH_CODE_ERRORS[$code] ?? '微信返回错误';
            return "{$hint}(errcode {$code}" . ($msg !== '' ? ": {$msg}" : '') . ')';
        }
        return mb_substr($raw, 0, 200);
    }

    public function getUnlimitedSceneCode(string $page, string $scene, $mall_id = 1, $is_hyaline = true): string
    {
        WechatHelper::init($mall_id);

        // 1. 获取 Web 根目录的物理路径
        $webRoot = Yii::getAlias('@webroot');
        if (Yii::$app instanceof \yii\console\Application || !is_dir($webRoot)) {
            $basePath = Yii::$app->basePath;
            $webRoot = $basePath . '/web';
        }

        // ==================== 修改开始 ====================

        // 定义相对 Web 路径 (用于浏览器访问，以及返回给前端)
        $webPath = '/uploads/mini_qrcode/';

        // 定义物理存储目录 (用于 file_exists 和 saveAs)
        $directory = $webRoot . $webPath;

        $filename = md5($scene . $page) . '.png';

        // 物理文件的完整路径
        $filePath = $directory . $filename;

        // 返回给调用者的应该是相对 Web 路径，而不是物理路径
        $resultUrl = $webPath . $filename;

        // ==================== 修改结束 ====================

        // 2. 检查文件是否已存在（缓存机制）
        if (file_exists($filePath)) {
            return $resultUrl; // 返回相对 Web 路径，例如 /uploads/mini_qrcode/xxx.png
        }


        // 3. 调用 EasyWeChat SDK (v6)
        try {
            $app = Yii::$app->wechat->miniProgram;
            $client = $app->getClient();
            $response = $client->postJson('wxa/getwxacodeunlimit', [
                'scene' => $scene,
                'page' => $page,
                'is_hyaline' => $is_hyaline,
            ]);
        } catch (\Exception $e) {
            Yii::error("获取微信小程序实例或调用接口时发生异常: " . $e->getMessage());
            throw new \Exception('小程序码服务配置或网络异常');
        }

        // 4. 解析响应并保存
        if ($response->getStatusCode() === 200) {
            $content = $response->getContent();

            // 检查内容是否为 JSON 格式的错误信息
            $decoded = json_decode($content, true);
            if (is_array($decoded) && isset($decoded['errcode']) && $decoded['errcode'] !== 0) {
                Yii::error("微信接口获取小程序码失败: " . $content);
                throw new \Exception('小程序码生成失败：' . ($decoded['errmsg'] ?? '未知错误'));
            }

            FileHelper::createDirectory($directory); // 确保目录存在
            file_put_contents($filePath, $content); // 保存二进制内容到物理路径

            if (!file_exists($filePath)) {
                throw new \Exception('保存小程序码文件失败，请检查目录权限：' . $directory);
            }

            return $resultUrl; // 返回相对 Web 路径
        } else {
            Yii::error("微信接口HTTP请求失败: HTTP " . $response->getStatusCode());
            throw new \Exception('小程序码生成失败：网络请求不成功');
        }
    }
}
