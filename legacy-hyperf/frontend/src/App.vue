<template>
  <el-config-provider :locale="zhCn">
    <div id="app">
      <!-- 后台管理页面 - 不显示头部导航 -->
      <router-view v-if="isAdminRoute" />

      <!-- 前台页面 - 显示头部导航 -->
      <template v-else>
        <AppHeader />
        <main class="app-main">
          <router-view />
        </main>
        <!-- 首页使用完整版页脚，其他页面使用简易版 -->
        <Footer v-if="isHomePage" />
        <SimpleFooter v-else />
      </template>
    </div>
  </el-config-provider>
</template>

<script setup lang="ts">
import { onMounted, computed } from 'vue'
import { useRoute } from 'vue-router'
import zhCn from 'element-plus/dist/locale/zh-cn.mjs'
import AppHeader from '@/components/layout/AppHeader.vue'
import Footer from '@/components/Footer.vue'
import SimpleFooter from '@/components/SimpleFooter.vue'

console.log('App.vue setup')

const route = useRoute()

// 判断是否是后台管理页面
const isAdminRoute = computed(() => {
  return route.path.startsWith('/admin')
})

// 判断是否是首页
const isHomePage = computed(() => {
  return route.path === '/' || route.path === '/m' || route.name === 'Home' || route.name === 'MobileHome'
})

onMounted(() => {
  console.log('App mounted')
  console.log('Vue app:', import.meta.env.VITE_APP_TITLE)
})
</script>

<style lang="scss">
#app {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.app-main {
  flex: 1;
}
</style>
