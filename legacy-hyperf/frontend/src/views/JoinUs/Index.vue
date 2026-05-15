<template>
  <div class="join-us-page">
    <!-- 页面头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <!-- 子分类导航 -->
    <section class="subcategory-section">
      <div class="container">
        <div class="subcategory-nav">
          <el-button
            :type="activeSubCategory === null ? 'primary' : ''"
            @click="filterBySubCategory(null)"
            size="default"
            plain
            round
          >
            全部
          </el-button>
          <el-button
            v-for="sub in subCategories"
            :key="sub.id"
            :type="activeSubCategory === sub.id ? 'primary' : ''"
            @click="filterBySubCategory(sub.id)"
            size="default"
            plain
            round
          >
            {{ sub.name }}
          </el-button>
        </div>
      </div>
    </section>

    <!-- 文章列表 -->
    <section class="articles-section">
      <div class="container">
        <!-- 单页文章内容视图 -->
        <div v-if="isSingleArticleView && singleArticleContent" class="single-article-view">
          <article class="single-article-content">
            <h1 class="single-article-title">{{ singleArticleContent.title }}</h1>
            <div class="single-article-meta" v-if="singleArticleContent.published_at">
              <span class="meta-date">
                <Icon name="calendar" :size="14" />
                {{ formatDate(singleArticleContent.published_at) }}
              </span>
              <span class="meta-views">
                <Icon name="eye" :size="14" />
                {{ singleArticleContent.view_count || 0 }} 浏览
              </span>
            </div>
            <div class="single-article-body" v-html="singleArticleContent.content"></div>
          </article>
        </div>

        <!-- 文章列表视图 -->
        <div v-else>
          <div class="article-grid" v-if="articles.length > 0">
            <article
              v-for="article in articles"
              :key="article.id"
              class="article-card"
              @click="goToArticle(article.id)"
            >
              <div class="article-cover">
                <img
                  :src="article.cover_image || defaultCoverImage"
                  :alt="article.title"
                  @error="handleImageError"
                />
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
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage } from 'element-plus'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'

const router = useRouter()
const route = useRoute()

const loading = ref(false)
const articles = ref<any[]>([])
const subCategories = ref<any[]>([])
const activeSubCategory = ref<number | null>(null)
const currentCategoryId = ref<number | null>(null)

// 单页文章分类的文章内容
const singleArticleContent = ref<any>(null)
const isSingleArticleView = ref(false)

// 默认封面图片
const defaultCoverImage = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'

const pagination = reactive({
  page: 1,
  pageSize: 12,
  total: 0
})

// 加载分类信息
const loadCategoryInfo = async (): Promise<void> => {
  try {
    const result = await articlesApi.getCategories('article')
    if (result && result.items) {
      // 尝试多种方式查找"加入我们"分类
      const joinUsCategory = result.items.find((cat: any) =>
        cat.slug === 'join_us' ||
        cat.slug === 'jianhui' ||
        cat.name.includes('加入')
      )

      if (joinUsCategory) {
        currentCategoryId.value = joinUsCategory.id
        subCategories.value = joinUsCategory.children || []
        console.log('加载到加入我们子分类:', subCategories.value.length, '个')
        // 加载分类信息后，检查是否需要根据路由设置子分类
        await setActiveSubCategoryFromRoute()
      } else {
        console.warn('未找到加入我们分类')
      }
    }
  } catch (error) {
    console.error('加载分类信息失败:', error)
  }
}

