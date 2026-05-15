<template>
  <div class="dashboard">
    <div class="dashboard-header">
      <div class="header-content">
        <h2>控制面板</h2>
        <p class="welcome">欢迎回来，{{ currentUser?.username || '管理员' }}！今天是 {{ currentDate }}</p>
      </div>
      <div class="header-actions">
        <el-button type="primary" @click="$router.push('/admin/articles')">
          <Icon name="add" :size="18" />
          快速发布
        </el-button>
      </div>
    </div>

    <!-- 统计卡片 -->
    <el-row :gutter="20" class="stats-row">
      <el-col :xs="24" :sm="12" :lg="6" v-for="(stat, index) in stats" :key="index">
        <div class="stat-card" :class="`stat-${index}`" @click="handleStatClick(stat)">
          <div class="stat-icon" :style="{ background: stat.color }">
            <Icon :name="stat.icon" :size="28" color="white" />
          </div>
          <div class="stat-content">
            <div class="stat-value">{{ stat.value }}</div>
            <div class="stat-label">{{ stat.label }}</div>
            <div class="stat-change">
              <span>{{ stat.change }}</span>
            </div>
          </div>
        </div>
      </el-col>
    </el-row>

    <!-- 快捷操作 -->
    <el-row :gutter="20" class="info-row">
      <el-col :xs="24" :lg="24">
        <el-card class="quick-actions-card" shadow="never">
          <template #header>
            <div class="card-header">
              <div class="header-left">
                <Icon name="settings" :size="18" color="#ff5000" class="header-icon" />
                <span>快捷操作</span>
              </div>
            </div>
          </template>
          <div class="quick-actions">
            <div class="quick-action-item" @click="router.push('/admin/articles')">
              <div class="action-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                <Icon name="article" :size="24" color="white" />
              </div>
              <span>发布文章</span>
            </div>
            <div class="quick-action-item" @click="router.push('/admin/projects')">
              <div class="action-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)">
                <Icon name="folder" :size="24" color="white" />
              </div>
              <span>新建项目</span>
            </div>
            <div class="quick-action-item" @click="router.push('/admin/slides')">
              <div class="action-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)">
                <Icon name="image" :size="24" color="white" />
              </div>
              <span>轮播图管理</span>
            </div>
            <div class="quick-action-item" @click="router.push('/admin/partners')">
              <div class="action-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)">
                <Icon name="users" :size="24" color="white" />
              </div>
              <span>合作伙伴</span>
            </div>
            <div class="quick-action-item" @click="router.push('/admin/categories')">
              <div class="action-icon" style="background: linear-gradient(135deg, #ffa726 0%, #fb8c00 100%)">
                <Icon name="folder" :size="24" color="white" />
              </div>
              <span>分类管理</span>
            </div>
            <div class="quick-action-item" @click="router.push('/admin/navigation')">
              <div class="action-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%)">
                <Icon name="navigation" :size="24" color="white" />
              </div>
              <span>导航菜单</span>
            </div>
          </div>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { useRouter } from 'vue-router'
import Icon from '@/components/Icon.vue'
import * as adminApi from '@/api/admin'

const router = useRouter()
const currentUser = ref<any>(null)
const currentDate = ref('')
const loading = ref(false)

// 处理统计卡片点击
const handleStatClick = (stat: any) => {
  if (stat.link) {
    router.push(stat.link)
  }
}

// 统计数据 - 从 API 获取
const stats = ref([
  {
    icon: 'article',
    label: '文章总数',
    value: '0',
    change: '篇内容',
    trend: 'up',
    color: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
    link: '/admin/articles'
  },
  {
    icon: 'folder',
    label: '项目总数',
    value: '0',
    change: '个项目',
    trend: 'up',
    color: 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
    link: '/admin/projects'
  },
  {
    icon: 'users',
    label: '合作伙伴',
    value: '0',
    change: '家机构',
    trend: 'up',
    color: 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
    link: '/admin/partners'
  },
  {
    icon: 'navigation',
    label: '导航菜单',
    value: '0',
    change: '个菜单',
    trend: 'up',
    color: 'linear-gradient(135deg, #ffa726 0%, #fb8c00 100%)',
    link: '/admin/navigation'
  }
])

