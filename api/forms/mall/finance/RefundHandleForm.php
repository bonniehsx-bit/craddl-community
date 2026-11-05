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

namespace app\forms\mall\finance;

use app\core\ApiCode;
use app\models\BaseModel;
use app\models\CommonPayRefund;
use app\models\CommonPayRefundHandleLog;
use app\services\RefundAttentionService;
use Yii;

/**
 * 「退款失败」处理台的三个操作:查询结果 / 重新发起 / 登记已退款。每次都写一条处理记录。
 *
 * 不会重复退款:
 *  · 每个操作都在事务里锁住这条退款、按锁内状态再判断 —— 两个人同时点,只有一个生效;
 *  · 只动定时任务(cron/RefundTask)不会自己处理好的记录:失败且系统不再自动重试的、「处理中」超过 30 分钟的,
 *    以及余额不足的(后台照样按时重试,充值后可以不等、马上重发);其他还在按时自动重试的、刚进入处理中的不让动;
 *  · 可能已经退过的(上次没收到网关回应、「处理中」卡住、记过退款成功又被回调改回失败),
 *    重发前必须到支付平台核实,勾选「已核实没退过」并写明(见 RefundAttentionService::verifyReason);
 *    重发交给定时任务,它还会先向网关查一次(能查的网关),退过就不再退;
 *  · 人工确认已退款的(登记或查询)会记下退款成功时间:之后万一网关回调把它改回失败,
 *    定时任务也不会自动再退(见 RefundTask::dueQuery),只会回到处理台等人看;
 *  · 金额不能改,只按原退款单的金额退。
 * 标成已退款后,不在这里做后续处理(售后单完成、订单状态同步等):交给定时任务的补做链路,约 1 分钟内完成 ——
 * 它每分钟会把「已退款、后续没做完」的都捞一遍,这里再调一次会与它撞上,重复执行分润扣回。
 */
class RefundHandleForm extends BaseModel
{
    public $id;
    public $action;
    public $remark;
    public $voucher;
    public $verified;

    public function rules()
    {
        return [
            [['id', 'action'], 'required'],
            [['id', 'verified'], 'integer'],
            [['action'], 'in', 'range' => [
                CommonPayRefundHandleLog::ACTION_QUERY,
                CommonPayRefundHandleLog::ACTION_RETRY,
                CommonPayRefundHandleLog::ACTION_MARK_REFUNDED,
            ]],
            [['remark'], 'trim'],
            [['remark'], 'string', 'max' => 255],
            [['voucher'], 'string', 'max' => 512],
            [['remark', 'voucher'], 'default', 'value' => ''],
            [['verified'], 'default', 'value' => 0],
        ];
    }

    public function handle()
    {
        if (!$this->validate()) {
            return $this->responseErrorInfo();
        }
        try {
            switch ($this->action) {
                case CommonPayRefundHandleLog::ACTION_QUERY:
                    $msg = $this->query();
                    break;
                case CommonPayRefundHandleLog::ACTION_RETRY:
                    $msg = $this->retry();
                    break;
                default:
                    $msg = $this->markRefunded();
            }
            return $this->apiResponse(ApiCode::CODE_SUCCESS, $msg);
        } catch (\Exception $e) {
            return $this->apiResponse(ApiCode::CODE_FAIL, $e->getMessage());
        }
    }

