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
namespace app\services\goods\pipeline;

use app\models\Attr;
use app\models\Goods;
use app\services\GoodsService;
use Exception;
use yii\db\Expression;

class GoodsSkuSyncPipe
{
    /**
     * @param GoodsContext $context
     * @param \Closure $next
     * @return mixed
     * @throws Exception
     */
    public function handle($context, \Closure $next)
    {
        $form = $context->form;
        $goods = $context->goods;

        if (!$context->isPlatformGoods) {
            // 编辑时按差额改库存(前端带回了打开页面时的库存快照),新建时整值写入
            $snapshot = is_array($form->stock_snapshot ?? null) ? $form->stock_snapshot : null;
            $byDelta = $context->isEditing && $snapshot !== null;

            // 差额不幂等:同一个编辑页提交两次(保存成功后按钮还能点、网络重发),差额会加两遍。
            // 同一页面只让第一次保存生效;重复的那次把已有规格的差额当 0(其他字段照常存,重复写一遍无害)
            if ($byDelta) {
                [$firstSave, $claim] = \app\helpers\EditOnceHelper::claim('goods_stock', (int)$goods->id, $form->stock_edit_token ?? null);
                $context->stockEditClaim = $claim;
                if (!$firstSave) {
                    foreach (($form->specs_data['sku_list'] ?? []) as $sku) {
                        $skuId = (int)($sku['id'] ?? 0);
                        if ($skuId > 0 && array_key_exists($skuId, $snapshot)) {
                            $snapshot[$skuId] = (int)($sku['stock'] ?? 0);
                        }
                    }
                }
            }

            // 使用新提取的公共服务进行同步
            $res = GoodsService::syncSkus($goods->id, $goods->mall_id, $form->specs_data['sku_list'] ?? [], $form->price, $byDelta ? $snapshot : null);
            if ($res['code'] != 0) {
                throw new Exception($res['msg']);
            }

            // 【核心反哺机制】底层准确 SKU 数据反哺主表（库存、重量、底价）
            if (!$byDelta) {
                $goods->stock = $res['data']['total_stock'];
                $goods->total_stock = $res['data']['total_stock'];
            }
            $goods->weight = $res['data']['weight'];
            $goods->price = $res['data']['price'];

            if (!$goods->save()) {
                throw new Exception($form->getErrorMsg($goods));
            }

            if ($byDelta) {
                $this->resyncGoodsStock($goods, (bool)($res['data']['stock_changed'] ?? false));
            }
        }

        return $next($context);
    }

    /**
     * 主表库存 = 各规格库存之和,一条 SQL 在库里算,不经过 PHP
     *
     * 和下单扣库存(BaseOrderHandler::deductStock 先扣规格、再扣主表,都是原子加减)不会互相盖掉:
     * 这句 UPDATE 读规格表时要等正在扣库存的事务提交,算出来的就是扣完之后的数。
     *
     * 售罄豁免要在这里补:库存不再走 AR save,Goods::beforeSave 里那条
     * 「运营手动把库存改成 0 却保持上架 → 记豁免,别让 GoodsDownTask 下架」就判断不到了。
     * 只在这次真动了库存时才记 —— 被订单卖光的不能算运营意图(见 Goods::beforeSave 的注释)。
     */
    private function resyncGoodsStock(Goods $goods, bool $stockChanged): void
    {
        // 两列各用一个参数名:MySQL 原生预处理不允许同一个命名参数在一条 SQL 里出现两次
        $sumOf = function (string $param) use ($goods) {
            return new Expression(
                '(SELECT COALESCE(SUM(a.stock), 0) FROM ' . Attr::tableName() . " a WHERE a.goods_id = {$param} AND a.is_delete = 0)",
                [$param => (int)$goods->id]
            );
        };
        Goods::updateAll(['stock' => $sumOf(':gid_s'), 'total_stock' => $sumOf(':gid_t')], ['id' => (int)$goods->id]);

        $fresh = Goods::find()->select(['stock', 'total_stock', 'status', 'auto_off_at'])->where(['id' => (int)$goods->id])->asArray()->one();
        if (!$fresh) {
            return;
        }
        if ($stockChanged && (int)$fresh['status'] === Goods::STATUS_UP && (int)$fresh['stock'] <= 0
            && (int)$fresh['auto_off_at'] !== Goods::AUTO_OFF_EXEMPT) {
            Goods::updateAll(['auto_off_at' => Goods::AUTO_OFF_EXEMPT], ['id' => (int)$goods->id]);
            $fresh['auto_off_at'] = Goods::AUTO_OFF_EXEMPT;
        }

        // 内存里的模型同步成库里的值,并标成「没改动」:后面的管道/事件再 save 一次也不会把它写回去
        foreach (['stock', 'total_stock', 'auto_off_at'] as $attr) {
            $goods->$attr = (int)$fresh[$attr];
            $goods->setOldAttribute($attr, (int)$fresh[$attr]);
        }
    }
}
