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
namespace app\services\cart;

use app\forms\api\cart\AddCartForm;
use app\helpers\GoodsMemberPriceHelper;
use app\jobs\goods\GoodsFootMarkJob;
use app\models\Attr;
use app\models\Cart;
use app\models\Goods;
use app\models\GoodsShare;
use app\models\User;
use app\plugins\mch\models\Mch;
use app\services\goods\StandaloneGoodsGuard;
use app\services\order\OrderInputGuard;
use yii\helpers\Json;

/** 分享保存规格/数量快照，展示和导入始终重查商品、规格、库存及当前访客价格。 */
class GoodsShareService
{
    const MAX_ITEMS = 20;

    public function create(int $mallId, int $userId, $input, string $title, string $note): array
    {
        $this->assertUser($mallId, $userId);
        $items = $this->items($input, 'cart_id');
        $carts = Cart::find()->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0, 'id' => array_column($items, 'cart_id')])->indexBy('id')->all();
        $snapshots = [];
        foreach ($items as $item) {
            $cart = $carts[$item['cart_id']] ?? null;
            if (!$cart) {
                throw new \InvalidArgumentException('购物车商品已变化，请刷新后重试');
            }
            $key = $cart->goods_id . ':' . (int) $cart->goods_attr_id;
            if (isset($snapshots[$key])) {
                $snapshots[$key]['num'] += $item['num'];
                OrderInputGuard::assertQuantity($snapshots[$key]['num']);
            } else {
                $snapshots[$key] = ['goods_id' => (int) $cart->goods_id, 'goods_attr_id' => (int) $cart->goods_attr_id, 'num' => $item['num']];
            }
        }
        $resolved = $this->resolve($mallId, array_values($snapshots));
        $this->assertPurchasable($resolved);
        $share = new GoodsShare();
        $share->mall_id = $mallId;
        $share->user_id = $userId;
        $share->token = bin2hex(random_bytes(16));
        $share->title = $title !== '' ? $title : '分享给你的好物';
        $share->note = $note;
        // 只存展示快照及商品标识，不存来源链接、成本、地址、购物车ID或用户账户数据。
        $share->items_json = Json::encode(array_map(static function ($item) {
            return array_intersect_key($item, array_flip(['goods_id', 'goods_attr_id', 'num', 'name', 'cover_pic', 'attr_name', 'mch_id']));
        }, $resolved));
        if (!$share->save()) {
            throw new \InvalidArgumentException($share->getErrorMsg());
        }
        return $this->detail($mallId, $share->token);
    }

    public function detail(int $mallId, string $token): array
    {
        $share = $this->find($mallId, $token);
        $owner = User::find()->select(['nickname', 'avatar_url'])->where(['mall_id' => $mallId, 'id' => $share->user_id, 'is_delete' => 0])->asArray()->one();
        return [
            'token' => $share->token, 'title' => $share->title, 'note' => $share->note,
            'sender' => ['nickname' => $owner['nickname'] ?? '好友', 'avatar' => $owner['avatar_url'] ?? ''],
            'created_at' => (int) $share->created_at,
            'items' => $this->resolve($mallId, Json::decode($share->items_json)),
        ];
    }

    public function recordView(int $mallId, int $userId, string $token): void
    {
        $this->assertUser($mallId, $userId);
        $data = $this->detail($mallId, $token);
        $ids = [];
        foreach ($data['items'] as $item) {
            if ($item['can_view']) {
                $ids[$item['goods_id']] = true;
            }
        }
        // 复用原有足迹去重逻辑，最多20款；直接执行避免共享调度队列延迟或跨环境消费。
        foreach (array_keys($ids) as $id) {
            (new GoodsFootMarkJob(['goods_id' => $id, 'user_id' => $userId, 'mall_id' => $mallId]))->execute();
        }
    }

    public function import(int $mallId, int $userId, string $token, $input, string $requestKey, string $mode): array
    {
        $this->assertUser($mallId, $userId);
        if (!preg_match('/^[a-zA-Z0-9_-]{16,32}$/D', $requestKey) || !in_array($mode, ['cart', 'checkout'], true)) {
            throw new \InvalidArgumentException('请求标识无效，请重新操作');
        }
        $share = $this->find($mallId, $token);
        $items = $this->items($input, 'index', true);
        $hash = hash('sha256', Json::encode([$token, $mode, $items]));
        $db = \Yii::$app->db;
        $transaction = $db->beginTransaction();
        try {
            // 串行化同一用户的分享导入；凭证和整批加购在同一事务，重试不会加倍。
            $sql = User::find()->select('id')->where(['id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0])->createCommand()->getRawSql();
            $db->createCommand($sql . ' FOR UPDATE')->queryScalar();
            $key = ['mall_id' => $mallId, 'user_id' => $userId, 'request_key' => $requestKey];
            $previous = (new \yii\db\Query())->from('{{%goods_share_import}}')->where($key)->one();
            if ($previous) {
                if (!hash_equals($previous['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('请求内容已变化，请重新确认');
                }
                $transaction->commit();
                return Json::decode($previous['result_json']);
            }
            $saved = Json::decode($share->items_json);
            $chosen = [];
            foreach ($items as $item) {
                if (!isset($saved[$item['index']])) {
                    throw new \InvalidArgumentException('分享中的商品不存在');
                }
                $chosen[] = array_merge($saved[$item['index']], ['num' => $item['num']]);
            }
            $resolved = $this->resolve($mallId, $chosen);
            $this->assertPurchasable($resolved);
            $mchId = $resolved[0]['mch_id'];
            if (count(array_unique(array_column($resolved, 'mch_id'))) !== 1) {
                throw new \InvalidArgumentException('不同店铺的商品需分开操作');
            }
            foreach ($resolved as $item) {
                $existing = (int) Cart::find()->where(['mall_id' => $mallId, 'user_id' => $userId, 'goods_id' => $item['goods_id'], 'goods_attr_id' => $item['goods_attr_id'], 'is_delete' => 0])->sum('num');
                if ($mode === 'cart' && $existing + $item['num'] > $item['stock']) {
                    throw new \InvalidArgumentException('「' . $item['name'] . '」与购物车已有数量合计超过库存');
                }
                $form = new AddCartForm();
                $form->goods_id = $item['goods_id'];
                $form->goods_attr_id = $item['goods_attr_id'];
                $form->num = $item['num'];
                $response = $form->save();
                if (($response['code'] ?? 1) !== 0) {
                    throw new \InvalidArgumentException($response['msg'] ?? '加入购物车失败');
                }
            }
            $result = ['mch_id' => $mchId, 'goods_list' => array_map(static function ($item) {
                return array_intersect_key($item, array_flip(['goods_id', 'goods_attr_id', 'num']));
            }, $resolved)];
            if ($mode === 'checkout') {
                $result['extra'] = ['cart_source' => 'goods-share'];
            }
            $db->createCommand()->insert('{{%goods_share_import}}', array_merge($key, [
                'share_id' => $share->id, 'request_hash' => $hash, 'result_json' => Json::encode($result), 'created_at' => time(),
            ]))->execute();
            $transaction->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($transaction->isActive) $transaction->rollBack();
            throw $e;
        }
    }

    public function mine(int $mallId, int $userId, int $page): array
    {
        $this->assertUser($mallId, $userId);
        $query = GoodsShare::find()->where(['mall_id' => $mallId, 'user_id' => $userId, 'is_delete' => 0]);
        $count = (int) $query->count();
        $rows = $query->orderBy(['id' => SORT_DESC])->offset((max(1, $page) - 1) * 10)->limit(10)->all();
        $list = [];
        foreach ($rows as $share) {
            $items = Json::decode($share->items_json);
            $list[] = ['token' => $share->token, 'title' => $share->title, 'created_at' => (int) $share->created_at,
                'count' => count($items), 'num' => array_sum(array_column($items, 'num')),
                'covers' => array_slice(array_column($items, 'cover_pic'), 0, 3)];
        }
        return ['list' => $list, 'has_more' => $page * 10 < $count];
    }

    private function find(int $mallId, string $token): GoodsShare
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new \InvalidArgumentException('分享不存在或已失效');
        }
        $share = GoodsShare::findOne(['mall_id' => $mallId, 'token' => $token, 'is_delete' => 0]);
        if (!$share) throw new \InvalidArgumentException('分享不存在或已失效');
        return $share;
    }

    private function assertUser(int $mallId, int $userId): void
    {
        if ($userId <= 0 || !User::find()->where(['id' => $userId, 'mall_id' => $mallId, 'is_delete' => 0, 'status' => 0])->exists()) {
            throw new \InvalidArgumentException('请先登录');
        }
    }

    /** 严格校验整数及重复项；只接受用户购物车ID或分享中的序号，不接受客户端价格。 */
    private function items($input, string $idKey, bool $zeroAllowed = false): array
    {
        if (is_string($input)) {
            try { $input = Json::decode($input); } catch (\Throwable $e) { throw new \InvalidArgumentException('商品数据格式错误'); }
        }
        OrderInputGuard::assertItems($input);
        if (count($input) > self::MAX_ITEMS) throw new \InvalidArgumentException('一次最多分享或选择20款商品');
        $out = [];
        $seen = [];
        foreach ($input as $item) {
            $id = $item[$idKey] ?? null;
            if ((!is_int($id) && !is_string($id)) || !preg_match('/^[0-9]{1,10}$/D', (string) $id)
                || (int) $id < ($zeroAllowed ? 0 : 1) || isset($seen[(int) $id])) {
                throw new \InvalidArgumentException('商品标识无效或重复');
            }
            $seen[(int) $id] = true;
            $out[] = [$idKey => (int) $id, 'num' => (int) $item['num']];
        }
        return $out;
    }

    private function assertPurchasable(array $items): void
    {
        foreach ($items as $item) {
            if (!$item['is_valid'] || $item['num'] > $item['stock']) {
                throw new \InvalidArgumentException('「' . $item['name'] . '」' . ($item['invalid_reason'] ?: '库存不足，请调整数量'));
            }
        }
        if (count(array_unique(array_column($items, 'mch_id'))) !== 1) {
            throw new \InvalidArgumentException('不同店铺的商品需分开分享');
        }
    }

    private function resolve(int $mallId, array $snapshots): array
    {
        $goods = Goods::find()->where(['mall_id' => $mallId, 'id' => array_column($snapshots, 'goods_id'), 'is_delete' => 0])->indexBy('id')->all();
        $attrs = Attr::find()->where(['id' => array_column($snapshots, 'goods_attr_id'), 'goods_id' => array_keys($goods), 'is_delete' => 0])->indexBy('id')->all();
        $mchIds = array_unique(array_filter(array_map(static function ($g) { return (int) $g->mch_id; }, $goods)));
        $shops = $mchIds ? Mch::find()->select(['id', 'mch_name'])->where(['mall_id' => $mallId, 'id' => $mchIds, 'is_delete' => 0])->indexBy('id')->asArray()->all() : [];
        $out = [];
        foreach ($snapshots as $index => $saved) {
            $g = $goods[$saved['goods_id']] ?? null;
            $a = $attrs[$saved['goods_attr_id']] ?? null;
            $reason = '';
            $canView = $g && (int) $g->status === Goods::STATUS_UP && !StandaloneGoodsGuard::isStandalone($g);
            $stock = 0;
            $price = '0.00';
            if (!$canView) $reason = '商品已下架或不支持分享购买';
            elseif ((int) $g->is_attr === 1 && (!$a || (int) $a->goods_id !== (int) $g->id)) $reason = '规格已调整，请重新选择商品';
            elseif ((int) $g->is_attr !== 1 && (int) $saved['goods_attr_id'] !== 0) $reason = '商品规格已调整';
            else {
                $stock = (int) ((int) $g->is_attr === 1 ? $a->stock : $g->stock);
                $price = (string) ((int) $g->is_attr === 1 ? $a->price : $g->price);
                if ($stock <= 0) $reason = '暂时缺货';
            }
            $mchId = $g ? (int) $g->mch_id : (int) ($saved['mch_id'] ?? 0);
            $out[] = [
                'index' => $index, 'goods_id' => (int) $saved['goods_id'], 'goods_attr_id' => (int) $saved['goods_attr_id'],
                'num' => (int) $saved['num'], 'name' => $g ? $g->name : ($saved['name'] ?? '商品已失效'),
                'cover_pic' => $g ? ($a && (int) $a->goods_id === (int) $g->id && $a->cover_pic ? $a->cover_pic : $g->cover_pic) : ($saved['cover_pic'] ?? ''),
                'attr_name' => $a && $g && (int) $a->goods_id === (int) $g->id ? $a->attr_name : ($saved['attr_name'] ?? ''),
                'price' => $price, 'stock' => max(0, $stock), 'is_valid' => $reason === '' ? 1 : 0,
                'can_view' => $canView ? 1 : 0, 'invalid_reason' => $reason,
                'mch_id' => $mchId, 'mch_name' => $mchId ? ($shops[$mchId]['mch_name'] ?? '商家店铺') : '平台自营',
            ];
        }
        GoodsMemberPriceHelper::applyToArrayList($out, GoodsMemberPriceHelper::currentLevelId(), 'price', 'goods_id');
        return $out;
    }
}
