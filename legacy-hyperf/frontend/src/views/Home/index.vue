<template>
  <div class="home-page">
    <!-- ==================== Hero Banner ==================== -->
    <section class="hero-section">
      <!-- Carousel -->
      <div class="hero-carousel" v-if="slides.length > 0">
        <el-carousel height="520px" :interval="5000" arrow="hover">
          <el-carousel-item v-for="slide in slides" :key="slide.id">
            <div
              class="carousel-slide"
              :style="{
                backgroundImage: slide.image ? `url(${slide.image})` : 'none',
                background: slide.image ? 'cover center no-repeat' : `linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%)`
              }"
              @click="handleSlideClick(slide)"
            ></div>
          </el-carousel-item>
        </el-carousel>
      </div>

      <!-- Default hero when no slides -->
      <div v-else class="hero-default">
        <div class="hero-default-bg"></div>
      </div>

      <!-- Stats Overlay -->
      <div class="hero-stats-bar">
        <div class="stats-inner">
          <div class="statistics">
            <div class="stat-block" style="border-right: 1px solid #e5e7eb;">
              <div class="stat-text">
                <div class="stat-title">历年累计捐赠总额(元)</div>
                <div class="stat-date">数据截至：{{ formatDate(statsDate) }}</div>
              </div>
              <div class="stat-value">{{ formatAmount(stats?.historical_total?.amount || 1583775789.69) }}</div>
            </div>
            <div class="stat-block">
              <div class="stat-text">
                <div class="stat-title">本年捐赠总额(元)</div>
                <div class="stat-date">数据截至：{{ formatDate(statsDate2) }}</div>
              </div>
              <div class="stat-value">{{ formatAmount(stats?.current_year?.amount || 47489886.11) }}</div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- ==================== News Center ==================== -->
    <section class="section news-section">
      <div class="section-container">
        <div class="section-head">
          <h2 class="section-title">新闻中心</h2>
          <router-link to="/projects" class="section-more-link">了解更多 &rarr;</router-link>
        </div>

        <!-- Tabs -->
        <div class="news-tabs" v-if="newsTabs.length > 0">
          <button
            v-for="tab in newsTabs"
            :key="tab.id"
            class="news-tab"
            :class="{ active: activeNewsTab === tab.id }"
            @click="switchNewsTab(tab)"
          >
            {{ tab.name }}
          </button>
        </div>

        <div class="news-content" v-if="currentNewsList.length > 0">
          <!-- Featured article -->
          <div class="news-featured" @click="$router.push(`/article/${currentNewsList[0].id}`)">
            <div class="news-featured-img">
              <img
                :src="currentNewsList[0].cover_image || defaultCoverImage"
                :alt="currentNewsList[0].title"
                @error="handleImageError"
              />
            </div>
            <div class="news-featured-info">
              <h3 class="news-featured-title">{{ currentNewsList[0].title }}</h3>
              <p class="news-featured-desc">{{ truncateText(currentNewsList[0].summary || currentNewsList[0].description || '', 80) }}</p>
              <span class="news-featured-date">{{ formatDate(currentNewsList[0].published_at || currentNewsList[0].published_date) }}</span>
            </div>
          </div>

          <!-- Article list -->
          <div class="news-list">
            <div
              v-for="item in currentNewsList.slice(1)"
              :key="item.id"
              class="news-item"
              @click="$router.push(`/article/${item.id}`)"
            >
              <span class="news-tag">【{{ item.category?.name || '项目动态' }}】</span>
              <span class="news-title">{{ item.title }}</span>
              <span class="news-date">{{ formatDate(item.published_at || item.published_date) }}</span>
            </div>
          </div>
        </div>

        <div v-else class="empty-state">
          <p>暂无新闻信息</p>
        </div>
      </div>
    </section>

    <!-- ==================== Stories Section ==================== -->
    <section class="section stories-section">
      <div class="section-container">
        <div class="section-head">
          <h2 class="section-title">行善者故事</h2>
        </div>

        <div class="stories-grid" v-if="heroStories.length > 0">
          <div
            v-for="story in heroStories"
            :key="story.id"
            class="story-card"
            @click="$router.push(`/article/${story.id}`)"
          >
            <div class="story-thumb">
              <img
                :src="story.cover_image || defaultCoverImage"
                :alt="story.title"
                @error="handleImageError"
              />
            </div>
            <h4 class="story-title">{{ story.title }}</h4>
            <p class="story-summary">{{ getHeroStoryDescription(story) }}</p>
          </div>
        </div>

        <div v-else class="empty-state">
          <p>暂无故事信息</p>
        </div>

        <div class="section-more">
          <router-link to="/stories">查看更多行善者故事 &rarr;</router-link>
        </div>
      </div>
    </section>

    <!-- ==================== Information Disclosure ==================== -->
    <section class="section disclosure-section">
      <div class="section-container">
        <div class="section-head">
          <h2 class="section-title">信息公开</h2>
          <router-link to="/disclosure" class="section-more-link">了解更多 &rarr;</router-link>
        </div>

        <div class="disclosure-grid" v-if="disclosureItems.length > 0">
          <div
            v-for="item in disclosureItems"
            :key="item.id"
            class="disclosure-card"
            @click="item.hasArticle && $router.push(`/article/${item.article.id}`)"
          >
            <img
              :src="(item.hasArticle && item.article.cover_image) || defaultCoverImage"
              :alt="item.hasArticle ? item.article.title : item.categoryName"
              class="disclosure-cover"
              @error="handleImageError"
            />
          </div>
        </div>
      </div>
    </section>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAppStore } from '@/stores/app'
