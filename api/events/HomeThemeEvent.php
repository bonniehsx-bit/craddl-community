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

namespace app\events;

use yii\base\Event;

/**
 * 商城首页「自定义主题」注册事件
 *
 * 与 GoodsEvent 同构的事件挂载思路 —— 主项目和 admin 后台对具体主题零感知：
 *   - 主框架在 MallController::actionHomeThemeList 中触发本事件
 *   - 各插件通过 Plugin.php::getListeners() 注册自己的 Listener
 *   - Listener 在监听到 EVENT_REGISTER 时往 $event->themes[] 推入自己的主题
 *
 * 推入格式：
 *   $event->themes[] = [
 *       'code'  => 'massage',          // 与 uniapp plugins1/{plugin}/home/HomeTheme.vue 一一对应
 *       'label' => '艾灸到家',
 *       'plugin'=> 'massage',           // 可选：插件名（用于排序/过滤）
 *   ];
 *
 * 默认主题 'diy' 在主框架层面强制存在（DIY 装修引擎），插件无需注册。
 */
class HomeThemeEvent extends Event
{
    /**
     * @var array 已注册的主题列表（每条 ['code'=>..., 'label'=>..., 'plugin'=>...]）
     */
    public $themes = [];

    /** 主题注册事件名 */
    const EVENT_REGISTER = 'home.theme.register';
}
