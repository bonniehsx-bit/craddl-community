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

use Yii;

/**
 * This is the model class for table "{{%payout_log}}".
 *
 * @property int $id
 * @property int $mall_id
 * @property int $user_id 收款用户ID
 * @property int $source_id 关联的源单ID (例如提现申请ID)
 * @property string $source_type 关联的源单模型类名 (例如 MchCashLog::class)
 * @property string|null $payout_no 第三方支付网关返回的流水号
 * @property string $fund_class 打款渠道类名
 * @property string $channel 打款方式 (e.g. bank, wechat, balance)
 * @property float $amount 实际打款金额
 * @property string $status 状态: PENDING(处理中), SUCCESS(成功), FAIL(失败)
 * @property string|null $request_data 请求报文
 * @property string|null $response_data 响应报文
 * @property string|null $remark 备注
 * @property int|null $completed_at 打款完成时间戳
 * @property int $is_delete
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $created_time
 * @property string|null $updated_time
 * @property string|null $deleted_time
 *
 * @property User $user
 * @property mixed $sourceModel 关联的源单模型
 */
class PayoutLog extends BaseActiveRecord
{
    /**
     * @var string PENDING 状态
     * 表示请求已成功发送给第三方，正在等待其处理或异步回调。
     * 这是大多数 API 调用的初始成功状态。
     */
    const STATUS_PENDING = 'PENDING';

    /**
     * @var string SUCCESS 状态
     * 表示打款已明确成功，资金已到账。
     */
    const STATUS_SUCCESS = 'SUCCESS';

    /**
     * @var string FAIL 状态 (注意: 我们在 Fund 类中用了 failed, 这里统一为 FAIL)
     * 表示打款已明确失败，或者在请求阶段就已失败。
     */
    const STATUS_FAIL = 'FAIL';


    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%payout_log}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['mall_id', 'user_id', 'source_id', 'source_type', 'fund_class', 'channel', 'amount', 'status'], 'required'],
            [['mall_id', 'user_id', 'source_id', 'completed_at', 'is_delete', 'created_at', 'updated_at', 'deleted_at'], 'integer'],
            [['amount'], 'number'],
            [['request_data', 'response_data'], 'string'],
            [['created_time', 'updated_time', 'deleted_time'], 'safe'],
            [['payout_no', 'fund_class', 'source_type'], 'string', 'max' => 255],
            [['remark'], 'string', 'max' => 512],
            [['channel', 'status'], 'string', 'max' => 50],
            // 增加一个规则，确保 status 字段的值只能是常量中定义的值
            ['status', 'in', 'range' => [self::STATUS_PENDING, self::STATUS_SUCCESS, self::STATUS_FAIL]],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'mall_id' => '商城ID',
            'user_id' => '收款用户',
            'source_id' => '关联单号',
            'source_type' => '关联单类型',
            'payout_no' => '渠道流水号',
            'fund_class' => '打款渠道',
            'channel' => '打款方式',
            'amount' => '打款金额',
            'status' => '打款状态',
            'request_data' => '请求报文',
            'response_data' => '响应报文',
            'remark' => '备注',
            'completed_at' => '完成时间',
            'is_delete' => '是否删除',
            'created_at' => '创建时间戳',
            'updated_at' => '更新时间戳',
            'deleted_at' => '删除时间戳',
            'created_time' => '创建时间',
            'updated_time' => '更新时间',
            'deleted_time' => '删除时间',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * [核心] 通用化的多态关联
     * 根据 source_type 字段动态关联到不同的提现记录表
     * @return \yii\db\ActiveQuery|null
     */
    public function getSourceModel()
    {
        if ($this->source_type && class_exists($this->source_type)) {
            /** @var \yii\db\ActiveRecord $class */
            $class = $this->source_type;
            return $this->hasOne($class, ['id' => 'source_id']);
        }
        return null;
    }
}