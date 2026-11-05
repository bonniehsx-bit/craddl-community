<template>
  <div class="flex-y-center flex-x-center">
    <el-image :preview-src-list="[src]" fit="cover" style="border-radius: 5px;" :src="src" :style="{ width: width + 'px', height: height + 'px' }"></el-image>
  </div>
</template>

<script>
export default {
  name: 'ImageView',
  props: {
    src: {
      type: String,
      default: '',
    },
    width: {
      type: Number,
      default: 25,
    },
    height: {
      type: Number,
      default: 25,
    }

  },
  data: () => ({}),
  computed: {},
  methods: {},
  watch: {},
}

</script>

<style scoped>
</style>
