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

namespace app\forms\api\user;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\User;
use Yii;

/**
 * C 端 — 公众号推送通知的绑定 / 解绑 / 状态。
 *
 * 公众号模板消息(WxNotifyEvent → WxNotifyListener)要靠 `user.wechat_openid` 才发得出去，
 * 而这套「绑定」原先只长在 hardware 插件里(`/plugin/hardware/api/notification/*`)：
 * 核心首页的关注横幅、核心设置页的「微信通知」、别的插件(商管审批提醒)想引导员工绑定，
 * 都得依赖 hardware 目录在场，hardware 没部署就静默失效。发是核心能力，绑也该是。
 *
 * 绑定有两条入口，都落到这里：
 *   · 小程序 `<official-account>` 关注组件 → bind()(组件回调直接给 openid)
 *   · 公众号网页授权 → `wechat/auth/bind-notification`(整页跳转，不经过本类)
 *
 * ⚠ `wechat_openid` 只用于推送 / 支付出方向，绝不用于身份认证。
 */
class WxNotifyForm extends BaseModel
{
    /** @var string 公众号 openid，来自小程序关注组件的 e.detail.openId */
    public $openid;

    public function rules(): array
    {
        return [
            [['openid'], 'required', 'message' => 'openid 不能为空'],
            [['openid'], 'string', 'max' => 128],
        ];
    }

    /** 当前用户绑没绑、关没关注 */
    public function status(): array
    {
        $user = self::currentUser();

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'bound'        => !empty($user['wechat_openid']),
                'is_subscribe' => (int)($user['is_subscribe'] ?? 0),
            ],
        ];
    }

    /** 关注组件回调：保存 openid，一步完成「关注 + 绑定」 */
    public function bind(): array
    {
        if (!$this->validate()) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => $this->getFirstError('openid')];
        }

        $user = self::currentUser(false);
        if (!$user) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户不存在'];
        }

        $user->wechat_openid = trim((string)$this->openid);
        $user->is_subscribe  = 1;
        $user->save(false);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '推送通知已开启'];
    }

    /** 关闭推送：清掉 openid,以后模板消息直接跳过这个人 */
    public function unbind(): array
    {
        $user = self::currentUser(false);
        if (!$user) {
            return ['code' => ApiCode::CODE_FAIL, 'msg' => '用户不存在'];
        }

        $user->wechat_openid = null;
        $user->is_subscribe  = 0;
        $user->save(false);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '推送通知已关闭'];
    }

    /**
     * 当前登录用户。查询一律带 mall_id —— 同一个 user_id 在别的商城也有号，不带会绑错人。
     *
     * @param bool $asArray 只读场景取数组(少一次模型实例化)
     * @return array|User|null
     */
    private static function currentUser(bool $asArray = true)
    {
        $userId = (int)Yii::$app->user->id;
        $mallId = (int)Yii::$app->mall->id;
        if ($userId <= 0) {
            return null;
        }

        $query = User::find()->where(['id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0]);
        return $asArray
            ? $query->select(['id', 'wechat_openid', 'is_subscribe'])->asArray()->one()
            : $query->one();
    }
}
