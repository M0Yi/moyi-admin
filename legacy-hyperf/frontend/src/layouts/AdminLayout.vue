<template>
  <div class="admin-layout">
    <!-- 侧边栏 -->
    <aside class="admin-sidebar" :class="{ collapsed: sidebarCollapsed }">
      <div class="sidebar-header">
        <div class="logo-wrapper">
          <div class="logo-icon">
            <Icon name="heart" :size="32" color="#ff5000" />
          </div>
          <transition name="fade">
            <h1 v-show="!sidebarCollapsed">建辉慈善</h1>
          </transition>
        </div>
        <div class="collapse-trigger" @click="toggleSidebar">
          <Icon :name="sidebarCollapsed ? 'unfold' : 'fold'" :size="20" color="rgba(255,255,255,0.6)" />
        </div>
      </div>

      <div class="sidebar-menu-wrapper">
        <el-menu
          :default-active="activeMenu"
          :collapse="sidebarCollapsed"
          :unique-opened="true"
          :collapse-transition="false"
          router
          class="sidebar-menu"
        >
          <el-menu-item index="/admin/dashboard">
            <template #title>
              <Icon name="dashboard" :size="18" />
              <span>控制面板</span>
            </template>
          </el-menu-item>

          <el-sub-menu index="content">
            <template #title>
              <Icon name="article" :size="18" />
              <span>内容管理</span>
            </template>
            <el-menu-item index="/admin/articles">
              <Icon name="articles" :size="16" />
              <span>文章管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/projects">
              <Icon name="folder" :size="16" />
              <span>项目管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/slides">
              <Icon name="image" :size="16" />
              <span>轮播图管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/navigation">
              <Icon name="navigation" :size="16" />
              <span>导航管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/categories">
              <Icon name="folder" :size="16" />
              <span>分类管理</span>
            </el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="partners">
            <template #title>
              <Icon name="link" :size="18" />
              <span>合作伙伴</span>
            </template>
            <el-menu-item index="/admin/partners">
              <Icon name="users" :size="16" />
              <span>合作伙伴管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/partner-categories">
              <Icon name="folder" :size="16" />
              <span>分类管理</span>
            </el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="donation">
            <template #title>
              <Icon name="donations" :size="18" />
              <span>捐赠管理</span>
            </template>
            <el-menu-item index="/admin/donations">
              <Icon name="list" :size="16" />
              <span>捐赠记录</span>
            </el-menu-item>
            <el-menu-item index="/admin/donations/disclosure">
              <Icon name="file" :size="16" />
              <span>捐赠披露</span>
            </el-menu-item>
            <el-menu-item index="/admin/invoices/applications">
              <Icon name="document" :size="16" />
              <span>开票申请</span>
            </el-menu-item>
            <el-menu-item index="/admin/invoices/list">
              <Icon name="ticket" :size="16" />
              <span>发票管理</span>
            </el-menu-item>
          </el-sub-menu>

          <el-sub-menu index="system">
            <template #title>
              <Icon name="settings" :size="18" />
              <span>系统设置</span>
            </template>
            <el-menu-item index="/admin/settings">
              <Icon name="tools" :size="16" />
              <span>基本设置</span>
            </el-menu-item>
            <el-menu-item index="/admin/users">
              <Icon name="users" :size="16" />
              <span>用户管理</span>
            </el-menu-item>
            <el-menu-item index="/admin/logs">
              <Icon name="log" :size="16" />
              <span>操作日志</span>
            </el-menu-item>
          </el-sub-menu>
        </el-menu>
      </div>

      <!-- 侧边栏底部 -->
      <div class="sidebar-footer">
        <transition name="fade">
          <div v-show="!sidebarCollapsed" class="footer-content">
            <p>让善良的人</p>
            <p>被这个世界温柔以待</p>
          </div>
        </transition>
      </div>
    </aside>

    <!-- 主内容区 -->
    <div class="admin-main">
      <!-- 顶部栏 -->
      <header class="admin-header">
        <div class="header-left">
          <el-breadcrumb separator="/">
            <el-breadcrumb-item v-for="item in breadcrumbs" :key="item.path" :to="{ path: item.path }">
              {{ item.title }}
            </el-breadcrumb-item>
          </el-breadcrumb>
        </div>

        <div class="header-right">
          <!-- 快捷操作 -->
          <div class="header-actions">
            <el-tooltip content="刷新页面" placement="bottom">
              <el-button circle @click="handleRefresh">
                <Icon name="refresh" :size="18" />
              </el-button>
            </el-tooltip>

            <el-tooltip content="全屏" placement="bottom">
              <el-button circle @click="handleFullscreen">
                <Icon name="fullscreen" :size="18" />
              </el-button>
            </el-tooltip>

            <el-badge :value="notificationCount" :hidden="notificationCount === 0" class="notification-badge">
              <el-button circle @click="handleNotifications">
                <Icon name="bell" :size="18" />
              </el-button>
            </el-badge>
          </div>

          <!-- 用户信息 -->
          <el-dropdown trigger="click" @command="handleCommand" class="user-dropdown">
            <div class="user-info">
              <el-avatar :size="36" :src="userAvatar" class="user-avatar">
                <Icon name="user" :size="20" />
              </el-avatar>
              <div class="user-details">
                <span class="username">{{ currentUser?.username || '管理员' }}</span>
                <span class="user-role">超级管理员</span>
              </div>
              <Icon name="chevron-down" :size="14" class="dropdown-icon" />
            </div>
            <template #dropdown>
              <el-dropdown-menu>
                <el-dropdown-item command="profile">
                  <Icon name="user" :size="16" />
                  <span>个人资料</span>
                </el-dropdown-item>
                <el-dropdown-item command="settings">
                  <Icon name="settings" :size="16" />
                  <span>账户设置</span>
                </el-dropdown-item>
                <el-dropdown-item divided command="logout">
                  <Icon name="logout" :size="16" />
                  <span>退出登录</span>
                </el-dropdown-item>
              </el-dropdown-menu>
            </template>
          </el-dropdown>
        </div>
      </header>

      <!-- 内容区 -->
      <main class="admin-content">
        <router-view v-slot="{ Component }">
          <transition name="fade-slide" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import Icon from '@/components/Icon.vue'

