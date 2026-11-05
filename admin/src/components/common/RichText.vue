<template>
  <div class="rich-text-wrap" :style="editorBoxStyle">
    <!-- 模式切换。默认「可视编辑」,行为与改造前完全一致 —— 12 处调用方无需改动 -->
    <div class="rt-modes">
      <el-radio-group v-model="mode" size="mini" @change="onModeChange">
        <el-radio-button label="wysiwyg">可视编辑</el-radio-button>
        <el-radio-button label="source">HTML 源码</el-radio-button>
        <el-radio-button label="preview">预览</el-radio-button>
      </el-radio-group>
      <span v-if="mode === 'source'" class="rt-hint">
        从外部网页拷来的 HTML 请用这里粘贴，内容原样保存，不会被编辑器改写
      </span>
      <span v-else-if="mode === 'preview'" class="rt-hint">
        按手机宽度（375px）预览，与 App 端实际显示接近
      </span>
    </div>

    <!-- 转换后的还原入口。只要没离开页面就一直挂着,点一下退回原文 -->
    <div v-if="backup !== null" class="rt-backup-bar">
      <span>
        可视编辑器不认识这段 HTML 里的部分标签，已简化：{{ lossDesc }}。
        原文（{{ backup.length }} 字符）已备份 —— <b>这次编辑内有效，保存离开后就没了</b>。
      </span>
      <el-button type="text" size="mini" @click="restoreBackup">还原原文</el-button>
    </div>

    <!-- 可视编辑:原有 wangEditor,一行没动 -->
    <div v-show="mode === 'wysiwyg'" class="rt-editor-box">
      <Toolbar style="border-bottom: 1px solid #ccc" :editor="editor" :defaultConfig="toolbarConfig" />
      <Editor :style="{ height: height }" style="overflow-y: hidden;" :defaultConfig="editorConfig"
        v-model="internalHtml" @onCreated="onCreated" />
    </div>

    <!-- HTML 源码:直接绑 value,不经过 wangEditor,贴什么存什么 -->
    <div v-show="mode === 'source'" class="rt-source-box">
      <el-input type="textarea" v-model="sourceHtml" :rows="sourceRows" resize="vertical"
        placeholder="可直接粘贴外部页面的 HTML 源码" @input="onSourceInput"></el-input>
      <div class="rt-source-foot">
        <span>{{ (sourceHtml || '').length }} 字符</span>
        <el-button type="text" size="mini" @click="formatSource">格式化</el-button>
        <el-button type="text" size="mini" @click="clearSource">清空</el-button>
      </div>
    </div>

    <!-- 预览:按手机宽度渲染,所见即 App 端所得 -->
    <div v-show="mode === 'preview'" class="rt-preview-box" :style="{ height: height }">
      <div class="rt-phone">
        <div v-if="previewHtml" class="rt-phone-body" v-html="previewHtml"></div>
        <div v-else class="rt-phone-empty">暂无内容</div>
      </div>
    </div>

    <file-picker @close="closeFilePicker" :type="type" :multiple="true" :max="10" :openDialog="show"
      @selected="selected">
    </file-picker>
  </div>
</template>

<script>
import { Editor, Toolbar } from '@wangeditor/editor-for-vue';

/**
 * 富文本编辑器。
 *
 * 为什么要有「HTML 源码」这一档:wangEditor 5 只认自己生成的 HTML(官方限制,不是配置问题)。
 * 从淘宝详情页之类的外部页面拷贝 HTML 进来,粘贴会被降级成纯文本、setHtml 会把不认识的
 * 标签整个剥掉 —— 表现就是「贴进去、保存、再打开看到的是一堆代码」。而这些 HTML 在
 * App 端是能正常渲染的(库里存的是完好的 HTML),所以问题只出在后台这一环。
 *
 * 源码模式**绕过 wangEditor 直接读写 value**,原样保存;可视模式行为与改造前完全一致。
 * 两种模式共用同一个 value,切换时同步。
 */
