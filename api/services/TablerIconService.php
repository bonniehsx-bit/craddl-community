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

namespace app\services;

use app\helpers\ServerHelper;

/** 公共图标资源，不读取商城、用户或业务插件数据。 */
class TablerIconService
{
    public const VERSION = '3.50.0';
    private static $catalog;
    private const KEYWORDS = [
        '购物' => 'shopping cart basket bag', '购物车' => 'shopping-cart',
        '订单' => 'receipt package shopping-bag', '商品' => 'package tag',
        '好友' => 'users friends', '用户' => 'user', '我的' => 'user',
        '首页' => 'home', '分享' => 'share gift', '收藏' => 'heart star bookmark',
        '优惠券' => 'ticket coupon', '支付' => 'credit-card wallet', '财务' => 'coin invoice receipt',
        '钱包' => 'wallet', '设置' => 'settings', '搜索' => 'search', '消息' => 'message bell',
        '客服' => 'message headset', '地址' => 'map-pin', '二维码' => 'qrcode',
        '数据' => 'chart report', '时间' => 'clock', '日期' => 'calendar',
        '图片' => 'photo', '视频' => 'video', '音乐' => 'music', '相机' => 'camera',
        '编辑' => 'edit pencil', '删除' => 'trash', '添加' => 'plus', '关闭' => 'x',
        '下载' => 'download', '上传' => 'upload', '云端' => 'cloud', '建筑' => 'building',
        '爱心' => 'heart', '星星' => 'star', '手机' => 'device-mobile', '箭头' => 'arrow',
    ];

    private function catalog(): array
    {
        if (self::$catalog === null) {
            self::$catalog = json_decode(file_get_contents($this->resourcePath('catalog.json')), true, 512, JSON_THROW_ON_ERROR);
        }
        return self::$catalog;
    }

    private function resourcePath(string $file): string
    {
        return dirname(__DIR__) . '/resources/tabler/' . self::VERSION . '/' . $file;
    }

    public function listing(array $params): array
    {
        foreach (['keyword', 'variant', 'category', 'page', 'limit'] as $key) {
            if (isset($params[$key]) && !is_scalar($params[$key])) {
                throw new \InvalidArgumentException('图标查询参数无效');
            }
        }
        $catalog = $this->catalog();
        $variant = $this->variant($params['variant'] ?? 'outline');
        $query = trim((string)($params['keyword'] ?? ''));
        if (mb_strlen($query) > 80) {
            throw new \InvalidArgumentException('搜索词不能超过 80 个字符');
        }
        $category = (string)($params['category'] ?? '');
        $page = max(1, min(10000, (int)($params['page'] ?? 1)));
        $limit = max(1, min(60, (int)($params['limit'] ?? 40)));
        $terms = preg_split('/\s+/', strtolower(self::KEYWORDS[$query] ?? $query), -1, PREG_SPLIT_NO_EMPTY);
        $list = array_values(array_filter($catalog['icons'], static function ($icon) use ($variant, $category, $terms) {
            if ($icon['variant'] !== $variant || ($category !== '' && $icon['category'] !== $category)) {
                return false;
            }
            if (!$terms) {
                return true;
            }
            $haystack = $icon['name'] . ' ' . implode(' ', $icon['tags']);
            foreach ($terms as $term) {
                if (strpos($haystack, $term) !== false) {
                    return true;
                }
            }
            return false;
        }));
        $popular = array_flip(['home', 'shopping-cart', 'shopping-bag', 'user', 'users', 'heart', 'star', 'gift', 'ticket', 'package', 'wallet', 'settings']);
        usort($list, static function ($a, $b) use ($popular) {
            return (($popular[$a['name']] ?? 999) <=> ($popular[$b['name']] ?? 999)) ?: strcmp($a['name'], $b['name']);
        });
        $counts = [];
        foreach ($catalog['icons'] as $icon) {
            if ($icon['variant'] === $variant) {
                $counts[$icon['category']] = ($counts[$icon['category']] ?? 0) + 1;
            }
        }
        $categories = [];
        foreach ($catalog['categories'] as $value => $label) {
            if (isset($counts[$value])) {
                $categories[] = ['value' => $value, 'label' => $label, 'count' => $counts[$value]];
            }
        }
        return [
            'version' => self::VERSION,
            'total_count' => count($list),
            'library_count' => count($catalog['icons']),
            'variant_count' => array_sum($counts),
            'page' => $page,
            'has_more' => $page * $limit < count($list),
            'categories' => $categories,
            // 标签只供服务端搜索，避免把完整标签字典传进小程序。
            'list' => array_map(static function ($icon) {
                return ['name' => $icon['name'], 'variant' => $icon['variant']];
            }, array_slice($list, ($page - 1) * $limit, $limit)),
        ];
    }

