<template>
  <div class="about-page">
    <!-- 页面头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <!-- 内容区域 -->
    <section class="content-section">
      <div class="container">
        <div class="about-content" v-html="pageContent"></div>
      </div>
    </section>

    <!-- 加载状态 -->
    <div v-if="loading" class="loading-container">
      <el-skeleton :rows="10" animated />
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { request } from '@/api/index'

const loading = ref(true)
const pageContent = ref('')

// 加载页面内容
const loadContent = async () => {
  try {
    loading.value = true
    // TODO: 从API加载页面内容
    // const data = await request.get('/api/pages/about-intro')
    // pageContent.value = data.content || ''

    // 临时静态内容
    pageContent.value = `
      <div class="about-section">
        <h2>建辉慈善基金会简介</h2>
        <p>深圳市建辉慈善基金会（原名"建辉慈善基金"）是经深圳市民政局批准成立的非公募基金会，成立于2016年。基金会以"致敬行善者，让行善者更开心"为使命，致力于发现和讲述平凡人的道德故事，推动社会道德建设和精神文明进步。</p>

        <h3>我们的使命</h3>
        <p>发现并讲述那些默默奉献的行善者故事，传播正能量，弘扬社会主义核心价值观，让更多人感受到善良的力量，让行善者得到应有的尊重和认可。</p>

        <h3>我们的愿景</h3>
        <p>构建一个人人向善的社会，让善良成为一种习惯，让奉献成为一种风尚，让每一个行善者都能被看见、被尊重、被学习。</p>

        <h3>核心价值观</h3>
        <ul>
          <li><strong>尊重</strong>：尊重每一位行善者，尊重每一份善心</li>
          <li><strong>真诚</strong>：真实记录，真诚讲述</li>
          <li><strong>奉献</strong>：为公共利益不懈努力</li>
          <li><strong>创新</strong>：不断创新公益模式和传播方式</li>
        </ul>
      </div>
    `
  } catch (error) {
    console.error('加载页面内容失败:', error)
  } finally {
    loading.value = false
}
onMounted(() => {
  loadContent()
})
</script>

<style scoped lang="scss">
.about-page {
  min-height: 100vh;
  background: #f5f7fa;
}
    }

    .separator {
      color: rgba(255, 255, 255, 0.6);
    }

    .current {
      color: #ffffff;
      font-weight: 500;
}
  .page-title {
    font-size: 48px;
    font-weight: 700;
    margin: 0;
}
.content-section {
  padding: 60px 0;

  .container {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px;
}
.about-content {
  background: #ffffff;
  padding: 40px;
  border-radius: 12px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  line-height: 1.8;
  color: #333;

  :deep(h2) {
    font-size: 28px;
    color: var(--color-primary);
    margin-top: 32px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;

    &:first-child {
      margin-top: 0;
}
  :deep(h3) {
    font-size: 22px;
    color: #333;
    margin-top: 24px;
    margin-bottom: 12px;
  }

  :deep(p) {
    margin-bottom: 16px;
    text-align: justify;
  }

  :deep(ul) {
    margin: 16px 0;
    padding-left: 24px;

    li {
      margin-bottom: 8px;
}
  :deep(strong) {
    color: var(--color-primary);
}
.loading-container {
  padding: 100px 20px;
  text-align: center;
}

  .content-section {
    padding: 40px 0;
  }

  .about-content {
    padding: 24px;
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