import { useProjectStore } from '@/stores/project'
import { statsApi } from '@/api/stats'
import { articlesApi } from '@/api/articles'
import { formatAmount, truncateText } from '@/utils/format'
import type { StatsOverview, Article } from '@/types'

const router = useRouter()
const appStore = useAppStore()
const projectStore = useProjectStore()

const stats = ref<StatsOverview>()
const statsDate = ref('2025-12-31')
const statsDate2 = ref('2025-12-31')
const slides = computed(() => appStore.slides)
const latestNews = ref<Article[]>([])
const allHeroArticles = ref<any[]>([])
const disclosureCategories = ref<any[]>([])
const disclosureArticles = ref<any[]>([])

// News tabs
const newsTabs = ref<any[]>([{ id: 0, name: '最新动态', category_id: 0 }])
const activeNewsTab = ref(0)
const newsTabData = ref<Record<number, Article[]>>({ 0: [] })

const currentNewsList = computed(() => newsTabData.value[activeNewsTab.value] || [])

const defaultCoverImage = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'

// Featured projects
const featuredProjects = computed(() => projectStore.featuredProjects)

const getProjectStatusLabel = (status: string) => {
  const map: Record<string, string> = {
    active: '进行中',
    completed: '已完成',
    upcoming: '即将开始',
    paused: '已暂停'
  }
  return map[status] || status
}

// Hero stories
const heroStories = computed(() => allHeroArticles.value.slice(0, 6))

const getHeroStoryDescription = (story?: any) => {
  if (!story) return ''
  const candidate = [
    story.summary,
    story.description,
    story.content?.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim()
  ].find((item) => typeof item === 'string' && item.trim().length > 0)
  return candidate ? truncateText(candidate, 60) : ''
}

// Disclosure
const disclosureConfig: Record<string, { icon: string; color: string }> = {
  financial_disclosure: { icon: 'financial_disclosure', color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)' },
  mechanism_dynamics: { icon: 'mechanism_dynamics', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' },
  institutional_annual_report: { icon: 'institutional_annual_report', color: 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)' },
  audit_report: { icon: 'audit_report', color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)' },
  default: { icon: 'default', color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' }
}

const disclosureItems = computed(() => {
  return disclosureCategories.value.map(cat => {
    const article = disclosureArticles.value.find(art => art.category && art.category.id === cat.id)
    return {
      id: cat.id,
      categoryId: cat.id,
      categorySlug: cat.slug,
      categoryName: cat.name,
      article: article || null,
      hasArticle: !!article
    }
  })
})

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN')
}

const switchNewsTab = async (tab: any) => {
  activeNewsTab.value = tab.id
  if (!newsTabData.value[tab.id]) {
    try {
      const result = await articlesApi.getList({ page: 1, per_page: 8, category_id: tab.category_id || undefined })
      newsTabData.value[tab.id] = result.items || []
    } catch {
      newsTabData.value[tab.id] = []
    }
  }
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    img.src = defaultCoverImage
    img.onerror = null
  }
}

