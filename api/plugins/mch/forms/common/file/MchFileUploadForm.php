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

namespace app\plugins\mch\forms\common\file;

use app\core\ApiCode;
use app\forms\common\file\FileUploadForm;
use app\models\File;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use Exception;
use Yii;

/**
 * Class MchFileUploadForm
 * @package app\plugins\mch\forms\common\file
 * @description 商家专用文件上传类，强制绑定 mch_id
 */
class MchFileUploadForm extends FileUploadForm
{
    /**
     * @var int 强制绑定的商户ID
     */
    public $mch_id;

    public function rules()
    {
        // 继承父类规则，并添加 mch_id 验证
        return array_merge(parent::rules(), [
            [['mch_id'], 'required', 'message' => '缺少商户ID'],
            [['mch_id'], 'integer'],
        ]);
    }

    /**
     * 重写：保存到数据库
     * 核心目的：将文件归属到指定的 mch_id，而不是依赖 UserHelper 的自动判断
     * @param array $uploadResult
     * @return File
     * @throws Exception
     */
    protected function saveToDatabase(array $uploadResult): File
    {
        $file = new File();
        $file->mall_id = \Yii::$app->mall->id ?? 0;
        $file->mch_id = $this->mch_id; // 【核心】强制写入 Controller 传入的 mch_id

        $file->type = $this->type;
        $file->location = $this->location;
        $file->path = $this->fileKey;
        $file->url = $uploadResult['url'];
        $file->thumb_url = $uploadResult['thumb_url'];
        $file->name = $uploadResult['name'];
        $file->size = (int) ($uploadResult['size'] ?? 0);
        $file->width = (int) ($uploadResult['width'] ?? 0);
        $file->height = (int) ($uploadResult['height'] ?? 0);

        $file->group_id = $this->group_id ?: 0;
        $file->is_site = $this->is_site ?: 0;
        $file->is_delete = 0;

        // 记录上传操作员 (店长或店员)
        $user = Yii::$app->user->identity;
        if ($user) {
            $file->user_id = $user->id;
        }

        if (!$file->save()) {
            \Yii::error("[商家文件上传] 数据库保存失败: " . json_encode($file->getFirstErrors()));
            // 如果入库失败，尝试清理已上传的物理文件
            // $this->deleteUploadedFile($file); // 父类方法如果是 private 则无法调用，建议也改为 protected
            throw new Exception("文件信息入库失败: " . current($file->getFirstErrors()));
        }

        return $file;
    }
}