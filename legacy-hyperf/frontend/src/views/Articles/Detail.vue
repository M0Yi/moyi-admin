<template>
  <div class="article-detail-page">
    <!-- 文章头部 -->
    <div class="article-header" v-if="article">
      <div class="container">
        <el-breadcrumb separator="/">
          <el-breadcrumb-item :to="{ path: '/articles' }">新闻中心</el-breadcrumb-item>
          <el-breadcrumb-item v-if="article.category">
            {{ article.category.name }}
          </el-breadcrumb-item>
        </el-breadcrumb>

        <h1 class="article-title">{{ article.title }}</h1>

        <div class="article-meta">
          <span class="meta-item">
            <Icon name="calendar" :size="16" />
            <span>{{ formatDate(article.published_date) }}</span>
          </span>
          <span class="meta-item">
            <Icon name="eye" :size="16" />
            <span>{{ article.view_count || 0 }} 浏览</span>
          </span>
          <span class="meta-item" v-if="article.author">
            <Icon name="user" :size="16" />
            <span>{{ article.author }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- 文章封面 -->
    <div class="article-cover" v-if="article && article.cover_image && showCover">
      <div class="container">
        <img
          :src="article.cover_image"
          :alt="article.title"
          @error="handleCoverError"
          class="cover-image"
          v-show="showCover"
        />
      </div>
    </div>

    <!-- 文章内容 -->
    <div class="article-content section" v-if="article">
      <div class="container">
        <!-- 文章摘要 -->
        <div class="article-summary" v-if="article.summary">
          <div class="summary-icon">
            <Icon name="info-circle" :size="24" color="#ff5000" />
          </div>
          <p>{{ article.summary }}</p>
        </div>

        <!-- 文章正文 -->
        <div class="article-body" v-html="article.content || '暂无内容'"></div>

        <!-- 附件下载 -->
        <div class="article-attachments" v-if="article.attachments && article.attachments.length > 0">
          <h3>附件下载</h3>
          <el-space wrap>
            <el-button
              v-for="attachment in article.attachments"
              :key="attachment.name"
              :href="attachment.url"
              type="primary"
              plain
              link
            >
              <Icon name="download" :size="16" />
              {{ attachment.name }}
              <span class="file-size">({{ formatFileSize(attachment.size) }})</span>
            </el-button>
          </el-space>
        </div>
      </div>
    </div>

    <!-- 相关文章推荐 -->
    <div class="related-articles section" v-if="article && article.related_articles && article.related_articles.length > 0">
      <div class="container">
        <h3 class="section-title">相关文章</h3>
        <div class="related-grid">
          <div
            v-for="related in article.related_articles"
            :key="related.id"
            class="related-card"
            @click="$router.push(`/article/${related.id}`)"
          >
            <div class="related-image">
              <img
                :src="related.cover_image || getFallbackImage()"
                :alt="related.title"
                @error="handleRelatedImageError"
              />
            </div>
            <h4 class="related-title">{{ related.title }}</h4>
          </div>
        </div>
      </div>
    </div>

    <!-- 加载状态 -->
    <div v-if="loading" class="loading-container">
      <el-skeleton :rows="10" animated />
    </div>

    <!-- 错误状态 -->
    <div v-if="error" class="error-container">
      <el-empty
        description="加载失败，请稍后重试"
        :image-size="200"
      >
        <el-button type="primary" @click="loadArticle">重新加载</el-button>
      </el-empty>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import Icon from '@/components/Icon.vue'
import { articlesApi } from '@/api/articles'

const route = useRoute()
const router = useRouter()

const loading = ref(true)
const error = ref(false)
const article = ref<any>(null)
const showCover = ref(true)

// 格式化日期
const formatDate = (date: string) => {
  if (!date) return ''
  return new Date(date).toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// 格式化文件大小
const formatFileSize = (bytes: number) => {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

// 获取默认图片
const getFallbackImage = () => {
  // 返回统一的默认图片
  return 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'
}

// 处理封面图错误
const handleCoverError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    showCover.value = false
  }
}

// 处理相关文章图片错误
const handleRelatedImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    // 隐藏图片，显示占位符背景
    img.style.display = 'none'
    // 添加占位符背景到父容器
    const parent = img.closest('.related-image') as HTMLElement
    if (parent) {
      parent.style.background = 'linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%)'
      parent.style.display = 'flex'
      parent.style.alignItems = 'center'
      parent.style.justifyContent = 'center'
      const icon = document.createElement('div')
      icon.innerHTML = '📄'
      icon.style.fontSize = '32px'
      icon.style.opacity = '0.5'
      parent.appendChild(icon)
    }
  }
}

