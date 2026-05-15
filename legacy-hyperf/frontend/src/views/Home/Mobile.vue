<template>
  <div class="mobile-home-page">
    <!-- Hero Banner -->
    <section class="hero-section">
      <div class="hero-carousel" v-if="slides.length > 0">
        <el-carousel height="240px" :interval="5000" arrow="never">
          <el-carousel-item v-for="slide in slides" :key="slide.id">
            <div
              class="carousel-slide"
              :style="{
                backgroundImage: (slide.image_mobile || slide.image) ? `url(${slide.image_mobile || slide.image})` : 'none',
                background: (slide.image_mobile || slide.image) ? 'cover center no-repeat' : `linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%)`
              }"
              @click="handleSlideClick(slide)"
            ></div>
          </el-carousel-item>
        </el-carousel>
      </div>
      <div v-else class="hero-default"></div>

      <!-- Stats -->
      <div class="stats-bar">
        <div class="stat-item">
          <div class="stat-label">累计捐赠(元)</div>
          <div class="stat-value">{{ formatAmount(stats?.historical_total?.amount || 1583775789.69) }}</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <div class="stat-label">本年捐赠(元)</div>
          <div class="stat-value">{{ formatAmount(stats?.current_year?.amount || 47489886.11) }}</div>
        </div>
      </div>
      <div class="hero-actions">
        <button class="btn-donate" @click="$router.push('/donate')">我要捐赠</button>
        <button class="btn-query" @click="$router.push('/donate')">捐赠查询</button>
      </div>
    </section>

    <!-- Projects -->
    <section class="section">
      <div class="container">
        <div class="section-head">
          <h2 class="section-title">公益项目</h2>
        </div>
        <div class="project-list" v-if="featuredProjects.length > 0">
          <div v-for="project in featuredProjects" :key="project.id" class="project-card">
            <div class="project-header">
              <span class="project-status" v-if="project.status">{{ getProjectStatusLabel(project.status) }}</span>
              <h3 class="project-name">{{ project.name }}</h3>
            </div>
            <p class="project-desc">{{ truncateText(project.description || project.summary || '', 60) }}</p>
            <div class="project-actions">
              <button class="btn-sm-primary" @click="$router.push('/donate')">立即捐赠</button>
              <button class="btn-sm-outline" @click="$router.push(`/project/${project.id}`)">项目查看</button>
            </div>
          </div>
        </div>
        <div v-else class="empty-state"><p>暂无项目信息</p></div>
        <div class="section-more"><router-link to="/projects">查看更多公益项目 &rarr;</router-link></div>
      </div>
    </section>

    <!-- News -->
    <section class="section section-gray">
      <div class="container">
        <div class="section-head">
          <h2 class="section-title">新闻中心</h2>
          <router-link to="/projects" class="more-link">了解更多 &rarr;</router-link>
        </div>
        <div class="news-list" v-if="latestNews.length > 0">
          <div v-for="item in latestNews" :key="item.id" class="news-item" @click="$router.push(`/article/${item.id}`)">
            <span class="news-tag">【{{ item.category?.name || '项目动态' }}】</span>
            <span class="news-title">{{ item.title }}</span>
            <span class="news-date">{{ formatDate(item.published_at || item.published_date) }}</span>
          </div>
        </div>
        <div v-else class="empty-state"><p>暂无新闻信息</p></div>
      </div>
    </section>

    <!-- Stories -->
    <section class="section">
      <div class="container">
        <div class="section-head">
          <h2 class="section-title">行善者故事</h2>
        </div>
        <div class="stories-grid" v-if="heroStories.length > 0">
          <div v-for="story in heroStories" :key="story.id" class="story-card" @click="$router.push(`/article/${story.id}`)">
            <div class="story-thumb">
              <img :src="story.cover_image || defaultCoverImage" :alt="story.title" @error="handleImageError" />
              <div class="play-icon">
                <svg viewBox="0 0 24 24" fill="white" width="24" height="24"><path d="M8 5v14l11-7z"/></svg>
              </div>
            </div>
            <h4 class="story-title">{{ story.title }}</h4>
          </div>
        </div>
        <div v-else class="empty-state"><p>暂无视频信息</p></div>
        <div class="section-more"><router-link to="/stories">查看更多行善者故事 &rarr;</router-link></div>
      </div>
    </section>

    <!-- Disclosure -->
    <section class="section section-gray">
      <div class="container">
        <div class="section-head">
          <h2 class="section-title">信息公开</h2>
          <router-link to="/disclosure" class="more-link">了解更多 &rarr;</router-link>
        </div>
        <div class="disclosure-grid" v-if="disclosureItems.length > 0">
          <div v-for="item in disclosureItems" :key="item.id" class="disclosure-card" @click="item.hasArticle && $router.push(`/article/${item.article.id}`)">
            <div class="disclosure-img">
              <img v-if="item.hasArticle && item.article.cover_image" :src="item.article.cover_image" :alt="item.article.title" @error="handleImageError" />
              <div v-else class="disclosure-placeholder"><span>{{ item.categoryName }}</span></div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Subscribe -->
    <section class="subscribe-section">
      <h2 class="subscribe-title">致敬行善者 让好人有好报</h2>
      <p class="subscribe-desc">订阅我们的项目进展</p>
      <div class="subscribe-form">
        <input v-model="email" type="email" placeholder="输入邮箱" class="subscribe-input" />
        <button class="subscribe-btn" @click="handleSubscribe">订阅</button>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { commonApi } from '@/api/common'
