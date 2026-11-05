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
use app\helpers\WechatHelper;
use app\models\BaseModel;
use Yii;

/**
 * 后台「清理微信 token 缓存」:把本商城小程序 / 公众号缓存在服务器上的 access_token 删掉,再立刻重新取一次。
 *
 * 什么时候用:调微信接口报 errcode 40001(access_token is invalid or not latest)/ 42001 时。
 * 典型原因:同一个 appid 在别处(本地、测试服)又换了新 token,这边缓存的那个就作废了,
 * 而缓存不过期就一直拿着它,要等最长 2 小时才自愈。
 *
 * 只清「当前这台服务器」的缓存(EasyWeChat 的 token 缓存是服务器本地文件);多台服务器要每台各点一次。
 * 现在用的是稳定版 token(WechatHelper::init 里开了 use_stable_access_token),重新取不会顶掉别处正在用的。
 */
class WechatTokenForm extends BaseModel
{
    public function clear(): array
    {
        $mallId = (int)Yii::$app->mall->id;
        WechatHelper::init($mallId);

        $items = [];
        // 小程序
        $mp = Yii::$app->params['wechatMiniProgramConfig'] ?? null;
        if (!empty($mp['app_id']) && !empty($mp['secret'])) {
            $items[] = $this->one('小程序', 'mini_app', $mp, function () {
                return Yii::$app->wechat->miniProgram;
            });
        }
        // 公众号
        $oa = Yii::$app->params['wechatConfig'] ?? null;
        if (!empty($oa['app_id']) && !empty($oa['secret'])) {
            $items[] = $this->one('公众号', 'official_account', $oa, function () {
                return Yii::$app->wechat->app;
            });
        }
        if (!$items) {
            return $this->apiResponse(ApiCode::CODE_FAIL, '还没有配置小程序或公众号的 AppID / 密钥,没有需要清理的');
        }

        $allOk = !in_array(false, array_column($items, 'ok'), true);
        return $this->apiResponse(ApiCode::CODE_SUCCESS, $allOk ? '已清理并重新获取成功' : '已清理,但重新获取失败,请看下面的原因', ['items' => $items]);
    }

    /**
     * @param callable $app 取对应的 EasyWeChat 应用
     * @return array ['name','removed','ok','msg']
     */
    private function one(string $name, string $prefix, array $cfg, callable $app): array
    {
        $removed = 0;
        $msg = '';
        $ok = false;
        try {
            $application = $app();
            $cache = $application->getCache();
            // 普通版和稳定版是两套缓存键,都清(老版本留下的也一并带走)
            foreach ([0, 1] as $stable) {
                $key = sprintf('%s.access_token.%s.%s.%d', $prefix, $cfg['app_id'], $cfg['secret'], $stable);
                if ($cache->has($key)) {
                    $cache->delete($key);
                    $removed++;
                }
            }
            // 立刻重新取一次,让运营当场看到能不能用(稳定版:同一个 token 多处共用,不会顶掉别处)
            $token = $application->getAccessToken()->getToken();
            $ok = $token !== '';
            $msg = $ok ? '重新获取成功' : '重新获取失败';
        } catch (\Throwable $e) {
            $msg = '重新获取失败:' . self::redact($e->getMessage());
        }
        return ['name' => $name, 'removed' => $removed, 'ok' => $ok, 'msg' => $msg];
    }

    /** 微信换 token 失败的异常消息里可能带着 secret / access_token 明文,抹掉再给页面 */
    private static function redact(string $text): string
    {
        $text = preg_replace('/((?:secret|access_token|component_access_token)=)[^&"\s]+/i', '$1***', $text);
        return mb_substr($text, 0, 300);
    }
}
