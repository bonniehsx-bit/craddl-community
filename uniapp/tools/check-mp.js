#!/usr/bin/env node
/**
 * 小程序兼容性检查 —— 专抓那些「本地编译能过、上传/编译时才炸」的写法。
 *
 *   node uniapp/tools/check-mp.js                        # 扫全项目
 *   node uniapp/tools/check-mp.js components/xx/xx.vue   # 只扫指定文件(改完自查)
 *
 * 这类问题的共同点是**普通检查抓不到**:Vue 层面语法完全合法,H5/App 端也跑得好好的,
 * 只有微信那边才拦。等到上传时才发现,报错还只给一个字符偏移量。
 *
 * 退出码:0=通过,1=有问题。
 */
const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..')
const args = process.argv.slice(2)
const targets = args.filter(a => !a.startsWith('--'))

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
		return targets
			.map(t => (path.isAbsolute(t) ? t : path.resolve(process.cwd(), t)))
			.filter(f => {
				if (fs.existsSync(f)) return true
				console.error('找不到文件: ' + f)
				return false
			})
	}
	return walk(ROOT, [])
}

/* ---------------------------------------------------------------
 * 规则 1:绑定表达式里出现**空对象** `{}`
 *
 *   Bad attr `style` with message: unexpected token `}`
 *
 * 三元套在外层时,uniapp 不会把它拆成字符串拼接,而是原样塞进 wxml 的 {{}}。
 * 而 WXML 表达式**本来就支持对象字面量**,所以带内容的对象没事 ——
 * 出事的只有空对象:`{` 后面紧跟 `}`,解析器等着读键名却撞上 `}`。
 *
 * 编译产物实测对照:
 *   { height: x + 'px' }         → 'height:'+(x+'px')+';'                       安全(编译器拆了)
 *   { bg: cond ? a : b }         → 'background:'+(cond?a:b)+';'                 安全(三元在**值**的位置)
 *   cond ? {color:a} : {color:b} → (cond?{color:a}:{color:b})                    安全(线上跑了很久)
 *   cond ? {color:a} : ''        → (cond?{color:a}:'')                           安全(线上跑了很久)
 *   cond ? {width:x}  : {}       → (cond?{width:x}:{})                           ✗ 空对象,上传即失败
 *
 * 一开始这条规则写成「三元分支是对象就报」,全项目报了 21 处 —— 而那些页面
 * 一直上传得好好的。去编译产物里对了一遍才发现症结只在空对象。
 * 宁可漏报也别误报:让人去改 21 处本来没问题的代码,比不检查更糟。
 * ------------------------------------------------------------- */
// 两种写法都收:`:style="…"` 和 `v-bind:style="…"`
const BIND_RE = /(?::|v-bind:)(style|class)\s*=\s*"([^"]*)"/g

function riskyBinding(expr) {
	const e = (expr || '').trim()
	if (!e) return false
	// 以 { 或 [ 开头 = 字面量,编译器会拆成字符串拼接,进不了 wxml 表达式
	if (e[0] === '{' || e[0] === '[') return false
	// 剩下的会原样进 {{}};只有空对象会让 WXML 解析器崩
	return /\{\s*\}/.test(e)
}

function scanBindings(file, src) {
	const hits = []
	let m
	BIND_RE.lastIndex = 0
	while ((m = BIND_RE.exec(src))) {
		if (!riskyBinding(m[2])) continue
		hits.push({
			file,
			line: src.slice(0, m.index).split('\n').length,
			rule: 'empty-object-in-binding',
			attr: m[1],
			code: m[0].length > 110 ? m[0].slice(0, 110) + '…' : m[0],
			fix: '把表达式挪进 computed 返回字符串(如 "width:50%"),或把空对象换成空字符串'
		})
	}
	return hits
}

/* ---------------------------------------------------------------
 * 规则 2:条件编译注释后面跟了中文
 *
 * `// #ifdef MP-WEIXIN 微信端专用` —— uniapp 会把 #ifdef 后面整段当条件表达式
 * 丢给 new Function 解析,中文直接 SyntaxError,而且**报错不带文件名**,
 * 全项目编译不过还找不到是哪一行。
 * ------------------------------------------------------------- */
const IFDEF_RE = /(\/\/|\/\*|<!--)\s*#(ifdef|ifndef)\s+([^\n*]*)/g
const CJK = /[一-龥]/

function scanIfdef(file, src) {
	const hits = []
	let m
	IFDEF_RE.lastIndex = 0
	while ((m = IFDEF_RE.exec(src))) {
		const cond = m[3] || ''
		if (!CJK.test(cond)) continue
		hits.push({
			file,
			line: src.slice(0, m.index).split('\n').length,
			rule: 'ifdef-with-cjk',
			code: m[0].trim().slice(0, 110),
			fix: '条件编译那一行只留平台名,说明文字另起一行写'
		})
	}
	return hits
}

/* ---------------- 执行 ---------------- */

const files = collect()
const problems = []

for (const f of files) {
	const src = fs.readFileSync(f, 'utf8')
	problems.push(...scanBindings(f, src))
	problems.push(...scanIfdef(f, src))
}

const rel = f => path.relative(path.resolve(ROOT, '..'), f)

console.log('扫描 ' + files.length + ' 个文件\n')

if (problems.length) {
	const byRule = {}
	for (const p of problems) (byRule[p.rule] = byRule[p.rule] || []).push(p)

	const TITLE = {
		'empty-object-in-binding': '绑定表达式里有空对象(上传小程序时报 Bad attr / unexpected token)',
		'ifdef-with-cjk': '条件编译注释后跟了中文(编译期 SyntaxError,且报错不带文件名)'
	}

	for (const rule of Object.keys(byRule)) {
		const list = byRule[rule]
		console.log('【' + TITLE[rule] + '】' + list.length + ' 处')
		for (const p of list) {
			console.log('  ✗ ' + rel(p.file) + ':' + p.line)
			console.log('      ' + p.code)
		}
		console.log('    修法:' + list[0].fix + '\n')
	}
}

console.log(problems.length ? '发现 ' + problems.length + ' 处问题' : '未发现问题')
process.exit(problems.length ? 1 : 0)
