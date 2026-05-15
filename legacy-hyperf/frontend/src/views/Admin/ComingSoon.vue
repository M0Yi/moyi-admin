<template>
  <div class="coming-soon">
    <el-result
      icon="info"
      title="功能开发中"
      sub-title="此功能正在开发中，敬请期待..."
    >
      <template #extra>
        <el-button type="primary" @click="$router.back()">
          返回上一页
        </el-button>
      </template>
    </el-result>
  </div>
</template>

<script setup lang="ts">
// 功能开发中页面
</script>

<style lang="scss" scoped>
.coming-soon {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 400px;
}
</style>