// 根据路由设置活动子分类
const setActiveSubCategoryFromRoute = async () => {
  const slug = route.params.slug as string
  console.log('setActiveSubCategoryFromRoute called, slug:', slug)
  console.log('subCategories:', subCategories.value)

  if (slug && subCategories.value.length > 0) {
    // 根据slug查找对应的子分类
    const matchedSubCategory = subCategories.value.find((cat: any) => cat.slug === slug)
    console.log('matchedSubCategory:', matchedSubCategory)

    if (matchedSubCategory) {
      activeSubCategory.value = matchedSubCategory.id
      console.log('自动选中子分类:', matchedSubCategory.name)
      console.log('is_single_article:', matchedSubCategory.is_single_article, 'type:', typeof matchedSubCategory.is_single_article)
      console.log('linked_article_id:', matchedSubCategory.linked_article_id, 'type:', typeof matchedSubCategory.linked_article_id)

      // 检查是否为单页文章分类（兼容多种布尔值表示）
      const isSingleArticle = matchedSubCategory.is_single_article === true ||
                             matchedSubCategory.is_single_article === 'true' ||
                             matchedSubCategory.is_single_article === 1 ||
                             matchedSubCategory.is_single_article === '1'

      if (isSingleArticle && matchedSubCategory.linked_article_id) {
        console.log('检测到单页文章分类，加载文章:', matchedSubCategory.linked_article_id)
        isSingleArticleView.value = true
        await loadSingleArticle(matchedSubCategory.linked_article_id)
      } else {
        console.log('不是单页文章分类或缺少linked_article_id')
      }
    } else {
      console.log('未找到匹配的子分类，slug:', slug)
    }
  }
}

// 加载文章列表
const loadArticles = async () => {
  try {
    loading.value = true

    if (activeSubCategory.value) {
      // 选择了特定子分类，只显示该子分类的文章
      const params: any = {
        page: pagination.page,
        per_page: pagination.pageSize,
        category_id: activeSubCategory.value
      }
      const result = await articlesApi.getList(params)

      if (result && result.items) {
        articles.value = result.items || []
        pagination.total = result.meta?.total || 0
      }
    } else {
      // 选择"全部"时，获取所有子分类的文章
      if (subCategories.value.length > 0) {
        // 方案：为每个子分类分别获取文章，然后合并
        const allArticles: any[] = []
        const promises = subCategories.value.map(async (subCategory: any) => {
          try {
            const result = await articlesApi.getList({
              page: 1,
              per_page: 100, // 获取更多文章以确保覆盖
              category_id: subCategory.id
            })
            return result.items || []
          } catch (error) {
            console.error(`加载分类 ${subCategory.name} 的文章失败:`, error)
            return []
          }
        })

        const results = await Promise.all(promises)
        results.forEach(items => {
          allArticles.push(...items)
        })

        // 按发布日期排序
        allArticles.sort((a, b) => {
          const dateA = new Date(a.published_at || a.created_at).getTime()
          const dateB = new Date(b.published_at || b.created_at).getTime()
          return dateB - dateA
        })

        // 前端分页
        pagination.total = allArticles.length
        const start = (pagination.page - 1) * pagination.pageSize
        const end = start + pagination.pageSize
        articles.value = allArticles.slice(start, end)
      } else if (currentCategoryId.value) {
        // 如果没有子分类，使用父分类ID
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
      } else {
        articles.value = []
        pagination.total = 0
      }
    }
  } catch (error) {
    console.error('加载文章失败:', error)
    ElMessage.error('加载文章失败')
  } finally {
    loading.value = false
  }
}

// 按子分类筛选
const filterBySubCategory = async (subCategoryId: number | null) => {
  activeSubCategory.value = subCategoryId
  pagination.page = 1

  // 检查是否为单页文章分类
  if (subCategoryId) {
    const category = subCategories.value.find((cat: any) => cat.id === subCategoryId)
    if (category) {
      const isSingleArticle = category.is_single_article === true ||
                             category.is_single_article === 'true' ||
                             category.is_single_article === 1 ||
                             category.is_single_article === '1'

      if (isSingleArticle && category.linked_article_id) {
        console.log('点击单页文章分类，加载文章:', category.linked_article_id)
        // 加载单页文章内容
        isSingleArticleView.value = true
        await loadSingleArticle(category.linked_article_id)
        return
      }
    }
  }
  // 不是单页文章分类，加载文章列表
  console.log('不是单页文章分类，加载文章列表')
  isSingleArticleView.value = false
  singleArticleContent.value = null
  loadArticles()
}

