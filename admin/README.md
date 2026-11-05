## 开发

```bash

# 进入项目目录
cd admin

# 安装依赖
npm install

# 建议不要直接使用 cnpm 安装依赖，会有各种诡异的 bug。可以通过如下操作解决 npm 下载速度慢的问题
npm install --registry=https://registry.npm.taobao.org

# 启动服务
npm run dev
```

浏览器访问 http://localhost:80

## 发布

```bash
# 构建测试环境
npm run build:stage

# 构建生产环境
npm run build:prod
```

```
# 菜单icon svg 
大小  128x128
```

## 许可与第三方声明

本社区版中由佛山今日鼎盛信息科技有限公司授权的 Craddl 自有代码适用 [CPAL-1.0](../LICENSE)。第三方部分保留其原许可及署名。
