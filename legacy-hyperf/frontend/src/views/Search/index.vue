<template>
  <div class="search-page">
    <!-- 搜索头部 -->
    <section class="page-header">
      <img src="https://cdn1.zhizhucms.com/materials/image/744/2026/3/27/1774599952748_323.jpg" alt="" class="header-bg" />
    </section>

    <!-- 搜索输入区域 -->
    <section class="search-section section">
      <div class="container">
        <div class="search-box">
          <el-input
            v-model="searchQuery"
            placeholder="搜索文章、项目等内容..."
            size="large"
            clearable
            @keyup.enter="handleSearch"
          >
            <template #prefix>
              <Icon name="search" :size="20" color="#999" />
            </template>
            <template #append>
              <el-button
                class="search-submit-btn"
                :loading="loading"
                @click="handleSearch"
              >
                搜索
              </el-button>
            </template>
          </el-input>
        </div>

        <!-- 热门搜索关键词 -->
        <div v-if="!hasSearched" class="hot-keywords">
          <span class="hot-label">热门搜索：</span>
          <el-tag
            v-for="keyword in hotKeywords"
            :key="keyword"
            class="keyword-tag"
            effect="plain"
            round
            @click="quickSearch(keyword)"
          >
            {{ keyword }}
          </el-tag>
        </div>
      </div>
    </section>

    <!-- 搜索结果 -->
    <section v-if="hasSearched" class="results-section section">
      <div class="container">
        <!-- 结果头部 -->
        <div class="results-header">
          <h2>搜索结果</h2>
          <p class="results-count">
            共找到 <strong>{{ totalCount }}</strong> 条相关结果
          </p>
        </div>

        <!-- 加载中 -->
        <div v-if="loading" class="loading-container">
          <el-skeleton :rows="3" animated />
        </div>

        <!-- 无结果 -->
        <div v-else-if="totalCount === 0" class="empty-state">
          <Icon name="search" :size="64" color="#ccc" />
          <p>未找到相关内容，请尝试其他关键词</p>
          <div class="tips">
            <p>搜索建议：</p>
            <ul class="tips-list">
              <li>检查输入是否正确</li>
              <li>尝试使用更简短的关键词</li>
              <li>尝试使用同义词</li>
            </ul>
          </div>
        </div>

        <!-- 文章结果 -->
        <div v-if="results.articles.length > 0" class="result-group">
          <h3 class="result-group-title">
            <Icon name="article" :size="22" color="var(--color-primary)" />
            文章结果（{{ results.articles.length }}）
          </h3>
          <div class="result-list">
            <div
              v-for="article in results.articles"
              :key="article.id"
              class="result-item"
              @click="navigateTo(`/article/${article.id}`)"
            >
              <div class="result-image">
                <el-image
                  :src="article.cover_url || article.coverUrl"
                  fit="cover"
                >
                  <template #error>
                    <div class="image-placeholder">
                      <Icon name="image" :size="32" color="#ccc" />
                    </div>
                  </template>
                </el-image>
              </div>
              <div class="result-content">
                <h4 class="result-title" v-html="highlightKeyword(article.title)" />
                <p class="result-description">{{ stripHtml(article.description || article.content || '') }}</p>
                <div class="result-meta">
                  <span class="meta-item">
                    <Icon name="calendar" :size="14" />
                    {{ formatDate(article.created_at || article.createdAt) }}
                  </span>
                  <span v-if="article.category_name || article.categoryName" class="meta-item">
                    <Icon name="tag" :size="14" />
                    {{ article.category_name || article.categoryName }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 项目结果 -->
        <div v-if="results.projects.length > 0" class="result-group">
          <h3 class="result-group-title">
            <Icon name="project" :size="22" color="var(--color-primary)" />
            项目结果（{{ results.projects.length }}）
          </h3>
          <div class="result-list">
            <div
              v-for="project in results.projects"
              :key="project.id"
              class="result-item"
              @click="navigateTo(`/project/${project.id}`)"
            >
              <div class="result-image">
                <el-image
                  :src="project.cover_url || project.coverUrl"
                  fit="cover"
                >
                  <template #error>
                    <div class="image-placeholder">
                      <Icon name="image" :size="32" color="#ccc" />
                    </div>
                  </template>
                </el-image>
              </div>
              <div class="result-content">
                <h4 class="result-title" v-html="highlightKeyword(project.title)" />
                <p class="result-description">{{ stripHtml(project.description || project.content || '') }}</p>
                <div class="result-meta">
                  <span class="meta-item">
                    <Icon name="calendar" :size="14" />
                    {{ formatDate(project.created_at || project.createdAt) }}
                  </span>
                  <span v-if="project.amount_raised || project.amountRaised" class="meta-item">
                    <Icon name="money" :size="14" />
                    已筹 ¥{{ formatNumber(project.amount_raised || project.amountRaised) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- 搜索建议 -->
    <section v-if="!hasSearched" class="suggestions-section section">
      <div class="container">
        <div class="suggestions-grid">
          <!-- 热门标签 -->
          <div class="suggestion-card">
            <h3>
              <Icon name="fire" :size="20" color="var(--color-primary)" />
              热门标签
            </h3>
            <div class="tag-cloud">
              <span
                v-for="keyword in hotKeywords"
                :key="keyword"
                class="keyword-tag"
                @click="quickSearch(keyword)"
              >
                {{ keyword }}
              </span>
            </div>
          </div>

          <!-- 快速链接 -->
          <div class="suggestion-card">
            <h3>
              <Icon name="link" :size="20" color="var(--color-primary)" />
              快速访问
            </h3>
            <div class="quick-links">
              <a class="quick-link" @click="navigateTo('/about')">
                <Icon name="info" :size="18" />
                <span>关于我们</span>
              </a>
              <a class="quick-link" @click="navigateTo('/projects')">
                <Icon name="project" :size="18" />
                <span>公益项目</span>
              </a>
              <a class="quick-link" @click="navigateTo('/joinus')">
                <Icon name="heart" :size="18" />
                <span>加入我们</span>
              </a>
              <a class="quick-link" @click="navigateTo('/donation')">
                <Icon name="money" :size="18" />
                <span>捐赠公示</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'
import { projectsApi } from '@/api/projects'

const route = useRoute()
const router = useRouter()

// 搜索状态
const searchQuery = ref('')
const loading = ref(false)
const hasSearched = ref(false)

// 搜索结果
const results = ref<{
  articles: any[]
  projects: any[]
}>({
  articles: [],
  projects: []
})

// 热门搜索关键词
const hotKeywords = ref([
  '慈善',
  '公益',
  '捐赠',
  '志愿者',
  '助学',
  '医疗',
  '环保',
  '敬老'
])

// 计算总结果数
const totalCount = computed(() => {
  return results.value.articles.length +
         results.value.projects.length
})

// 执行搜索
const handleSearch = async () => {
  if (!searchQuery.value.trim()) {
    ElMessage.warning('请输入搜索关键词')
    return
  }

  loading.value = true
  hasSearched.value = true

  try {
    // 清空之前的结果
    results.value = {
      articles: [],
      projects: []
    }

    // 并行搜索文章和项目
    const promises: Promise<any>[] = [
      articlesApi.getList({ search: searchQuery.value, per_page: 20 })
        .then(data => {
          console.log('文章搜索结果:', data)
          results.value.articles = data?.items || []
        })
        .catch(() => {}),
      projectsApi.getList({ search: searchQuery.value, per_page: 20 })
        .then(data => {
          console.log('项目搜索结果:', data)
          results.value.projects = data?.items || []
        })
        .catch(() => {})
    ]

    await Promise.all(promises)

    // 更新URL
    router.push({
      path: '/search',
      query: { q: searchQuery.value }
    })

  } catch (error) {
    console.error('搜索失败:', error)
    ElMessage.error('搜索失败，请稍后重试')
  } finally {
    loading.value = false
  }
}
// 快速搜索
const quickSearch = (keyword: string) => {
  searchQuery.value = keyword
  handleSearch()
}

// 高亮关键词
const highlightKeyword = (text: string) => {
  if (!searchQuery.value) return text
  const regex = new RegExp(`(${searchQuery.value})`, 'gi')
  return text.replace(regex, '<span class="highlight">$1</span>')
}

// 去除HTML标签
const stripHtml = (html: string) => {
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return tmp.textContent || tmp.innerText || ''
}

// 格式化日期
const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN')
}

// 格式化数字
const formatNumber = (num: number) => {
  if (!num) return '0'
  return num.toLocaleString('zh-CN')
}

// 导航到指定页面
const navigateTo = (path: string) => {
  router.push(path)
}

// 初始化
onMounted(() => {
  // 从URL获取搜索关键词
  const query = route.query.q as string
  if (query) {
    searchQuery.value = query
    handleSearch()
  }
})
</script>

<style scoped lang="scss">
.search-page {
  min-height: 100vh;
  background: var(--color-bg-secondary);
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

.section {
  padding: 40px 20px;
}

.container {
  max-width: 1000px;
  margin: 0 auto;
}

.search-section {
  background: #fff;
  box-shadow: var(--shadow-md);
  border-radius: var(--radius-md);

  .search-box {
    max-width: 700px;
    margin: 0 auto;

    :deep(.el-input__wrapper) {
      box-shadow: none;
      border-radius: var(--radius-md) var(--radius-md) 0 0;
      padding: 12px 16px;

      &:focus {
        border-color: var(--color-primary);
        box-shadow: 0 0 0 1px var(--color-primary-lighter) inset;
      }
    }
  }

  .search-submit-btn {
    min-width: 100px;
    height: 100%;
    font-size: 16px;
    font-weight: 500;
    border-radius: 0 var(--radius-md) var(--radius-md) 0;
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(255, 80, 0, 0.3);
    transition: all var(--transition);

    &:hover {
      background: linear-gradient(135deg, var(--color-primary-light) 0%, var(--color-primary) 100%);
      box-shadow: 0 6px 16px rgba(255, 80, 0, 0.4);
    }

    &:disabled {
      opacity: 0.7;
      cursor: not-allowed;
    }
  }

  .hot-keywords {
    max-width: 700px;
    margin: 20px auto 0;
    text-align: center;

    .hot-label {
      font-size: 14px;
      color: var(--color-text-secondary);
      margin-right: 8px;
    }

    .keyword-tag {
      cursor: pointer;
      margin: 4px;
      transition: all var(--transition);

      &:hover {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
      }
    }
  }
}

.results-section {
  .results-header {
    margin-bottom: 30px;

    h2 {
      font-size: 28px;
      margin-bottom: 8px;
      color: var(--color-text-primary);
    }

    .results-count {
      font-size: 15px;
      color: var(--color-text-secondary);
    }
  }

  .loading-container {
    background: #fff;
    padding: 30px;
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
  }

  .empty-state {
    text-align: center;
    padding: 60px 20px;

    p {
      font-size: 18px;
      opacity: 0.9;
      margin-top: 20px;
      color: var(--color-text-secondary);
    }

    .tips {
      font-size: 14px;
      color: var(--color-text-secondary);
      margin-top: 10px;
    }

    .tips-list {
      text-align: left;
      display: inline-block;
      margin-top: 10px;
      color: var(--color-text-tertiary);
      font-size: 14px;

      li {
        margin: 5px 0;
      }
    }
  }
}

.result-group {
  margin-bottom: 40px;

  .result-group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 20px;
    margin-bottom: 20px;
    color: var(--color-text-primary);
    font-weight: 600;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--color-primary);
  }
}

.result-list {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.result-item {
  background: #fff;
  border-radius: var(--radius-md);
  padding: 20px;
  display: flex;
  gap: 20px;
  cursor: pointer;
  transition: all var(--transition);
  box-shadow: var(--shadow-sm);
  border: 1px solid var(--color-border-light);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(255, 80, 0, 0.15);
    border-color: var(--color-primary-lighter);
  }

  .result-image {
    flex-shrink: 0;
    width: 160px;
    height: 120px;
    border-radius: var(--radius);
    overflow: hidden;
    background: var(--color-bg-tertiary);

    :deep(.el-image) {
      width: 100%;
      height: 100%;
    }

    .image-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--color-bg-tertiary);
    }
  }

  .result-content {
    flex: 1;
    min-width: 0;
  }

  .result-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--color-text-primary);
    margin-bottom: 10px;
    line-height: 1.4;

    :deep(.highlight) {
      background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
      padding: 2px 6px;
      border-radius: 3px;
      color: #333;
    }
  }

  .result-description {
    font-size: 14px;
    color: var(--color-text-secondary);
    line-height: 1.6;
    margin-bottom: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }

  .result-meta {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;

    .meta-item {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 13px;
      color: var(--color-text-tertiary);
    }
  }
}