    /** 查询结果:向网关查一次。已退成功就记成已退款(后续处理交给定时任务补做),没退就只留一条查询记录 */
    private function query(): string
    {
        $task = $this->findTask();
        $payOrder = $task->commonPayOrder;
        if (!$payOrder || !RefundAttentionService::canQuery($payOrder->payment)) {
            throw new \Exception('这种支付方式查不了退款结果,请到支付平台核实');
        }
        $this->assertHandleable($task);

        $payment = Yii::createObject($payOrder->payment, [$payOrder->mall_id]);
        try {
            $res = $payment->queryRefund([
                'out_trade_no' => $task->out_trade_no ?: ($payOrder->out_trade_no ?: $payOrder->order_no),
                'out_refund_no' => $task->out_refund_no,
            ]);
            $refunded = isset($res['code']) && (int)$res['code'] === ApiCode::CODE_SUCCESS;
            $detail = json_encode($res, JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $refunded = false;
            $detail = $e->getMessage();
        }
        if (!$refunded) {
            $this->writeLog($task, CommonPayRefundHandleLog::ACTION_QUERY, 0, (int)$task->status, (int)$task->status, $detail);
            return '网关显示这笔还没有退款成功:' . mb_substr($detail, 0, 100);
        }
        $this->transition($task->id, CommonPayRefundHandleLog::ACTION_QUERY, function (CommonPayRefund $t) {
            self::confirmRefunded($t);
        }, $detail);
        return '网关显示已退款成功,已记为退款成功;售后单、订单状态等后续处理约 1 分钟内自动完成';
    }

    /** 重新发起:退回「待处理」,交给定时任务按原支付方式再退一次(它会先查网关、换新的退款单号) */
    private function retry(): string
    {
        $this->transition((int)$this->id, CommonPayRefundHandleLog::ACTION_RETRY, function (CommonPayRefund $t) {
            $payOrder = $t->commonPayOrder;
            if (!$payOrder || !RefundAttentionService::isPayment($payOrder->payment)) {
                throw new \Exception('这笔的支付方式记录有误,系统没法原路退款,重发也会失败;请到支付平台或线下把钱退给顾客,再「登记已退款」');
            }
            $why = RefundAttentionService::verifyReason($t);
            if ($why !== '' && (!$this->verified || $this->remark === '')) {
                throw new \Exception($why . ',直接重发可能退两次。请先到支付平台核实(能查的先点「查询结果」),'
                    . '确认没退过再勾选「已核实没退过」并写明核实情况,才能重新发起');
            }
            $err = (string)$t->error_msg;
            if (RefundAttentionService::outcomeUnknown($err)) {
                $err = substr($err, strlen(RefundAttentionService::OUTCOME_UNKNOWN_PREFIX));
            }
            // 待处理的定时任务下一轮就捞;已试过(retry_count > 0)会按「重试」走:先向网关查、换新的退款单号再退。
            // 还不成功时,按这次的失败原因接着算下次重试时间
            $t->status = CommonPayRefund::STATUS_PENDING;
            $t->next_retry_at = 0;
            // 去掉「结果未知」前缀(已人工核实),否则定时任务查不了的网关会拒绝换号重发
            $t->error_msg = mb_substr('[人工重发] ' . $err, 0, 500);
        });
        return '已重新发起,约 1 分钟内按原支付方式再退一次;还不成功会重新出现在这里';
    }

    /** 登记已退款:钱已经在支付平台或线下退了,这里记成已退款(后续处理交给定时任务补做) */
    private function markRefunded(): string
    {
        if ($this->remark === '') {
            throw new \Exception('请写明钱是怎么退的,如:已在微信商户平台退款 / 已线下转账给顾客');
        }
        $this->transition((int)$this->id, CommonPayRefundHandleLog::ACTION_MARK_REFUNDED, function (CommonPayRefund $t) {
            self::confirmRefunded($t);
        });
        return '已登记为退款成功;售后单、订单状态等后续处理约 1 分钟内自动完成';
    }

    /**
     * 记成已退款。记下退款成功时间:之后万一网关的失败回调把它改回失败,定时任务也不会自动再退一次
     * (RefundTask::dueQuery 不捞记过成功的)—— 钱已经在别处退了,再退就是两次
     */
    private static function confirmRefunded(CommonPayRefund $t): void
    {
        $t->status = CommonPayRefund::STATUS_SUCCESS;
        $t->success_at = time();
        $t->next_retry_at = 0;
    }

    private function findTask(): CommonPayRefund
    {
        $task = CommonPayRefund::find()->where(['id' => (int)$this->id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0])->one();
        if (!$task) {
            throw new \Exception('退款记录不存在');
        }
        return $task;
    }

    /**
     * 只处理定时任务不会自己处理好的记录:失败且系统不再自动重试的、余额不足的(充值后不等下次、马上重发),
     * 或「处理中」超过 30 分钟(定时任务退到一半中断)
     */
    private function assertHandleable(CommonPayRefund $t): void
    {
        $status = (int)$t->status;
        if ($status === CommonPayRefund::STATUS_FAIL) {
            if (RefundAttentionService::autoRetryPending($t) && !RefundAttentionService::isBalance($t->error_msg)) {
                throw new \Exception('系统会在 ' . date('m-d H:i', (int)$t->next_retry_at) . ' 自动再试这笔退款,到时还不成功会出现在这里');
            }
            return;
        }
        if ($status === CommonPayRefund::STATUS_PROCESSING) {
            if ((int)$t->updated_at >= time() - RefundAttentionService::STUCK_SECONDS) {
                throw new \Exception('这笔退款刚开始处理,请过一会儿再看');
            }
            return;
        }
        throw new \Exception('这笔退款现在不需要处理(可能刚被别人处理过),请刷新后再看');
    }

    /** 锁住这条退款,按锁内状态再判断一次,改状态并写处理记录;两个人同时点只有一个生效 */
    private function transition(int $id, string $action, callable $apply, string $detail = ''): void
    {
        $tx = Yii::$app->db->beginTransaction();
        try {
            $t = CommonPayRefund::find()->where(['id' => $id, 'mall_id' => Yii::$app->mall->id, 'is_delete' => 0])->forUpdate()->one();
            if (!$t) {
                throw new \Exception('退款记录不存在');
            }
            $this->assertHandleable($t);
            $before = (int)$t->status;
            $apply($t);
            if (!$t->save(false)) {
                throw new \Exception('保存失败,请重试');
            }
            $this->writeLog($t, $action, 1, $before, (int)$t->status, $detail);
            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e instanceof \Exception ? $e : new \Exception($e->getMessage());
        }
    }

    private function writeLog(CommonPayRefund $t, string $action, int $result, int $before, int $after, string $detail = ''): void
    {
        [$operatorId, $operatorName] = self::operator();
        $log = new CommonPayRefundHandleLog();
        $log->mall_id = (int)$t->mall_id;
        $log->refund_id = (int)$t->id;
        $log->action = $action;
        $log->result = $result;
        $log->status_before = $before;
        $log->status_after = $after;
        $log->operator_id = $operatorId;
        $log->operator_name = $operatorName;
        $log->remark = (string)$this->remark;
        $log->voucher = $action === CommonPayRefundHandleLog::ACTION_MARK_REFUNDED ? (string)$this->voucher : '';
        $log->detail = $detail === '' ? null : mb_substr($detail, 0, 2000);
        if (!$log->save(false)) {
            throw new \Exception('处理记录保存失败');
        }
    }

    /** 当前后台账号 [id, 名称];命令行 / 测试里没有登录的后台账号时记 0 */
    private static function operator(): array
    {
        try {
            if (Yii::$app->has('admin') && !Yii::$app->admin->isGuest) {
                $a = Yii::$app->admin->identity;
                return [(int)$a->id, (string)($a->name ?: ($a->username ?? ''))];
            }
        } catch (\Throwable $e) {
        }
        return [0, ''];
    }
}
