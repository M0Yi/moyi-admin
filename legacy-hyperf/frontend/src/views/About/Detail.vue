<template>
  <div class="about-page">
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <section class="content-section">
      <div class="container">
        <div class="about-content" v-html="pageContent"></div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const pageContent = ref('')

const pageTitle = computed(() => {
  const titles: Record<string, string> = {
    'council': '理事会',
    'organization': '组织架构',
    'audit': '章程审计',
    'finance': '财务公开'
  }
  return titles[route.params.slug as string] || '详情'
})

const loadContent = async () => {
  const slug = route.params.slug as string
  pageContent.value = `
    <div class="about-section">
      <h2>${pageTitle.value}</h2>
      <p>内容正在建设中，敬请期待...</p>
    </div>
  `
}

onMounted(() => {
  loadContent()
})
</script>

<style scoped src="./Intro.vue?vue&type=style&index=0&scoped=true" lang="scss"></style>
