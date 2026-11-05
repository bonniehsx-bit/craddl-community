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

namespace app\forms\admin;

use app\core\ApiCode;
use app\models\BaseModel;
use Yii;

class YiiErrorLogForm extends BaseModel
{
    public $id;
    public $ids;       // 批量操作，逗号分隔或数组
    public $page;
    public $limit;
    public $level;     // error/warning
    public $is_handled;

    public function rules()
    {
        return [
            [['id', 'page', 'limit', 'is_handled'], 'integer'],
            [['level'], 'string'],
            [['page'], 'default', 'value' => 1],
            [['limit'], 'default', 'value' => 20],
        ];
    }

    const CACHE_KEY_COUNT = 'admin:yii_error_log:unhandled_count';

    /**
     * 未处理错误数量（用于 Header badge 轮询）
     * 结果缓存 30 秒，避免频繁轮询打穿框架层
     */
    public function getCount()
    {
        $count = Yii::$app->cache->getOrSet(self::CACHE_KEY_COUNT, function () {
            return (int)Yii::$app->db->createCommand(
                'SELECT COUNT(*) FROM {{%yii_error_log}} WHERE is_delete=0 AND is_handled=0 AND level="error"'
            )->queryScalar();
        }, 30);

        return ['code' => ApiCode::CODE_SUCCESS, 'data' => ['count' => (int)$count]];
    }

    /**
     * 列表
     */
    public function getList()
    {
        if (!$this->validate()) return $this->responseErrorInfo();

        $query = Yii::$app->db->createCommand(
            'SELECT * FROM {{%yii_error_log}} WHERE is_delete=0'
            . ($this->level ? ' AND level=:level' : '')
            . (isset($this->is_handled) && $this->is_handled !== '' ? ' AND is_handled=:handled' : '')
            . ' ORDER BY is_handled ASC, id DESC'
            . ' LIMIT :offset, :limit'
        );

        $countQuery = Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM {{%yii_error_log}} WHERE is_delete=0'
            . ($this->level ? ' AND level=:level' : '')
            . (isset($this->is_handled) && $this->is_handled !== '' ? ' AND is_handled=:handled' : '')
        );

        $params = [];
        if ($this->level) $params[':level'] = $this->level;
        if (isset($this->is_handled) && $this->is_handled !== '') $params[':handled'] = (int)$this->is_handled;

        $query->bindValues(array_merge($params, [
            ':offset' => ((int)$this->page - 1) * (int)$this->limit,
            ':limit'  => (int)$this->limit,
        ]));
        $countQuery->bindValues($params);

        $list  = $query->queryAll();
        $total = (int)$countQuery->queryScalar();

        foreach ($list as &$item) {
            $item['created_time_text'] = $item['created_at'] > 0 ? date('m-d H:i:s', $item['created_at']) : '';
            $item['last_seen_text']    = $item['last_seen_at'] > 0 ? date('m-d H:i:s', $item['last_seen_at']) : '';
        }
        unset($item);

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'data' => [
                'list'  => $list,
                'total' => $total,
                'page'  => (int)$this->page,
                'limit' => (int)$this->limit,
            ],
        ];
    }

    /**
     * 标记已处理（支持单条 id 或批量 ids）
     */
    public function handle()
    {
        $ids = $this->resolveIds();
        if (empty($ids)) return ['code' => ApiCode::CODE_FAIL, 'msg' => '参数错误'];

        Yii::$app->db->createCommand()
            ->update('{{%yii_error_log}}', ['is_handled' => 1, 'updated_at' => time()], ['id' => $ids, 'is_delete' => 0])
            ->execute();
        Yii::$app->cache->delete(self::CACHE_KEY_COUNT);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '已标记处理'];
    }

    /**
     * 全部标记已处理
     */
    public function handleAll()
    {
        Yii::$app->db->createCommand()
            ->update('{{%yii_error_log}}', ['is_handled' => 1, 'updated_at' => time()], ['is_delete' => 0, 'is_handled' => 0])
            ->execute();
        Yii::$app->cache->delete(self::CACHE_KEY_COUNT);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '全部已标记处理'];
    }

    /**
     * 删除（软删除）
     */
    public function destroy()
    {
        $ids = $this->resolveIds();
        if (empty($ids)) return ['code' => ApiCode::CODE_FAIL, 'msg' => '参数错误'];

        Yii::$app->db->createCommand()
            ->update('{{%yii_error_log}}', ['is_delete' => 1, 'deleted_at' => time()], ['id' => $ids])
            ->execute();
        Yii::$app->cache->delete(self::CACHE_KEY_COUNT);

        return ['code' => ApiCode::CODE_SUCCESS, 'msg' => '删除成功'];
    }

    private function resolveIds(): array
    {
        if ($this->id) return [(int)$this->id];
        if ($this->ids) {
            $raw = is_array($this->ids) ? $this->ids : explode(',', $this->ids);
            return array_filter(array_map('intval', $raw));
        }
        return [];
    }
}
