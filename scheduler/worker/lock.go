package worker

import (
	"fmt"
	"io/ioutil"
	"log"
	"os"
	"path/filepath"
	"scheduler/common"
	"strconv"
	"strings"
	"syscall"
)

var (
	portLockHandle *os.File
	dbLockHandle   *os.File
)

func initPidPath() {
	entryFile := common.Cfg.Yii.EntryFile
	if entryFile == "" {
		entryFile = "yii"
	}
	absEntry, err := filepath.Abs(entryFile)
	if err != nil {
		absEntry = entryFile
	}
	runtimeDir := filepath.Join(filepath.Dir(filepath.Dir(absEntry)), "runtime")
	if _, err := os.Stat(runtimeDir); os.IsNotExist(err) {
		_ = os.MkdirAll(runtimeDir, 0755)
	}
}

// TryLockAndWritePid 获取文件锁并写入PID
func TryLockAndWritePid() {
	initPidPath()
	entryFile := common.Cfg.Yii.EntryFile
	if entryFile == "" {
		entryFile = "yii"
	}
	absEntry, _ := filepath.Abs(entryFile)
	runtimeDir := filepath.Join(filepath.Dir(filepath.Dir(absEntry)), "runtime")

	rawPort := common.Cfg.Server.Port
	cleanPort := strings.TrimPrefix(rawPort, ":")
	if cleanPort == "" {
		cleanPort = "default"
	}
	dbIndex := common.Cfg.Redis.DB

	// 端口锁
	portLockFile := filepath.Join(runtimeDir, fmt.Sprintf("lock.port.%s", cleanPort))
	var err error
	portLockHandle, err = os.OpenFile(portLockFile, os.O_RDWR|os.O_CREATE, 0666)
	if err != nil {
		log.Fatalf("❌ [FATAL] 无法创建端口锁文件: %v", err)
	}
	if err = syscall.Flock(int(portLockHandle.Fd()), syscall.LOCK_EX|syscall.LOCK_NB); err != nil {
		log.Fatalf("❌ [启动失败] 端口 [%s] 已被其他进程占用！", cleanPort)
	}

	// DB锁
	dbLockFile := filepath.Join(runtimeDir, fmt.Sprintf("lock.db.%d", dbIndex))
	dbLockHandle, err = os.OpenFile(dbLockFile, os.O_RDWR|os.O_CREATE, 0666)
	if err != nil {
		log.Fatalf("❌ [FATAL] 无法创建DB锁文件: %v", err)
	}
	if err = syscall.Flock(int(dbLockHandle.Fd()), syscall.LOCK_EX|syscall.LOCK_NB); err != nil {
		log.Fatalf("❌ [启动失败] Redis库 [DB %d] 正在被处理中！", dbIndex)
	}

	// PID
	pidName := fmt.Sprintf("scheduler_%s_db%d.pid", cleanPort, dbIndex)
	pidFile := filepath.Join(runtimeDir, pidName)
	if err := ioutil.WriteFile(pidFile, []byte(strconv.Itoa(os.Getpid())), 0644); err != nil {
		common.Logger.Printf("⚠️ 无法写入 PID 文件: %v", err)
	}

	fmt.Printf("🔒 成功获取独占锁:\n   1. 端口锁: %s\n   2. 数据锁: %s\n", portLockFile, dbLockFile)
}
