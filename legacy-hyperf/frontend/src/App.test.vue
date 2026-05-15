<template>
  <div id="app">
    <h1>测试页面</h1>
    <p>如果你能看到这个，说明 Vue 基础运行正常</p>
    <div class="stats">
      <div class="stat-item">
        <h2>测试1</h2>
        <p>数据1</p>
      </div>
      <div class="stat-item">
        <h2>测试2</h2>
        <p>数据2</p>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
console.log('Test page loaded')
</script>

<style scoped>
#app {
  padding: 20px;
}

.stats {
  display: flex;
  gap: 20px;
  margin-top: 20px;
}

.stat-item {
  padding: 20px;
  background: #f0f0f0;
  border-radius: 8px;
}
</style>
