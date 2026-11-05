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
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;
use Imagine\Gd\Font;
use Imagine\Image\Palette\RGB;
use Yii;
use yii\base\Exception;

/**
 * Class PosterService
 * @package app\services
 *
 * 一个通用的、由后台配置驱动的海报生成服务。
 * 核心逻辑: 加载一张背景图，然后根据配置将动态文本和图片“贴”上去。
 */
class PosterService
{
    private $imagine;
    private $fontFile;

    /**
     * PosterService constructor.
     * @throws Exception
     */
    public function __construct()
    {
        $this->imagine = new Imagine();
        // [重要] 确保这个字体文件存在
        $this->fontFile = Yii::getAlias('@app/web/statics/fonts/NotoSansSC-Regular.otf');
        if (!file_exists($this->fontFile)) {
            Yii::error("海报服务初始化失败：字体文件不存在！路径: " . $this->fontFile);
            throw new Exception('海报服务缺少必要的字体文件。');
        }
    }

    /**
     * 主方法：根据后台配置和动态数据生成分享海报
     * @param string $configKey 在系统设置中存储海报配置的键名, e.g., 'user_share_poster'
     * @param array $dynamicData 包含所有动态数据的关联数组，键名必须与后台配置中的元素'name'对应
     * @return string 返回生成海报的相对 Web 路径, e.g., /uploads/posters/xxxx.png
     * @throws \Exception
     */
    public function generate(string $configKey, array $dynamicData): string
    {
        // 1. 从数据库获取指定的海报配置
        $config = OptionHelper::get($configKey, Yii::$app->mall->id);
        if (empty($config) || empty($config['background_image'])) {
            throw new Exception("海报配置 '{$configKey}' 不存在或背景图未设置。");
        }

        // 2. 定义缓存路径和文件名
        // 文件名基于配置和动态数据生成，确保内容变化时文件名也变化
        $cacheKeyData = array_merge($config, $dynamicData);
        $filename = md5(json_encode($cacheKeyData)) . '.png';
        $directory = Yii::getAlias('@webroot/uploads/user_posters/');
        $filePath = $directory . $filename;
        $relativeUrl = Yii::getAlias('@web/uploads/user_posters/') . $filename;

        if (file_exists($filePath)) {
            return $relativeUrl; // 如果缓存存在，直接返回
        }

        // 3. 开始绘制：以运营上传的背景图作为画布（远程图片先下载）
        $bgSource = $config['background_image'];
        if (preg_match('/^https?:\/\//', $bgSource)) {
            $bgSource = \app\helpers\HttpHelper::getTempPath($bgSource);
            if (!$bgSource || !file_exists($bgSource)) {
                throw new Exception('背景图下载失败');
            }
        }
        $canvas = $this->imagine->open($bgSource);
        $palette = new RGB();

        // 4. 遍历配置中的所有动态元素，并逐个绘制到画布上
        foreach ($config['elements'] as $element) {
            $name = $element['name'];

            // 如果传入的动态数据中没有这个元素的数据，则跳过
            if (!isset($dynamicData[$name]) || empty($dynamicData[$name])) {
                Yii::warning("生成海报 '{$configKey}' 时跳过元素 '{$name}'，因为缺少动态数据。");
                continue;
            }

            $content = $dynamicData[$name]; // 当前元素要填充的内容 (图片URL或文本)

            // 根据元素类型调用不同的绘制方法
            if ($element['type'] === 'image') {
                $this->drawImage($canvas, $content, $element);
            }
            elseif ($element['type'] === 'text') {
                $this->drawText($canvas, $content, $element);
            }
        }

        // 5. 保存最终合成的图片
        FileHelper::createDirectory($directory);
        $canvas->save($filePath, ['png_compression_level' => 7]); // 保存为PNG格式以支持透明度

        return $relativeUrl;
    }

    /**
     * [辅助函数] 绘制图片元素
     */
    private function drawImage($canvas, $imageUrl, $config)
    {
        try {
            // 远程图片先下载到本地临时文件，避免 Imagine::open 远程 URL 无超时卡死
            $localPath = $imageUrl;
            if (preg_match('/^https?:\/\//', $imageUrl)) {
                $localPath = \app\helpers\HttpHelper::getTempPath($imageUrl);
                if (!$localPath || !file_exists($localPath)) {
                    Yii::warning("海报元素图片下载失败: {$imageUrl}");
                    return;
                }
            }
            $image = $this->imagine->open($localPath);
            $image->resize(new Box($config['width'], $config['height']));

            // 如果配置要求将图片裁剪成圆形
            if (!empty($config['is_circle'])) {
                $mask = $this->imagine->create(new Box($config['width'], $config['height']));
                // 创建一个黑色的圆形蒙版
                $mask->draw()->ellipse(
                    new Point($config['width'] / 2, $config['height'] / 2),
                    new Box($config['width'], $config['height']),
                    (new RGB())->color('#000'),
                    true
                );
                $image->applyMask($mask);
            }

            $canvas->paste($image, new Point($config['x'], $config['y']));
        } catch (\Exception $e) {
            Yii::error("绘制图片元素失败: URL={$imageUrl}, 错误: " . $e->getMessage());
            // 即使单个图片绘制失败，也不中断整个流程
        }
    }

    /**
     * [辅助函数] 绘制文本元素
     */
    private function drawText($canvas, $text, $config)
    {
        try {
            $color = (new RGB())->color($config['color'] ?? '#FFFFFF');
            $font = new Font($this->fontFile, $config['font_size'], $color);

            $x = $config['x'];

            // 如果配置要求文本水平居中
            if (!empty($config['align_center'])) {
                $box = $font->box($text);
                $textWidth = $box->getWidth();
                // 居中X坐标 = (画布总宽度 - 文本宽度) / 2
                $x = ($canvas->getSize()->getWidth() - $textWidth) / 2;
            }

            $canvas->draw()->text($text, $font, new Point($x, $config['y']));
        } catch (\Exception $e) {
            Yii::error("绘制文本元素失败: Text={$text}, 错误: " . $e->getMessage());
        }
    }
}