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

namespace app\commands;

use app\helpers\ImageThumbHelper;
use app\models\File;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * 老图补缩略图 + 补记大小 / 尺寸。
 *
 * 新上传的图在 FileUploadForm 里已经自动生成;这个命令给上线前传的老图补一遍。
 * 只处理本地存储的图片,原图一个字节都不改,只在旁边新增 xxx_thumb.jpg,并更新 file 表的
 * thumb_url / size / width / height。可以反复跑:已有缩略图的直接跳过。
 *
 *   php yii image-thumb/run --dry-run=1      只统计不动文件
 *   php yii image-thumb/run                  全部商城
 *   php yii image-thumb/run --mall=1 --limit=200
 */
class ImageThumbController extends Controller
{
    /** 只处理这个商城,0 = 全部 */
    public $mall = 0;
    /** 最多处理多少条,0 = 不限 */
    public $limit = 0;
    /** 1 = 只统计,不生成文件、不改库 */
    public $dryRun = 0;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['mall', 'limit', 'dryRun']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), ['dry-run' => 'dryRun']);
    }

    public function actionRun()
    {
        // GD 解一张 4000×4000 的图就要六七十 MB,命令行默认 128M 遇到超大图会整批中断
        @ini_set('memory_limit', '1024M');
        $query = File::find()
            ->where(['is_delete' => 0, 'type' => 'image'])
            ->andWhere(['or', ['location' => 'local'], ['location' => ''], ['location' => null]])
            ->orderBy(['id' => SORT_ASC]);
        if ($this->mall) {
            $query->andWhere(['mall_id' => (int) $this->mall]);
        }
        if ($this->limit) {
            $query->limit((int) $this->limit);
        }

        $stat = ['total' => 0, 'made' => 0, 'skipped' => 0, 'missing' => 0, 'unsupported' => 0, 'failed' => 0, 'saved_kb' => 0];
        $webroot = \Yii::getAlias('@app/web');

        foreach ($query->each(200) as $file) {
            /** @var File $file */
            $stat['total']++;
            $src = $this->resolvePath($file, $webroot);
            if (!$src) {
                $stat['missing']++;
                continue;
            }
            $thumbUrl = ImageThumbHelper::thumbName($file->url);
            if (!$thumbUrl || !ImageThumbHelper::thumbName($src)) {
                $stat['unsupported']++; // gif / bmp 等,只补记大小尺寸
            }
            $probe = ImageThumbHelper::probe($src);

            if ($this->dryRun) {
                continue;
            }

            $attrs = ['size' => $probe['size'], 'width' => $probe['width'], 'height' => $probe['height']];
            if ($thumbUrl) {
                $existed = is_file(ImageThumbHelper::thumbName($src));
                $thumb = ImageThumbHelper::make($src);
                if ($thumb) {
                    $existed ? $stat['skipped']++ : $stat['made']++;
                    $stat['saved_kb'] += max(0, $probe['size'] - (int) filesize($thumb)) / 1024;
                    // thumb_url 还是原图(没被别处改过)才换成缩略图
                    if ($file->thumb_url === '' || $file->thumb_url === null || $file->thumb_url === $file->url) {
                        $attrs['thumb_url'] = $thumbUrl;
                    }
                } else {
                    $stat['failed']++;
                }
            }
            File::updateAll($attrs, ['id' => $file->id]);

            if ($stat['total'] % 100 === 0) {
                $this->stdout("  已处理 {$stat['total']} 条…\n");
            }
        }

        $this->stdout(($this->dryRun ? "【只统计,未改动】\n" : '') . sprintf(
            "共 %d 条:新生成 %d,已有跳过 %d,文件不在本机 %d,格式不支持 %d,失败 %d;列表每张图平均省下 %d KB\n",
            $stat['total'], $stat['made'], $stat['skipped'], $stat['missing'], $stat['unsupported'], $stat['failed'],
            ($stat['made'] + $stat['skipped']) ? round($stat['saved_kb'] / ($stat['made'] + $stat['skipped'])) : 0
        ), $stat['failed'] ? Console::FG_YELLOW : Console::FG_GREEN);
        return ExitCode::OK;
    }

    /**
     * 找原图的本地文件:优先 path 字段;服务器搬过家 path 对不上时,按 url 里的 /uploads/... 到 web 目录下找
     */
    private function resolvePath(File $file, string $webroot): ?string
    {
        if ($file->path && is_file($file->path)) {
            return $file->path;
        }
        $urlPath = parse_url((string) $file->url, PHP_URL_PATH);
        if ($urlPath && strpos($urlPath, '/uploads/') !== false) {
            $local = $webroot . substr($urlPath, strpos($urlPath, '/uploads/'));
            if (is_file($local)) {
                return $local;
            }
        }
        return null;
    }
}
