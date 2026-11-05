<template>
  <div class="diy-bargain-group" :style="outerStyle">
    <!-- 大图主推 + 两列:主题色专区头 + 本场倒计时 -->
    <div v-if="safeData.type == 2" class="zone" :style="zoneStyle">
      <div class="zone-head" v-if="safeData.show_title != 0" :style="{ background: headBg }">
        <div class="zh-row">
          <div class="zh-title"><b>{{ safeData.title || (bn + '专区') }}</b><i>邀好友 砍到底价</i></div>
          <div class="zh-more" v-if="safeData.show_more != 0">更多 <i class="el-icon-arrow-right"></i></div>
        </div>
        <div class="zh-sub" v-if="safeData.show_countdown != 0 && clock">
          本场还剩
          <template v-if="clock.d > 0"><span class="cb" :style="{ color: mainColor }">{{ clock.d }}</span>天</template>
          <span class="cb" :style="{ color: mainColor }">{{ clock.h }}</span>:<span class="cb" :style="{ color: mainColor }">{{ clock.m }}</span>:<span class="cb" :style="{ color: mainColor }">{{ clock.s }}</span>
        </div>
      </div>
      <div v-if="isSample" class="sample-tip zone-tip">示例数据：当前没有进行中的{{ bn }}活动，小程序里这一块不会显示</div>
      <div class="lc">
        <div class="lc-hero" :style="picStyle(items[0])">
          <i v-if="!items[0].pic" class="el-icon-picture-outline lc-ph"></i>
          <span v-if="cut(items[0])" class="lc-hero-rib">最多砍 ¥{{ cut(items[0]) }}</span>
          <div class="lc-hero-info">
            <div class="lc-hero-name">{{ items[0].name }}</div>
            <div class="lc-hero-row">
              <div>
                <div class="lc-hero-price"><small>底价 ¥</small><b>{{ money(items[0].floor) }}</b><s v-if="safeData.show_origin != 0">¥{{ money(items[0].start) }}</s></div>
                <div class="lc-hero-meta">
                  <template v-if="safeData.show_success != 0 && items[0].success > 0">
                    <span v-if="safeData.show_avatar != 0" class="avs"><span v-for="n in Math.min(3, items[0].success)" :key="n" class="av" :style="{ background: avColor(n) }"></span></span>
                    已有 {{ items[0].success }} 人砍成
                  </template>
                  <template v-else-if="safeData.show_success != 0">刚开始，快来抢第一个砍成</template>
                  <template v-if="safeData.show_stock != 0">{{ safeData.show_success != 0 ? ' · ' : '' }}仅剩 {{ items[0].stock }} 件</template>
                </div>
              </div>
              <div class="lc-hero-btn" :style="{ color: mainColor }">去{{ bn }}</div>
            </div>
          </div>
        </div>
        <div class="lc-grid" v-if="items.length > 1">
          <div v-for="(g, i) in items.slice(1)" :key="'c' + i" class="lc-card">
            <div class="lc-pic" :style="picStyle(g)">
              <i v-if="!g.pic" class="el-icon-picture-outline"></i>
              <span v-if="cut(g)" class="lc-rib" :style="{ background: mainColor }">砍 ¥{{ cut(g) }}</span>
            </div>
            <div class="lc-body">
              <div class="lc-name">{{ g.name }}</div>
              <div class="lc-row">
                <span class="price"><span class="sym" :style="{ color: mainColor }">¥</span><span class="floor small" :style="{ color: mainColor }">{{ money(g.floor) }}</span><span v-if="safeData.show_origin != 0" class="ori">¥{{ money(g.start) }}</span></span>
                <span class="btn small" :style="{ background: mainColor }">去砍</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="group-inner" :style="innerStyle">
      <div class="group-header" v-if="safeData.show_title != 0">
        <div class="title">{{ safeData.title || (bn + '专区') }}</div>
        <div class="more" v-if="safeData.show_more != 0">更多 <i class="el-icon-arrow-right"></i></div>
      </div>

      <!-- 没有进行中的活动:C 端整块不显示,这里画示例并说明,免得装修时以为组件坏了 -->
      <div v-if="isSample" class="sample-tip">示例数据：当前没有进行中的{{ bn }}活动，小程序里这一块不会显示</div>

      <!-- 列表:左图右文,和砍价专区页同一套样式 -->
      <div v-if="safeData.type == 0" class="list-layout">
        <div v-for="(g, i) in items" :key="'l' + i" class="list-item">
          <div class="pic" :style="picStyle(g)"><i v-if="!g.pic" class="el-icon-picture-outline"></i></div>
          <div class="info">
            <div class="name">{{ g.name }}</div>
            <div class="price">
              <span class="floor-label" :style="{ color: mainColor }">底价</span>
              <span class="sym" :style="{ color: mainColor }">¥</span>
              <span class="floor" :style="{ color: mainColor }">{{ g.floor }}</span>
              <span v-if="safeData.show_origin != 0" class="ori">¥{{ g.start }}</span>
            </div>
            <div class="foot">
              <div class="meta">
                <span v-if="safeData.show_success != 0 && g.success > 0">已有 {{ g.success }} 人砍成</span>
                <span v-if="safeData.show_stock != 0">仅剩 {{ g.stock }} 件</span>
              </div>
              <div class="btn" :style="{ background: mainColor }">去{{ bn }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- 两列网格 -->
      <div v-if="safeData.type == 1" class="grid-layout">
        <div v-for="(g, i) in items" :key="'g' + i" class="grid-item">
          <div class="pic square" :style="picStyle(g)">
            <i v-if="!g.pic" class="el-icon-picture-outline"></i>
            <span v-if="safeData.show_success != 0 && g.success > 0" class="badge">{{ g.success }}人砍成</span>
          </div>
          <div class="g-info">
            <div class="name">{{ g.name }}</div>
            <div class="price">
              <span class="floor-label" :style="{ color: mainColor }">底价</span>
              <span class="sym" :style="{ color: mainColor }">¥</span>
              <span class="floor small" :style="{ color: mainColor }">{{ g.floor }}</span>
            </div>
            <div class="g-foot">
              <span v-if="safeData.show_origin != 0" class="ori">¥{{ g.start }}</span>
              <span class="btn small" :style="{ background: mainColor }">去{{ bn }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { BARGAIN_GROUP_DEFAULT } from './BargainGroupConfig'

// 没有进行中的活动时的示例(组件给所有商城用,别写具体某家的商品)
const SAMPLE = [
  { name: '示例商品名称，超出两行自动省略', start: '199.00', floor: '99.00', success: 12, stock: 30, pic: '', end: 0 },
  { name: '示例商品名称', start: '128.00', floor: '68.00', success: 5, stock: 18, pic: '' },
  { name: '示例商品名称', start: '89.00', floor: '49.00', success: 0, stock: 8, pic: '' },
  { name: '示例商品名称', start: '299.00', floor: '159.00', success: 21, stock: 50, pic: '' }
]

export default {
  name: 'BargainGroup',
  props: {
    data: {
      type: Object,
      default: () => ({})
    }
  },
  data() {
    return {
      real: [],
      loaded: false
    }
  },
  computed: {
    safeData() {
      return Object.assign({}, BARGAIN_GROUP_DEFAULT, this.data || {})
    },
    bn() {
      const w = this.$noun ? this.$noun('bargain.name') : ''
      return w && w !== 'bargain.name' ? w : '砍价'
    },
    // 不选强调色时,装修预览拿不到小程序的主题色,用砍价专区常用的红色示意
    mainColor() {
      return this.safeData.main_color || '#e2231a'
    },
    limit() {
      return Math.max(1, parseInt(this.safeData.limit) || 4)
    },
    isSample() {
      return this.loaded && this.real.length === 0
    },
    items() {
      const src = this.real.length ? this.real : SAMPLE
      const out = []
      for (let i = 0; i < this.limit && (this.real.length ? i < src.length : true); i++) {
        out.push(src[i % src.length])
      }
      return out
    },
    outerStyle() {
      return { padding: this.safeData.is_padding == 1 ? '0 10px' : '0', marginBottom: '10px' }
    },
    zoneStyle() {
      return {
        borderRadius: this.safeData.is_circle == 1 ? '16px' : '0',
        backgroundColor: this.safeData.background,
        overflow: 'hidden'
      }
    },
    headBg() {
      return 'linear-gradient(120deg, ' + this.mainColor + ', ' + this.mainColor + 'b3)'
    },
    // 预览里的「本场还剩」:取最早结束的活动,示例数据按 2 天半算;预览不跳秒
    clock() {
      const now = Math.floor(Date.now() / 1000)
      const ends = this.real.length ? this.real.map(g => g.end).filter(t => t > now) : [now + 2 * 86400 + 13 * 3600 + 45 * 60 + 9]
      if (!ends.length) return null
      const sec = Math.min(...ends) - now
      const p = n => (n < 10 ? '0' + n : '' + n)
      return { d: Math.floor(sec / 86400), h: p(Math.floor(sec % 86400 / 3600)), m: p(Math.floor(sec % 3600 / 60)), s: p(sec % 60) }
    },
    innerStyle() {
      return {
        borderRadius: this.safeData.is_circle == 1 ? '10px' : '0',
        backgroundColor: this.safeData.background,
        padding: '10px',
        overflow: 'hidden'
      }
    }
  },
  watch: {
    limit() {
      this.fetch()
    }
  },
  created() {
    this.fetch()
  },
  methods: {
    // 取正在进行的活动(phase=1)画预览,和 C 端专区同一批数据
    fetch() {
      this.$request({
        url: 'plugin/bargain/mall/goods/index',
        method: 'get',
        data: { phase: 1, page: 1, limit: this.limit }
      }).then(res => {
        this.loaded = true
        if (res.code !== 0) return
        this.real = ((res.data && res.data.list) || [])
          .filter(r => r.goods_on_sale == 1)
          .map(r => ({
            name: r.goods_name,
            start: r.start_price,
            floor: r.floor_price,
            success: r.succeeded,
            stock: r.stock,
            pic: r.goods_pic,
            // 后台列表给的是「Y-m-d H:i」,预览倒计时换成时间戳
            end: Math.floor(new Date(String(r.end_at).replace(/-/g, '/')).getTime() / 1000) || 0
          }))
      }).catch(() => {
        this.loaded = true
      })
    },
    cut(g) {
      const v = Number(g.start) - Number(g.floor)
      return v > 0 ? this.money(v) : ''
    },
    money(v) {
      const n = Number(v) || 0
      return n % 1 === 0 ? String(n) : n.toFixed(2)
    },
    avColor(i) {
      return ['#f7775a', '#5b8def', '#36b37e'][i % 3]
    },
    picStyle(g) {
      return g.pic ? { backgroundImage: 'url(' + g.pic + ')' } : {}
    }
  }
}
</script>

<style lang="scss" scoped>
.diy-bargain-group {
  width: 100%;
  box-sizing: border-box;
}

.group-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 10px;

  .title {
    font-size: 15px;
    font-weight: bold;
    color: #222;
  }

  .more {
    font-size: 12px;
    color: #999;
  }
}

.sample-tip {
  margin-bottom: 8px;
  padding: 4px 8px;
  border-radius: 4px;
  font-size: 11px;
  color: #b26b00;
  background: #fdf3e2;
}

.pic {
  flex-shrink: 0;
  border-radius: 6px;
  background: #f2f3f5 center / cover no-repeat;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c0c4cc;
  font-size: 22px;
  position: relative;
}

.name {
  font-size: 13px;
  color: #222;
  line-height: 1.4;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
}

.price {
  display: flex;
  align-items: baseline;
  margin-top: 4px;

  .floor-label {
    font-size: 11px;
    margin-right: 3px;
  }

  .sym {
    font-size: 12px;
  }

  .floor {
    font-size: 19px;
    font-weight: 700;

    &.small {
      font-size: 16px;
    }
  }
}

.ori {
  margin-left: 6px;
  font-size: 11px;
  color: #bbb;
  text-decoration: line-through;
}

.btn {
  flex-shrink: 0;
  padding: 5px 13px;
  border-radius: 14px;
  font-size: 12px;
  color: #fff;

  &.small {
    padding: 3px 9px;
    font-size: 11px;
  }
}

.list-item {
  display: flex;
  padding: 8px 0;

  & + .list-item {
    border-top: 1px solid #f3f3f3;
  }

  .pic {
    width: 96px;
    height: 96px;
  }

  .info {
    flex: 1;
    min-width: 0;
    padding-left: 10px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }

  .foot {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
  }

  .meta {
    display: flex;
    flex-direction: column;
    font-size: 11px;
    color: #999;
    line-height: 1.6;
  }
}

.grid-layout {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
}

.grid-item {
  min-width: 0;
  border-radius: 8px;
  overflow: hidden;
  background: #fafafa;

  .square {
    width: 100%;
    padding-top: 100%;
    border-radius: 0;

    i {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
    }
  }

  .badge {
    position: absolute;
    left: 6px;
    bottom: 6px;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 10px;
    color: #fff;
    background: rgba(0, 0, 0, 0.45);
  }

  .g-info {
    padding: 6px 8px 8px;
  }

  .g-foot {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 4px;

    .ori {
      margin-left: 0;
    }
  }
}
/* 大图主推 + 两列 */
.zone-head {
  position: relative;
  padding: 12px 12px 10px;
  color: #fff;
  overflow: hidden;

  &::before {
    content: '';
    position: absolute;
    right: -30px;
    top: -40px;
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
  }
}

.zh-row {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.zh-title {
  display: flex;
  align-items: center;
  gap: 7px;

  b {
    font-size: 17px;
    letter-spacing: 1px;
  }

  i {
    font-style: normal;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 9px;
    color: #7a3b00;
    background: #ffd76a;
  }
}

.zh-more {
  font-size: 12px;
  opacity: 0.9;
}

.zh-sub {
  position: relative;
  margin-top: 6px;
  display: flex;
  align-items: center;
  gap: 3px;
  font-size: 11px;

  .cb {
    min-width: 18px;
    height: 18px;
    padding: 0 3px;
    border-radius: 4px;
    background: #fff;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
  }
}

.zone-tip {
  margin: 8px 10px 0;
}

.lc {
  padding: 10px;
}

.lc-hero {
  position: relative;
  height: 160px;
  border-radius: 12px;
  overflow: hidden;
  background: #f2f3f5 center / cover no-repeat;

  &::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(0, 0, 0, 0) 35%, rgba(0, 0, 0, 0.62));
  }

  .lc-ph {
    position: absolute;
    left: 50%;
    top: 40%;
    transform: translate(-50%, -50%);
    font-size: 28px;
    color: #c0c4cc;
  }
}

