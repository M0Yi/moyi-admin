<template>
  <div class="articles-page">
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <section class="content section">
      <div class="container">
        <!-- 搜索和筛选 -->
        <el-card class="filter-card" shadow="never">
          <el-form :inline="true" :model="filterForm" @submit.prevent="handleSearch">
            <el-form-item label="搜索">
              <el-input
                v-model="filterForm.search"
                placeholder="搜索文章标题或内容"
                clearable
                @clear="handleSearch"
                @keyup.enter="handleSearch"
              >
                <template #append>
                  <el-button :icon="Search" @click="handleSearch" />
                </template>
              </el-input>
            </el-form-item>
            <el-form-item label="分类">
              <el-select v-model="filterForm.category" placeholder="全部分类" clearable @change="handleCategoryChange">
                <el-option label="全部分类" value="" />
                <el-option
                  v-for="cat in allCategories"
                  :key="cat.id"
                  :label="cat.name"
                  :value="cat.id"
                />
              </el-select>
            </el-form-item>
            <el-form-item>
              <el-button type="primary" @click="handleSearch">
                搜索
              </el-button>
              <el-button @click="handleReset">
                重置
              </el-button>
            </el-form-item>
          </el-form>
        </el-card>

        <!-- 文章列表 -->
        <div v-loading="loading" class="articles-list">
          <el-empty v-if="!loading && articles.length === 0" description="暂无文章" />

          <div v-else class="article-grid">
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
                  @error="handleImageError($event, article)"
                />
                <div v-else class="no-cover">
                  <img
                    src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png"
                    alt="默认封面"
                    class="default-cover"
                  />
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
                    {{ formatDate(article.published_at) }}
                  </span>
                  <span class="meta-views">
                    <Icon name="eye" :size="14" />
                    {{ article.view_count || 0 }} 浏览
                  </span>
                </div>
              </div>
            </article>
          </div>

          <!-- 分页 -->
          <div v-if="pagination.total > 0" class="pagination">
            <el-pagination
              v-model:current-page="pagination.page"
              v-model:page-size="pagination.pageSize"
              :total="pagination.total"
              :page-sizes="[12, 24, 48]"
              layout="total, sizes, prev, pager, next, jumper"
              @size-change="handleSizeChange"
              @current-change="handlePageChange"
            />
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { Search } from '@element-plus/icons-vue'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const articles = ref<any[]>([])
const allCategories = ref<any[]>([])

// 筛选表单
const filterForm = reactive({
  search: '',
  category: ''
})

// 分页
const pagination = reactive({
  page: 1,
  pageSize: 12,
  total: 0
})

// 格式化日期
const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN')
}

// 加载分类列表
const loadCategories = async () => {
  try {
    const result = await articlesApi.getCategories('article')
    if (result && result.items) {
      // 展平所有分类（包括子分类）
      const flat: any[] = []
      result.items.forEach((parent: any) => {
        flat.push(parent)
        if (parent.children && parent.children.length > 0) {
          parent.children.forEach((child: any) => {
            flat.push(child)
          })
        }
      })
      allCategories.value = flat
    }
  } catch (error) {
    console.error('加载分类失败:', error)
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

    // 处理搜索
    if (filterForm.search) {
      params.search = filterForm.search
    }

    // 处理分类筛选
    const categoryId = filterForm.category || route.query.category_id
    const categorySlug = route.query.category

    if (categoryId) {
      params.category_id = categoryId
    } else if (categorySlug) {
      params.category_slug = categorySlug
    }

    const result = await articlesApi.getList(params)

    if (result && result.items) {
      articles.value = result.items
      pagination.total = result.meta?.total || result.items.length
    }
  } catch (error) {
    console.error('加载文章失败:', error)
    ElMessage.error('加载文章失败')
  } finally {
    loading.value = false
  }
}

// 搜索
const handleSearch = () => {
  pagination.page = 1
  loadArticles()
}

// 重置
const handleReset = () => {
  filterForm.search = ''
  filterForm.category = ''
  pagination.page = 1
  loadArticles()
}

// 分类变化
const handleCategoryChange = () => {
  pagination.page = 1
  loadArticles()
}

