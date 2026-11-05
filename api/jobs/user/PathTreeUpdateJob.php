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

namespace app\jobs\user;

use app\core\Job;
use app\models\UserPath;
use Yii;

/**
 * 改写某人的子孙 Path(他的上级变了,整棵子树的前缀跟着变)
 *
 * bindParent 在事务内只同步改他自己的 path,commit 之后推本 Job 改子孙(推送失败时当场同步执行)。
 *
 * ## 为什么执行时重读当前 path,而不是用入队时带来的 oldPath → newPath
 *
 * 原来是「把以 oldPath 开头的行换成 newPath 开头」。两个 Job 乱序执行时会永久错位(2026-09 核查):
 *   · 同一个人连着改挂两次(Q → R),两个 Job 同一秒入队、调度器并行跑:后一个先跑时按它的 oldPath
 *     一行都匹配不到,前一个再把子孙改到 Q 下 —— 子孙永远停在中间那个上级下面;
 *   · 祖先 X 和后代 Z 先后改挂:Z 的 Job 带的 oldPath 是 X 改挂前的旧前缀,X 的 Job 先跑完后它就落空。
 * 现在执行时读这个人**当前**的 path,把所有路径里含 `-他-` 且前缀不对的行统一改成「当前 path + 他之后那一段」:
 * 不管几个 Job、谁先谁后,最后一个跑完的结果都等于当前真值。oldPath / newPath 只留作日志(兼容已入队的旧 Job)。
 *
 * ## 为什么出错要抛出
 *
 * 原来 catch 住只记日志、正常返回,调度器当它成功,不会重试,整棵子树永久停在旧链上(复现:锁等待超时即如此)。
 * 现在抛出去交给调度器按 max 次数重试;本 Job 幂等(改的是「前缀不对」的行,重跑只会改剩下的)。
 */
class PathTreeUpdateJob extends Job
{
    public $userId;
    public $mallId;
    public $oldPath;  // 入队时的旧路径(仅日志)
    public $newPath;  // 入队时的新路径(仅日志)

    public function execute($params = null)
    {
        $userId = (int)$this->userId;
        $mallId = (int)$this->mallId;
        if ($userId <= 0) {
            return;
        }

        try {
            $cur = (string)UserPath::find()->select('path')->where(['user_id' => $userId])->scalar();
            if ($cur === '') {
                Yii::warning("[PathTreeUpdateJob] User:{$userId} 没有 path 记录,跳过");
                return;
            }
            $needle = "-{$userId}-";

            // 子孙:路径里含「-他-」;前缀已经是他当前 path 的,说明已经对了,不动
            // 用 LOCATE 而不是 LIKE '%-id-%':参数里的 id 不会被当成通配符,口径也和下面截取的位置一致
            $sql = "UPDATE {{%user_path}}
                    SET path = CONCAT(:cur, SUBSTRING(path, LOCATE(:needle, path) + CHAR_LENGTH(:needle2)))
                    WHERE mall_id = :mallId AND user_id != :userId
                      AND LOCATE(:needle3, path) > 0
                      AND LEFT(path, CHAR_LENGTH(:cur2)) != :cur3";
            $affected = Yii::$app->db->createCommand($sql, [
                ':cur' => $cur, ':cur2' => $cur, ':cur3' => $cur,
                ':needle' => $needle, ':needle2' => $needle, ':needle3' => $needle,
                ':mallId' => $mallId, ':userId' => $userId,
            ])->execute();

            Yii::warning("[PathTreeUpdateJob] User:{$userId} 子孙路径已对齐当前 path({$cur}),改写 {$affected} 条"
                . "(入队时 {$this->oldPath} → {$this->newPath})");
        } catch (\Throwable $e) {
            Yii::error("[PathTreeUpdateJob] User:{$userId} 失败(抛出交调度器重试): " . $e->getMessage());
            throw $e;
        }
    }
}
