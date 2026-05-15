<template>
  <svg :class="['stat-icon', `stat-icon-${name}`]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- 捐赠金额 - 钱币/爱心 -->
    <template v-if="name === 'amount'">
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" fill="currentColor"/>
    </template>

    <!-- 捐赠人次 - 用户 -->
    <template v-else-if="name === 'donors'">
      <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z" fill="currentColor"/>
    </template>

    <!-- 本年捐赠 - 日历/增长 -->
    <template v-else-if="name === 'growth'">
      <path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z" fill="currentColor"/>
    </template>

    <!-- 受益人数 - 握手/关怀 -->
    <template v-else-if="name === 'people'">
      <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z" fill="currentColor"/>
    </template>
  </svg>
</template>

<script setup lang="ts">
defineProps<{
  name: 'amount' | 'donors' | 'growth' | 'people'
}>()
</script>

<style scoped>
.stat-icon {
  width: 48px;
  height: 48px;
  color: #ff5000;
  display: inline-block;
}
</style>