// 加载统计数据
const loadStats = async () => {
  try {
    loading.value = true

    // 并行获取所有统计数据
    const [articlesRes, projectsRes, partnersRes, navigationRes] = await Promise.allSettled([
      adminApi.getArticles({ page: 1, per_page: 1 }),
      adminApi.getProjects({ page: 1, per_page: 1 }),
      adminApi.getPartners({ page: 1, per_page: 1 }),
      adminApi.getNavigation({ position: 'header' })
    ])

    // 提取文章总数
    if (articlesRes.status === 'fulfilled' && articlesRes.value) {
      let total = 0

      if (articlesRes.value?.meta?.total) {
        total = articlesRes.value.meta.total
      } else if (articlesRes.value?.total) {
        total = articlesRes.value.total
      } else if (articlesRes.value?.count) {
        total = articlesRes.value.count
      } else if (Array.isArray(articlesRes.value?.items)) {
        total = articlesRes.value.items.length
      } else if (Array.isArray(articlesRes.value)) {
        total = articlesRes.value.length
      }

      stats.value[0].value = total.toString()
    }

    // 提取项目总数
    if (projectsRes.status === 'fulfilled' && projectsRes.value) {
      let total = 0

      if (projectsRes.value?.meta?.total) {
        total = projectsRes.value.meta.total
      } else if (projectsRes.value?.total) {
        total = projectsRes.value.total
      } else if (projectsRes.value?.count) {
        total = projectsRes.value.count
      } else if (Array.isArray(projectsRes.value?.items)) {
        total = projectsRes.value.items.length
      } else if (Array.isArray(projectsRes.value)) {
        total = projectsRes.value.length
      }

      stats.value[1].value = total.toString()
    }

    // 提取合作伙伴总数
    if (partnersRes.status === 'fulfilled' && partnersRes.value) {
      let total = 0

      // 尝试从不同的字段获取总数
      if (partnersRes.value?.meta?.total) {
        total = partnersRes.value.meta.total
      } else if (partnersRes.value?.total) {
        total = partnersRes.value.total
      } else if (partnersRes.value?.count) {
        total = partnersRes.value.count
      } else if (Array.isArray(partnersRes.value?.items)) {
        total = partnersRes.value.items.length
      } else if (Array.isArray(partnersRes.value)) {
        total = partnersRes.value.length
      }

      stats.value[2].value = total.toString()
    }

    // 提取导航菜单总数（计算所有菜单项）
    if (navigationRes.status === 'fulfilled' && navigationRes.value) {
      let count = 0
      if (Array.isArray(navigationRes.value)) {
        // 递归计算所有菜单项数量
        const countItems = (items: any[]) => {
          items.forEach(item => {
            count++
            if (item.children && Array.isArray(item.children)) {
              countItems(item.children)
            }
          })
        }
        countItems(navigationRes.value)
      }
      stats.value[3].value = count.toString()
    }

  } catch (error) {
    console.error('加载统计数据失败:', error)
    ElMessage.warning('统计数据加载失败')
  } finally {
    loading.value = false
  }
}

// 更新日期
const updateDate = () => {
  const now = new Date()
  currentDate.value = now.toLocaleDateString('zh-CN', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long'
  })
}

onMounted(() => {
  const userStr = localStorage.getItem('admin_user')
  if (userStr) {
    try {
      currentUser.value = JSON.parse(userStr)
    } catch (e) {
      console.error('解析用户信息失败:', e)
    }
  }

  // 加载真实数据
  loadStats()
  updateDate()
})
</script>

<style lang="scss" scoped>
.dashboard {
  .dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;

    .header-content {
      flex: 1;

      h2 {
        font-size: 28px;
        color: #1a1a1a;
        margin-bottom: 8px;
        font-weight: 700;
        background: linear-gradient(135deg, #1a1a1a 0%, #666 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
      }

      .welcome {
        font-size: 14px;
        color: #666;
        margin: 0;
      }
    }
  }

  .stats-row {
    margin-bottom: 20px;
  }

  .stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    height: 100%;
    position: relative;
    overflow: hidden;

    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, transparent 0%, rgba(255, 255, 255, 0.5) 100%);
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    &:hover {
      transform: translateY(-8px);
      box-shadow: 0 12px 32px rgba(0, 0, 0, 0.15);

      &::before {
        opacity: 1;
      }

      .stat-icon {
        transform: scale(1.1) rotate(5deg);
      }
    }

    .stat-icon {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      flex-shrink: 0;
      transition: transform 0.3s ease;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }

    .stat-content {
      flex: 1;

      .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #1a1a1a;
        line-height: 1;
        margin-bottom: 8px;
      }

      .stat-label {
        font-size: 14px;
        color: #666;
        margin-bottom: 8px;
      }

      .stat-change {
        display: flex;
        align-items: center;
        gap: 4px;
        font-size: 12px;
        font-weight: 500;

        &.up {
          color: #67c23a;
        }

        &.down {
          color: #f56c6c;
        }
      }
    }
  }

  .quick-actions-card {
    height: 100%;
    border-radius: 12px;
    border: 1px solid #f0f0f0;

    :deep(.el-card__header) {
      border-bottom: 1px solid #f0f0f0;
      padding: 20px 24px;
    }

    :deep(.el-card__body) {
      padding: 24px;
    }
  }

  .quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 16px;

    .quick-action-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

      &:hover {
        background: #fff;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        transform: translateY(-4px);

        .action-icon {
          transform: scale(1.1);
        }
      }

      .action-icon {
        width: 56px;
        height: 56px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 12px;
        transition: transform 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      }

      span {
        font-size: 14px;
        font-weight: 500;
        color: #333;
        text-align: center;
      }
    }
  }

  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;

    .header-left {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 600;
      color: #1a1a1a;

      .header-icon {
        display: inline-flex;
        align-items: center;
      }
    }
  }
}

// 响应式
@media (max-width: 768px) {
  .dashboard {
    .stat-card {
      margin-bottom: 12px;
    }
  }
}
</style>
