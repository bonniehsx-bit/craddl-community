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

namespace app\forms\mall\platform;

use app\core\ApiCode;
use app\models\BaseModel;
use app\services\MiniProgramCodeService;
use Yii;
use yii\helpers\Url;

/**
 * 后台「获取小程序码」:选一个页面链接,生成对应的小程序码。
 *
 * 用的是 MiniProgramCodeService::getPathCode(微信 getwxacode),不是海报用的
 * getUnlimitedSceneCode —— 后台可以选任意页面,而大多数页面并不解析 scene,
 * 走 scene 那条路的话,带参数的链接扫进去会缺参数。原因详见 getPathCode 的注释。
 */
class MpwxCodeForm extends BaseModel
{
    /** 微信 getwxacode 的 path 上限 */
    const PATH_MAX = 1024;

    public $path;

    public function rules()
    {
        return [
            [['path'], 'trim'],
            [['path'], 'required', 'message' => '请选择或填写页面路径'],
            [['path'], 'string', 'max' => self::PATH_MAX, 'tooLong' => '页面路径不能超过 ' . self::PATH_MAX . ' 个字符'],
        ];
    }

    public function generate()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $path = self::normalizePath((string)$this->path);
        if ($path === '') {
            return $this->apiResponse(ApiCode::CODE_FAIL, '请选择或填写页面路径');
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            return $this->apiResponse(ApiCode::CODE_FAIL,
                '只能生成小程序内页面的码。外部网址请先在「外部链接」里新建一条,再选它');
        }

        try {
            $relative = (new MiniProgramCodeService())->getPathCode($path, Yii::$app->mall->id);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', [
            'url'  => Url::to($relative, true),
            'path' => $path,   // 实际编进码里的路径(去掉了开头斜杠),回显给用户核对
        ]);
    }

    /**
     * link-picker 选出来的是 /others/goods/goods?id=12 这种带开头斜杠的,
     * 而微信要求 path 不以斜杠开头。
     */
    public static function normalizePath(string $raw): string
    {
        return ltrim(trim($raw), '/');
    }
}