// 分页大小变化
const handleSizeChange = (size: number) => {
  pagination.pageSize = size
  pagination.page = 1
  loadArticles()
}

// 当前页变化
const handlePageChange = (page: number) => {
  pagination.page = page
  loadArticles()
}

// 跳转到文章详情
const goToArticle = (id: number) => {
  router.push(`/article/${id}`)
}

// 监听路由变化
watch(
  () => route.query,
  () => {
    loadArticles()
  },
  { immediate: false }
)

onMounted(async () => {
  await loadCategories()
  await loadArticles()
})
</script>

<style scoped lang="scss">
.articles-page {
  min-height: 100vh;
  background: var(--color-bg-secondary);
}

.section {
  padding: 40px 20px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.filter-card {
  margin-bottom: 24px;
  border-radius: var(--radius-md);

  :deep(.el-button--primary) {
    background-color: var(--color-primary);
    border-color: var(--color-primary);

    &:hover,
    &:focus {
      background-color: var(--color-primary-light);
      border-color: var(--color-primary-light);
    }
  }

  :deep(.el-input__wrapper) {
    &:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 1px var(--color-primary-lighter) inset;
    }
  }

  :deep(.el-select .el-input__wrapper) {
    &:focus {
      border-color: var(--color-primary);
      box-shadow: 0 0 0 1px var(--color-primary-lighter) inset;
    }
  }

  :deep(.el-select .el-select__caret.is-reverse) {
    color: var(--color-primary);
  }

  :deep(.el-checkbox__input.is-checked .el-checkbox__inner) {
    background-color: var(--color-primary);
    border-color: var(--color-primary);
  }

  :deep(.el-checkbox__input.is-checked + .el-checkbox__label) {
    color: var(--color-primary);
  }
}

.articles-list {
  min-height: 400px;
}

.article-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 24px;
  margin-bottom: 32px;
}

.article-card {
  background: #fff;
  border-radius: var(--radius-md);
  overflow: hidden;
  box-shadow: var(--shadow-sm);
  transition: all var(--transition);
  cursor: pointer;
  border: 1px solid var(--color-border-light);

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(255, 80, 0, 0.15);
    border-color: var(--color-primary-lighter);
  }
}

.article-cover {
  position: relative;
  width: 100%;
  height: 200px;
  overflow: hidden;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .default-cover {
    width: 100%;
    height: 100%;
    object-fit: cover;
    opacity: 0.9;
  }

  .no-cover {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--color-bg-secondary) 0%, var(--color-bg-tertiary) 100%);

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      opacity: 0.8;
    }
  }

  .article-category {
    position: absolute;
    top: 12px;
    left: 12px;
    padding: 4px 12px;
    background: var(--color-primary);
    color: #fff;
    border-radius: var(--radius-sm);
    font-size: 12px;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(255, 80, 0, 0.3);
  }
}

.article-content {
  padding: 20px;
}

.article-title {
  font-size: 18px;
  font-weight: 600;
  color: var(--color-text-primary);
  margin-bottom: 12px;
  line-height: 1.4;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;

  &:hover {
    color: var(--color-primary);
  }
}

.article-summary {
  font-size: 14px;
  color: var(--color-text-secondary);
  line-height: 1.6;
  margin-bottom: 16px;
  display: -webkit-box;
  -webkit-line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.article-meta {
  display: flex;
  gap: 16px;
  font-size: 13px;
  color: var(--color-text-tertiary);

  span {
    display: flex;
    align-items: center;
    gap: 4px;
  }
}

.pagination {
  display: flex;
  justify-content: center;
  padding: 20px 0;

  :deep(.el-pagination) {
    .el-pager li {
      &.is-active {
        background-color: var(--color-primary);
        color: #fff;
      }

      &:hover {
        color: var(--color-primary);
      }
    }

    .btn-prev,
    .btn-next {
      &:hover {
        color: var(--color-primary);
      }
    }

    .el-select-dropdown__item {
      &.is-selected {
        color: var(--color-primary);
      }
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

  .article-grid {
    grid-template-columns: 1fr;
    gap: 16px;
  }
}
</style>
