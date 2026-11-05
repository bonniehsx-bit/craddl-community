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

namespace app\plugins\withdraw\forms\admin\setting;

use app\core\ApiCode;
use app\helpers\OptionHelper;
use app\models\BaseModel;
use app\services\WalletRegistry;
use Yii;

/**
 * 扩展钱包设置(逐商城)。
 *
 * 管的是「本商城要不要挂载某个扩展钱包」以及「它能不能提现」,
 * 不碰钱包余额本身 —— 余额读写一律走 WalletService。
 *
 * ## 为什么单独存 option 而不是塞进商城设置
 * mall_setting 走的是整表覆盖式保存(SettingForm),往里加字段容易被别处的保存动作
 * 连带写坏(历史上写坏过 mch_setting)。这里用独立的 option 名,读写都只碰自己这一份。
 *
 * ## 未配置 = 启用
 * 开关是后加的能力。如果默认关,已经在用扩展钱包的商城会在升级后突然看不到钱包、
 * 提现来源凭空少一项 —— 所以没配过的一律视为启用,由运营主动去关。
 */
class WalletSettingForm extends BaseModel
{
    /** @var array 形如 [['key'=>'xwy.channel','enabled'=>1,'withdrawable'=>0], ...] */
    public $list;

    public function rules()
    {
        return [
            [['list'], 'safe'],
        ];
    }

    /**
     * 列出本商城【已授权插件】声明的全部钱包及其当前开关。
     *
     * 注意这里刻意不用 WalletRegistry::all() —— 那个方法已经把「未启用」的过滤掉了,
     * 拿它来做设置页会导致钱包一旦被关就再也不出现在列表里,没法重新打开。
     */
    public function getList()
    {
        $mallId = (int)Yii::$app->mall->id;
        $authorized = Yii::$app->plugin->resolveAuthorizedPluginNames();
        $conf = WalletRegistry::settings($mallId);

        $list = [];
        foreach (WalletRegistry::allRaw() as $w) {
            if ($authorized !== null && !in_array($w['plugin'], $authorized, true)) {
                continue;
            }
            $list[] = [
                'key'             => $w['key'],
                'name'            => WalletRegistry::label($w['key']),
                'plugin'          => $w['plugin'],
                'plugin_label'    => $w['plugin_label'],
                // 插件声明的默认可提现性,前端提示「插件默认」用
                'declared_withdrawable' => !empty($w['withdrawable']),
                'enabled'         => WalletRegistry::isEnabled($w['key'], $mallId) ? 1 : 0,
                'withdrawable'    => WalletRegistry::isWithdrawable($w['key'], $mallId) ? 1 : 0,
                // 是否已被显式配置过(没配过的走默认值,前端可加淡提示)
                'configured'      => isset($conf[$w['key']]) ? 1 : 0,
            ];
        }
        return $this->apiResponse(ApiCode::CODE_SUCCESS, '获取成功', ['list' => $list]);
    }

    public function save()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        $mallId = (int)Yii::$app->mall->id;

        // 只接受本商城已授权插件声明过的 key —— 不认识的一律丢弃,
        // 免得前端传脏数据在 option 里养出一堆野 key,日后排查时分不清哪些是真钱包。
        $authorized = Yii::$app->plugin->resolveAuthorizedPluginNames();
        $allow = [];
        foreach (WalletRegistry::allRaw() as $w) {
            if ($authorized !== null && !in_array($w['plugin'], $authorized, true)) {
                continue;
            }
            $allow[$w['key']] = true;
        }

        $conf = [];
        foreach ((array)$this->list as $item) {
            $key = (string)($item['key'] ?? '');
            if ($key === '' || !isset($allow[$key])) {
                continue;
            }
            $conf[$key] = [
                'enabled'      => !empty($item['enabled']) ? 1 : 0,
                'withdrawable' => !empty($item['withdrawable']) ? 1 : 0,
            ];
        }

        OptionHelper::set(WalletRegistry::SETTING_NAME, $conf, $mallId);
        // 进程内缓存要跟着失效,否则同一次请求里后续读到的还是旧开关
        WalletRegistry::flushSettingCache($mallId);

        return $this->apiResponse(ApiCode::CODE_SUCCESS, '保存成功');
    }
}