const handleSlideClick = (slide: any) => {
  if (slide.link_url) {
    if (slide.link_url.startsWith('http')) {
      window.open(slide.link_url, '_blank')
    } else {
      router.push(slide.link_url)
    }
  }
}

onMounted(async () => {
  // Load all data in parallel
  const tasks = [
    statsApi.getOverview().then(data => { stats.value = data }).catch(() => {}),
    projectStore.loadFeaturedProjects(4).catch(() => {}),
    appStore.loadSlides().catch(() => {}),
    articlesApi.getList({ page: 1, per_page: 6, category_id: 34 })
      .then(result => { allHeroArticles.value = result.items || [] })
      .catch(() => {}),
    // Load project categories for news tabs + "最新动态" data
    articlesApi.getCategories('article')
      .then(async (catResult) => {
        if (catResult.items) {
          const projectCat = catResult.items.find((cat: any) => cat.id === 1)
          if (projectCat && projectCat.children) {
            projectCat.children.forEach((child: any) => {
              newsTabs.value.push({ id: child.id, name: child.name, category_id: child.id })
            })
            // "最新动态" = category 1 + all children
            const allChildIds = projectCat.children.map((c: any) => c.id)
            try {
              const promises = allChildIds.map(cid =>
                articlesApi.getList({ page: 1, per_page: 4, category_id: cid }).catch(() => ({ items: [] }))
              )
              const results = await Promise.all(promises)
              let allItems: Article[] = []
              results.forEach(r => {
                if (r.items) allItems = allItems.concat(r.items)
              })
              // Sort by date descending
              allItems.sort((a: any, b: any) => {
                const da = new Date(a.published_at || a.published_date || 0).getTime()
                const db = new Date(b.published_at || b.published_date || 0).getTime()
                return db - da
              })
              newsTabData.value[0] = allItems.slice(0, 8)
              latestNews.value = allItems.slice(0, 8)
            } catch {
              newsTabData.value[0] = []
            }
          }
        }
      })
      .catch(() => {}),
  ]

  // Load disclosure categories and their articles
  tasks.push(
    articlesApi.getCategories('article')
      .then(async (catResult) => {
        if (catResult.items) {
          const disclosureCat = catResult.items.find((cat: any) => cat.id === 21)
          if (disclosureCat && disclosureCat.children) {
            disclosureCategories.value = disclosureCat.children
            const articlePromises = disclosureCat.children.map(async (cat: any) => {
              try {
                const result = await articlesApi.getList({ category_id: cat.id, page: 1, per_page: 1, status: 'published' })
                return result.items && result.items.length > 0 ? result.items[0] : null
              } catch { return null }
            })
            const articles = await Promise.all(articlePromises)
            disclosureArticles.value = articles.filter(art => art !== null)
          }
        }
      })
      .catch(() => {})
  )

  await Promise.all(tasks)
})
</script>

<style scoped lang="scss">
.home-page {
  background: #fff;
  min-height: 100vh;
}

/* ==================== Hero Section ==================== */
.hero-section {
  position: relative;
  width: 100%;
}

.hero-carousel {
  :deep(.el-carousel) {
    height: 520px;
  }

  // 轮播箭头按钮 - 加大 + 主题色
  :deep(.el-carousel__arrow) {
    width: 52px;
    height: 52px;
    font-size: 22px;
    background: rgba(0, 0, 0, 0.25);
    color: var(--color-primary);
    border-radius: 50%;
    transition: all 0.3s;

    &:hover {
      background: rgba(0, 0, 0, 0.45);
      color: #fff;
    }

    .el-icon {
      font-size: 24px;
    }
  }

  // 轮播指示器
  :deep(.el-carousel__indicators) {
    .el-carousel__indicator {
      .el-carousel__button {
        width: 24px;
        height: 4px;
        border-radius: 2px;
      }

      &.is-active .el-carousel__button {
        background: var(--color-primary);
      }
    }
  }

  .carousel-slide {
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    cursor: pointer;
  }
}

