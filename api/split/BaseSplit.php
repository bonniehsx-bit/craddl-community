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

namespace app\split;

/**
 * 分账渠道抽象基类
 *
 * 定义了所有分账渠道必须实现的通用接口。
 * 分账状态常量的规范来源在此(网关无关);分账记录模型(收银台 SplitLog)沿用相同字面量。
 */
abstract class BaseSplit
{
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    public static function transStatus($status): string
    {
        $map = [
            'FAIL' => self::STATUS_FAILED,
            'fail' => self::STATUS_FAILED,
            'failed' => self::STATUS_FAILED,
            'FAILED' => self::STATUS_FAILED,
            'SUCCESS' => self::STATUS_SUCCESS,
            'success' => self::STATUS_SUCCESS,
            'processing' => self::STATUS_PROCESSING
        ];
        return $map[$status] ?? $status;
    }


    /** @var int 商城ID */
    public $mall_id;
    /** @var string 渠道名称 */
    public $name;
    /** @var string 渠道类型 (实现类的类名) */
    public $split_type;

    /**
     * H5进件接口
     * @param array $params
     * @return array 包含 h5进件链接、流水号等信息的数组
     */
    abstract public function merchantH5Register(array $params);

    /**
     * 进件状态查询接口
     * @param array $params
     * @return array 包含进件状态、描述等信息的数组
     */
    abstract public function merchantRegisterQuery(array $params);

    /**
     * 进件状态查询接口
     * @param array $params
     * @return array 绑定接收方
     */
    abstract public function splitBindReceiveUserId(array $params);
}

