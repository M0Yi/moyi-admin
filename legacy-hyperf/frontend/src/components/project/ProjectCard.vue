<template>
  <div class="project-card">
    <div class="card-image" @click="viewDetail">
      <img :src="project.cover_image || 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'" :alt="project.title" @error="handleImageError" />
      <el-tag v-if="project.is_featured" class="featured-tag" type="danger">
        精选
      </el-tag>
      <el-tag
        :type="getProjectStatusColor(project.status)"
        class="status-tag"
        size="small"
      >
        {{ project.status_label }}
      </el-tag>
    </div>

    <div class="card-body">
      <div class="card-meta">
        <el-tag size="small">{{ project.project_type_label }}</el-tag>
      </div>

      <h3 class="card-title" @click="viewDetail">
        {{ project.title }}
      </h3>

      <p class="card-description">
        {{ truncateText(project.description, 80) }}
      </p>

      <!-- Progress -->
      <div class="card-progress">
        <div class="progress-header">
          <span class="progress-label">筹款进度</span>
          <span class="progress-value">{{ project.progress_percentage }}%</span>
        </div>
        <el-progress
          :percentage="project.progress_percentage"
          :color="'#67c23a'"
          :show-text="false"
        />
        <div class="progress-info">
          <span>已筹 {{ formatAmount(project.raised_amount) }}元</span>
          <span>目标 {{ formatAmount(project.target_amount) }}元</span>
        </div>
      </div>

      <!-- Beneficiaries -->
      <div class="card-stats">
        <span class="stats-icon">👤</span>
        <span>已帮助 {{ formatAmount(project.beneficiary_count) }} 人</span>
      </div>

      <!-- Actions -->
      <div class="card-actions">
        <el-button size="small" @click="viewDetail">查看详情</el-button>
        <el-button type="primary" size="small" @click="donate">
          爱心捐赠
        </el-button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'
import type { Project } from '@/types'
import { formatAmount, truncateText, getProjectStatusColor } from '@/utils/format'

interface Props {
  project: Project
}

const props = defineProps<Props>()
const router = useRouter()

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  if (!img.dataset.handled) {
    img.dataset.handled = 'true'
    img.src = 'https://cdn1.zhizhucms.com/materials/image/744/2026/3/25/1774429608049_788.png'
    img.onerror = null
  }
}

const viewDetail = () => {
  router.push(`/project/${props.project.id}`)
}

const donate = () => {
  router.push(`/donate?project_id=${props.project.id}`)
}
</script>

<style scoped lang="scss">
.project-card {
  background: #fff;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
  transition: all 0.3s;
  height: 100%;
  display: flex;
  flex-direction: column;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
  }
}

.card-image {
  position: relative;
  height: 200px;
  overflow: hidden;
  cursor: pointer;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
  }

  &:hover img {
    transform: scale(1.05);
  }

  .featured-tag {
    position: absolute;
    top: 10px;
    right: 10px;
  }

  .status-tag {
    position: absolute;
    top: 10px;
    left: 10px;
  }
}

.card-body {
  padding: 20px;
  flex: 1;
  display: flex;
  flex-direction: column;
}

.card-meta {
  margin-bottom: 10px;
}

.card-title {
  font-size: 18px;
  font-weight: 600;
  margin: 0 0 10px;
  cursor: pointer;
  transition: color 0.3s;

  &:hover {
    color: #409eff;
  }
}

.card-description {
  color: #666;
  font-size: 14px;
  line-height: 1.6;
  margin-bottom: 15px;
  flex: 1;
}

.card-progress {
  margin-bottom: 15px;

  .progress-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
    font-size: 14px;
  }

  .progress-label {
    color: #666;
  }

  .progress-value {
    font-weight: 600;
    color: #67c23a;
  }

  .progress-info {
    display: flex;
    justify-content: space-between;
    margin-top: 8px;
    font-size: 12px;
    color: #999;
  }
}

.card-stats {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 15px;
  font-size: 14px;
  color: #666;

  .stats-icon {
    font-size: 16px;
  }
}

.card-actions {
  display: flex;
  gap: 10px;

  .el-button {
    flex: 1;
  }
}
</style>
