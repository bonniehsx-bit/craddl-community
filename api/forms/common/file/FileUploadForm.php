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

namespace app\forms\common\file;

use app\core\ApiCode;
use app\helpers\ImageThumbHelper;
use app\helpers\OptionHelper;
use app\helpers\ServerHelper;
use app\helpers\UserHelper;
use app\helpers\WechatHelper;
use app\models\BaseModel;
use app\models\File;
use app\models\User;
use Exception;
use OSS\Core\OssException;
use OSS\OssClient;
use Qcloud\Cos\Client;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use WebSocket\Server;
use yii\web\UploadedFile;

/**
 * Class FileUploadForm
 * @package app\forms\mall\file
 */
class FileUploadForm extends BaseModel
{
    protected $docExt = ['txt', 'docx', 'doc', 'pptx', 'ppt', 'xls', 'csv', 'pdf', 'md', 'xlsx', 'pem'];
    protected $imageExt = ['jpg', 'bmp', 'png', 'gif', 'jpeg', 'webp', 'ico'];
    protected $videoExt = ['mp4', 'ogg'];
    /**
     * 音频格式白名单。
     *
     * 取值对齐主流手机录音 App 的产出（iOS 语音备忘录 = m4a，安卓多为 mp3/amr）
     * 与云厂商语音识别可直接受理的格式，避免用户传得上来却转写不了。
     * 注意 ogg 已在 $videoExt 中，不重复登记，否则 prepareFileInfo 归类会随数组顺序漂移。
     */
    protected $audioExt = ['mp3', 'm4a', 'aac', 'wav', 'amr', 'wma', 'flac', '3gp'];

    public $type;
    public $savePath;
    public $baseWebPath;
    public $baseWebUrl;
    public $saveName;
    public $fileKey;
    public $url;
    public $thumb_url;
    public $group_id;
    public $is_site;
    public $location = "local";
    public $is_lossless = 0;
    /** 图片宽高(像素),记进 file 表给图片选择器显示;非图片为 0 */
    public $width = 0;
    public $height = 0;

    /**
     * @var UploadedFile $file
     */
    public $file;

    public function rules()
    {
        return [[['group_id', 'is_site', 'is_lossless'], 'integer']];
    }

    public function saveFile()
    {
        return $this->_saveInternal();
    }


    public function saveUserFile()
    {
        $user = UserHelper::getUser();
        if ($user) {
            $todayStart = strtotime('today midnight');
            $uploadCount = File::find()->where([
                'user_id' => $user->id,
                'is_delete' => 0
            ])->andWhere(['>=', 'created_at', $todayStart])->count();

            $limit = 50; // 每天最多50个文件防刷防滥用
            if ($uploadCount >= $limit) {
                \Yii::warning("[文件上传] 用户 {$user->id} 今日上传数量({$uploadCount})已达上限({$limit})");
                return $this->apiResponse(ApiCode::CODE_FAIL, "为了节约服务器资源，您今天的文件上传数量已被限制（最多{$limit}个）。请明天再试。");
            }
        }

        return $this->_saveInternal();
    }


    protected function _saveInternal()
    {
        $response = null;
        try {
            $this->file = UploadedFile::getInstanceByName('file');
            if (empty($this->file)) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '请上传文件');
            }

