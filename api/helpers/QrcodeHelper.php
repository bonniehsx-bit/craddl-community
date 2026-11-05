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

use Yii;
use Endroid\QrCode\QrCode;
use yii\helpers\FileHelper;

class QrcodeHelper
{
    /**
     * 生成二维码
     * @param string $data 二维码内容
     * @param string|null $saveName 自定义文件名 (建议包含相对路径，如 'goods/123.png')
     * @return array
     * @throws \yii\base\Exception
     */
    public static function create($data, $saveName = null)
    {
        // 1. 定义基础相对目录
        $baseDir = '/uploads/qrcode';

        // 2. 确定相对路径文件名
        if (!empty($saveName)) {
            // 如果传入了 saveName，直接拼接到 baseDir 后面
            // 注意：调用者需要确保 saveName 包含后缀，或者在这里强制加后缀
            $relativePath = $baseDir . '/' . $saveName;
        } else {
            // 如果没传，自动生成
            $relativePath = $baseDir . '/' . md5($data) . '.png';
        }

        // 3. 获取服务器物理绝对路径
        $webRoot = Yii::getAlias('@webroot');
        $absolutePath = $webRoot . $relativePath;

        // 4. 获取文件所在的目录路径 (用于创建文件夹)
        $dirPath = dirname($absolutePath);

        // 5. 如果文件不存在，则生成
        if (!file_exists($absolutePath)) {
            // [修复] 创建目录时，使用 dirname($absolutePath) 确保子目录（如 goods/）也能被创建
            if (FileHelper::createDirectory($dirPath)) {

                // 初始化二维码
                $qrCode = new QrCode($data);
                $qrCode->setSize(200);
                $qrCode->setMargin(10);

                // 保存文件
                $qrCode->writeFile($absolutePath);
            }
        }

        // 6. 返回结果
        return [
            // 物理路径：用于 PHP 内部处理（如绘制海报时 open 图片）
            'path' => $absolutePath,

            // 完整 URL：用于前端展示或 API 返回
            // 假设你有一个 ServerHelper::getHost() 获取 http://域名，如果没有，可以用 Yii::$app->request->hostInfo
            'url' => \app\helpers\ServerHelper::getHost() . $relativePath,

            // 相对路径：备用
            'file_name' => $relativePath
        ];
    }
}