.suggestions-section {
  .suggestions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
  }

  .suggestion-card {
    background: #fff;
    border-radius: var(--radius-md);
    padding: 30px;
    box-shadow: var(--shadow-sm);
    border: 1px solid var(--color-border-light);

    h3 {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 18px;
      margin-bottom: 20px;
      color: var(--color-text-primary);
      font-weight: 600;
    }
  }

  .tag-cloud {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;

    .keyword-tag {
      cursor: pointer;
      padding: 8px 16px;
      font-size: 14px;
      background: var(--color-bg-secondary);
      border: 1px solid var(--color-border);
      transition: all var(--transition);
      border-radius: var(--radius);

      &:hover {
        background: var(--color-primary);
        border-color: var(--color-primary);
        color: #fff;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 80, 0, 0.3);
      }
    }
  }

  .quick-links {
    display: flex;
    flex-direction: column;
    gap: 12px;

    .quick-link {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 16px;
      background: var(--color-bg-secondary);
      border-radius: var(--radius);
      text-decoration: none;
      color: var(--color-text-primary);
      transition: all var(--transition);
      border: 1px solid var(--color-border-light);
      cursor: pointer;

      &:hover {
        background: var(--color-primary);
        color: #fff;
        transform: translateX(5px);
        border-color: var(--color-primary);
      }

      span {
        font-size: 15px;
        font-weight: 500;
      }
    }
  }
}

@media (max-width: 768px) {
  .page-header {
    height: 200px;
  }

  .result-item {
    flex-direction: column;

    .result-image {
      width: 100%;
      height: 200px;
    }
  }

  .search-section .search-box {
    :deep(.el-input-group--append) {
      flex-direction: column;
    }
  }
}
</style>
