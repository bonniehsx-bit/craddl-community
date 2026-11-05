package worker

import (
	"encoding/json"
	"fmt"
	"io"
	"path/filepath"
	"scheduler/common"
	"scheduler/config"
	"strconv"
	"strings"
	"time"

	"github.com/tomasen/fcgi_client"
)

// DispatchToFpm 通过 FastCGI 把任务派发给 PHP-FPM 执行。
// 返回 (是否成功, 失败原因, 耗时毫秒)。
//
// 成功判定：读取 PHP schedule/execute 返回体的 {"status":"success"}。
// HTTP 200 不代表任务成功 —— PHP 抛异常同样是 200 + {"status":"error"}。
func DispatchToFpm(payload string, sourceType string) (bool, string, int64) {
	var job config.JobPayload
	_ = json.Unmarshal([]byte(payload), &job)
	jobName := job.Class
	if jobName == "" {
		jobName = "Unknown"
	}

	if sourceType == "queue" {
		common.Logger.Printf("📥 执行 | Class: %s", jobName)
	}
	startTime := time.Now()

	fcgi, err := fcgiclient.Dial(common.Cfg.Yii.FpmNetwork, common.Cfg.Yii.FpmAddr)
	if err != nil {
		common.Logger.Printf("❌ FPM 连接失败 | Class: %s | Err: %v", jobName, err)
		return false, "FPM 连接失败: " + err.Error(), time.Since(startTime).Milliseconds()
	}
	defer fcgi.Close()

	timer := time.AfterFunc(30*time.Second, func() { fcgi.Close() })
	defer timer.Stop()

	env := map[string]string{
		"SCRIPT_FILENAME": common.Cfg.Yii.EntryFile,
		"DOCUMENT_ROOT":   filepath.Dir(common.Cfg.Yii.EntryFile),
		"REQUEST_METHOD":  "POST",
		"CONTENT_TYPE":    "application/json",
		"CONTENT_LENGTH":  strconv.Itoa(len(payload)),
		"SERVER_PROTOCOL": "HTTP/1.1",
		"REMOTE_ADDR":     "127.0.0.1",
		"QUERY_STRING":    "r=schedule/execute",
	}

	resp, err := fcgi.Post(env, "application/json", strings.NewReader(payload), len(payload))
	duration := time.Since(startTime)
	durMs := duration.Milliseconds()

	if err != nil {
		common.Logger.Printf("❌ 网络异常 | Class: %s | Err: %v", jobName, err)
		return false, "派发网络异常: " + err.Error(), durMs
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)

	if resp.StatusCode != 200 && resp.StatusCode != 0 {
		common.Logger.Printf("⚠️ 失败 [%d] | Class: %s", resp.StatusCode, jobName)
		return false, fmt.Sprintf("HTTP %d", resp.StatusCode), durMs
	}

	// 解析 PHP 返回体的 status —— 剥离可能的前导内容，定位首个 '{'
	s := string(body)
	if idx := strings.Index(s, "{"); idx > 0 {
		s = s[idx:]
	}
	var r struct {
		Status  string `json:"status"`
		Message string `json:"message"`
	}
	if err := json.Unmarshal([]byte(s), &r); err != nil {
		common.Logger.Printf("⚠️ 响应解析失败 | Class: %s | Body: %.200s", jobName, s)
		return false, "响应解析失败", durMs
	}

	if r.Status == "success" {
		common.Logger.Printf("✅ 完成 | Class: %s | %v", jobName, duration)
		return true, "", durMs
	}

	common.Logger.Printf("⚠️ 任务失败 | Class: %s | %s", jobName, r.Message)
	return false, r.Message, durMs
}