export default {
  name: 'RichTextEditor',
  components: { Editor, Toolbar },
  model: {
    prop: 'value',
    event: 'input'
  },
  props: {
    value: {
      type: String,
      default: ''
    },
    width: {
      type: [String, Number],
      default: '750px'
    },
    height: {
      type: [String, Number],
      default: '540px'
    }
  },
  data() {
    return {
      editor: null,
      internalHtml: '',
      mode: 'wysiwyg',
      sourceHtml: '',
      // 记住自己最后一次 emit 的值,用来区分「外部灌进来的新内容」和「自己发出去又回流的」
      lastEmit: null,
      // 「转为可视编辑」前的原文备份。wangEditor 一解析就把不认识的标签剥了,且不可逆 ——
      // 只要用户还没保存离开,就得让他能一键退回去。
      backup: null,
      // 用户是否**主动**要求用可视模式编辑这段外部 HTML。
      // 没有这个标志,转换后 watch 会因为内容仍像外部 HTML 又把他弹回源码模式。
      userChoseWysiwyg: false,
      // 规整时到底少了什么(图片数/字符数),用来把提示说具体
      lossInfo: null,
      show: false,
      insertFunc: null,
      type: 'image',
      toolbarConfig: {},
      editorConfig: {
        placeholder: '请输入内容...'
      }
    };
  },
  computed: {
    // 编辑器外框尺寸:width 语义为"最大宽度" —— 外框先 width:100% 占满父级实现自适应,
    // 再用传入的 width(默认 750px)作为上限封顶。窄容器/小屏自动收缩不溢出,宽屏也不会过宽。
    editorBoxStyle() {
      const max = typeof this.width === 'number' ? `${this.width}px` : this.width;
      return { width: '100%', maxWidth: max, boxSizing: 'border-box' };
    },
    sourceRows() {
      const h = parseInt(this.height, 10);
      return isNaN(h) ? 20 : Math.max(Math.floor((h - 40) / 21), 8);
    },
    /** 当前内容是不是编辑器啃不动的外部 HTML(只用于「打开时默认停在哪个模式」) */
    isExternal() {
      return !!(this.value && this.looksExternal(this.value));
    },
    /** 把「少了什么」说清楚 —— 图文都没少的话就直说只是排版变了,免得白紧张 */
    lossDesc() {
      const l = this.lossInfo;
      if (!l) return '排版结构已简化';
      const parts = [];
      if (l.imgLost > 0) parts.push('少了 ' + l.imgLost + ' 张图');
      if (l.charLost > 0) parts.push('少了 ' + l.charLost + ' 个字');
      return parts.length ? parts.join('，') : '图片和文字都完整保留，仅排版结构被简化';
    },
    /**
     * 预览用的 HTML。剥掉 script 和 on* 事件属性 —— 后台粘贴的是外部页面内容,
     * 真带了脚本会在管理员浏览器里执行。App 端渲染不受影响,存的仍是原文。
     */
    previewHtml() {
      const raw = this.value || '';
      if (!raw) return '';
      return raw
        .replace(/<script[\s\S]*?<\/script>/gi, '')
        .replace(/<\/?(?:script|iframe|object|embed)\b[^>]*>/gi, '')
        .replace(/\son\w+\s*=\s*"[^"]*"/gi, '')
        .replace(/\son\w+\s*=\s*'[^']*'/gi, '');
    }
  },
  watch: {
    value: {
      handler(newVal) {
        const v = newVal || '';
        // 自己 emit 出去又绕回来的值,不要再走一遍下面的判定 ——
        // 否则用户在源码框每敲一个字都会被重新判定一次模式。
        if (v === this.lastEmit) return;

        this.sourceHtml = v;

        // 【关键】按**内容本身**决定用哪个模式,而不是看用户上次选了什么。
        //
        // 之前是「源码模式下才不碰 wangEditor」,但组件每次重建(重新打开编辑抽屉)
        // mode 都会重置成 wysiwyg,守卫直接失效:外部 HTML 灌进 wangEditor 被剥光,
        // 过滤后的残骸又通过 v-model 反向 emit 回去,把 form.detail 覆盖掉 ——
        // 用户再一保存,库里的原文就永久没了。这不是"看不到",是真丢数据。
        //
        // 所以外部 HTML 一律进源码模式,并且**绝不写进 internalHtml**,
        // 让 wangEditor 从头到尾碰不到它。
        // userChoseWysiwyg:用户已经显式点过「转为可视编辑」并接受了简化,
        // 这时别再把他弹回源码模式(转换后的内容可能仍含 div,会反复触发)。
        if (v && this.looksExternal(v) && !this.userChoseWysiwyg) {
          this.mode = 'source';
          this.internalHtml = '';
          return;
        }
        this.internalHtml = v;
      },
      immediate: true
    },
    internalHtml(newVal) {
      // 只有可视模式下的改动才回写,否则源码模式会被编辑器的过滤结果覆盖
      if (this.mode !== 'wysiwyg') return;
      const realVal = (newVal === '<p><br></p>') ? '' : newVal;
      if (realVal !== this.value) {
        this.lastEmit = realVal;
        this.$emit('input', realVal);
      }
    }
  },
  created() {
    this.editorConfig.MENU_CONF = {
      uploadImage: {
        customBrowseAndUpload: this.chooseImage
      },
      uploadVideo: {
        customBrowseAndUpload: this.chooseVideo
      }
    };
  },
  methods: {
    onCreated(editor) {
      this.editor = Object.seal(editor);
    },
    onModeChange(next) {
      if (next === 'source') {
        // 进源码:以当前 value 为准
        this.sourceHtml = this.value || '';
        return;
      }
      if (next === 'wysiwyg') {
        this.enterWysiwyg();
      }
    },

    /**
     * 进可视编辑:先把 HTML **规整**成 wangEditor 认得的结构,再喂给它。
     *
     * 关键在这一步。直接把原文扔进去,它顶层只接受自己那套块级节点,
     * <section> 里包着的 <img> 会连着外层容器一起被丢掉 —— 图片就这么没的。
     * 先自己走一遍 DOM 把图片和文字全捞出来、各自包成 <p>,内容就能完整带过去,
     * 丢掉的只是纯装饰的容器标签。
     */
    enterWysiwyg() {
      const raw = this.value || '';
      if (!raw) {
        this.internalHtml = '';
        this.backup = null;
        return;
      }
      const normalized = this.normalizeForEditor(raw);
      this.userChoseWysiwyg = true;

      // 结构确实变了才留备份和还原入口;完全一致就别拿黄条打扰人
      if (normalized !== raw) {
        this.backup = raw;
        this.lossInfo = this.diffContent(raw, normalized);
      } else {
        this.backup = null;
        this.lossInfo = null;
      }

      this.internalHtml = normalized;
      if (normalized !== this.value) {
        this.lastEmit = normalized;
        this.$emit('input', normalized);
      }
    },

    /**
     * 把任意 HTML 规整成 wangEditor 能承载的扁平结构。
     * 递归下去只取两样东西:图片和文字 —— 这两样才是商品详情的实际内容,
     * 外层的 section/div/table 只是排版容器,编辑器本来也表达不了。
     */
    normalizeForEditor(html) {
      let doc;
      try {
        doc = new DOMParser().parseFromString(html, 'text/html');
      } catch (e) {
        return html;
      }
      const DROP = { script: 1, style: 1, iframe: 1, object: 1, embed: 1, noscript: 1 };
      const out = [];
      const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

      const walk = node => {
        const kids = node.childNodes || [];
        for (let i = 0; i < kids.length; i++) {
          const c = kids[i];
          if (c.nodeType === 3) {
            const t = (c.textContent || '').replace(/\s+/g, ' ').trim();
            if (t) out.push('<p>' + esc(t) + '</p>');
            continue;
          }
          if (c.nodeType !== 1) continue;
          const tag = (c.tagName || '').toLowerCase();
          if (DROP[tag]) continue;
          if (tag === 'img') {
            const src = c.getAttribute('src') || c.getAttribute('data-src') || '';
            if (src) {
              const alt = esc(c.getAttribute('alt') || '');
              out.push('<p><img src="' + esc(src) + '" alt="' + alt + '" style="max-width:100%;"/></p>');
            }
            continue;
          }
          if (tag === 'video') {
            const src = c.getAttribute('src') || '';
            if (src) out.push('<p><video src="' + esc(src) + '" controls></video></p>');
            continue;
          }
          if (tag === 'br') continue;
          walk(c);
        }
      };
      walk(doc.body);
      return out.join('') || '';
    },

    /** 对比规整前后,统计到底少了什么(用于告诉用户,而不是含糊说"可能有变化") */
    diffContent(before, after) {
      const imgs = h => ((h || '').match(/<img\b/gi) || []).length;
      const text = h => (h || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, '').length;
      return {
        imgLost: Math.max(imgs(before) - imgs(after), 0),
        charLost: Math.max(text(before) - text(after), 0)
      };
    },

    /** 退回转换前的原文 */
    restoreBackup() {
      if (this.backup === null) return;
      const html = this.backup;
      this.userChoseWysiwyg = false;
      this.backup = null;
      this.mode = 'source';
      this.sourceHtml = html;
      this.internalHtml = '';     // 清空编辑器,免得它继续拿着残骸往外 emit
      this.lastEmit = html;
      this.$emit('input', html);
      this.$message({ type: 'success', message: '已还原为原始 HTML' });
    },
    /** 粗判是不是外部拷来的复杂 HTML(wangEditor 自己生成的很简单,基本只有 p/img/strong 之类) */
    looksExternal(html) {
      return /<(section|table|iframe|video|svg|style)\b/i.test(html) ||
        /\sclass\s*=/i.test(html) ||
        /<div\b/i.test(html);
    },
    onSourceInput(val) {
      const v = val || '';
      this.lastEmit = v;
      this.$emit('input', v);
    },
    /** 给密集的一行 HTML 断行,方便肉眼找位置。只加换行,不动内容 */
    formatSource() {
      const html = this.sourceHtml || '';
      if (!html) return;
      this.sourceHtml = html
        .replace(/>\s*</g, '>\n<')
        .replace(/\n{2,}/g, '\n')
        .trim();
      this.lastEmit = this.sourceHtml;
      this.$emit('input', this.sourceHtml);
    },
    clearSource() {
      this.$confirm('确定清空商品详情内容吗？', '提示', { type: 'warning' }).then(() => {
        this.sourceHtml = '';
        this.internalHtml = '';
        this.lastEmit = '';
        this.$emit('input', '');
      }).catch(() => { });
    },
    // 在光标处插入文本（供外部调用，如插入 {{占位符}}）
    insertText(text) {
      if (this.editor && typeof this.editor.insertText === 'function') {
        this.editor.focus();
        this.editor.insertText(text);
      }
    },
    closeFilePicker() {
      this.show = false;
    },
    selected(e) {
      this.show = false;
      e.forEach(item => {
        if (this.type === 'image') {
          this.insertFunc(item.url, item.name, item.url);
        } else {
          this.insertFunc(item.url);
        }
      });
    },
    chooseImage(insertFn) {
      this.type = 'image';
      this.show = true;
      this.insertFunc = insertFn;
    },
    chooseVideo(insertFn) {
      this.type = 'video';
      this.show = true;
      this.insertFunc = insertFn;
    }
  },
  beforeDestroy() {
    const editor = this.editor;
    if (editor == null) return;
    editor.destroy();
  }
};
</script>

