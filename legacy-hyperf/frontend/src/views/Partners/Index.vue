<template>
  <div class="partners-page">
    <!-- 页面头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <!-- 合作伙伴列表 -->
    <section class="partners-section">
      <div class="container">
        <!-- 加载状态 -->
        <div v-if="loading" class="loading-state">
          <el-skeleton :rows="3" animated />
        </div>

        <!-- 空状态 -->
        <div v-else-if="categories.length === 0" class="empty-state">
          <el-empty description="暂无合作伙伴" />
        </div>

        <!-- 分类和合作伙伴 -->
        <div v-else class="categories-wrapper">
          <div
            v-for="category in categories"
            :key="category.id"
            class="category-section"
          >
            <!-- 分类标题 -->
            <div class="category-header">
              <h2 class="category-title">{{ category.name }}</h2>
              <p v-if="category.description" class="category-description">
                {{ category.description }}
              </p>
            </div>

            <!-- 合作伙伴logo网格 -->
            <div v-if="category.partners.length > 0" class="partners-grid">
              <a
                v-for="partner in category.partners"
                :key="partner.id"
                :href="partner.websiteUrl || 'javascript:;'"
                :target="partner.websiteUrl ? '_blank' : ''"
                :rel="partner.websiteUrl ? 'noopener noreferrer' : ''"
                class="partner-card"
                :title="partner.name"
              >
                <div class="partner-logo-wrapper">
                  <img
                    :src="partner.logoUrl"
                    :alt="partner.name"
                    class="partner-logo"
                    @error="handleImageError"
                  />
                </div>
              </a>
            </div>

            <!-- 该分类下无合作伙伴 -->
            <div v-else class="category-empty">
              <p>暂无{{ category.name }}</p>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { partnersApi, type PartnerCategory } from '@/api/partners'

const loading = ref(false)
const categories = ref<PartnerCategory[]>([])

// 默认logo图片（当加载失败时使用）
const defaultLogo = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2YwZjBmMCIvPgogIDx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LXNpemU9IjE0IiBmaWxsPSIjOTk5Ij7ljZrlrqLmraM8L3RleHQ+Cjwvc3ZnPg=='

// 加载合作伙伴数据
const loadPartners = async () => {
  try {
    loading.value = true
    const data = await partnersApi.getList()
    categories.value = data
  } catch (error) {
    console.error('加载合作伙伴失败:', error)
    ElMessage.error('加载合作伙伴失败')
  } finally {
    loading.value = false
  }
}
// 图片加载失败处理
const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    img.src = defaultLogo
    img.onerror = null // 移除错误处理，防止无限循环
  }
}
onMounted(() => {
  loadPartners()
})
</script>

<style scoped lang="scss">
.partners-page {
  min-height: 100vh;
  background: #f5f7fa;
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

.partners-section {
  padding: 60px 0;

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }
}

.loading-state {
  padding: 60px 20px;
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
}

.categories-wrapper {
  display: flex;
  flex-direction: column;
  gap: 48px;
}

.category-section {
  background: #ffffff;
  border-radius: 12px;
  padding: 32px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
}

.category-header {
  margin-bottom: 32px;
  text-align: center;

  .category-title {
    font-size: 32px;
    font-weight: 700;
    color: #333;
    margin: 0 0 12px 0;
  }

  .category-description {
    font-size: 16px;
    color: #666;
    margin: 0;
  }
}

.partners-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 32px;
}

.partner-card {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  background: #ffffff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 32px 24px;
  transition: all 0.3s ease;
  min-height: 160px;

  &:hover {
    border-color: var(--color-primary);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    transform: translateY(-4px);
  }

  .partner-logo-wrapper {
    width: 100%;
    height: 120px;
    display: flex;
    align-items: center;
    justify-content: center;

    .partner-logo {
      max-width: 100%;
      max-height: 100%;
      width: auto;
      height: auto;
      object-fit: contain;
    }
  }
}

.category-empty {
  text-align: center;
  padding: 40px 20px;
  color: #999;
}

@media (max-width: 768px) {
  .page-header { height: 200px; }
  .partners-section { padding: 40px 0; }
  .category-section { padding: 24px 20px; }
  .category-header .category-title { font-size: 24px; }
  .category-header .category-description { font-size: 14px; }
  .partners-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 20px; }
  .partner-card { padding: 24px 16px; min-height: 120px; }
  .partner-card .partner-logo-wrapper { height: 80px; }
}
</style>
