package common

import (
	"encoding/json"
	"fmt"
	"regexp"
	"time"

	"github.com/go-redis/redis/v8"
)

var (
	// 缓存前缀 (对应 PHP 的 \Yii::$app->db->dbName)
	// 例如: "jxmall"
	dbNamePrefix string
)

// InitCacheHelper 初始化缓存助手
// 必须在 InitConfig() 之后调用
func InitCacheHelper() {
	dsn := Cfg.Database.DSN

	// 正则解释：匹配 )/ 后面，直到 ? 或 字符串结束 的内容
	// 兼容格式 1: user:pass@tcp(127.0.0.1:3306)/jxmall?charset=utf8
	// 兼容格式 2: user:pass@tcp(127.0.0.1:3306)/jxmall_v2
	re := regexp.MustCompile(`\)/([a-zA-Z0-9_]+)(\?|$)`)
	matches := re.FindStringSubmatch(dsn)

	if len(matches) >= 2 {
		dbNamePrefix = matches[1]
		Logger.Printf("✅ 从 DSN 解析数据库名成功: %s", dbNamePrefix)
	} else {
		// 兜底方案：建议在 Config 结构体中也加一个 DBName 字段作为备用
		if Cfg.Database.DbName != "" {
			dbNamePrefix = Cfg.Database.DbName
		} else {
			dbNamePrefix = "jxmall" // 最后的倔强
			Logger.Printf("⚠️ 无法从 DSN 解析数据库名，使用默认前缀: %s", dbNamePrefix)
		}
	}

	Logger.Printf("🔥 CacheHelper 就绪 | Redis Key前缀: %s_", dbNamePrefix)
}

// GetKeyName 复刻 PHP 逻辑: dbName_name
func GetKeyName(name string) string {
	return fmt.Sprintf("%s_%s", dbNamePrefix, name)
}

// CacheSet 写入缓存 (对应 PHP::set)
// data: 任意结构体、Map、切片、基本类型
// duration: 0 表示不过期
func CacheSet(name string, data interface{}, duration time.Duration) error {
	key := GetKeyName(name)

	// 1. 序列化为 JSON 字符串
	val, err := json.Marshal(data)
	if err != nil {
		Logger.Printf("❌ CacheSet JSON Marshal Error: %v", err)
		return err
	}

	// 2. 存入 Redis
	return RDB.Set(Ctx, key, val, duration).Err()
}

// CacheGet 读取缓存 (对应 PHP::get)
// name: 缓存 Key (不带前缀)
// dest: 目标对象的指针 (例如 &user, &mapData)
// 返回: true(命中), false(未命中或错误)
func CacheGet(name string, dest interface{}) bool {
	key := GetKeyName(name)

	// 1. 读取 Redis 原始字符串
	val, err := RDB.Get(Ctx, key).Result()
	if err == redis.Nil {
		return false // Key 不存在
	}
	if err != nil {
		Logger.Printf("❌ CacheGet Redis Error: %v", err)
		return false
	}

	// 2. 反序列化 JSON 到目标对象
	if err := json.Unmarshal([]byte(val), dest); err != nil {
		Logger.Printf("❌ CacheGet JSON Unmarshal Error: Key=%s, Val=%s, Err=%v", key, val, err)
		return false
	}

	return true
}

// CacheExists 判断 Key 是否存在 (对应 PHP::exists)
func CacheExists(name string) bool {
	key := GetKeyName(name)
	n, _ := RDB.Exists(Ctx, key).Result()
	return n > 0
}

// CacheRemove 删除缓存 (对应 PHP::remove)
func CacheRemove(name string) bool {
	key := GetKeyName(name)
	err := RDB.Del(Ctx, key).Err()
	return err == nil
}

// CacheGetOrSet 获取或设置 (对应 Yii 的 getOrSet)
// fetchFunc: 如果缓存不存在，执行此函数获取数据
func CacheGetOrSet(name string, dest interface{}, duration time.Duration, fetchFunc func() (interface{}, error)) error {
	// 1. 尝试读取
	if CacheGet(name, dest) {
		return nil
	}

	// 2. 未命中，执行获取逻辑
	data, err := fetchFunc()
	if err != nil {
		return err
	}

	// 3. 写入缓存
	if err := CacheSet(name, data, duration); err != nil {
		return err
	}

	// 4. 将新数据填回 dest (为了让调用者能立马拿到数据)
	// 这里通过 json再一次转换虽然有性能损耗，但能保证 dest 被正确赋值且类型安全
	// 更高性能的做法是利用 reflect，但 json 最稳妥
	bytes, _ := json.Marshal(data)
	json.Unmarshal(bytes, dest)

	return nil
}
