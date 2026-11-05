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
namespace app\controllers\admin;

use app\controllers\BaseController;
use app\core\ApiCode;
use app\forms\admin\LoginForm;
use app\forms\admin\RegisterForm;
use app\helpers\LoginHelper;
use Yii;

class PassportController extends BaseController
{
    // 关闭 CSRF 验证，防止 API 跨域或 Post 请求被拦截
    public $enableCsrfValidation = false;

    /**
     * 管理员登录
     */
    public function actionLogin()
    {
        if (\Yii::$app->request->isPost) {
            $form = new LoginForm();
            $form->attributes = \Yii::$app->request->post();

            // 调用 LoginForm 的 login 方法，获取结果
            // 注意：LoginForm 内部需要处理 captcha_key 的校验
            $res = $form->login();

            return $this->asJson($res);
        } else {
            return $this->asJson([
                'code' => ApiCode::CODE_FAIL,
                'msg' => '请求方式错误'
            ]);
        }
    }

    /**
     * 注册 (如果系统开放注册)
     */
    public function actionRegister()
    {
        if (\Yii::$app->request->isPost) {
            $form = new RegisterForm();
            $form->attributes = \Yii::$app->request->post();
            return $this->asJson($form->save());
        }
        return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '请求方式错误']);
    }

    /**
     * 生成图形验证码 (Base64 + Key 模式)
     */
    public function actionCaptcha()
    {
        // 1. 定义验证码字符源 (去掉了容易混淆的 I, 1, 0, O)
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        // 生成 4 位随机码
        for ($i = 0; $i < 4; $i++) {
            $code .= $chars[mt_rand(0, strlen($chars) - 1)];
        }

        // 2. 生成唯一的 Key (前端提交登录时需带上此 Key)
        // uniqid 生成唯一ID，mt_rand 增加随机性
        $captchaKey = uniqid('c_', true) . mt_rand(1000, 9999);

        // 3. 将 "Key => Code" 存入缓存
        // 注意：这里调用的是修改后的 LoginHelper，支持 key 参数
        LoginHelper::setAuthCode($captchaKey, $code);

        // 4. 创建图片画布 (GD 库)
        $width = 120;
        $height = 40;
        // imagecreate 兼容性好，imagecreatetruecolor 画质更好但需要处理背景色
        $image = imagecreate($width, $height);

        // 设置背景色 (白色 RGB: 255, 255, 255)
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        // 设置边框色
        $borderColor = imagecolorallocate($image, 220, 220, 220);

        // 5. 绘制干扰元素 (增加识别难度)
        // 5.1 画干扰点
        for ($i = 0; $i < 150; $i++) {
            $pointColor = imagecolorallocate($image, mt_rand(150, 220), mt_rand(150, 220), mt_rand(150, 220));
            imagesetpixel($image, mt_rand(1, $width - 1), mt_rand(1, $height - 1), $pointColor);
        }
        // 5.2 画干扰线
        for ($i = 0; $i < 3; $i++) {
            $lineColor = imagecolorallocate($image, mt_rand(200, 240), mt_rand(200, 240), mt_rand(200, 240));
            imageline($image, mt_rand(1, $width - 1), mt_rand(1, $height - 1), mt_rand(1, $width - 1), mt_rand(1, $height - 1), $lineColor);
        }

        // 6. 写入验证码文字
        // imagefontwidth(5) 获取内置字体5的宽度，用于计算居中位置
        $fontWidth = imagefontwidth(5);
        $fontHeight = imagefontheight(5);

        // 计算居中坐标
        $x = ($width - ($fontWidth * 4)) / 2;
        $y = ($height - $fontHeight) / 2;

        // 文字颜色 (随机深色)
        $textColor = imagecolorallocate($image, mt_rand(20, 100), mt_rand(20, 100), mt_rand(20, 100));

        // 写入字符串
        imagestring($image, 5, (int)$x, (int)$y, $code, $textColor);

        // 7. 输出图片流并转 Base64
        ob_start(); // 开启缓冲区
        imagepng($image); // 输出 PNG
        $imgData = ob_get_contents(); // 获取缓冲区内容
        ob_end_clean(); // 清除并关闭缓冲区
        imagedestroy($image); // 销毁内存对象

        // 拼接 Base64 头
        $base64Image = 'data:image/png;base64,' . base64_encode($imgData);

        // 8. 返回 JSON
        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg' => '获取成功',
            'data' => [
                'captcha_key' => $captchaKey, // 前端必须保存这个 key
                'img' => $base64Image         // 前端直接展示这个图片
            ]
        ]);
    }
}