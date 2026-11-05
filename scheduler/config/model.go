package config

// Config 配置结构体
type Config struct {
	Server struct {
		Port      string `yaml:"port"`
		WorkerNum int    `yaml:"worker_num"`
	} `yaml:"server"`
	Redis struct {
		Addr        string `yaml:"addr"`
		Password    string `yaml:"password"`
		DB          int    `yaml:"db"`
		TaskKey     string `yaml:"task_key"`
		EventKey    string `yaml:"event_key"`
		CronKey     string `yaml:"cron_key"`
		AdminLogKey string `yaml:"admin_log_key"`
		UserLogKey  string `yaml:"user_log_key"`
		YiiLogKey   string `yaml:"yii_log_key"`
	} `yaml:"redis"`
	Yii struct {
		FpmNetwork string `yaml:"fpm_network"`
		FpmAddr    string `yaml:"fpm_addr"`
		EntryFile  string `yaml:"entry_file"`
		PhpBin     string `yaml:"php_bin"` // 升级迁移走 CLI 的 php 可执行路径,留空默认 "php"
	} `yaml:"yii"`
	Database struct {
		DSN    string `yaml:"dsn"`
		DbName string `yaml:"dbName"`
	} `yaml:"database"`
	Log struct {
		OutputDir string `yaml:"output_dir"`
	} `yaml:"log"`
	License struct {
		ServerUrl     string `yaml:"server_url"`
		CheckInterval int    `yaml:"check_interval"` // Unit: Seconds
		SkipCheck     bool   `yaml:"skip_check"`
	} `yaml:"license"`
}

// JobPayload 任务载荷
type JobPayload struct {
	Class string `json:"class"`
}

// Envelope 任务信封 —— jxmall_scheduler ZSet 成员的标准格式
// PHP JobHelper::pushRaw 写入；Go 解析后只把 Payload 派发给 FPM。
// 兼容旧格式：无 payload 字段时整个成员当作 Payload，Attempt=0。
type Envelope struct {
	V          int                    `json:"v"`
	Attempt    int                    `json:"attempt"`
	Max        int                    `json:"max"`
	Lane       string                 `json:"lane"`       // high/normal/low/throttle，重试回原车道
	UniqueKey  string                 `json:"unique_key"` // 唯一性投递占位 key，终态时释放
	EnqueuedAt int64                  `json:"enqueued_at"`
	Payload    map[string]interface{} `json:"payload"`
}

// EnqueueEvent 队列事件
type EnqueueEvent struct {
	Class   string `json:"class"`
	RunTime int64  `json:"run_time"`
}

// CronConfig 定时任务配置
type CronConfig struct {
	Class       string `json:"class"`
	Method      string `json:"method"`
	Expression  string `json:"expression"`
	Description string `json:"description"`
	Mode        string `json:"mode"` // 'fpm'(默认,走 FastCGI、受 30s 派发超时)| 'cli'(重/长任务走命令行 php yii,无超时、不占 fpm worker)
}
