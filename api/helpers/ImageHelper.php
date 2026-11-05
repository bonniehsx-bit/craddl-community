<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-25
 * Time: 12:00
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
use Imagick;
use Imagine\Gd\Imagine;
use Imagine\Image\Box;
use Imagine\Image\Point;

class ImageHelper
{
    public static function getUrl($name)
    {
        return ServerHelper::getHost() . $name;
    }

    public static function getUrlOnPlugin($name)
    {
        return ServerHelper::getHost() . '/plugins' . $name;
    }


    //请确保是方形的图片
    public static function getCircleImage($path, $format = 'png')
    {
        if (!file_exists($path) || filesize($path) === 0) {
            throw new \Exception("getCircleImage: 图片文件不存在或为空: {$path}");
        }
        try {
            $ic = new Imagick($path); //打开正方形的
        } catch (\ImagickException $e) {
            throw new \Exception("getCircleImage: Imagick 无法解析图片（文件可能损坏）: {$path} — " . $e->getMessage());
        }
        $ic->setImageFormat($format);
        $width = $ic->getImageWidth();
        $ic->roundCorners($width / 2, $width / 2);
        $temp_path = \Yii::$app->runtimePath . '/temp/' . uniqid() . '.' . $format;
        $ic->writeImage($temp_path); //剪成原型
        $ic->destroy();
        JobHelper::push(new UnlinkJob(['path' => $temp_path]), 5 * 60);
        return $temp_path;
    }


    //裁剪为正方形
    public static function getSquareImage($path, $format = 'jpg')
    {
        $gine = new Imagine();
        $image = $gine->open($path);
        $height = $image->getSize()->getHeight();
        $width = $image->getSize()->getWidth();
        $width = $height > $width ? $width : $height;
        $image->crop(new Point(0, 0), new Box($width, $width)); //剪成正方形
        $temp_path = \Yii::$app->runtimePath . '/temp/' . uniqid() . '.' . $format;
        $image->save($temp_path);
        JobHelper::push(new UnlinkJob(['path' => $temp_path]), 5 * 60);
        return $temp_path;
    }

    //设置水印
    public static function setWaterMark($original, $watermark)
    {

        // 源图片路径
        $sourceImage = $original;
        // 打开源图片
        $image = imagecreatefromjpeg($sourceImage);

        // 获取源图片的宽度和高度
        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        // 水印图片路径
        $watermarkImage = $watermark;
        // 打开水印图片
        $watermark = imagecreatefrompng($watermarkImage);

        // 获取水印图片的宽度和高度
        $watermarkWidth = imagesx($watermark);
        $watermarkHeight = imagesy($watermark);

        // 水印位置（右下角，可根据需求调整）
        $wmX = $imageWidth - $watermarkWidth - 10;
        $wmY = $imageHeight - $watermarkHeight - 10;

        // 将水印图片复制到源图片上
        imagecopy($image, $watermark, $wmX, $wmY, 0, 0, $watermarkWidth, $watermarkHeight);

        // 覆盖原文件
        imagejpeg($image, $sourceImage);

        // 释放内存
        imagedestroy($image);
        imagedestroy($watermark);
    }
}