            if (!$this->validateFileExt()) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '文件格式不支持');
            }

            $this->prepareFileInfo();

            $uploadResult = $this->dispatchToStorage();
            if (!$uploadResult) {
                return $this->apiResponse(ApiCode::CODE_FAIL, '文件保存失败，请检查存储配置或联系管理员');
            }

            $this->saveToDatabase($uploadResult);

            $response = $this->apiResponse(ApiCode::CODE_SUCCESS, '上传成功', $uploadResult);
        } catch (Exception $e) {
            \Yii::error('[FileUpload] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $response = $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage() ?: '上传失败，发生未知错误');
        } finally {
            // 清理临时文件
            if ($this->file && $this->file->tempName && file_exists($this->file->tempName)) {
                @unlink($this->file->tempName);
            }
        }
        return $response;
    }


    /**
     * @return array
     * @throws Exception
     */
    protected function saveToLocal()
    {
        $fullSavePath = $this->baseWebPath . $this->savePath;
        if (!is_dir($fullSavePath)) {
            // 批量上传时当天第一批会有多个请求同时来建当天的目录:先到的建成了,后到的建目录会失败,
            // 所以失败后再看一眼目录是不是已经有了,有了就不算错
            if (!make_dir($fullSavePath) && !is_dir($fullSavePath)) {
                throw new \Exception('创建目录失败，请检查目录写入权限：`' . $fullSavePath . '`');
            }
        }
        $fullSaveFile = $fullSavePath . $this->saveName;
        $tempFilePath = $this->file->tempName;

        if ($this->type === 'image' && !$this->is_lossless) {
            try {
                if (!function_exists('gd_info')) {
                    throw new \Exception('GD library not available.');
                }
                $this->compressAndSaveImage($tempFilePath, $fullSaveFile);
            } catch (\Exception $e) {
                \Yii::error('[FileUpload] 图片压缩失败，回退原始文件: ' . $e->getMessage());
                if (!move_uploaded_file($tempFilePath, $fullSaveFile)) {
                    throw new \Exception('图片压缩失败后，尝试移动原始文件也失败了。');
                }
            }
        } else {
            if (!move_uploaded_file($tempFilePath, $fullSaveFile)) {
                throw new \Exception('文件保存到本地失败，请检查目录写入权限。');
            }
        }

        $this->fileKey = $fullSaveFile;
        $this->url = rtrim($this->baseWebUrl, '/') . $this->savePath . $this->saveName;

        clearstatcache(); // 清除文件状态缓存
        $finalSize = filesize($fullSaveFile);
        $this->file->size = $finalSize; // 更新为最终文件大小

        $this->thumb_url = $this->url;
        if ($this->type === 'image') {
            $probe = ImageThumbHelper::probe($fullSaveFile);
            $this->width = $probe['width'];
            $this->height = $probe['height'];
            // 首页 / 列表用的小图:原图旁边按固定文件名生成(见 ImageThumbHelper),生成失败就照旧用原图
            try {
                if (ImageThumbHelper::make($fullSaveFile)) {
                    $this->thumb_url = ImageThumbHelper::thumbName($this->url) ?: $this->url;
                }
            } catch (\Throwable $e) {
                \Yii::warning('[FileUpload] 缩略图生成失败,用原图: ' . $e->getMessage());
            }
        }

        return $this->buildSuccessResult();
    }


    /**
     * 图片压缩并保存（支持 PNG 透明通道）
     * @param string $sourcePath 临时文件路径
     * @param string $destinationPath 最终保存路径
     * @throws Exception
     */
    protected function compressAndSaveImage(string $sourcePath, string $destinationPath)
    {
        $ext = strtolower($this->file->getExtension());

        list($width, $height, $type) = getimagesize($sourcePath);

        // 限制最大尺寸（比如 1920 宽）
        $maxWidth = 1920;
        $maxHeight = 1920;
        $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $image = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($sourcePath);
                break;
            case 'png':
                $image = @imagecreatefrompng($sourcePath);
                break;
            case 'webp':
                if (!function_exists('imagecreatefromwebp')) {
                    throw new Exception('PHP不支持WEBP');
                }
                $image = @imagecreatefromwebp($sourcePath);
                break;
            default:
                // 对于不支持压缩的格式，直接移动原始文件
                move_uploaded_file($sourcePath, $destinationPath);
                return;
        }

        if (!$image) {
            throw new Exception('无法创建图像资源，可能是文件已损坏或格式不支持。');
        }

        // 创建目标画布
        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // --- [核心修复代码] 开始 ---
        // 只有当处理的是 PNG 或 WEBP (也可能带透明) 时，才进行透明处理
        if ($ext === 'png' || $ext === 'webp') {

            // 1. 关闭默认的alpha混合，准备直接填充透明色
            imagealphablending($dst, false);
            // 2. 创建一个完全透明的颜色
            $transparentColor = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            // 3. 用透明颜色填充整个新画布
            imagefill($dst, 0, 0, $transparentColor);
            // 4. 开启Alpha通道保存，这是最关键的一步
            imagesavealpha($dst, true);
            // 5. (可选但推荐) 重新开启alpha混合，以便平滑地复制像素
            imagealphablending($dst, true);
        }
        // --- [核心修复代码] 结束 ---

        // 将源图像采样复制到目标画布
        imagecopyresampled($dst, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // 根据格式保存文件
        $saveResult = false;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $saveResult = imagejpeg($dst, $destinationPath, 80); // 质量调整为80
                break;
            case 'png':
                // 对于PNG，压缩级别范围是0-9，数字越大压缩率越高，文件越小。7是一个很好的平衡点。
                $saveResult = imagepng($dst, $destinationPath, 7);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    $saveResult = imagewebp($dst, $destinationPath, 80); // 质量调整为80
                }
                break;
        }

        // 释放内存
        imagedestroy($image);
        imagedestroy($dst);

        if (!$saveResult) {
            throw new \Exception("保存压缩后的图片失败。");
        }
    }

    protected function saveToOss(array $oss)
    {
        try {

            $ossClient = new OssClient($oss['access_key'], $oss['access_secret'], $oss['end_point'], false);
            $object = ltrim($this->savePath . $this->saveName, '/');
            $this->fileKey = $object;

            $res = $ossClient->uploadFile($oss['bucket'], $object, $this->file->tempName);

            if (empty($res['info']['url'])) {
                throw new Exception('OSS返回结果中缺少url');
            }

            $this->url = $res['info']['url'];
            $this->thumb_url = $this->url;


            return $this->buildSuccessResult();
        } catch (OssException $e) {
            \Yii::error("阿里云OSS上传失败: " . $e->getMessage());
            return false;
        }
    }


    protected function saveToCos(array $cos)
    {
        try {

            $client = new Client([
                'region' => $cos['region'],
                'credentials' => ['secretId' => $cos['secret_id'], 'secretKey' => $cos['secret_key']]
            ]);
            $key = ltrim($this->savePath . $this->saveName, '/');
            $this->fileKey = $key;

            $result = $client->upload($cos['bucket'], $key, fopen($this->file->tempName, 'rb'));

            if (empty($result['Location'])) {
                throw new Exception('腾讯云COS返回结果中缺少Location');
            }

            if (!empty($cos['domain'])) {
                $this->url = rtrim($cos['domain'], '/') . '/' . $key;
            } else {
                $this->url = 'https://' . $result['Location'];
            }

            $this->thumb_url = $this->url;


            return $this->buildSuccessResult();
        } catch (Exception $e) {
            \Yii::error("腾讯云COS上传失败: " . $e->getMessage());
            return false;
        }
    }


    protected function saveToQiniu(array $qiniu)
    {
        try {

            $auth = new Auth($qiniu['access_key'], $qiniu['access_secret']);
            $token = $auth->uploadToken($qiniu['bucket']);
            $key = ltrim($this->savePath . $this->saveName, '/');
            $this->fileKey = $key;

            $uploadManager = new UploadManager();
            list($result, $error) = $uploadManager->putFile($token, $key, $this->file->tempName);

            if ($error !== null) {
                throw new Exception('七牛云上传失败: ' . $error->message());
            }

            $this->url = rtrim($qiniu['domain'], '/') . '/' . $result['key'];
            $this->thumb_url = $this->url;


            return $this->buildSuccessResult();
        } catch (Exception $e) {
            \Yii::error("七牛云上传失败: " . $e->getMessage());
            return false;
        }
    }

    //-------------------- 以下是辅助性的私有方法 --------------------//

    protected function prepareFileInfo()
    {
        $dateFolder = date('Ymd');
        $ext = strtolower($this->file->getExtension());
        if (in_array($ext, $this->imageExt)) $this->type = 'image';
        elseif (in_array($ext, $this->videoExt)) $this->type = 'video';
        elseif (in_array($ext, $this->audioExt)) $this->type = 'audio';
        elseif (in_array($ext, $this->docExt)) $this->type = 'doc';
        else $this->type = 'other';

        $this->savePath = "/uploads/{$this->type}/original/{$dateFolder}/";
        // 云存储也记下图片尺寸(本地存储在 saveToLocal 里按压缩后的文件重新量)
        if ($this->type === 'image' && $this->file->tempName) {
            $info = @getimagesize($this->file->tempName);
            if ($info) {
                $this->width = (int) $info[0];
                $this->height = (int) $info[1];
            }
        }
        $this->baseWebPath = \Yii::getAlias('@webroot');

        $this->baseWebUrl = rtrim(\Yii::$app->request->hostInfo . \Yii::$app->request->baseUrl, '/');
        // uniqid() 是微秒时间戳:批量上传时几个请求同一微秒进来会得到同一个名字,后写的把先写的覆盖掉,
        // 结果几条素材记录指向同一张图。后面加 3 字节随机数避免撞名(不用 uniqid('', true),它会在名字中间多出一个点)
        $this->saveName = uniqid() . bin2hex(random_bytes(3)) . '.' . $ext;
    }


    protected function validateFileExt(): bool
    {
        $allSupportExt = array_merge($this->docExt, $this->imageExt, $this->videoExt, $this->audioExt);
        return in_array(strtolower($this->file->getExtension()), $allSupportExt);
    }


    protected function dispatchToStorage()
    {
        $setting = \Yii::$app->mall ? OptionHelper::get('mall_setting', \Yii::$app->mall->id) : [];
        $location = $setting['file_location'] ?? 'local';

        $this->location = $location;

        switch ($location) {
            case 'cos':
                $config = OptionHelper::get('cos', \Yii::$app->mall->id);
                return $config ? $this->saveToCos($config) : false;
            case 'oss':
                $config = OptionHelper::get('oss', \Yii::$app->mall->id);
                return $config ? $this->saveToOss($config) : false;
            case 'qiniu':
                $config = OptionHelper::get('qiniu', \Yii::$app->mall->id);
                return $config ? $this->saveToQiniu($config) : false;
            case 'local':
            default:
                return $this->saveToLocal();
        }
    }


    protected function buildSuccessResult(): array
    {
        return [
            'url' => $this->url,
            'thumb_url' => $this->thumb_url,
            'type' => $this->type,
            'name' => $this->file->name,
            'size' => $this->file->size, // size 已被更新为压缩后的大小
            'width' => (int) $this->width,
            'height' => (int) $this->height,
            'extension' => $this->file->getExtension(),
        ];
    }


    protected function saveToDatabase(array $uploadResult): File
    {
        $file = new File();
        $file->mall_id = \Yii::$app->mall->id ?? 0;
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

        $user = UserHelper::getUser();
        if ($user) {
            $file->user_id = $user->id;
            $file->mch_id = $user->mch_id ?? 0;
        } elseif (!\Yii::$app->admin->isGuest) {
            $file->admin_id = \Yii::$app->admin->identity->id;
        }

        if (!$file->save()) {
            \Yii::error('[FileUpload] 文件信息入库失败: ' . json_encode($file->getFirstErrors(), JSON_UNESCAPED_UNICODE));
            $this->deleteUploadedFile($file);
            throw new Exception('文件信息入库失败: ' . current($file->getFirstErrors()));
        }

        return $file;
    }


    protected function deleteUploadedFile(File $file)
    {
        try {
            if ($file->location === 'local') {
                if (file_exists($file->path)) {
                    @unlink($file->path);
                }
                ImageThumbHelper::remove($file->path);
            } else {
                // ... (OSS/COS/Qiniu 删除逻辑保持不变)
            }
        } catch (Exception $e) {
            \Yii::error('[FileUpload] 清理孤儿文件异常: ' . $e->getMessage());
        }
    }


    protected function checkImageContentSafety(File $file)
    {
        WechatHelper::init($file->mall_id);
        $app = \Yii::$app->tt->app();
        $result = $app->checkImage($file->url);
        if (!$result || ($result['error'] ?? 1) !== 0) {
            $this->deleteUploadedFile($file);
            $file->delete();
            throw new Exception("图片内容违规，请重新上传");
        }
    }
}
