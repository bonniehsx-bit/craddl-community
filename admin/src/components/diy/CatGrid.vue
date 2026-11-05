<template>
  <div class="cg-wrap" :style="{ background: d.bg_color, padding: d.is_padding == 1 ? '6px 10px' : '6px 0' }">
    <div v-if="!items.length" class="empty">分类宫格:在右侧添加分类</div>
    <div class="grid" :style="{ gridTemplateColumns: 'repeat(' + (d.columns || 3) + ', 1fr)' }">
      <div v-for="(it, i) in items" :key="i" class="cell" :class="'is-' + d.style">
        <div class="box" :style="{ background: d.bg_color2 }">
          <div class="pic">
            <img v-if="it.pic_url || it.cat_pic" :src="it.pic_url || it.cat_pic" alt="">
            <i v-else class="el-icon-picture-outline" />
          </div>
          <div class="name" :style="{ color: d.text_color }">{{ it.name || it.cat_name || '分类' }}</div>
        </div>
        <span v-if="badge(it)" class="badge" :style="{ background: d.accent_color }">{{ badge(it) }}</span>
        <span v-if="d.style === 'stamp' && markLines.length" class="mark" :style="{ color: d.sub_color, borderColor: d.sub_color }">
          <b v-for="(l, j) in markLines" :key="j">{{ l }}</b>
        </span>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'CatGrid',
  props: { data: { type: Object, default: () => ({}) }},
  computed: {
    d() { return this.data || {} },
    items() { return this.d.items || [] },
    markLines() { return String(this.d.mark_text || '').split('\n').filter(Boolean).slice(0, 2) }
  },
  methods: {
    badge(it) {
      if (it.badge) return it.badge
      if (this.d.show_count != 1) return ''
      return it.goods_count !== undefined ? it.goods_count + (this.d.count_suffix || '') : 'N' + (this.d.count_suffix || '')
    }
  }
}
</script>

<style lang="scss" scoped>
.empty { padding: 20px; text-align: center; color: #c0c4cc; font-size: 12px; }
.grid { display: grid; gap: 8px; }
.cell { position: relative; }
.box { padding: 4px; }
.pic { height: 60px; background: #f0ebe1; display: flex; align-items: center; justify-content: center; overflow: hidden; color: #c0c4cc;
  img { width: 100%; height: 100%; object-fit: cover; } }
.name { margin-top: 4px; font-size: 11px; font-weight: 700; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.badge { position: absolute; left: 8px; top: 8px; font-size: 8px; color: #fff; padding: 0 3px; border-radius: 2px; }
.is-stamp { padding: 4px; background: #fff; filter: drop-shadow(0 1px 2px rgba(0,0,0,.12));
  -webkit-mask: linear-gradient(#000 0 0) content-box, radial-gradient(circle 2.6px at 3.8px 3.8px, transparent 96%, #000) -3.8px -3.8px / 7.6px 7.6px; }
.is-card .box { border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.08); .pic { border-radius: 6px; } }
.is-circle .box { background: transparent !important; .pic { width: 56px; height: 56px; margin: 0 auto; border-radius: 50%; } }
.mark { position: absolute; right: 2px; top: 38px; width: 30px; height: 30px; border-radius: 50%; border: 1px solid;
  display: flex; flex-direction: column; align-items: center; justify-content: center; transform: rotate(-18deg);
  b { font-size: 5px; line-height: 1.1; } }
</style>
