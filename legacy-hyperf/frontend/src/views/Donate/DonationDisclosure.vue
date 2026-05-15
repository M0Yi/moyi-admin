<template>
  <div class="disclosure-page">
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <section class="content section">
      <div class="container">
        <el-empty description="页面开发中..." />
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
// 捐赠披露页面 - 开发中
</script>

<style scoped lang="scss">
.disclosure-page {
  min-height: 100vh;
  background: #f5f7fa;
}

.section {
  padding: 60px 20px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.page-header {
  position: relative;
  width: 100%;
  height: 300px;
  overflow: hidden;

  .header-bg {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
}

@media (max-width: 768px) {
  .page-header {
    height: 200px;
  }
}
</style>
