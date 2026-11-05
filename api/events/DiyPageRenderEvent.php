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
 * 装修页渲染:插件组件数据加工(核心问、插件答)
 *
 * 背景:装修组件由插件通过 DiyComponentEvent 注册(如 article 插件的 `article-list`),
 * 但 C 端渲染时要给组件补运行时数据(如文章浏览量)。原先这段补数据的逻辑
 * **硬编码在核心** `DiyPageForm`,直接 `use app\plugins\article\models\Article` ——
 * 装修页配了「文章列表」组件、之后卸载 article 插件,C 端首页就 500。
 *
 * 组件是谁注册的,数据就该谁来补。核心只按组件名把 item 抛出来,谁认领谁加工。
 *
 * ⚠ 监听器约定:
 *   - 先判 `$e->component` 是不是自己的组件,不是就直接 return,别碰别人的数据
 *   - 只能【修改】$e->item,不要整个替换成新结构(核心与前端按原结构渲染)
 *   - 内部 try/catch,加工失败不冒泡 —— 一个组件出错不该让整个装修页崩掉
 *
 * 用法(核心):
 *   $e = new DiyPageRenderEvent(['component' => $item['name'], 'item' => $item, 'mallId' => $mallId]);
 *   Yii::$app->trigger(DiyPageRenderEvent::NAME, $e);
 *   $item = $e->item;
 *
 * 用法(插件监听器):
 *   if ($e->component !== 'article-list') return;
 *   foreach ($e->item['data']['list'] as &$it) { $it['views'] = ...; }
 */
class DiyPageRenderEvent extends Event
{
    const NAME = 'diy.page.render.component';

    /**
     * 组件名(如 article-list),监听器据此认领。
     *
     * ⚠ 字段名不能叫 `name` —— `yii\base\Event` 自带 public $name,
     * trigger() 会把它覆写成事件名(diy.page.render.component),
     * 监听器里 `if ($e->name !== 'article-list') return;` 就永远成立、直接返回,
     * 表现为"监听器注册了但什么都没做"。同理别用 sender / handled / data。
     */
    public $component = '';

    /** 组件数据,监听器就地加工 */
    public $item = [];

    /** 商城 ID */
    public $mallId = 0;
}