    private function variant(string $variant): string
    {
        if (!in_array($variant, ['outline', 'filled'], true)) {
            throw new \InvalidArgumentException('不支持的图标风格');
        }
        return $variant;
    }

    public function asset(array $params): array
    {
        foreach (['name', 'color', 'variant', 'scale'] as $key) {
            if (isset($params[$key]) && !is_scalar($params[$key])) {
                throw new \InvalidArgumentException('图标参数无效');
            }
        }
        $name = (string)($params['name'] ?? '');
        $color = strtolower((string)($params['color'] ?? '#303133'));
        $variant = (string)($params['variant'] ?? 'outline');
        $scale = $this->scale($params['scale'] ?? 75);
        $this->svg($name, $color, $variant, self::VERSION, $scale);
        $host = ServerHelper::getHost();
        if (!$host) {
            throw new \RuntimeException('系统资源域名尚未配置');
        }
        return ['url' => $host . '/index.php?' . http_build_query([
            'r' => 'common/icon/svg', 'v' => self::VERSION, 'variant' => $variant,
            'name' => $name, 'color' => $color, 'scale' => $scale,
        ], '', '&', PHP_QUERY_RFC3986)];
    }

    public function scale($value): int
    {
        if (!is_scalar($value) || !preg_match('/\A[0-9]{2,3}\z/D', (string)$value)) {
            throw new \InvalidArgumentException('图标占比无效');
        }
        $scale = (int)$value;
        if ($scale < 50 || $scale > 100 || $scale % 5 !== 0) {
            throw new \InvalidArgumentException('图标占比应为 50–100，步长 5');
        }
        return $scale;
    }

    public function svg(string $name, string $color, string $variant, string $version, int $scale = 100): string
    {
        if ($version !== self::VERSION || !preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $name) || strlen($name) > 100) {
            throw new \InvalidArgumentException('图标名称或版本无效');
        }
        $variant = $this->variant($variant);
        $this->scale($scale);
        if (!preg_match('/\A#[a-f0-9]{3}(?:[a-f0-9]{3})?\z/iD', $color)) {
            throw new \InvalidArgumentException('颜色须为三位或六位十六进制色值');
        }
        $zip = new \ZipArchive();
        if ($zip->open($this->resourcePath('icons.zip'), \ZipArchive::RDONLY) !== true) {
            throw new \RuntimeException('图标资源不可用');
        }
        try {
            $svg = $zip->getFromName($variant . '/' . $name . '.svg');
        } finally {
            $zip->close();
        }
        if ($svg === false) {
            throw new \OutOfBoundsException('图标不存在');
        }
        // 只对仓库内固定版本的官方 SVG 换色，不接收外部 SVG 或任意文件路径。
        $svg = str_replace('currentColor', strtolower($color), $svg);
        if ($scale !== 100) {
            // 仅扩展透明画布，不给业务控件写入像素尺寸；同一资源适配宫格与列表。
            $side = 24 * 100 / $scale;
            $padding = ($side - 24) / 2;
            $number = static function ($value) {
                return rtrim(rtrim(number_format($value, 6, '.', ''), '0'), '.');
            };
            $viewBox = '-' . $number($padding) . ' -' . $number($padding) . ' ' . $number($side) . ' ' . $number($side);
            $svg = str_replace('viewBox="0 0 24 24"', 'viewBox="' . $viewBox . '"', $svg);
        }
        return $svg;
    }
}
