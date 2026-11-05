<template>
  <el-avatar :src="src" fit="cover">
  </el-avatar>
</template>
<script>
  export default {
    name: "Avatar",
    props: {
      src: {
        type: String,
        required: true
      }
    },

  };
</script>

<style lang="scss" scoped>

</style>
