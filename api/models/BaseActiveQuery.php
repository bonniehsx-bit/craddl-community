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

namespace app\models;

use app\core\BasePagination;
use yii\db\ActiveQuery;
use yii\db\Command;
use yii\db\Connection;

/**
 * Class BaseActiveQuery
 * [最终修复版] 基于您的代码进行优化
 * - 保留了您的全部自定义方法和向上兼容检查
 * - 修复了 createCommand() 中手动拼接 SQL 导致的参数绑定错误
 */
class BaseActiveQuery extends ActiveQuery
{
    /**
     * 内部锁标记（仅当父类不支持时使用）
     */
    private $_forUpdate = false;
    private $_noWait = false;
    private $_sharedLock = false;

    /**
     * 分页（带总数）
     */
    public function page(&$pagination = null, $limit = 20, $page = null)
    {
        $count = $this->count();
        if (!$page) {
            $page = \Yii::$app->request->get('page', 1);
            if (!$page) {
                $page = 1;
            }
        }
        $currentPage = $page - 1;
        $pagination = new BasePagination([
            'totalCount' => $count,
            'pageSize' => $limit,
            'page' => $currentPage,
        ]);
        $this->limit($pagination->limit)->offset($pagination->offset);
        return $this;
    }

    /**
     * API 分页（无需计算总数）
     */
    public function apiPage($limit = 20, $page = 1)
    {
        $offset = ($page - 1) * $limit;
        $this->limit($limit)->offset($offset);
        return $this;
    }

    /**
     * 关键字条件封装
     */
    public function keyword($keyword, $condition)
    {
        if ($keyword) {
            $this->andWhere($condition);
        }
        return $this;
    }

    /**
     * 行级锁：FOR UPDATE
     */
    public function forUpdate($value = true)
    {
        // 您的向上兼容检查逻辑非常好，予以保留
        if (method_exists(get_parent_class($this), 'forUpdate')) {
            return parent::forUpdate($value);
        }
        $this->_forUpdate = (bool)$value;
        return $this;
    }

    /**
     * 共享锁
     */
    public function sharedLock($value = true)
    {
        if (method_exists(get_parent_class($this), 'sharedLock')) {
            return parent::sharedLock($value);
        }
        $this->_sharedLock = (bool)$value;
        return $this;
    }

    /**
     * NOWAIT 行级锁行为
     */
    public function noWait($value = true)
    {
        if (method_exists(get_parent_class($this), 'noWait')) {
            return parent::noWait($value);
        }
        $this->_noWait = (bool)$value;
        return $this;
    }

    /**
     * [最终修复] 覆盖 createCommand，在父类完全生成 Command 对象后，再安全地修改其 SQL
     * @param Connection|null $db
     * @return Command
     */
    public function createCommand($db = null)
    {
        $command = parent::createCommand($db);

        if (!($this->_forUpdate || $this->_sharedLock)) {
            return $command;
        }

        if ($db === null) {
            $modelClass = $this->modelClass;
            $db = $modelClass::getDb();
        }
        $driver = $db->driverName;
        $suffix = '';

        if ($this->_forUpdate) {
            $suffix = ' FOR UPDATE';
            if ($driver === 'pgsql' && $this->_noWait) {
                $suffix .= ' NOWAIT';
            }
        } elseif ($this->_sharedLock) {
            if ($driver === 'pgsql') {
                $suffix = ' FOR SHARE';
                if ($this->_noWait) {
                    $suffix .= ' NOWAIT';
                }
            } else {
                $suffix = ' LOCK IN SHARE MODE';
            }
        }

        $sql = rtrim($command->getSql());

        if ($suffix
            && stripos($sql, 'FOR UPDATE') === false
            && stripos($sql, 'LOCK IN SHARE MODE') === false
            && stripos($sql, 'FOR SHARE') === false) {

            $params = $command->params;   // 🔑 保留参数
            $command->setSql($sql . $suffix);
            $command->bindValues($params); // 🔑 重新绑定参数
        }

        return $command;
    }

}