// 加载单页文章内容
const loadSingleArticle = async (articleId: number) => {
  try {
    loading.value = true
    const article = await articlesApi.getDetail(articleId)
    singleArticleContent.value = article
  } catch (error) {
    console.error('加载单页文章失败:', error)
    ElMessage.error('加载文章内容失败')
  } finally {
    loading.value = false
  }
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement

  // 如果已经处理过，不再处理
  if (img.dataset.handled === 'true') {
    return
  }

  // 标记为已处理
  img.dataset.handled = 'true'

  // 设置默认图片
  img.src = defaultCoverImage
  img.onerror = null // 移除错误处理，防止无限循环
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

// 监听路由变化，当从/join-us导航到/join-us/:slug时更新子分类
watch(
  () => route.params.slug,
  async (newSlug, oldSlug) => {
    if (newSlug !== oldSlug && subCategories.value.length > 0) {
      await setActiveSubCategoryFromRoute()
      pagination.page = 1
      if (!isSingleArticleView.value) {
        loadArticles()
      }
      // 滚动到顶部
      window.scrollTo({ top: 0, behavior: 'smooth' })
    }
  }
)

onMounted(async () => {
  console.log('JoinUs page mounted, route:', route.path)
  await loadCategoryInfo()

  console.log('loadCategoryInfo completed, isSingleArticleView:', isSingleArticleView.value)

  // 如果不是单页文章视图，才加载文章列表
  if (!isSingleArticleView.value) {
    console.log('不是单页文章视图，加载文章列表')
    loadArticles()
  } else {
    console.log('是单页文章视图，不加载文章列表')
  }
})
</script>

<style scoped lang="scss">
.join-us-page {
  min-height: 100vh;
  background: #f5f7fa;
}

.subcategory-section {
  background: #ffffff;
  border-bottom: 1px solid #e5e7eb;
  padding: 32px 0 24px;

  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .subcategory-nav {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    justify-content: center;
    align-items: center;
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

// 单页文章视图样式
.single-article-view {
  background: #ffffff;
  border-radius: 12px;
  padding: 40px;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);

  .single-article-content {
    max-width: 900px;
    margin: 0 auto;
  }

  .single-article-title {
    font-size: 36px;
    font-weight: 700;
    color: #333;
    margin: 0 0 24px 0;
    line-height: 1.4;
  }

  .single-article-meta {
    display: flex;
    gap: 20px;
    padding: 16px 0;
    margin-bottom: 32px;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    color: #999;

    span {
      display: flex;
      align-items: center;
      gap: 6px;
    }
  }

  .single-article-body {
    font-size: 16px;
    line-height: 1.8;
    color: #333;

    // 文章内容样式
    :deep(h1),
    :deep(h2),
    :deep(h3),
    :deep(h4),
    :deep(h5),
    :deep(h6) {
      font-weight: 600;
      margin: 24px 0 16px 0;
      color: #222;
    }

    :deep(h2) {
      font-size: 28px;
    }

    :deep(h3) {
      font-size: 24px;
    }

    :deep(p) {
      margin: 0 0 16px 0;
    }

    :deep(img) {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      margin: 24px 0;
    }

    :deep(a) {
      color: var(--color-primary);
      text-decoration: none;

      &:hover {
        text-decoration: underline;
      }
    }

    :deep(ul),
    :deep(ol) {
      margin: 16px 0;
      padding-left: 24px;
    }

    :deep(li) {
      margin-bottom: 8px;
    }

    :deep(blockquote) {
      margin: 24px 0;
      padding: 16px 24px;
      background: #f9fafb;
      border-left: 4px solid var(--color-primary);
      color: #555;
    }

    :deep(table) {
      width: 100%;
      margin: 24px 0;
      border-collapse: collapse;

      th,
      td {
        padding: 12px;
        border: 1px solid #e5e7eb;
        text-align: left;
      }

      th {
        background: #f9fafb;
        font-weight: 600;
      }
    }
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

  // 移动端单页文章样式
  .single-article-view {
    padding: 24px 20px;

    .single-article-title {
      font-size: 28px;
    }

    .single-article-body {
      font-size: 15px;

      :deep(h2) {
        font-size: 24px;
      }

      :deep(h3) {
        font-size: 20px;
      }
    }
  }
}
</style>
