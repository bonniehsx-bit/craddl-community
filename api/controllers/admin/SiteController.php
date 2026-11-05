<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-29
 * Time: 8:52
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


use app\controllers\admin\filters\AdminFilter;
use app\controllers\BaseController;
use app\core\ApiCode;
use app\forms\admin\CheckQueueForm;
use app\forms\admin\SiteSettingForm;
use app\forms\admin\SourceLicenseForm;
use app\forms\admin\SmsForm;
use app\forms\admin\UpdateForm;
use app\helpers\OptionHelper;
use app\models\AccessToken;

class SiteController extends BaseController
{
    public $enableCsrfValidation = false;

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-29
     * @Time: 8:53
     * @Note:站点设置
     */
    public function actionSetting()
    {
        $form = new SiteSettingForm();
        if ($this->request->isPost) {
            if (\Yii::$app->admin->isGuest) {
                \Yii::warning('ddddddddd======');
                $headers = \Yii::$app->request->headers;
                if ($headers && $headers['x-admin-token']) {
                    $admin = \app\models\Admin::find()->alias('u')
                        ->innerJoin(['a' => AccessToken::tableName()], 'a.model_id=u.id')
                        ->where(['a.token' => $headers['x-admin-token'], 'model' => \app\models\Admin::class])
                        ->select('u.*')
                        ->one();
                    if ($admin) {
                        \Yii::$app->admin->login($admin);
                    } else {
                        \Yii::$app->response->data = [
                            'code' => ApiCode::CODE_NOT_LOGIN,
                            'msg' => '请先登录!',
                        ];
                    }
                }
            }
            \Yii::warning('ddddddddd');
            $form->attributes = $this->request->post();
            return $form->save();
        }
        return $form->search();
    }

    /**
     * 查看本次交付的源码使用协议，不记录同意或变更授权。
     */
    public function actionSourceLicense()
    {
        return $this->asJson((new SourceLicenseForm())->search());
    }

    public function actionLoginSite()
    {
        $form = new SiteSettingForm();

        return $this->asJson($form->baseInfo());
    }


    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-22
     * @Time: 13:47
     * @Note:检查更新
     */
    public function actionCheckUpdate()
    {
        $form = new UpdateForm();

        return $this->asJson($form->check());
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-11-22
     * @Time: 13:36
     * @Note:更新
     */
    public function actionUpdate()
    {
        $form = new UpdateForm();
        $form->attributes = $this->request->get();
        return $this->asJson($form->update());
    }

    public function actionClearCache()
    {
        if (\Yii::$app->admin->identity->admin_type != 0) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '你没有权限清理缓存']);
        }
        // 只清配置(option)缓存,不整库清空:同一个 Redis 库里还有定时任务配置、延时任务队列等,
        // 整库清空会连它们一起清掉。(原先这里是 redis->flush(),Connection 没有这个方法,调用即报错)
        $result = OptionHelper::refreshCache();
        return $this->asJson(['code' => ApiCode::CODE_SUCCESS, 'msg' => '清理成功', 'data' => $result]);
    }

    /**
     * Go 调度器存活状态
     * scheduler 的 tick 循环每 5s 写一次 jxmall_scheduler:heartbeat（unix 时间戳）。
     * 15s 内有心跳 = 运行中（容 3 次丢失）。
     */
    public function actionSchedulerStatus()
    {
        $beat = (int) \app\helpers\RedisHelper::get('jxmall_scheduler:heartbeat');
        $ago  = $beat > 0 ? (time() - $beat) : null;
        $alive = ($beat > 0 && $ago >= 0 && $ago <= 15);
        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '',
            'data' => [
                'alive'       => $alive ? 1 : 0,
                'last_beat'   => $beat,
                'seconds_ago' => $ago,
            ],
        ]);
    }

    /**
     * 刷新 OptionHelper 缓存
     * 清掉全部配置缓存,下次读从 jxmall_option 表重新取(见 OptionHelper::refreshCache)。
     * 适用：DB 被手动修改/导入备份后，Redis 缓存与 DB 不一致。
     */
    public function actionRefreshOptionCache()
    {
        if (\Yii::$app->admin->identity->admin_type != 0) {
            return $this->asJson(['code' => ApiCode::CODE_FAIL, 'msg' => '仅超级管理员可刷新配置缓存']);
        }
        $result = OptionHelper::refreshCache();
        return $this->asJson([
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => sprintf('已刷新 %d / %d 条配置缓存%s%s',
                $result['refreshed'],
                $result['total'],
                $result['skipped'] > 0 ? '（跳过 ' . $result['skipped'] . ' 条无效数据）' : '',
                $result['removed'] > 0 ? '，清掉 ' . $result['removed'] . ' 条数据库里已不存在的旧缓存' : ''
            ),
            'data' => $result,
        ]);
    }

    public function actionCheckQueue()
    {
        $form = new CheckQueueForm();
        if ($this->request->isPost) {
            return $this->asJson($form->createJob());
        }
        $form->attributes = $this->request->get();
        return $this->asJson($form->checkJob());
    }
}