import { articlesApi } from '@/api/articles'
import { statsApi } from '@/api/stats'
import { useProjectStore } from '@/stores/project'
import { useAppStore } from '@/stores/app'
import type { Slide, Article } from '@/types'
import { formatAmount, truncateText } from '@/utils/format'

const router = useRouter()
const projectStore = useProjectStore()
const appStore = useAppStore()

const slides = ref<Slide[]>([])
const stats = ref<any>(null)
const latestNews = ref<Article[]>([])
const allHeroArticles = ref<any[]>([])
const disclosureCategories = ref<any[]>([])
const disclosureArticles = ref<any[]>([])
const email = ref('')
const defaultCoverImage = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'

const featuredProjects = computed(() => projectStore.featuredProjects)
const heroStories = computed(() => allHeroArticles.value.slice(0, 6))

const getProjectStatusLabel = (status: string) => {
  const map: Record<string, string> = { active: '进行中', completed: '已完成', upcoming: '即将开始', paused: '已暂停' }
  return map[status] || status
}

const disclosureItems = computed(() => {
  return disclosureCategories.value.map(cat => {
    const article = disclosureArticles.value.find(art => art.category && art.category.id === cat.id)
    return { id: cat.id, categoryId: cat.id, categorySlug: cat.slug, categoryName: cat.name, article: article || null, hasArticle: !!article }
  })
})

const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN')
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) { img.dataset.handled = 'true'; img.src = defaultCoverImage; img.onerror = null }
}

const handleSlideClick = (slide: Slide) => {
  if (slide.link_url) {
    if (slide.link_url.startsWith('http')) window.open(slide.link_url, '_blank')
    else router.push(slide.link_url)
  }
}

const handleSubscribe = () => {
  if (!email.value) return
  alert('订阅成功！')
  email.value = ''
}

onMounted(async () => {
  await Promise.all([
    commonApi.getSlides().then(r => { slides.value = r.items || [] }).catch(() => {}),
    statsApi.getOverview().then(r => { stats.value = r }).catch(() => {}),
    projectStore.loadFeaturedProjects(4).catch(() => {}),
    appStore.loadSlides().catch(() => {}),
    articlesApi.getList({ page: 1, per_page: 6, category_id: 1 }).then(r => { latestNews.value = r.items || [] }).catch(() => {}),
    articlesApi.getList({ page: 1, per_page: 6, category_id: 34 }).then(r => { allHeroArticles.value = r.items || [] }).catch(() => {}),
    articlesApi.getCategories('article').then(async (catResult) => {
      if (catResult.items) {
        const dc = catResult.items.find((c: any) => c.id === 21)
        if (dc?.children) {
          disclosureCategories.value = dc.children
          const arts = await Promise.all(dc.children.map(async (c: any) => {
            try { const r = await articlesApi.getList({ category_id: c.id, page: 1, per_page: 1 }); return r.items?.[0] || null } catch { return null }
          }))
          disclosureArticles.value = arts.filter(Boolean)
        }
      }
    }).catch(() => {})
  ])
})
</script>

<style scoped lang="scss">
.mobile-home-page {
  min-height: 100vh;
  background: #fff;
}

/* Hero */
.hero-section { position: relative; }
.hero-carousel .carousel-slide { width: 100%; height: 100%; background-size: cover; background-position: center; cursor: pointer; }
.hero-default { height: 240px; background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%); }

