//go:build community

package main

import (
	"encoding/json"
	"net/http"
	"scheduler/common"
	"scheduler/worker"
	"strings"
	"time"
)

func main() {
	common.InitConfig()
	runScheduler(func() { go startCommunityServer() }, nil)
}

func startCommunityServer() {
	addr := common.Cfg.Server.Port
	if !strings.Contains(addr, ":") {
		addr = ":" + addr
	}

	mux := http.NewServeMux()
	mux.HandleFunc("GET /health", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]string{"status": "ok"})
	})
	mux.HandleFunc("GET /check-update", func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "application/json")
		_ = json.NewEncoder(w).Encode(map[string]any{
			"ok": false, "online_upgrade": false, "message": worker.ManualUpgradeMessage,
		})
	})
	server := http.Server{Addr: addr, Handler: mux, ReadHeaderTimeout: 5 * time.Second}
	if err := server.ListenAndServe(); err != nil {
		common.Logger.Printf("❌ 社区版健康检查服务启动失败: %v", err)
	}
}
