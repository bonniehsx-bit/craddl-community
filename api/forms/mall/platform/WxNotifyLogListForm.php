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
use app\models\BaseModel;
use app\models\User;
use app\models\WxNotifyLog;

/**
 * 后台 — 公众号推送记录查询。
 *
 * 运营问得最多的是「这条通知为什么没收到」，答案基本落在四种结局里：
 * 成功 / 微信返回错误 / 用户没绑公众号 / 这个场景没配模板 ID。
 * 表是核心的(wx_notify_log)，各插件的推送都记在一起，按插件和场景筛。
 */
class WxNotifyLogListForm extends BaseModel
{
    public $page = 1;
    public $limit = 20;
    public $plugin;
    public $scene;
    public $status;
    public $keyword;      // openid / 失败原因 / 场景 模糊
    public $user_id;
    public $start_date;   // Y-m-d
    public $end_date;

    public function rules()
    {
        return [
            [['page', 'limit', 'status', 'user_id'], 'integer'],
            [['plugin', 'scene', 'keyword', 'start_date', 'end_date'], 'string'],
            [['plugin', 'scene', 'keyword'], 'trim'],
            [['status'], 'in', 'range' => array_keys(WxNotifyLog::STATUS_TEXT), 'skipOnEmpty' => true],
        ];
    }

    public function search()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }

        $mallId = (int)\Yii::$app->mall->id;
        $query = $this->buildQuery($mallId);

        $pagination = null;
        $list = (clone $query)->orderBy('id DESC')
            ->page($pagination, (int)($this->limit ?: 20), (int)($this->page ?: 0))
            ->asArray()->all();

        // 接收人名字批量取,别在循环里查库
        $userIds = array_values(array_unique(array_filter(array_column($list, 'user_id'))));
        $users = $userIds
            ? User::find()->select(['id', 'nickname'])->where(['id' => $userIds])->indexBy('id')->asArray()->all()
            : [];

        foreach ($list as &$item) {
            $item['status_text']  = WxNotifyLog::STATUS_TEXT[(int)$item['status']] ?? '';
            $item['created_time'] = !empty($item['created_at']) ? date('Y-m-d H:i:s', (int)$item['created_at']) : '';
            $item['user_name']    = $users[$item['user_id']]['nickname'] ?? ('用户' . (int)$item['user_id']);
            // openid 是对外标识,列表里只给前后各 6 位,要全量点开详情看 payload
            $oid = (string)$item['openid'];
            $item['openid_mask']  = mb_strlen($oid) > 14 ? mb_substr($oid, 0, 6) . '****' . mb_substr($oid, -6) : $oid;
        }
        unset($item);

        // 同一套筛选下各结局多少条 —— 运营看「今天发了多少、卡了多少」就靠它
        $summary = ['total' => 0];
        foreach (array_keys(WxNotifyLog::STATUS_TEXT) as $s) {
            $summary[$s] = (int)(clone $query)->andWhere(['status' => $s])->count();
            $summary['total'] += $summary[$s];
        }

        return [
            'code' => ApiCode::CODE_SUCCESS,
            'msg'  => '',
            'data' => [
                'list'         => $list,
                'pagination'   => $pagination,
                'summary'      => $summary,
                'status_map'   => WxNotifyLog::STATUS_TEXT,
                // 下拉只列本商城真正出现过的插件和场景,免得运营在几百个场景里找
                'plugin_options' => $this->distinct($mallId, 'plugin'),
                'scene_options'  => $this->distinct($mallId, 'scene'),
            ],
        ];
    }

    private function buildQuery(int $mallId)
    {
        $query = WxNotifyLog::find()->where(['mall_id' => $mallId, 'is_delete' => 0]);

        if ($this->plugin !== null && $this->plugin !== '') {
            $query->andWhere(['plugin' => $this->plugin]);
        }
        if ($this->scene !== null && $this->scene !== '') {
            $query->andWhere(['scene' => $this->scene]);
        }
        if ($this->status !== null && $this->status !== '') {
            $query->andWhere(['status' => (int)$this->status]);
        }
        if (!empty($this->user_id)) {
            $query->andWhere(['user_id' => (int)$this->user_id]);
        }
        if ($this->keyword !== null && $this->keyword !== '') {
            $query->andWhere(['or',
                ['like', 'openid', $this->keyword],
                ['like', 'reason', $this->keyword],
                ['like', 'scene', $this->keyword],
            ]);
        }
        // ⚠ created_at 是 INT 时间戳,禁止拿 'Y-m-d' 字符串直接比(MySQL 会把 '2026-..' 当 2026)
        if (!empty($this->start_date) && strtotime($this->start_date)) {
            $query->andWhere(['>=', 'created_at', strtotime(date('Y-m-d 00:00:00', strtotime($this->start_date)))]);
        }
        if (!empty($this->end_date) && strtotime($this->end_date)) {
            $query->andWhere(['<=', 'created_at', strtotime(date('Y-m-d 23:59:59', strtotime($this->end_date)))]);
        }

        return $query;
    }

    private function distinct(int $mallId, string $column): array
    {
        return array_values(array_filter(WxNotifyLog::find()
            ->select($column)->distinct()
            ->where(['mall_id' => $mallId, 'is_delete' => 0])
            ->orderBy([$column => SORT_ASC])
            ->limit(200)
            ->column()));
    }
}