.hero-default {
  height: 520px;
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);

  .hero-default-bg {
    width: 100%;
    height: 100%;
    background-image: url('data:image/svg+xml,<svg width="60" height="60" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="2" cy="2" r="1" fill="rgba(255,255,255,0.12)"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>');
  }
}

.hero-stats-bar {
  padding: 32px 0;
  position: relative;
  z-index: 10;
  margin-top: -40px;
  text-align: center;

  .stats-inner {
    display: inline-flex;
    align-items: center;
    margin: 0 auto;
    padding: 36px 56px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
  }

  .statistics {
    display: flex;
    align-items: center;
  }

  .stat-block {
    display: flex;
    flex-direction: column;
    padding: 0 32px;

    .stat-text {
      display: flex;
      align-items: baseline;
      gap: 14px;
      margin-bottom: 8px;
    }

    .stat-title {
      font-size: 16px;
      color: #333;
      font-weight: 600;
    }

    .stat-date {
      font-size: 14px;
      color: #999;
    }

    .stat-value {
      font-size: 58px;
      font-weight: 700;
      color: var(--color-primary);
      letter-spacing: -0.5px;
    }
  }

  .donate-actions {
    display: none;
  }
}


/* ==================== Common Section ==================== */
.section {
  padding: 60px 0;
}

.section-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.section-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 32px;

  .section-title {
    font-size: 28px;
    font-weight: 700;
    color: #222;
    margin: 0;
    position: relative;
    padding-left: 16px;

    &::before {
      content: '';
      position: absolute;
      left: 0;
      top: 4px;
      bottom: 4px;
      width: 4px;
      background: var(--color-primary);
      border-radius: 50px;
    }
  }

  .section-more-link {
    color: #666;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s;

    &:hover {
      color: var(--color-primary);
    }
  }
}

.section-more {
  text-align: center;
  margin-top: 32px;

  a {
    color: #666;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: color 0.2s;

    &:hover {
      color: var(--color-primary);
    }
  }
}

.empty-state {
  text-align: center;
  padding: 48px 20px;
  color: #999;

  p {
    margin: 0;
    font-size: 14px;
  }
}


/* ==================== News Section ==================== */
.news-section {
  background: #fafafa;
}

.news-content {
  display: flex;
  gap: 28px;
}

.news-featured {
  width: 420px;
  flex-shrink: 0;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    .news-featured-img img {
      transform: scale(1.05);
    }

    .news-featured-title {
      color: var(--color-primary);
    }
  }

  .news-featured-img {
    width: 100%;
    height: 260px;
    overflow: hidden;
    border-radius: 16px;
    background: #e8e8e8;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }
  }

  .news-featured-info {
    padding: 16px 4px 0;
  }

  .news-featured-title {
    font-size: 18px;
    font-weight: 700;
    color: #222;
    margin: 0 0 10px;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    transition: color 0.2s;
  }

  .news-featured-desc {
    font-size: 14px;
    color: #888;
    line-height: 1.6;
    margin: 0 0 10px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .news-featured-date {
    font-size: 13px;
    color: #aaa;
  }
}

.news-list {
  flex: 1;
  display: flex;
  flex-direction: column;
}

.news-tabs {
  display: flex;
  gap: 0;
  margin-bottom: 24px;
  border-bottom: 2px solid #eee;
}

.news-tab {
  padding: 12px 24px;
  border: none;
  background: none;
  font-size: 16px;
  font-weight: 600;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
  position: relative;
  white-space: nowrap;

  &::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 50%;
    transform: translateX(-50%);
    width: 0;
    height: 3px;
    background: var(--color-primary);
    border-radius: 2px;
    transition: width 0.3s ease;
  }

  &:hover {
    color: var(--color-primary);
  }

  &.active {
    color: var(--color-primary);

    &::after {
      width: 100%;
    }
  }
}

