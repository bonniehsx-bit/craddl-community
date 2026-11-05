<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-10-29
 * Time: 11:30
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

namespace app\plugins\article;


use app\helpers\FileHelper;


use yii\caching\DbDependency;

class Plugin extends \app\plugins\Plugin
{

    public function getLabel()
    {
        // TODO: Implement getLabel() method.
        return '文章营销';
    }

    public function getName()
    {
        // TODO: Implement getName() method.
        return "article";
    }

    /**
     * 应用中心图标（element-ui 图标类名）。矢量图标不依赖文件/host，免设计、免托管。
     */
    public function getIcon(): string
    {
        return 'el-icon-document';
    }

    public function getLogo()
    {
        $plugin = \app\models\Plugin::findOne(['name' => $this->getName(), 'is_delete' => 0]);
        return \app\helpers\PluginHelper::resolveLogo($this->getName(), $plugin->logo ?? '');
    }

    /**
     * 获取移动端链接
     * @return array
     */
    public function getLinks()
    {
        // TODO: Implement getLinks() method.

        return [
            [
                'url' => '/plugins/article/list',
                'name' => '文章列表'
            ]

        ];
    }

    /**
     * @Author: 动力宇宙 ganxiaohao
     * @Date: 2020-09-10
     * @Time: 16:59
     * @Note:获取事件监听器
     */
    public function getListeners()
    {
        return [
            \app\plugins\article\listeners\DiyComponentListener::class,
            // 「文章列表」组件的渲染数据(浏览量)由本插件自己补 ——
            // 原先硬编码在核心 DiyPageForm 并直接 use Article,插件一卸载 C 端首页 500
            \app\plugins\article\listeners\DiyPageRenderListener::class,
        ];
    }


    public function getType()
    {
        // TODO: Implement getType() method.
        return self::TYPE_MARKET;
    }

    public function getVersion()
    {
        // TODO: Implement getVersion() method.
        return file_get_contents(__DIR__ . '/version');
    }

    public function getDsc()
    {
        // TODO: Implement getDsc() method.
        return "文章营销";
    }

    public function getHistoryVersion()
    {
        // TODO: Implement getHistoryVersion() method.

        return [
            '1.0.0'
        ];
    }

    public static function syncGoods($id) {}

    /**
     * 声明本插件不可清空的表（配置/字典/模板/等级类）
     */
    public function getProtectedTables(): array
    {
        return [
            'plugin_article',
            'plugin_article_cat',
            'plugin_article_goods',
        ];
    }
}