.stats-bar {
  display: flex; align-items: center; justify-content: space-around;
  padding: 20px 16px; background: #fff; box-shadow: 0 2px 12px rgba(0,0,0,0.06);

  .stat-item { text-align: center; }
  .stat-label { font-size: 11px; color: #999; margin-bottom: 4px; }
  .stat-value { font-size: 18px; font-weight: 700; color: #333; }
  .stat-divider { width: 1px; height: 36px; background: #eee; }
}

.hero-actions {
  display: flex; gap: 12px; padding: 16px; background: #fff;
  .btn-donate { flex: 1; padding: 12px; background: var(--color-primary); color: #fff; border: none; border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; }
  .btn-query { flex: 1; padding: 12px; background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 4px; font-size: 14px; font-weight: 600; cursor: pointer; }
}

/* Section */
.section { padding: 28px 0; }
.section-gray { background: #f7f8fa; }
.container { padding: 0 16px; }
.section-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.section-title { font-size: 20px; font-weight: 700; color: #222; margin: 0; padding-left: 12px; border-left: 3px solid var(--color-primary); }
.more-link { color: #666; text-decoration: none; font-size: 13px; }
.section-more { text-align: center; margin-top: 20px; a { color: #666; text-decoration: none; font-size: 13px; } }
.empty-state { text-align: center; padding: 32px 16px; color: #999; p { margin: 0; font-size: 13px; } }

/* Project Cards */
.project-list { display: flex; flex-direction: column; gap: 16px; }
.project-card { border: 1px solid #eee; border-radius: 8px; padding: 20px 16px; }
.project-header { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.project-status { display: inline-block; padding: 2px 8px; background: var(--color-primary); color: #fff; font-size: 11px; font-weight: 600; border-radius: 3px; flex-shrink: 0; }
.project-name { font-size: 16px; font-weight: 700; color: #222; margin: 0; }
.project-desc { font-size: 13px; color: #888; line-height: 1.5; margin: 0 0 16px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.project-actions { display: flex; gap: 12px; }
.btn-sm-primary { padding: 8px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-sm-outline { padding: 8px 20px; background: #fff; color: var(--color-primary); border: 1px solid var(--color-primary); border-radius: 4px; font-size: 13px; font-weight: 600; cursor: pointer; }

/* News */
.news-list { display: flex; flex-direction: column; }
.news-item { display: flex; align-items: center; padding: 14px 0; border-bottom: 1px solid #f0f0f0; gap: 8px; cursor: pointer; &:last-child { border-bottom: none; } }
.news-tag { font-size: 12px; color: var(--color-primary); font-weight: 600; flex-shrink: 0; }
.news-title { flex: 1; font-size: 14px; color: #333; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.news-date { font-size: 12px; color: #bbb; flex-shrink: 0; }

/* Stories */
.stories-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.story-card { cursor: pointer; }
.story-thumb { position: relative; width: 100%; height: 120px; overflow: hidden; border-radius: 6px; background: #f0f0f0; img { width: 100%; height: 100%; object-fit: cover; } .play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); width: 36px; height: 36px; background: rgba(0,0,0,0.5); border-radius: 50%; display: flex; align-items: center; justify-content: center; } }
.story-title { font-size: 13px; font-weight: 600; color: #333; margin: 8px 0 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* Disclosure */
.disclosure-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.disclosure-card { cursor: pointer; }
.disclosure-img { width: 100%; height: 100px; overflow: hidden; border-radius: 6px; background: #f0f0f0; img { width: 100%; height: 100%; object-fit: cover; } }
.disclosure-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f5f5f5; font-size: 12px; color: #999; }

/* Subscribe */
.subscribe-section { background: var(--color-primary); padding: 36px 16px; text-align: center; }
.subscribe-title { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 8px; }
.subscribe-desc { font-size: 13px; color: rgba(255,255,255,0.8); margin: 0 0 20px; }
.subscribe-form { display: flex; gap: 8px; }
.subscribe-input { flex: 1; padding: 10px 12px; border: 1px solid rgba(255,255,255,0.3); border-radius: 4px; font-size: 13px; outline: none; background: rgba(255,255,255,0.15); color: #fff; &::placeholder { color: rgba(255,255,255,0.5); } }
.subscribe-btn { padding: 10px 20px; background: #fff; color: var(--color-primary); border: none; border-radius: 4px; font-size: 13px; font-weight: 700; cursor: pointer; }
</style>