// 加载文章详情
const loadArticle = async () => {
  try {
    loading.value = true
    error.value = false
    showCover.value = true // 重置封面显示状态

    const id = Number(route.params.id)
    const data = await articlesApi.getDetail(id)

    // 处理文章数据，确保字段兼容性
    article.value = {
      ...data,
      // 如果没有 cover_image，设置为空字符串
      cover_image: data.cover_image || '',
      // 处理附件
      attachments: data.attachments || [],
      // 处理相关文章
      related_articles: data.related_articles || []
    }

    // 检查封面图片是否有效
    if (!article.value.cover_image || article.value.cover_image.trim() === '') {
      showCover.value = false
    }

    // 更新页面标题
    document.title = `${article.value.title} - 建辉慈善基金会`
  } catch (err) {
    console.error('加载文章失败:', err)
    error.value = true
    ElMessage.error('文章加载失败')
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadArticle()
})
</script>

<style scoped lang="scss">
.article-detail-page {
  min-height: 100vh;
  background: #fff;
}

.article-header {
  background: #fff;
  padding: 40px 0 30px;
  border-bottom: 1px solid #e5e7eb;

  .container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .article-title {
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    line-height: 1.4;
    margin: 20px 0 15px;
  }

  .article-meta {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;

    .meta-item {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 14px;
      color: #6b7280;

      :deep(.icon-wrapper) {
        color: #9ca3af;
      }
    }
  }
}

.article-cover {
  background: #fff;
  padding: 20px 0;
  border-bottom: 1px solid #e5e7eb;

  .container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px;
  }

  .cover-image {
    width: 100%;
    max-height: 400px;
    object-fit: cover;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
}

.article-content {
  background: #f5f7fa;

  .container {
    max-width: 800px;
    margin: 0 auto;
    padding: 40px 20px;
  }

  .article-summary {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: linear-gradient(135deg, #fff5f5 0%, #fff 100%);
    border-left: 4px solid #ff5000;
    border-radius: 8px;
    margin-bottom: 30px;

    .summary-icon {
      flex-shrink: 0;
      display: flex;
      align-items: flex-start;
    }

    p {
      flex: 1;
      font-size: 16px;
      color: #4b5563;
      line-height: 1.8;
      margin: 0;
    }
  }

  .article-body {
    font-size: 16px;
    line-height: 1.8;
    color: #374151;

    :deep(p) {
      margin-bottom: 1.5em;
    }

    :deep(h2), :deep(h3), :deep(h4) {
      margin-top: 2em;
      margin-bottom: 1em;
      color: #111827;
      font-weight: 600;
    }

    :deep(img) {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
      margin: 20px 0;
    }

    :deep(pre) {
      background: #f5f7fa;
      padding: 20px;
      border-radius: 8px;
      overflow-x: auto;
      margin: 20px 0;
    }

    :deep(blockquote) {
      border-left: 4px solid #ff5000;
      padding-left: 20px;
      margin: 20px 0;
      color: #6b7280;
      font-style: italic;
    }
  }

  .article-attachments {
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid #e5e7eb;

    h3 {
      font-size: 20px;
      margin-bottom: 20px;
      color: #111827;
    }

    .file-size {
      font-size: 12px;
      color: #9ca3af;
      margin-left: 4px;
    }
  }

}

.related-articles {
  background: #f5f7fa;
  padding: 60px 20px;

  .container {
    max-width: 1200px;
    margin: 0 auto;
  }

  .section-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 30px;
  }

  .related-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;

    .related-card {
      cursor: pointer;
      transition: all 0.3s ease;

      &:hover {
        transform: translateY(-4px);

        .related-image img {
          transform: scale(1.05);
        }
      }

      .related-image {
        width: 100%;
        height: 160px;
        border-radius: 8px;
        overflow: hidden;
        background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
        margin-bottom: 12px;

        img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          transition: transform 0.3s ease;
        }
      }

      .related-title {
        font-size: 14px;
        font-weight: 600;
        color: #374151;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
      }
    }
  }
}

.section {
  padding: 60px 20px;
}

.loading-container,
.error-container {
  padding: 100px 20px;
  text-align: center;
}

@media (max-width: 768px) {
  .article-header .article-title {
    font-size: 24px;
  }

  .article-meta {
    gap: 16px;
  }

  .related-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
  }
}
</style>
