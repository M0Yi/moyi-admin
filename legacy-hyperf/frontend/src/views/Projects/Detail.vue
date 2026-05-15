<template>
  <div class="project-detail-page" v-loading="loading">
    <div v-if="project">
      <!-- Page Header -->
      <section class="page-header" :style="{ backgroundImage: `url(${project.cover_image || 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'})` }">
        <div class="header-overlay">
          <div class="container">
            <el-breadcrumb separator="/">
              <el-breadcrumb-item to="/">首页</el-breadcrumb-item>
              <el-breadcrumb-item to="/projects">公益项目</el-breadcrumb-item>
              <el-breadcrumb-item>{{ project.title }}</el-breadcrumb-item>
            </el-breadcrumb>
            <h1>{{ project.title }}</h1>
            <p class="header-subtitle" v-if="project.subtitle">{{ project.subtitle }}</p>
          </div>
        </div>
      </section>

      <!-- Project Info -->
      <section class="project-info section">
        <div class="container">
          <el-row :gutter="30">
            <!-- Main Content -->
            <el-col :xs="24" :md="16">
              <!-- Progress Card -->
              <el-card class="progress-card mb-4">
                <template #header>
                  <h3>筹款进度</h3>
                </template>
                <div class="progress-stats">
                  <div class="progress-circle">
                    <el-progress
                      type="circle"
                      :percentage="project.progress_percentage"
                      :width="120"
                    >
                      <template #default="{ percentage }">
                        <span class="percentage-value">{{ percentage }}%</span>
                      </template>
                    </el-progress>
                  </div>
                  <div class="progress-details">
                    <div class="detail-item">
                      <span class="label">已筹集</span>
                      <span class="value">{{ formatAmount(project.raised_amount) }}元</span>
                    </div>
                    <div class="detail-item">
                      <span class="label">目标金额</span>
                      <span class="value">{{ formatAmount(project.target_amount) }}元</span>
                    </div>
                    <div class="detail-item">
                      <span class="label">捐赠人次</span>
                      <span class="value">{{ project.donor_count || 0 }}次</span>
                    </div>
                    <div class="detail-item">
                      <span class="label">受益人数</span>
                      <span class="value">{{ project.beneficiary_count }}人</span>
                    </div>
                  </div>
                </div>
              </el-card>

              <!-- Content -->
              <el-card class="content-card mb-4">
                <template #header>
                  <h3>项目介绍</h3>
                </template>
                <div class="project-content" v-html="project.content || project.description"></div>
              </el-card>

              <!-- Progress Timeline -->
              <el-card class="timeline-card mb-4" v-if="project.progress && project.progress.length > 0">
                <template #header>
                  <h3>项目进展</h3>
                </template>
                <el-timeline>
                  <el-timeline-item
                    v-for="item in project.progress"
                    :key="item.id"
                    :timestamp="item.progress_date"
                    placement="top"
                  >
                    <div class="timeline-content">
                      <h4>{{ item.title }}</h4>
                      <p>{{ item.description }}</p>
                      <div class="timeline-images" v-if="item.images && item.images.length > 0">
                        <el-image
                          v-for="(img, idx) in item.images.slice(0, 3)"
                          :key="idx"
                          :src="img"
                          :preview-src-list="item.images"
                          fit="cover"
                          style="width: 100px; height: 100px; margin-right: 10px;"
                        />
                      </div>
                    </div>
                  </el-timeline-item>
                </el-timeline>
              </el-card>
            </el-col>

            <!-- Sidebar -->
            <el-col :xs="24" :md="8">
              <!-- Donate Card -->
              <el-card class="donate-card mb-4">
                <template #header>
                  <h3>立即捐赠</h3>
                </template>
                <el-button type="primary" size="large" @click="donate" style="width: 100%;">
                  我要捐赠
                </el-button>
              </el-card>

              <!-- Donation Records -->
              <el-card class="donors-card mb-4" v-if="project.donations && project.donations.length > 0">
                <template #header>
                  <h3>爱心榜</h3>
                </template>
                <div class="donors-list">
                  <div
                    v-for="donation in project.donations.slice(0, 10)"
                    :key="donation.id"
                    class="donor-item"
                  >
                    <span class="donor-name">{{ donation.donor_name }}</span>
                    <span class="donor-amount">{{ formatAmount(donation.amount) }}元</span>
                  </div>
                </div>
              </el-card>

              <!-- Related Projects -->
              <el-card v-if="project.related_projects && project.related_projects.length > 0">
                <template #header>
                  <h3>相关项目</h3>
                </template>
                <div class="related-projects">
                  <div
                    v-for="related in project.related_projects"
                    :key="related.id"
                    class="related-item"
                    @click="$router.push(`/project/${related.id}`)"
                  >
                    <img
                      :src="related.cover_image || 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'"
                      :alt="related.title"
                      @error="handleRelatedImageError"
                    />
                    <div class="related-info">
                      <h4>{{ truncateText(related.title, 30) }}</h4>
                      <el-progress
                        :percentage="related.progress_percentage"
                        :show-text="false"
                        :stroke-width="6"
                      />
                    </div>
                  </div>
                </div>
              </el-card>
            </el-col>
          </el-row>
        </div>
      </section>
    </div>

    <!-- Empty State -->
    <el-empty v-else-if="!loading" description="项目不存在" />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { projectsApi } from '@/api/projects'
