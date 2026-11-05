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

namespace app\plugins\article\listeners;

use app\events\DiyPageRenderEvent;
use app\listeners\BaseListener;
use app\plugins\article\models\Article;
use Yii;

/**
 * 装修页「文章列表」组件的数据加工(响应 DiyPageRenderEvent)。
 *
 * 组件由本插件的 DiyComponentListener 注册,渲染时补的浏览量数据自然也归本插件。
 * 原先这段写在核心 DiyPageForm 里并直接 use Article —— 插件一卸载,C 端首页 500。
 */
class DiyPageRenderListener extends BaseListener
{
    public function register()
    {
        Yii::$app->on(DiyPageRenderEvent::NAME, function (DiyPageRenderEvent $e) {
            if ($e->component !== 'article-list') {
                return; // 不是本插件的组件,不碰
            }
            try {
                $list = $e->item['data']['list'] ?? [];
                if (!is_array($list) || !$list) {
                    return;
                }
                // 批量取浏览量,避免原来那种逐条 scalar 查询的 N+1
                $ids = array_values(array_filter(array_column($list, 'id')));
                // 注意:别写 indexBy('id')->column() —— column() 取的是**第一列**,
                // 会得到 id=>id 而不是 id=>views。用 asArray + array_column 明确指定。
                // 按商城隔离:事件里带了 mallId 就用上,别只按 id 查。
                // 装修页配的文章 id 正常都属于本商城,但只按 id 查等于把多租户隔离交给
                // "数据不会错"的假设 —— 页面数据被改过或跨商城复制装修页时就读到别家的了。
                $cond = ['id' => $ids];
                if ((int) $e->mallId > 0) {
                    $cond['mall_id'] = (int) $e->mallId;
                }
                $rows = $ids
                    ? Article::find()->select(['id', 'views'])->where($cond)->asArray()->all()
                    : [];
                $views = array_column($rows, 'views', 'id');
                foreach ($list as &$it) {
                    $v = (int) ($views[$it['id'] ?? 0] ?? 0);
                    $it['views'] = $v > 0 ? $v : 0;
                }
                unset($it);
                $e->item['data']['list'] = $list;
            } catch (\Throwable $ex) {
                // 一个组件加工失败不该让整个装修页崩掉
                Yii::warning('[ArticleDiyRender] 文章浏览量填充失败: ' . $ex->getMessage(), 'article');
            }
        });
    }
}