const router = useRouter()
const route = useRoute()

const sidebarCollapsed = ref(false)
const userAvatar = ref('')
const currentUser = ref<any>(null)
const notificationCount = ref(5)

// 当前激活的菜单
const activeMenu = computed(() => {
  return route.path
})

// 面包屑
const breadcrumbs = computed(() => {
  const matched = route.matched.filter(item => item.meta && item.meta.title)
  return matched.map(item => ({
    path: item.path,
    title: (item.meta?.title as string) || '未知'
  }))
})

// 切换侧边栏
const toggleSidebar = () => {
  sidebarCollapsed.value = !sidebarCollapsed.value
}

// 刷新页面
const handleRefresh = () => {
  router.go(0)
}

// 全屏切换
const handleFullscreen = () => {
  if (!document.fullscreenElement) {
    document.documentElement.requestFullscreen()
  } else {
    document.exitFullscreen()
  }
}

// 通知
const handleNotifications = () => {
  ElMessage.info('暂无新通知')
  notificationCount.value = 0
}

// 处理用户菜单命令
const handleCommand = (command: string) => {
  switch (command) {
    case 'profile':
      ElMessage.info('个人资料功能开发中...')
      break
    case 'settings':
      ElMessage.info('账户设置功能开发中...')
      break
    case 'logout':
      handleLogout()
      break
  }
}

// 退出登录
const handleLogout = () => {
  ElMessageBox.confirm(
    '确定要退出登录吗？',
    '提示',
    {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      type: 'warning',
      confirmButtonClass: 'el-button--danger'
    }
  ).then(() => {
    localStorage.removeItem('admin_token')
    localStorage.removeItem('admin_user')
    ElMessage.success('已退出登录')
    router.push('/admin/login')
  }).catch(() => {
    // 用户取消
  })
}