.lc-hero-rib {
  position: absolute;
  left: 10px;
  top: 10px;
  z-index: 1;
  font-size: 11px;
  font-weight: 700;
  padding: 2px 9px;
  border-radius: 10px;
  color: #7a3b00;
  background: #ffd76a;
}

.lc-hero-info {
  position: absolute;
  left: 12px;
  right: 12px;
  bottom: 10px;
  z-index: 1;
  color: #fff;
}

.lc-hero-name {
  font-size: 14px;
  font-weight: 600;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.lc-hero-row {
  margin-top: 4px;
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
}

.lc-hero-price {
  display: flex;
  align-items: baseline;

  small {
    font-size: 11px;
    font-weight: 700;
  }

  b {
    font-size: 24px;
  }

  s {
    margin-left: 6px;
    font-size: 11px;
    opacity: 0.75;
  }
}

.lc-hero-meta {
  margin-top: 2px;
  display: flex;
  align-items: center;
  font-size: 10.5px;
  opacity: 0.92;

  .avs {
    display: inline-flex;
    margin-right: 5px;
  }

  .av {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 1.5px solid #fff;
    margin-left: -5px;

    &:first-child {
      margin-left: 0;
    }
  }
}

.lc-hero-btn {
  flex-shrink: 0;
  margin-left: 8px;
  padding: 5px 13px;
  border-radius: 14px;
  font-size: 12px;
  font-weight: 700;
  background: #fff;
}

.lc-grid {
  margin-top: 10px;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.lc-card {
  min-width: 0;
  border-radius: 10px;
  overflow: hidden;
  background: #faf7f5;
}

.lc-pic {
  position: relative;
  padding-top: 78%;
  background: #f2f3f5 center / cover no-repeat;

  i {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    font-size: 22px;
    color: #c0c4cc;
  }
}

.lc-rib {
  position: absolute;
  left: 6px;
  top: 6px;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 7px;
  color: #fff;
}

.lc-body {
  padding: 6px 8px 8px;
}

.lc-name {
  font-size: 12.5px;
  color: #222;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.lc-row {
  margin-top: 3px;
  display: flex;
  align-items: center;
  justify-content: space-between;

  .price {
    margin-top: 0;
  }
}
</style>
