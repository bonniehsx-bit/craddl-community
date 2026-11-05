package common

import (
	"context"
	"flag"
	"fmt"
	"log"
	"os"
	"path/filepath"
	"scheduler/config" // 确保 go.mod module 名为 scheduler
	"strings"
	"time"

	"github.com/go-redis/redis/v8"
	"gopkg.in/yaml.v3"
	"gorm.io/driver/mysql"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"
)

// 全局变量导出
var (
	Cfg      config.Config
	RDB      *redis.Client
	DB       *gorm.DB
	Logger   *log.Logger
	Ctx      = context.Background()
	DBPrefix = "jxmall_"
)

// PopScript Lua 脚本：原子地把到期任务从主队列「认领」到 processing 队列
// （不再是弹出即删 —— 任务执行成功后才从 processing 删除，保证不丢）
//
//	KEYS[1] = 主队列        KEYS[2] = processing 队列
//	ARGV[1] = now（到期上界）ARGV[2] = limit  ARGV[3] = processing 可见性截止分数(now+超时)
var PopScript = redis.NewScript(`
    local due = redis.call('ZRANGEBYSCORE', KEYS[1], '-inf', ARGV[1], 'LIMIT', 0, ARGV[2])
    if #due > 0 then
       redis.call('ZREM', KEYS[1], unpack(due))
       for i=1,#due do
          redis.call('ZADD', KEYS[2], ARGV[3], due[i])
       end
    end
    return due
`)

// InitConfig 加载配置
func InitConfig() {
	exePath, _ := os.Executable()
	defaultConfig := filepath.Join(filepath.Dir(exePath), "config.yaml")

	// 1. 提前声明一个字符串变量用来接收路径
	var configPath string

	// 2. 将 -config 和 -c 都绑定到 configPath 的内存地址上
	flag.StringVar(&configPath, "config", defaultConfig, "指定配置文件路径")
	flag.StringVar(&configPath, "c", defaultConfig, "指定配置文件路径 (简写)")
	flag.Parse()

	// 3. 尝试读取配置文件 (注意：这里直接用 configPath，不再需要加 * 解引用了)
	data, err := os.ReadFile(configPath)
	if err != nil {
		// 回退：如果指定的路径或可执行文件目录下找不到，尝试读取当前执行目录下的 config.yaml
		data, err = os.ReadFile("config.yaml")
		if err != nil {
			log.Fatalf("❌ 无法读取配置文件: %v", err)
		}
	}
	// 解析 YAML
	if err := yaml.Unmarshal(data, &Cfg); err != nil {
		log.Fatalf("❌ 配置文件解析错误: %v", err)
	}
}

// InitLogger 初始化日志
func InitLogger() {
	logDir := Cfg.Log.OutputDir
	// 如果是相对路径，转换为绝对路径
	if !filepath.IsAbs(logDir) {
		wd, _ := os.Getwd()
		logDir = filepath.Join(wd, logDir)
	}
	// 创建日志目录
	if _, err := os.Stat(logDir); os.IsNotExist(err) {
		_ = os.MkdirAll(logDir, 0755)
	}
	// 生成日志文件名，按日期分割
	logName := fmt.Sprintf("%s/scheduler_%s.log",
		strings.TrimSuffix(logDir, "/"),
		time.Now().Format("2006-01-02"))

	file, err := os.OpenFile(logName, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		// 如果文件打开失败，回退到标准输出
		Logger = log.New(os.Stdout, "", log.LstdFlags|log.Lshortfile)
		log.Printf("⚠️ 无法写入日志文件，切换至 Stdout: %v", err)
		return
	}
	// 设置全局 Logger
	Logger = log.New(file, "", log.LstdFlags|log.Lshortfile)
}

// InitRedis 初始化 Redis
func InitRedis() {
	RDB = redis.NewClient(&redis.Options{
		Addr:         Cfg.Redis.Addr,
		Password:     Cfg.Redis.Password,
		DB:           Cfg.Redis.DB,
		PoolSize:     Cfg.Server.WorkerNum + 5, // 连接池大小略大于 Worker 数
		MinIdleConns: 5,                        // 最小空闲连接
	})

	// 测试连接
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
	defer cancel()
	if _, err := RDB.Ping(ctx).Result(); err != nil {
		log.Fatalf("❌ 无法连接 Redis: %v", err)
	}
}

// InitDB 初始化数据库 (GORM)
func InitDB() {
	var err error

	// 配置 GORM 日志，将 SQL 日志输出对接到我们自己的 Logger
	newLogger := logger.New(
		Logger, // 使用我们定义的 Logger
		logger.Config{
			SlowThreshold:             time.Second,  // 慢 SQL 阈值 (超过1秒记录)
			LogLevel:                  logger.Error, // 日志级别 (生产环境建议 Error，开发环境 Info)
			IgnoreRecordNotFoundError: true,         // 忽略 RecordNotFound 错误
			Colorful:                  false,        // 禁用彩色打印 (因为写入文件)
		},
	)

	// 连接 MySQL
	DB, err = gorm.Open(mysql.Open(Cfg.Database.DSN), &gorm.Config{
		Logger: newLogger,
	})

	if err != nil {
		log.Fatalf("❌ 数据库连接失败: %v", err)
	}

	// 获取底层 SQL DB 对象以设置连接池
	sqlDB, err := DB.DB()
	if err != nil {
		log.Fatalf("❌ 获取原生 SQL DB 失败: %v", err)
	}

	// 设置连接池参数
	sqlDB.SetMaxIdleConns(10)           // 空闲连接数
	sqlDB.SetMaxOpenConns(100)          // 最大打开连接数
	sqlDB.SetConnMaxLifetime(time.Hour) // 连接最大生命周期

	Logger.Println("✅ MySQL 连接成功")
}
