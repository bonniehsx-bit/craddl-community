<?php

/**
 * Copyright (c) 2025-2026 佛山今日鼎盛信息科技有限公司
 * Created by PhpStorm
 * Author: ganxiaohao
 * Date: 2020-09-09
 * Time: 17:58
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

use app\core\ApiCode;
use yii\base\Model;
use Yii; // 引入Yii的命名空间

/**
 * Class BaseModel
 * @package app\models
 * @property int $page
 * @property int $limit
 */
class BaseModel extends Model // 确保这里是 extends Model
{
    public $mall_id = 0;
    public $page = 1;
    public $limit = 15;
    public $pagination;

    public function rules()
    {
        return [
            [['page', 'limit'], 'integer'],
            // 可以在这里增加一个安全规则，允许 mall_id 被加载
            [['mall_id'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init(); // 【优化】建议先调用父类的init

        // --- 【核心修正】: 判断当前是否是 Web 应用环境 ---
        if (Yii::$app instanceof \yii\web\Application) {
            // 只有在 Web 环境下，才存在 mall 组件和 request 组件
            if (Yii::$app->mall) {
                $this->mall_id = Yii::$app->mall->id;
            }

            $params = Yii::$app->request->get();
            if ($params && is_array($params)) {
                $this->page = isset($params['page']) && $params['page'] ? (int)$params['page'] : 1;
                $this->limit = isset($params['limit']) && $params['limit'] ? (int)$params['limit'] : $this->limit;
            }
        }
    }

    /**
     * 获取模型验证失败的第一条错误信息。
     * @param Model|null $model
     * @return string
     */
    public function getErrorMsg(?Model $model = null): string
    {
        $model = $model ?: $this;

        if ($model->hasErrors()) {
            $errors = $model->getFirstErrors();
            return current($errors);
        }

        return '保存失败，发生未知错误。';
    }

    /**
     * 返回标准API格式的数组
     * @param int $code
     * @param string $msg
     * @param array|object $data
     * @return array
     */
    public function apiResponse(int $code = 0, string $msg = '', $data = [])
    {
        if ($code === ApiCode::CODE_SUCCESS && empty($msg)) {
            $msg = '请求成功';
        }
        return ['code' => $code, 'msg' => $msg, 'data' => $data];
    }

    /**
     * 返回验证错误的API格式数组
     * @param Model|null $model
     * @return array
     */
    public function responseErrorInfo(?Model $model = null)
    {
        $model = $model ?: $this;
        $msg = '数据验证失败';
        if ($model->hasErrors()) {
            $msg = current($model->getFirstErrors());
        }
        return $this->apiResponse(ApiCode::CODE_FAIL, $msg);
    }

    /**
     * 【优化】合并 responseErrorMsg 到 responseErrorInfo
     * responseErrorMsg 方法与 responseErrorInfo 功能完全重复，建议只保留一个。
     * 如果为了兼容旧代码，可以保留并让它调用新的方法。
     */
    public function responseErrorMsg(?Model $model = null)
    {
        return $this->responseErrorInfo($model);
    }

    /**
     * 获取分页数据
     * @param \yii\data\Pagination $pagination
     * @return array
     */
    public function getPaginationInfo($pagination)
    {
        if (!$pagination) {
            return null;
        }
        return [
            "total_count" => (int)$pagination->totalCount,
            "page_count" => $pagination->getPageCount(),
            "page_size" => $pagination->getPageSize(),
            "current_page" => $pagination->getPage() + 1, // Yii的分页从0开始，前端需要从1开始
        ];
    }
}
