<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-14
 * Time: 9:44
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

namespace app\controllers\mall;


use app\core\ApiCode;
use app\forms\mall\platform\MpwxCodeForm;
use app\forms\mall\platform\MpwxForm;
use app\forms\mall\platform\WechatForm;
use app\forms\mall\platform\WechatTokenForm;
use app\forms\mall\platform\WxappForm;
use app\forms\mall\platform\WxNotifyLogListForm;

class PlatformController extends Controller
{
    /** 公众号验证文件：web 根目录下的系统保留文件名，禁止本接口覆盖/删除 */
    const WECHAT_VERIFY_PROTECTED = ['robots.txt'];

    /** 验证文件名白名单：仅 字母/数字/下划线/连字符 + .txt。天然杜绝 ..、斜杠、多点、可执行扩展 */
    const WECHAT_VERIFY_NAME_RE = '/^[A-Za-z0-9_-]+\.txt$/';

    public function actionWechat()
    {
        $form = new WechatForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }


    public function actionMpwx()
    {
        $form = new MpwxForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * 公众号推送记录(核心 wx_notify_log)：谁、什么场景、成功还是失败、为什么没发出去
     */
    public function actionWechatNotifyLog()
    {
        $form = new WxNotifyLogListForm();
        $form->attributes = $this->request->get();
        return $form->search();
    }

    /**
     * 小程序代码上传记录列表
     */
    public function actionMpwxUploadLog()
    {
        $form = new MpwxForm();
        $form->attributes = $this->request->get();
        return $form->uploadLogList();
    }

    /**
     * 获取小程序码(后台手动生成,支持带参数的页面路径)
     */
    public function actionMpwxCode()
    {
        $form = new MpwxCodeForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->generate());
    }

    /**
     * 清理微信 access_token 缓存并重新获取(报 40001 / 42001 时用)
     */
    public function actionWechatTokenClear()
    {
        $form = new WechatTokenForm();
        return $this->asJson($form->clear());
    }

    /**
     * Created by PhpStorm.
     * User：ganxi
     * Date：2022/4/30
     * Time：9:40
     * Note：上传小程序
     */
    public function actionMpwxUpload()
    {

        $form = new MpwxForm();
        $form->attributes = $this->request->post();
        return $this->asJson($form->upload());
    }

    public function actionWxapp()
    {
        $form = new WxappForm();
        if ($this->request->isPost) {
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    public function actionWechatMenu()
    {
        $app = \Yii::$app->wechat->app;
        if (\Yii::$app->request->isPost) {
            $menus = \Yii::$app->request->post('form');
            try {
                $response = $app->getClient()->postJson('cgi-bin/menu/create', $menus);
                $res = $response->toArray();
                if (isset($res['errcode']) && $res['errcode'] == 0) {
                    return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '发布成功']);
                }
                return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => "错误码:" . ($res['errmsg'] ?? '未知错误')]);
            } catch (\Exception $e) {
                return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => "发布菜单失败:" . $e->getMessage()]);
            }
        }
        try {
            $response = $app->getClient()->get('cgi-bin/menu/get');
            $list = $response->toArray();
        } catch (\Exception $e) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => "获取菜单失败: " . $e->getMessage()]);
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '获取成功', 'data' => $list]);
    }

    /**
     * 公众号域名验证文件 —— 保存（填文件名+内容，系统写到 web 根目录 api/web，供 https://域名/文件名 访问）
     * POST /mall/platform/wechat-verify-file-save  { filename, content }
     */
    public function actionWechatVerifyFileSave()
    {
        $filename = trim((string)\Yii::$app->request->post('filename', ''));
        $content  = (string)\Yii::$app->request->post('content', '');

        if (!preg_match(self::WECHAT_VERIFY_NAME_RE, $filename)) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '文件名只允许 字母/数字/下划线/连字符 且必须以 .txt 结尾，如 MP_verify_xxxxxxxx.txt']);
        }
        if (in_array(strtolower($filename), self::WECHAT_VERIFY_PROTECTED, true)) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '该文件名为系统保留，不可覆盖']);
        }
        $content = trim($content);
        if ($content === '') {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '文件内容不能为空']);
        }
        if (strlen($content) > 4096) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '内容过长（验证文件应很小，须 < 4KB）']);
        }

        $path = \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $filename;
        if (@file_put_contents($path, $content) === false) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '写入失败，请检查 api/web 目录写权限']);
        }

        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '保存成功',
            'data' => ['filename' => $filename, 'url' => $this->wechatVerifyUrl($filename)],
        ]);
    }

    /**
     * 列出 web 根目录下的验证文件（仅匹配安全文件名、排除系统保留文件如 robots.txt）
     * GET /mall/platform/wechat-verify-file-list
     */
    public function actionWechatVerifyFileList()
    {
        $dir = \Yii::getAlias('@webroot');
        $list = [];
        foreach ((array)glob($dir . '/*.txt') as $p) {
            $name = basename($p);
            if (!preg_match(self::WECHAT_VERIFY_NAME_RE, $name)) {
                continue;
            }
            if (in_array(strtolower($name), self::WECHAT_VERIFY_PROTECTED, true)) {
                continue;
            }
            $list[] = [
                'filename' => $name,
                'url'      => $this->wechatVerifyUrl($name),
                'size'     => @filesize($p) ?: 0,
                'updated'  => @filemtime($p) ?: 0,
            ];
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '获取成功', 'data' => ['list' => $list]]);
    }

    /**
     * 删除 web 根目录下的某个验证文件（同样的白名单 + 系统保留保护）
     * POST /mall/platform/wechat-verify-file-delete  { filename }
     */
    public function actionWechatVerifyFileDelete()
    {
        $filename = trim((string)\Yii::$app->request->post('filename', ''));
        if (!preg_match(self::WECHAT_VERIFY_NAME_RE, $filename)
            || in_array(strtolower($filename), self::WECHAT_VERIFY_PROTECTED, true)) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '非法或受保护的文件名']);
        }
        $path = \Yii::getAlias('@webroot') . DIRECTORY_SEPARATOR . $filename;
        if (is_file($path)) {
            @unlink($path);
        }
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '删除成功']);
    }

    /** 验证文件的可访问 URL = 站点根(scheme+host) + 文件名 */
    private function wechatVerifyUrl($filename)
    {
        return rtrim(\Yii::$app->request->hostInfo, '/') . '/' . $filename;
    }
}
