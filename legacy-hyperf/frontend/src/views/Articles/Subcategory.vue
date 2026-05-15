<template>
  <div class="subcategory-page">
    <!-- 页面头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
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
              <p class="article-summary">{{ article.summary || article.description || '暂无摘要' }}</p>
              <div class="article-meta">
                <span class="meta-date">
                  <Icon name="calendar" :size="14" />
                  {{ formatDate(article.published_date || article.published_at) }}
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
import { ref, reactive, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const articles = ref<any[]>([])
const categoryName = ref('')
const categoryDescription = ref('')
const currentCategoryId = ref<number | null>(null)
const parentCategory = ref<any>(null)

const pagination = reactive({
  page: 1,
  pageSize: 12,
  total: 0
})

// 根据当前slug判断父级路径
const parentPath = computed(() => {
  const slug = route.params.slug as string
  const aboutSlugs = ['council', 'who_we_are', 'basic_info', 'mission_vision', 'milestones', 'our_team', 'media_coverage_about']
  if (aboutSlugs.includes(slug)) return '/about'

  const projectSlugs = ['project_info', 'life_story_of_good_doer', 'project_progress', 'project_effect', 'funding_object', 'executing_agency', 'activity-announcement']
  if (projectSlugs.includes(slug)) return '/articles'

  const disclosureSlugs = ['financial_disclosure', 'mechanism_dynamics', 'institutional_annual_report', 'audit_report', 'donation_history', 'expenditure_history', 'rules_and_regulations', 'donor']
  if (disclosureSlugs.includes(slug)) return '/disclosure'

  const joinSlugs = ['participate', 'work']
  if (joinSlugs.includes(slug)) return '/join-us'

  const storySlugs = ['inspirational', 'kindness']
  if (storySlugs.includes(slug)) return '/life-stories'

  return '/articles'
})

// 加载分类信息
const loadCategoryInfo = async () => {
  try {
    const slug = route.params.slug as string

    const articleResult = await articlesApi.getCategories('article')
    const storyResult = await articlesApi.getCategories('story')

    const allCategories = [...(articleResult?.items || []), ...(storyResult?.items || [])]

    let currentCategory = null
    let parent = null

    for (const mainCat of allCategories) {
      if (mainCat.children && mainCat.children.length > 0) {
        const found = mainCat.children.find((child: any) => child.slug === slug)
        if (found) {
          currentCategory = found
          parent = mainCat
          break
        }
      }
    }

    if (currentCategory) {
      categoryName.value = currentCategory.name
      categoryDescription.value = currentCategory.description || ''
      currentCategoryId.value = currentCategory.id
      parentCategory.value = parent
    }
  } catch (error) {
    console.error('加载分类信息失败:', error)
  }
}

// 加载文章列表
const loadArticles = async () => {
  if (!currentCategoryId.value) return

  try {
    loading.value = true
    const params: any = {
      page: pagination.page,
      per_page: pagination.pageSize,
      category_id: currentCategoryId.value
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

const handleImageError = (event: Event) => {
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
  if (!date) return ''
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

onMounted(() => {
  loadCategoryInfo()
  loadArticles()
})
</script>

<style scoped lang="scss">
.subcategory-page {
  min-height: 100vh;
  background: #f5f7fa;
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
}
</style>
