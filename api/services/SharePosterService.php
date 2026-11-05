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
use app\helpers\HttpHelper;
use app\helpers\ImageHelper;
use Imagine\Gd\Font;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Image\Palette\RGB;
use Yii;

/**
 * Class SharePosterService
 * @package app\services
 *
 * 这是一个通用的海报生成服务，严格按照 PosterHelper 提供的配置数组进行绘制。
 */
class SharePosterService
{
    private $imagine;
    private $fontFile;
    private $tempFiles = [];

    public function __construct()
    {
        $this->imagine = new Imagine();
        $this->fontFile = Yii::getAlias('@app/web/statics/fonts/NotoSansSC-Regular.otf');
        if (!file_exists($this->fontFile)) {
            throw new \Exception('海报服务缺少字体文件');
        }
    }

    public function __destruct()
    {
        foreach ($this->tempFiles as $file) {
            if (file_exists($file)) @unlink($file);
        }
    }

    /**
     * 主方法：接收配置和动态数据URL，完成所有下载、处理和绘制工作
     * @param array $config PosterHelper 返回的配置数组
     * @param array $dynamicData 包含网络URL和文本的动态数据
     * @return string 返回最终海报的本地绝对路径（在临时目录中）
     */
    public function generate(array $config, array $dynamicData): string
    {
        // 1. [核心] 准备素材：下载所有网络图片，处理成圆形等，并用本地路径替换配置中的 URL
        $finalConfig = $this->prepareLocalMaterials($config, $dynamicData);

        // 2. 绘制海报
        $bgConfig = $finalConfig['bg_pic'];
        $canvas = $this->imagine->open($bgConfig['path']);
        $canvas->resize(new Box($bgConfig['width'], $bgConfig['height']));

        foreach ($finalConfig as $key => $item) {
            if ($key === 'bg_pic' || empty($item['is_show'])) continue;

            if ($item['file_type'] === 'image') $this->drawImage($canvas, $item);
            elseif ($item['file_type'] === 'text') $this->drawText($canvas, $item);
        }

        // 3. 保存到临时文件并返回路径
        $saveDir = Yii::getAlias('@runtime/poster_temp/');
        FileHelper::createDirectory($saveDir);
        $savePath = $saveDir . uniqid() . '.jpg';
        $canvas->save($savePath, ['jpeg_quality' => 90]);
        $this->tempFiles[] = $savePath;

        return $savePath;
    }

    /**
     * 将配置中的网络资源下载并处理为本地路径
     */
    private function prepareLocalMaterials(array $config, array $dynamicData): array
    {
        // 填充动态文本
        foreach ($dynamicData as $key => $value) {
            if (isset($config[$key]) && $config[$key]['file_type'] === 'text') {
                $config[$key]['text'] = $value;
            }
        }

        // 处理所有图片元素
        foreach ($config as $key => &$item) {
            if ($item['file_type'] === 'image') {
                // 优先使用动态数据中的 URL (如头像, 二维码)，其次是配置中的默认 URL (如背景图)
                $imageUrl = $dynamicData[$key] ?? $item['url'] ?? null;
                Yii::warning("下载图片: " . $imageUrl);
                if ($imageUrl) {

                    $localPath = HttpHelper::getTempPath($imageUrl);
                    $this->tempFiles[] = $localPath;

                    // 如果是头像，需要裁成圆形
                    if ($key === 'head') {
                        $circlePath = ImageHelper::getCircleImage($localPath);
                        $this->tempFiles[] = $circlePath;
                        $item['path'] = $circlePath;
                    } else {
                        $item['path'] = $localPath;
                    }
                }
            }
        }
        unset($item);

        return $config;
    }

    /**
     * 绘制图片元素
     */
    private function drawImage($canvas, array $config)
    {
        if (empty($config['path']) || !file_exists($config['path'])) {
            Yii::warning("跳过绘制图片元素，因为路径不存在: " . ($config['path'] ?? ''));
            return;
        }

        try {
            $image = $this->imagine->open($config['path']);
            $image->resize(new Box($config['width'], $config['height']));
            $canvas->paste($image, new Point($config['left'], $config['top']));
        } catch (\Exception $e) {
            Yii::error("绘制图片失败: " . $e->getMessage());
        }
    }

    /**
     * 绘制文本元素
     */
    private function drawText($canvas, array $config)
    {
        try {
            $color = (new RGB())->color($config['color'] ?? '#000000');
            $font = new Font($this->fontFile, $config['size'], $color);

            $text = $config['text'] ?? '';
            $point = new Point($config['left'], $config['top']);

            // [新增] 处理多行文本逻辑
            if (!empty($config['max_line']) && $config['max_line'] > 1 && !empty($config['width'])) {
                $wrappedText = $this->wrapText($text, $config['size'], $this->fontFile, $config['width']);
                $lines = explode("\n", $wrappedText);
                $lines = array_slice($lines, 0, $config['max_line']); // 最多显示 max_line 行

                $lineHeight = $config['size'] * 1.5; // 1.5倍行高
                foreach ($lines as $index => $line) {
                    $currentPoint = new Point($config['left'], $config['top'] + ($index * $lineHeight));
                    $canvas->draw()->text($line, $font, $currentPoint);
                }
            } else {
                // 单行文本
                $canvas->draw()->text($text, $font, $point);
            }

            // [新增] 处理删除线
            if (!empty($config['is_strike'])) {
                $box = $font->box($text);
                $textWidth = $box->getWidth();
                $lineY = $point->getY() - ($box->getHeight() / 2); // 文字中线位置
                $canvas->draw()->line(
                    new Point($point->getX(), $lineY),
                    new Point($point->getX() + $textWidth, $lineY),
                    $color
                );
            }

        } catch (\Exception $e) {
            Yii::error("绘制文本失败: " . $e->getMessage());
        }
    }

    /**
     * 辅助函数：将长文本分割成多行
     */
    private function wrapText($text, $size, $fontFile, $maxWidth): string
    {
        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';
        foreach ($words as $word) {
            $testLine = $currentLine . ($currentLine ? ' ' : '') . $word;
            $box = imagettfbbox($size, 0, $fontFile, $testLine);
            if ($box[2] - $box[0] > $maxWidth) {
                $lines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        $lines[] = $currentLine;
        return implode("\n", $lines);
    }
}