// 初始化用户信息
const initUserInfo = () => {
  const userStr = localStorage.getItem('admin_user')
  if (userStr) {
    try {
      currentUser.value = JSON.parse(userStr)
    } catch (e) {
      console.error('解析用户信息失败:', e)
    }
  }
}

// 检查登录状态
const checkAuth = () => {
  const token = localStorage.getItem('admin_token')
  if (!token && route.path !== '/admin/login') {
    router.push('/admin/login')
  }
}

// 初始化
initUserInfo()
checkAuth()

// 监听路由变化
watch(
  () => route.path,
  () => {
    checkAuth()
  }
)
</script>

<style lang="scss" scoped>
.admin-layout {
  display: flex;
  min-height: 100vh;
  background: #f0f2f5;
}

.admin-sidebar {
  width: 260px;
  background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.08);
  position: relative;
  z-index: 100;

  &.collapsed {
    width: 70px;

    // 折叠状态下的菜单项居中
    :deep(.el-menu-item),
    :deep(.el-sub-menu__title) {
      padding: 0;
      justify-content: center;

      .iconify {
        margin-right: 0;
      }

      > span {
        display: none;
      }
    }
  }

  .sidebar-header {
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.25);

    .logo-wrapper {
      display: flex;
      align-items: center;
      gap: 12px;
      flex: 1;
      min-width: 0; // 防止flex子项溢出

      .logo-icon {
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
      }

      h1 {
        font-size: 18px;
        font-weight: 600;
        color: #fff;
        white-space: nowrap;
        margin: 0;
        letter-spacing: 0.5px;
        line-height: 1;
      }
    }

    .collapse-trigger {
      cursor: pointer;
      transition: all 0.3s ease;
      padding: 6px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;

      &:hover {
        background: rgba(255, 255, 255, 0.1);
      }

      &:active {
        transform: scale(0.95);
      }
    }
  }

  .sidebar-menu-wrapper {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;

    &::-webkit-scrollbar {
      width: 6px;
    }

    &::-webkit-scrollbar-track {
      background: transparent;
    }

    &::-webkit-scrollbar-thumb {
      background: rgba(255, 255, 255, 0.2);
      border-radius: 3px;

      &:hover {
        background: rgba(255, 255, 255, 0.3);
      }
    }
  }

  .sidebar-menu {
    border: none;
    background: transparent;
    padding: 12px;

    // 统一菜单项样式
    :deep(.el-menu-item),
    :deep(.el-sub-menu__title) {
      color: rgba(255, 255, 255, 0.75);
      margin-bottom: 4px;
      border-radius: 8px;
      transition: all 0.3s ease;
      height: 44px;
      line-height: 44px;
      padding: 0 16px;

      // 使用flexbox确保icon和文字对齐
      display: flex !important;
      align-items: center !important;

      // 移除Element Plus默认的padding
      > * {
        display: flex;
        align-items: center;
      }

      &:hover {
        background: rgba(255, 80, 0, 0.1) !important;
        color: #fff !important;
      }

      // Icon样式优化
      .iconify {
        font-size: 18px;
        width: 20px;
        height: 20px;
        flex-shrink: 0;
        margin-right: 10px;
      }

      // span（文字）样式
      > span {
        font-size: 14px;
        font-weight: 400;
        letter-spacing: 0.3px;
        flex: 1;
      }
    }

    // 激活状态
    :deep(.el-menu-item.is-active) {
      background: linear-gradient(135deg, #ff5000 0%, #ff6a1f 100%) !important;
      color: #fff !important;
      box-shadow: 0 4px 12px rgba(255, 80, 0, 0.3);
      font-weight: 500;

      .iconify {
        color: #fff;
      }
    }

    // 子菜单样式
    :deep(.el-sub-menu) {
      .el-sub-menu__title {
        &:hover {
          background: rgba(255, 255, 255, 0.05) !important;
        }

        // 展开箭头
        .el-sub-menu__icon-arrow {
          margin-left: auto;
          transition: transform 0.3s ease;
        }
      }

      &.is-opened {
        > .el-sub-menu__title {
          color: #fff;
          font-weight: 500;
        }
      }

      // 子菜单列表
      .el-menu {
        background: rgba(0, 0, 0, 0.2);
        padding: 4px 0;
        margin-top: 4px;

        .el-menu-item {
          padding-left: 52px !important;
          height: 40px;
          line-height: 40px;
          margin-bottom: 2px;

          &:hover {
            background: rgba(255, 80, 0, 0.15) !important;
          }

          &.is-active {
            background: rgba(255, 80, 0, 0.2) !important;
            font-weight: 500;
          }

          .iconify {
            font-size: 16px;
            width: 18px;
            height: 18px;
          }

          > span {
            font-size: 13px;
          }
        }
      }
    }
  }

  .sidebar-footer {
    padding: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.05);
    background: rgba(0, 0, 0, 0.2);

    .footer-content {
      text-align: center;
      color: rgba(255, 255, 255, 0.5);
      font-size: 12px;
      line-height: 1.8;
    }
  }
}