.news-list {
  display: flex;
  flex-direction: column;
}

.news-item {
  display: flex;
  align-items: center;
  padding: 18px 0;
  border-bottom: 1px solid #eee;
  cursor: pointer;
  transition: all 0.2s;
  gap: 12px;

  &:last-child {
    border-bottom: none;
  }

  &:hover {
    padding-left: 8px;

    .news-title {
      color: var(--color-primary);
    }
  }

  .news-tag {
    font-size: 13px;
    color: var(--color-primary);
    font-weight: 600;
    white-space: nowrap;
    flex-shrink: 0;
  }

  .news-title {
    flex: 1;
    font-size: 15px;
    color: #333;
    font-weight: 500;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    transition: color 0.2s;
  }

  .news-date {
    font-size: 13px;
    color: #aaa;
    white-space: nowrap;
    flex-shrink: 0;
  }
}

/* ==================== Stories Section ==================== */
.stories-section {
  background: #fff;
}

.stories-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 24px;
}

.story-card {
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-4px);

    .story-thumb img {
      transform: scale(1.05);
    }
  }

  .story-thumb {
    position: relative;
    width: 100%;
    height: 180px;
    overflow: hidden;
    border-radius: 16px;
    background: #f0f0f0;

    img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      transition: transform 0.4s ease;
    }
  }

  .story-title {
    font-size: 16px;
    font-weight: 600;
    color: #222;
    margin: 14px 0 8px;
    line-height: 1.4;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .story-summary {
    font-size: 13px;
    color: #999;
    line-height: 1.5;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
}

/* ==================== Disclosure Section ==================== */
.disclosure-section {
  background: #fafafa;
}

.disclosure-grid {
  display: flex;
  gap: 20px;
  overflow-x: auto;
}

.disclosure-card {
  flex-shrink: 0;
  cursor: pointer;
  transition: all 0.3s ease;
  border-radius: 12px;
  overflow: hidden;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);

    .disclosure-cover {
      transform: scale(1.03);
    }
  }

  .disclosure-cover {
    width: 200px;
    height: 280px;
    display: block;
    object-fit: cover;
    border-radius: 12px;
    transition: transform 0.4s ease;
  }

  .disclosure-placeholder {
    width: 220px;
    height: 280px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #f5f5f5, #e8e8e8);
    border-radius: 12px;
    font-size: 14px;
    color: #999;
    font-weight: 500;
  }
}


/* ==================== Responsive ==================== */
@media (max-width: 1024px) {
  .stories-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .disclosure-card .disclosure-cover,
  .disclosure-card .disclosure-placeholder {
    width: 180px;
  }
}

@media (max-width: 768px) {
  .hero-carousel {
    :deep(.el-carousel) {
      height: 300px;
    }
  }

  .hero-default {
    height: 300px;
  }

  .hero-stats-bar {
    padding: 24px 0;

    .stats-inner {
      flex-direction: column;
      gap: 20px;
    }

    .statistics {
      flex-direction: column;
      gap: 16px;
      width: 100%;
    }

    .stat-block {
      padding: 0;
      text-align: center;

      .stat-text {
        justify-content: center;
      }
    }

    .stat-block .stat-value {
      font-size: 22px;
    }

    .donate-actions {
      display: none;
    }
  }

  .section {
    padding: 40px 0;
  }

  .news-content {
    flex-direction: column;
  }

  .news-featured {
    width: 100%;
  }

  .news-featured-img {
    height: 200px !important;
  }

  .news-item {
    flex-wrap: wrap;

    .news-title {
      width: 100%;
      white-space: normal;
      -webkit-line-clamp: 2;
    }

    .news-date {
      margin-left: auto;
    }
  }

  .stories-grid {
    grid-template-columns: 1fr;
  }

  .disclosure-card .disclosure-cover,
  .disclosure-card .disclosure-placeholder {
    width: 140px;
  }
}
</style>
