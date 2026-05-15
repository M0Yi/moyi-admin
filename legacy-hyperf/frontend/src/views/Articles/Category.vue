<template>
  <div class="category-page">
    <!-- 页面头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <!-- 子分类导航 -->
    <section class="subcategory-section" v-if="subCategories.length > 0">
      <div class="container">
        <div class="subcategory-nav">
          <el-button
            :type="activeSubCategory === null ? 'primary' : 'default'"
            @click="filterBySubCategory(null)"
            size="small"
          >
            全部
          </el-button>
          <el-button
            v-for="sub in subCategories"
            :key="sub.id"
            :type="activeSubCategory === sub.id ? 'primary' : 'default'"
            @click="filterBySubCategory(sub.id)"
            size="small"
          >
            {{ sub.name }}
          </el-button>
        </div>
      </div>
    </section>

    <!-- 文章列表 -->
    <section class="articles-section">
      <div class="container">
        <div class="article-grid" v-if="articles.length > 0">
          <article
            v-for="article in articles"
            :key="article.id"
            class="article-card"
            @click="goToArticle(article.id)"
          >
            <div class="article-cover">
              <img
                v-if="article.cover_image && article.cover_image.trim().length > 0"
                :src="article.cover_image"
                :alt="article.title"
                @error="handleImageError"
              />
              <div v-else class="no-cover">
                <Icon name="image" :size="48" color="#ccc" />
              </div>
              <div class="article-category">
                {{ article.category?.name || '未分类' }}
              </div>
            </div>
            <div class="article-content">
              <h3 class="article-title">{{ article.title }}</h3>
              <p class="article-summary">{{ article.summary || '暂无摘要' }}</p>
              <div class="article-meta">
                <span class="meta-date">
                  <Icon name="calendar" :size="14" />
                  {{ formatDate(article.published_date) }}
                </span>
                <span class="meta-views">
                  <Icon name="eye" :size="14" />
                  {{ article.view_count || 0 }} 浏览
                </span>
              </div>
            </div>
          </article>
        </div>

        <!-- 空状态 -->
        <div v-else class="empty-state">
          <el-empty description="暂无文章" />
        </div>

        <!-- 分页 -->
        <div class="pagination-wrapper" v-if="pagination.total > 0">
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.pageSize"
            :total="pagination.total"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="handleSizeChange"
            @current-change="handlePageChange"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const articles = ref<any[]>([])
const subCategories = ref<any[]>([])
const activeSubCategory = ref<number | null>(null)

const categoryName = ref('')
const categoryDescription = ref('')
const currentCategorySlug = computed(() => route.params.category as string)
const currentCategoryId = ref<number | null>(null)

const pagination = reactive({
  page: 1,
  pageSize: 12,
  total: 0
})

// 加载分类信息
const loadCategoryInfo = async () => {
  try {
    const result = await articlesApi.getCategories('article')
    if (result && result.items) {
      // 查找当前分类
      const allCategories: any[] = []
      result.items.forEach((cat: any) => {
        allCategories.push(cat)
        if (cat.children && cat.children.length > 0) {
          cat.children.forEach((child: any) => allCategories.push(child))
        }
      })

      const category = allCategories.find(c => c.slug === currentCategorySlug.value)

      if (category) {
        categoryName.value = category.name
        categoryDescription.value = category.description || ''
        currentCategoryId.value = category.id

        // 加载子分类
        subCategories.value = category.children || []
      }
    }
  } catch (error) {
    console.error('加载分类信息失败:', error)
  }
}

// 加载文章列表
const loadArticles = async () => {
  try {
    loading.value = true
    const params: any = {
      page: pagination.page,
      per_page: pagination.pageSize
    }

    if (currentCategoryId.value) {
      params.category_id = currentCategoryId.value
    }

    if (activeSubCategory.value) {
      params.category_id = activeSubCategory.value
    }

    const result = await articlesApi.getList(params)

    if (result && result.items) {
      articles.value = result.items || []
      pagination.total = result.meta?.total || 0
    }
  } catch (error) {
    console.error('加载文章失败:', error)
    ElMessage.error('加载文章失败')
  } finally {
    loading.value = false
  }
}

// 按子分类筛选
const filterBySubCategory = (subCategoryId: number | null) => {
  activeSubCategory.value = subCategoryId
  pagination.page = 1
  loadArticles()
}

const handleImageError = (event: Event, article: any) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    img.src = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'
    img.onerror = null
  }
}

const goToArticle = (id: number) => {
  router.push(`/article/${id}`)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('zh-CN')
}

const handlePageChange = (page: number) => {
  pagination.page = page
  loadArticles()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const handleSizeChange = (size: number) => {
  pagination.pageSize = size
  pagination.page = 1
  loadArticles()
}

// 监听路由参数变化
watch(
  () => [route.params.category],
  () => {
    loadCategoryInfo()
    loadArticles()
  },
  { immediate: true }
)

onMounted(() => {
  loadCategoryInfo()
  loadArticles()
})
</script>

<style scoped lang="scss">
.category-page {
  min-height: 100vh;
  background: #f5f7fa;
}

.subcategory-section {
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
  padding: 20px 0;

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .subcategory-nav {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }
}

.articles-section {
  padding: 60px 0;

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }
}

.article-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}

.article-card {
  background: #ffffff;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  transition: all 0.3s ease;
  cursor: pointer;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
  }

  .article-cover {
    position: relative;
    height: 200px;
    overflow: hidden;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .no-cover {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
    }

    .article-category {
      position: absolute;
      top: 12px;
      left: 12px;
      background: rgba(0, 0, 0, 0.7);
      color: #ffffff;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 500;
      backdrop-filter: blur(4px);
    }
  }

  .article-content {
    padding: 20px;
  }

  .article-title {
    font-size: 18px;
    font-weight: 600;
    color: #333;
    margin: 0 0 12px 0;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .article-summary {
    font-size: 14px;
    color: #666;
    line-height: 1.6;
    margin-bottom: 16px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .article-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: #999;

    .meta-date,
    .meta-views {
      display: flex;
      align-items: center;
      gap: 4px;
    }
  }
}

.empty-state {
  padding: 60px 20px;
  text-align: center;
}

.pagination-wrapper {
  display: flex;
  justify-content: center;
  margin-top: 40px;
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

  .article-grid {
    grid-template-columns: 1fr;
  }

  .subcategory-nav {
    justify-content: center;
  }
}
</style>
