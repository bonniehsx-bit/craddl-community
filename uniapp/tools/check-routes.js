#!/usr/bin/env node
/**
 * uniapp 页面跳转死链检查。
 *
 *   node uniapp/tools/check-routes.js                      # 扫全项目
 *   node uniapp/tools/check-routes.js others/cat2/cat2.vue # 只扫指定文件(改完自查)
 *   node uniapp/tools/check-routes.js --strict             # 把「无法静态判定」也算失败
 *
 * 为什么要有这个:跳一个不存在的页面**不报错** —— 点了没反应,不弹错、不跳转,
 * 控制台也未必有东西。模板语法检查、逻辑单测、字段验证,没有一项能覆盖
 * 「这个路径在不在」。真实事故:分类主题页跳详情写成 /others/goods-detail/goods-detail,
 * 而那个目录压根不存在(全项目其余 6 处都是 /others/goods/goods),点商品毫无反应。
 *
 * 退出码:0=全通过,1=有死链。
 */
const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..')
const args = process.argv.slice(2)
const strict = args.includes('--strict')
const targets = args.filter(a => !a.startsWith('--'))

/* ---------- 1. 读 pages.json,建注册表 ---------- */

function loadPages() {
	let raw = fs.readFileSync(path.join(ROOT, 'pages.json'), 'utf8')
	// pages.json 允许注释,JSON.parse 不认。只去行注释,块注释在这个文件里没出现过。
	raw = raw.replace(/(^|[^:])\/\/.*$/gm, '$1')
	const json = JSON.parse(raw)

	const set = new Set()
	for (const p of json.pages || []) set.add('/' + p.path)
	for (const sp of json.subPackages || []) {
		for (const p of sp.pages || []) set.add('/' + sp.root + '/' + p.path)
	}
	return set
}

/* ---------- 2. 收集要扫的文件 ---------- */

const SKIP_DIR = new Set(['node_modules', 'unpackage', '.git', 'tools', 'uni_modules'])

function walk(dir, out) {
	for (const name of fs.readdirSync(dir)) {
		if (SKIP_DIR.has(name)) continue
		const full = path.join(dir, name)
		const st = fs.statSync(full)
		if (st.isDirectory()) walk(full, out)
		else if (/\.(vue|js)$/.test(name)) out.push(full)
	}
	return out
}

function collect() {
	if (targets.length) {
		return targets.map(t => path.isAbsolute(t) ? t : path.resolve(process.cwd(), t))
			.filter(f => {
				if (fs.existsSync(f)) return true
				console.error('找不到文件: ' + f)
				return false
			})
	}
	return walk(ROOT, [])
}

/* ---------- 3. 提取跳转 url ---------- */

/**
 * 只认页面跳转 API 里的 url,不按前缀黑名单过滤。
 *
 * 一开始是反过来写的 —— 抓所有 `url:` 再排除 /api/ 和 /plugin/。结果 $request
 * 调接口的 `/common/address/town-list` 全被当成页面报了死链。接口前缀有几种、
 * 以后还可能加,黑名单永远补不全;而「它是不是跳转」看调用的是哪个 API 就够了。
 */
const JUMP_RE = /\b(?:uni|wx)\.(navigateTo|redirectTo|reLaunch|switchTab)\s*\(\s*\{([\s\S]{0,400}?)\}/g
const INNER_URL_RE = /url\s*:\s*(['"`])([^'"`]*)\1/

function scan(file) {
	const src = fs.readFileSync(file, 'utf8')
	const found = []
	let m
	while ((m = JUMP_RE.exec(src))) {
		const api = m[1]
		const inner = INNER_URL_RE.exec(m[2])
		// url 由变量传入(先拼好再跳)的,静态查不了,跳过
		if (!inner) continue
		const rawUrl = inner[2]
		if (/^https?:/.test(rawUrl)) continue

		const q = rawUrl.indexOf('?')
		const p = q > -1 ? rawUrl.slice(0, q) : rawUrl
		const line = src.slice(0, m.index).split('\n').length
		found.push({
			file, line, api, url: rawUrl, pagePath: p,
			// 路径段里带变量的没法静态判定;query 里带变量不影响
			dynamic: p.includes('${') || !p.startsWith('/')
		})
	}
	return found
}

/* ---------- 4. 校验 ---------- */

const registered = loadPages()
const files = collect()
const hits = files.flatMap(scan)

const dead = []
const unknown = []
let ok = 0

for (const h of hits) {
	if (h.dynamic) { unknown.push(h); continue }
	const inJson = registered.has(h.pagePath)
	const onDisk = fs.existsSync(path.join(ROOT, h.pagePath + '.vue'))
	if (inJson && onDisk) { ok++; continue }
	dead.push(Object.assign({ inJson, onDisk }, h))
}

/* ---------- 5. 报告 ---------- */

const rel = f => path.relative(path.resolve(ROOT, '..'), f)

console.log('扫描 ' + files.length + ' 个文件,找到 ' + hits.length + ' 处页面跳转\n')

if (dead.length) {
	console.log('死链 ' + dead.length + ' 处:')
	for (const d of dead) {
		const why = !d.inJson && !d.onDisk ? 'pages.json 没注册,文件也不存在'
			: !d.inJson ? 'pages.json 没注册(文件存在,补注册即可)'
				: '已注册但文件不存在'
		console.log('  ✗ ' + rel(d.file) + ':' + d.line)
		console.log('      ' + d.url)
		console.log('      → ' + why)
	}
	console.log('')
}

if (unknown.length) {
	console.log('无法静态判定 ' + unknown.length + ' 处(路径里有变量,需人工确认):')
	for (const u of unknown) console.log('  ? ' + rel(u.file) + ':' + u.line + '  ' + u.url)
	console.log('')
}

console.log('通过 ' + ok + ' / 死链 ' + dead.length + ' / 待确认 ' + unknown.length)

process.exit(dead.length || (strict && unknown.length) ? 1 : 0)