import type { ProjectDetail } from '@/types'
import { formatAmount, truncateText } from '@/utils/format'

const route = useRoute()
const router = useRouter()

const loading = ref(false)
const project = ref<ProjectDetail>()

const loadProject = async () => {
  loading.value = true
  try {
    const id = Number(route.params.id)
    project.value = await projectsApi.getDetail(id)
  } catch (error) {
    console.error('Failed to load project:', error)
  } finally {
    loading.value = false
  }
}

const donate = () => {
  router.push(`/donate?project_id=${route.params.id}`)
}

const handleRelatedImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    img.src = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'
    img.onerror = null
  }
}

onMounted(() => {
  loadProject()
})
</script>

<style scoped lang="scss">
.project-detail-page {
  background: #f5f7fa;
}

.page-header {
  position: relative;
  background-size: cover;
  background-position: center;
  min-height: 300px;

  &::before {
    content: '';
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
  }
}

.header-overlay {
  position: relative;
  z-index: 1;
  color: #fff;
  padding: 60px 20px;

  h1 {
    font-size: 36px;
    margin: 20px 0;
  }

  .header-subtitle {
    font-size: 18px;
    opacity: 0.9;
  }
}

.section {
  padding: 40px 20px;
}

.container {
  max-width: 1200px;
  margin: 0 auto;
}

.mb-4 {
  margin-bottom: 20px;
}

.progress-card {
  :deep(.el-card__header) {
    h3 {
      margin: 0;
      font-size: 18px;
    }
  }
}

.progress-stats {
  display: flex;
  gap: 40px;
  align-items: center;
}

.progress-details {
  flex: 1;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
}

.detail-item {
  text-align: center;

  .label {
    display: block;
    font-size: 14px;
    color: #666;
    margin-bottom: 8px;
  }

  .value {
    display: block;
    font-size: 20px;
    font-weight: 600;
    color: #333;
  }
}

.percentage-value {
  font-size: 24px;
  font-weight: 600;
  color: #67c23a;
}

.project-content {
  line-height: 1.8;
  color: #333;
}

.timeline-content {
  h4 {
    margin: 0 0 10px;
    font-size: 16px;
  }

  p {
    color: #666;
    margin-bottom: 10px;
  }

  .timeline-images {
    margin-top: 10px;
  }
}

.donate-card {
  :deep(.el-card__header) {
    background: #67c23a;
    color: #fff;

    h3 {
      margin: 0;
    }
  }
}

.donors-list {
  max-height: 400px;
  overflow-y: auto;
}

.donor-item {
  display: flex;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid #f0f0f0;

  &:last-child {
    border-bottom: none;
  }

  .donor-name {
    color: #333;
  }

  .donor-amount {
    color: #67c23a;
    font-weight: 600;
  }
}

.related-projects {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.related-item {
  display: flex;
  gap: 10px;
  cursor: pointer;
  padding: 10px;
  border-radius: 4px;
  transition: background 0.3s;

  &:hover {
    background: #f5f7fa;
  }

  img {
    width: 80px;
    height: 60px;
    object-fit: cover;
    border-radius: 4px;
  }

  .related-info {
    flex: 1;

    h4 {
      margin: 0 0 8px;
      font-size: 14px;
    }
  }
}

@media (max-width: 768px) {
  .header-overlay {
    padding: 40px 20px;

    h1 {
      font-size: 24px;
    }
  }

  .progress-stats {
    flex-direction: column;
  }

  .progress-details {
    grid-template-columns: 1fr;
  }
}
</style>
