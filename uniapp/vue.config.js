module.exports = {
  configureWebpack(config) {
    if (process.env.UNI_PLATFORM !== 'mp-weixin') return

    config.plugins.push({
      apply(compiler) {
        // 这些资源仅用于原生 App 图标或已被编译的样式，不进入微信小程序包。
        // 保留源文件和其他平台的产物，不能通过删除 static 文件来压缩小程序。
        compiler.hooks.emit.tap('ExcludeWechatUnusedAssets', compilation => {
          Object.keys(compilation.assets).forEach(name => {
            const asset = name.replace(/\\/g, '/')
            if (asset.startsWith('static/brand/icons/') || asset === 'static/style/main.scss') {
              delete compilation.assets[name]
            }
          })
        })
      }
    })
  }
}
