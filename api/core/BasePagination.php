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


namespace app\core;


class BasePagination extends \yii\data\Pagination
{
    public $defaultPageSize = 10;
    public $pageSize;
    public $total_count;
    public $page_count;
    public $current_page;
    public $page_size;

    /**
     * 单页上限。
     *
     * 父类 yii\data\Pagination 本来有 pageSizeLimit(默认 [1,50]),但本类把 pageSize
     * 重新声明成了 public 属性、又重写了 getPageSize() 直接返回它 —— 父类那套限制被整个绕开,
     * 于是 limit 完全由前端说了算,任何登录用户都能让某个列表接口一次把整张表捞进内存。
     *
     * 设 500 而不是父类的 50:全仓没有一处主动传超过 200 的 limit
     * (默认值分布是 10 / 20 / 100,导出走独立的 ExportJob 不经过分页),
     * 500 足够宽松、不会误伤任何既有调用,同时把「一次拉全表」挡在外面。
     */
    public $maxPageSize = 500;

    public function init()
    {
        parent::init();
        // pageSize 可能来自请求参数是字符串("20"),强制转 int —— 否则前端 el-pagination 的
        // :page-size 期望 Number,会报 "Invalid prop: type check failed"。下方 total_count/current_page 同理已 int 化。
        //
        // 三条都得堵,少一条就还能拉全表:
        //   limit=999999 → 过大;limit=-1 → 父类 getLimit() 把 <1 视作 -1 即「不限制」;
        //   limit=abc    → (int) 后是 0,同样落进 <1 那一档变成 -1。
        // 所以先无条件 (int),再把 <1 的一律回落默认值、超上限的截断。
        $size = (int)$this->pageSize;
        if ($size > $this->maxPageSize) {
            $size = $this->maxPageSize;
        } elseif ($size < 1) {
            $size = $this->defaultPageSize;
        }
        $this->pageSize = $size;
        $this->total_count  = $this->totalCount ? intval($this->totalCount) : 0;
        $this->page_count   = $this->pageCount;
        $this->current_page = $this->page + 1;
        // 蛇形别名始终镜像 pageSize（修复：之前仅在 if 分支内赋值，pageSize 已被父类置值时漏赋）
        $this->page_size    = $this->pageSize;
    }

    public function getPageSize()
    {
        return $this->pageSize;
    }
}