<!-- wangEditor 自带样式,不能少也不能加 scoped:工具栏图标全靠它,
     丢了这行工具栏会变成一列没样式的巨大方块 -->
<style src="@wangeditor/editor/dist/css/style.css"></style>

<style scoped>
.rich-text-wrap {
  margin-top: 10px;
}

.rt-modes {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.rt-hint {
  font-size: 12px;
  color: #909399;
  line-height: 1.5;
}

.rt-backup-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 8px;
  padding: 6px 12px;
  background: #fdf6ec;
  border: 1px solid #f5dab1;
  border-radius: 4px;
  font-size: 12px;
  color: #b88230;
  line-height: 1.5;
}

.rt-editor-box,
.rt-source-box,
.rt-preview-box {
  border: 1px solid #ccc;
  box-sizing: border-box;
}

.rt-source-box >>> .el-textarea__inner {
  border: none;
  border-radius: 0;
  font-family: Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.6;
}

.rt-source-foot {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 4px 10px;
  border-top: 1px solid #eee;
  background: #fafafa;
  font-size: 12px;
  color: #909399;
}

.rt-preview-box {
  overflow-y: auto;
  background: #f5f7fa;
  padding: 16px 0;
}

.rt-phone {
  width: 375px;
  margin: 0 auto;
  background: #fff;
  min-height: 100%;
  box-shadow: 0 0 0 1px #e4e7ed;
}

.rt-phone-empty {
  padding: 60px 0;
  text-align: center;
  color: #c0c4cc;
  font-size: 13px;
}

/* 预览内容按手机端的渲染规则来:图片撑满不溢出,长词强制换行 */
.rt-phone-body {
  padding: 0;
  font-size: 14px;
  line-height: 1.7;
  color: #333;
  word-break: break-word;
}

.rt-phone-body >>> img,
.rt-phone-body >>> video {
  max-width: 100%;
  height: auto;
  display: block;
}

.rt-phone-body >>> table {
  max-width: 100%;
}
</style>
