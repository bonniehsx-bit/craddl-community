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

namespace app\plugins\mch\services;

use app\helpers\FileHelper;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Gd\Font;

// 即使使用Imagick，字体处理依然可以依赖此组件
use Yii;
use yii\base\Exception;

/**
 * Class PosterGenerateService
 * @package app\services
 *
 * [最终优化版] 负责使用 Imagick 驱动生成高质量海报图片。
 */
class PosterGenerateService
{
    private const CANVAS_WIDTH = 640;
    private const CANVAS_HEIGHT = 1000;
    private const FONT_PATH = '@app/web/statics/fonts/NotoSansSC-Regular.otf';

    private $fontFile;
    private $imagine;

    public function __construct()
    {
        $this->fontFile = Yii::getAlias(self::FONT_PATH);
        if (!file_exists($this->fontFile)) {
            Yii::error("海报生成失败：字体文件不存在！路径: " . $this->fontFile);
            throw new Exception('海报服务初始化失败：缺少必要的字体文件。');
        }

        // [核心] 明确指定使用 Imagick 驱动
        if (!class_exists('\Imagine\Imagick\Imagine')) {
            throw new Exception('海报服务初始化失败：Imagine Imagick 驱动未找到，请确认是否已安装。');
        }
        if (!extension_loaded('imagick')) {
            throw new Exception('海报服务初始化失败：服务器未安装或未启用 imagick PHP 扩展。');
        }
        $this->imagine = new \Imagine\Imagick\Imagine();
    }

    /**
     * 主方法：根据传入的数据生成商家店铺推广海报
     * @param array $data 包含所有海报所需信息的数组, 包括完整的图片URL
     * @return string 返回生成海报的相对Web路径, e.g., /uploads/shop_poster/xxxx.png
     * @throws \Exception
     */
    public function generateMchShopPoster(array $data): string
    {
        $filename = md5(json_encode($data)) . '.png';
        $directory = Yii::getAlias('@webroot/uploads/shop_poster/');
        $filePath = $directory . $filename;
        $relativeUrl = Yii::getAlias('@web/uploads/shop_poster/') . $filename;
        if (file_exists($filePath)) {
            return $relativeUrl;
        }
        $canvas = $this->draw($data);

        FileHelper::createDirectory($directory);
        // 使用 png 格式以支持完美的透明度
        $canvas->save($filePath, ['png_compression_level' => 7]);

        return $relativeUrl;
    }

    /**
     * 核心绘制方法
     * @param array $data 包含所有文本和图片URL的数据
     * @return \Imagine\Image\ImageInterface
     * @throws \Exception
     */
    private function draw(array $data): \Imagine\Image\ImageInterface
    {
        // --- 使用静态背景图 ---
        $posterBgPath = Yii::getAlias('@webroot/poster/shop_poster_bg.png');
        if (!file_exists($posterBgPath)) {
            throw new Exception('海报背景图文件不存在: ' . $posterBgPath);
        }

        // 1. 打开背景图作为画布
        $canvas = $this->imagine->open($posterBgPath);
        // 确保画布尺寸与常量一致
        $canvas->resize(new Box(self::CANVAS_WIDTH, self::CANVAS_HEIGHT));
        // --- 修改结束 ---

        $palette = new \Imagine\Image\Palette\RGB();
        $white = $palette->color('#FFFFFF'); // 白色用于绘制文字

        // --- 布局调整 ---
        $padding = 40; // 边距

        // 2. 绘制圆形 Logo (调整到左上角)
        $logoSize = new Box(80, 80);
        $hasLogo = false;
        if (!empty($data['mch_info']['logo_url'])) {
            try {
                /** @var \Imagine\Imagick\Image $logo */
                $logo = $this->imagine->open($data['mch_info']['logo_url']);
                $logo->resize($logoSize);

                $imagick = $logo->getImagick();
                $imagick->roundCorners($logoSize->getWidth() / 2, $logoSize->getHeight() / 2);

                $pastePoint = new Point($padding, $padding); // 左上角坐标 + 边距
                $canvas->paste($logo, $pastePoint);
                $hasLogo = true;
            } catch (\Exception $e) {
                Yii::error("绘制 Logo 失败: " . $e->getMessage());
            }
        }

        // 3. 绘制商家名称 (调整到 Logo 右侧)
        $mchNameFontSize = 18;
        $mchNameY = $padding + ($logoSize->getHeight() / 2) - ($mchNameFontSize / 2); // 垂直居中于 Logo
        $mchNameX = $hasLogo ? $padding + $logoSize->getWidth() + 20 : $padding;
        $mchNameMaxWidth = self::CANVAS_WIDTH - $mchNameX - $padding; // 最大宽度限制
        $this->drawSingleLineText($canvas, $data['mch_info']['mch_name'], $mchNameFontSize, $mchNameX, $mchNameY, $white, $mchNameMaxWidth);
        // 4. 绘制二维码 (调整到中间靠下位置)
        if (!empty($data['qr_code_image_url'])) {
            try {
                $qrCodeSize = new Box(380, 380); // 二维码尺寸调整
                $qrCode = $this->imagine->open($data['qr_code_image_url']);
                $qrCode->resize($qrCodeSize);
                $qrCodeY = 500; // Y 坐标调整
                $pastePoint = new Point((self::CANVAS_WIDTH - $qrCodeSize->getWidth()) / 2, $qrCodeY); // 水平居中
                $canvas->paste($qrCode, $pastePoint);
            } catch (\Exception $e) {
                Yii::error("绘制二维码失败: " . $e->getMessage());
            }
        }

        // [移除] 不再需要绘制 sub_title, service_text 和底部提示

        return $canvas;
    }

    /**
     * [修改] 绘制单行文字（超出部分截断或省略）
     */
    private function drawSingleLineText($canvas, $text, $size, $startX, $startY, $color, $maxWidth)
    {
        $font = new Font($this->fontFile, $size, $color);
        $box = $font->box($text);

        // 如果文本本身就超宽，需要截断处理（这里简单截断，复杂省略号需要额外计算）
        if ($box->getWidth() > $maxWidth) {
            // 这是一个简单的估算截断，可能不完全精确
            $charWidth = $box->getWidth() / mb_strlen($text, 'UTF-8');
            $maxChars = floor($maxWidth / $charWidth);
            $text = mb_substr($text, 0, $maxChars - 1, 'UTF-8') . ''; // 可以加省略号 ...
        }

        $canvas->draw()->text($text, $font, new Point($startX, $startY));
    }


    /**
     * 绘制可换行、水平居中的文字 (保留此方法，以备将来可能需要)
     */
    private function drawMultiLineText($canvas, $text, $size, $startY, $color, $maxWidth, $lineHeightRatio = 1.5)
    {
        $font = new Font($this->fontFile, $size, $color);
        $lineHeight = $size * $lineHeightRatio;
        $lines = [];
        $currentLine = '';

        if (mb_strlen($text, 'UTF-8') === 0) {
            return;
        }

        $words = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($words as $char) {
            $testLine = $currentLine . $char;
            $box = $font->box($testLine);
            if ($box->getWidth() > $maxWidth) {
                $lines[] = $currentLine;
                $currentLine = $char;
            } else {
                $currentLine = $testLine;
            }
        }
        $lines[] = $currentLine;

        $currentY = $startY;
        foreach ($lines as $line) {
            $box = $font->box($line);
            $textWidth = $box->getWidth();
            $x = (self::CANVAS_WIDTH - $textWidth) / 2;

            $canvas->draw()->text($line, $font, new Point($x, $currentY));
            $currentY += $lineHeight;
        }
    }
}

