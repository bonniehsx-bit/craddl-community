<template>
    <div>
        <div style="border: 1px solid #ccc; margin-top: 10px;width:750px">
            <!-- 工具栏 -->
            <Toolbar style="border-bottom: 1px solid #ccc" :editor="editor" :defaultConfig="toolbarConfig" />
            <!-- 编辑器 -->
            <Editor style="height: 750px; overflow-y: hidden;" :defaultConfig="editorConfig" v-model="html"
                @onChange="onChange" @onCreated="onCreated" />
        </div>


        <file-picker :type="type" :multiple='true' :max="10" :openDialog="show" @selected="selected">
        </file-picker>
    </div>
</template>

<script>
import { Editor, Toolbar } from '@wangeditor/editor-for-vue'

export default {
  name: 'RichText',
  components: { Editor, Toolbar },

  // 【修改点 1】添加 model 配置，实现标准 v-model
  model: {
    prop: 'content',
    event: 'update:content'
  },

  props: {
    content: {
      type: String,
      default: '',
    },
  },

  data() {
    return {
      show: false,
      editor: null,
      insertFunc: null,
      type: 'image',
      toolbarConfig: {},
      editorConfig: {
        placeholder: '请输入内容...',
      }
    }
  },
  computed: {
    html: {
      set(newVal) {
        // 【修改点 2】优化逻辑：
        // 1. 允许传值给父组件
        // 2. 如果内容是 wangeditor 的默认空标签 '<p><br></p>'，则传空字符串给父组件，保持数据干净
        if (newVal === '<p><br></p>') {
          this.$emit('update:content', '');
        } else {
          this.$emit('update:content', newVal);
        }
      },
      get() {
        return this.content ? this.content : '';
      },
    },
  },
  created() {
    this.editorConfig.MENU_CONF = {
      uploadImage: { customBrowseAndUpload: this.chooseImage },
      uploadVideo: { customBrowseAndUpload: this.chooseVideo }
    }
  },

  methods: {
    selected(e) {
      this.show = false;
      e.forEach(item => {
        // 这里加个防御性判断，防止 insertFunc 丢失
        if (this.insertFunc) {
          if (this.type == 'image') {
            this.insertFunc(item.url, item.name, item.url);
          } else {
            this.insertFunc(item.url);
          }
        }
      })
    },
    confirm() {
      this.show = false;
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
    },
    onCreated(editor) {
      this.editor = Object.seal(editor)
      // 这里其实不需要手动 setHtml，因为 Editor 组件绑定了 v-model="html"，会自动同步
      // if (this.html) {
      //     this.editor.setHtml(this.html);
      // }
    },
    onChange(editor) {
      // 可以在这里处理其他逻辑
    },
  },

  beforeDestroy() {
    const editor = this.editor
    if (editor == null) return
    editor.destroy()
  },
}
</script>
<style src="@wangeditor/editor/dist/css/style.css">

</style>