.admin-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.admin-header {
  height: 64px;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  position: sticky;
  top: 0;
  z-index: 99;

  .header-left {
    flex: 1;
  }

  .header-right {
    display: flex;
    align-items: center;
    gap: 16px;

    .header-actions {
      display: flex;
      align-items: center;
      gap: 8px;

      .el-button {
        width: 40px;
        height: 40px;
        border: 1px solid #e5e7eb;
        background: #fff;
        color: #606266;
        transition: all 0.3s ease;

        &:hover {
          border-color: #ff5000;
          color: #ff5000;
          background: #fff5f0;
        }
      }

      .notification-badge {
        :deep(.el-badge__content) {
          background: #ff5000;
          border: 2px solid #fff;
        }
      }
    }

    .user-dropdown {
      cursor: pointer;

      .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 6px 12px;
        border-radius: 8px;
        transition: all 0.3s ease;

        &:hover {
          background: #f5f7fa;
        }

        .user-avatar {
          background: linear-gradient(135deg, #ff5000 0%, #ff6a1f 100%);
          color: #fff;
          border: 2px solid #fff;
          box-shadow: 0 2px 8px rgba(255, 80, 0, 0.3);
        }

        .user-details {
          display: flex;
          flex-direction: column;
          align-items: flex-start;

          .username {
            font-size: 14px;
            font-weight: 500;
            color: #303133;
            line-height: 1.2;
          }

          .user-role {
            font-size: 12px;
            color: #909399;
            line-height: 1.2;
          }
        }

        .dropdown-icon {
          color: #909399;
          transition: transform 0.3s ease;
        }

        &:hover .dropdown-icon {
          transform: rotate(180deg);
        }
      }
    }
  }
}

.admin-content {
  flex: 1;
  padding: 24px;
  overflow-y: auto;

  &::-webkit-scrollbar {
    width: 8px;
  }

  &::-webkit-scrollbar-track {
    background: #f0f2f5;
  }

  &::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.2);
    border-radius: 4px;

    &:hover {
      background: rgba(0, 0, 0, 0.3);
    }
  }
}

// 动画
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

.fade-slide-enter-active,
.fade-slide-leave-active {
  transition: all 0.3s ease;
}

.fade-slide-enter-from {
  opacity: 0;
  transform: translateX(-10px);
}

.fade-slide-leave-to {
  opacity: 0;
  transform: translateX(10px);
}

// 响应式
@media (max-width: 768px) {
  .admin-sidebar {
    position: fixed;
    left: 0;
    top: 0;
    height: 100vh;
    z-index: 1000;
    transform: translateX(-100%);

    &.collapsed {
      width: 260px;
      transform: translateX(0);
    }
  }

  .admin-header {
    padding: 0 16px;

    .header-right {
      .user-details {
        display: none;
      }
    }
  }

  .admin-content {
    padding: 16px;
  }
}
</style>
