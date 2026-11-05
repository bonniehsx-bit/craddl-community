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

namespace app\plugins\mch\controllers\admin;

use app\controllers\api\filters\LoginFilter;
use app\core\ApiCode;
use app\helpers\LoginHelper;

// 确保引用了 LoginHelper
use app\helpers\ResponseHelper;
use app\plugins\ApiController;
use app\plugins\mch\forms\admin\passport\MchLoginForm;
use app\plugins\mch\models\Mch;
use app\plugins\mch\models\MchClerk;
use app\models\User;
use Yii;

class PassportController extends ApiController
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // 登录和验证码不需要 token 验证
        $behaviors['login'] = [
            'class' => LoginFilter::class,
            'ignore' => ['login', 'captcha']
        ];
        return $behaviors;
    }

    /**
     * 商家登录接口
     */
    public function actionLogin()
    {
        $form = new MchLoginForm();
        $form->attributes = Yii::$app->request->post();
        return $this->asJson($form->login());
    }

    /**
     * 生成图形验证码
     * 返回：Base64图片 + 唯一Key
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
        $captchaKey = uniqid('c_', true) . mt_rand(1000, 9999);


        LoginHelper::setAuthCode($captchaKey, $code);

        // 4. 创建图片画布
        $width = 120;
        $height = 40;
        $image = imagecreate($width, $height);

        // 设置背景色 (白色)
        $bgColor = imagecolorallocate($image, 255, 255, 255);
        // 设置边框色
        $borderColor = imagecolorallocate($image, 220, 220, 220);

        // 5. 绘制干扰元素
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
        $fontWidth = imagefontwidth(5);
        $fontHeight = imagefontheight(5);
        $x = ($width - ($fontWidth * 4)) / 2;
        $y = ($height - $fontHeight) / 2;
        $textColor = imagecolorallocate($image, mt_rand(20, 100), mt_rand(20, 100), mt_rand(20, 100));
        imagestring($image, 5, (int)$x, (int)$y, $code, $textColor);

        // 7. 输出图片流并转 Base64
        ob_start();
        imagepng($image);
        $imgData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

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


    /**
     * 获取商家/店员详情
     */
    public function actionUserInfo()
    {
        $user = Yii::$app->user->identity;
        if (!$user) {
            return $this->apiResponse(ApiCode::CODE_NOT_LOGIN, '用户未登录');
        }

        // 1. 查询该用户关联的所有有效商户 (作为店长 或 作为店员)
        // 利用 MchClerk 表关联查询 (注意：店长在 MchClerk 表里也有记录，is_manager=1)
        $clerkRecords = \app\plugins\mch\models\MchClerk::find()
            ->alias('mc')
            ->joinWith(['mch m'])
            ->where([
                'mc.user_id' => $user->id,
                'mc.status' => 1,
                'mc.is_delete' => 0,
                'm.status' => 1,
                'm.is_delete' => 0
            ])
            // 与 MchFilter 未指定 mch_id 时的兜底排序(mc.id ASC)保持一致，
            // 保证 mch_list[0](UI 默认当前店) 与 MchFilter 默认命中店为同一家
            ->orderBy(['mc.id' => SORT_ASC])
            ->all();

        if (empty($clerkRecords)) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '您尚未加入任何商户');
        }

        // 2. 格式化商户列表
        $mchList = [];
        foreach ($clerkRecords as $record) {
            if ($record->mch) {
                $mchList[] = [
                    'id' => $record->mch->id,
                    'name' => $record->mch->mch_name,
                    'logo' => $record->mch->logo,
                    'is_manager' => (bool)$record->is_manager, // 标记在该店的身份
                    'is_clerk' => !(bool)$record->is_manager,  // 同时输出 is_clerk，前端(dashboard)按此口径展示
                    'type' => $record->mch->type // 2=门店, 3=酒店
                ];
            }
        }

        // 3. 确定当前上下文的商户 (逻辑与 MchFilter 保持一致)
        // 优先取前端传来的 X-Mch-Id，如果没有，默认取列表第一个
        $currentMchId = Yii::$app->request->headers->get('X-Mch-Id');
        $currentMchInfo = null;

        // 如果传了ID，校验是否在列表中
        if ($currentMchId) {
            foreach ($mchList as $item) {
                if ($item['id'] == $currentMchId) {
                    $currentMchInfo = $item;
                    break;
                }
            }
        }

        // 如果没传ID或传的ID无效，默认取第一个
        if (!$currentMchInfo && !empty($mchList)) {
            $currentMchInfo = $mchList[0];
        }

        $data = [
            'name' => $user->nickname,
            'avatar' => $user->avatar_url,
            'roles' => ['admin'],
            // [核心新增] 所有可用商户列表
            'mch_list' => $mchList,
            // [核心新增] 当前选中的商户信息
            'current_mch' => $currentMchInfo,
            // [核心新增] 当前商城已开通插件名列表(含基座 'mall')，前端据此按插件开关过滤菜单/动态路由
            'enabled_plugins' => \app\models\MallPlugin::enabledPlugins($user->mall_id),
        ];


        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '获取成功', $data);
    }

    /**
     * 商家登出接口
     */
    public function actionLogout()
    {
        $user = Yii::$app->user->identity;
        if ($user) {
            // 清除 Token 缓存
            $tokenString = Yii::$app->request->headers->get('x-access-token');
            if ($tokenString) {
                // 清理 AppBootstrap 里的缓存
                $tokenCacheKey = User::CACHE_KEY_TOKEN_MAP . md5($tokenString);
                \app\helpers\CacheHelper::remove($tokenCacheKey);
                // 库里那条也删掉:只清缓存的话 AppBootstrap 回源 DB 还能认出来,令牌在有效期内(默认 30 天、用着还会续)照样能用
                \app\models\AccessToken::deleteAll(['token' => hash('sha256', $tokenString), 'model' => User::class]);
            }
        }
        return ResponseHelper::send(ApiCode::CODE_SUCCESS, '登出成功');
    }
}