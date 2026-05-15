<template>
  <header class="app-header">
    <!-- ========== 上层：Logo + Slogan + 搜索 ========== -->
    <div class="header-top">
      <div class="header-top-inner">
        <!-- Logo -->
        <router-link to="/" class="header-logo">
          <img
            src="https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1770800772-a72d5fe8c5c55d8b.png"
            alt="建辉慈善基金会"
            class="logo-image"
          />
        </router-link>

        <!-- Slogan -->
        <p class="header-slogan">致敬行善者 让好人有好报</p>

        <!-- 右侧操作区 -->
        <div class="header-top-actions">
          <!-- 搜索 -->
          <div class="search-box">
            <input
              v-model="searchKeyword"
              type="text"
              placeholder="搜索"
              class="search-input"
              @keyup.enter="doSearch"
            />
            <button class="search-btn" @click="doSearch">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <circle cx="11" cy="11" r="8"/>
                <path d="M21 21L16.65 16.65"/>
              </svg>
            </button>
          </div>

          <!-- 登录 / 注册 -->
          <button class="top-action-btn" @click="$router.push('/donate')">登录</button>
          <button class="top-action-btn top-action-register" @click="$router.push('/donate')">注册</button>

          <!-- 移动端菜单按钮 -->
          <div class="mobile-menu-toggle" @click="mobileMenuOpen = !mobileMenuOpen">
            <span :class="{ 'is-open': mobileMenuOpen }" class="hamburger">
              <span></span>
              <span></span>
              <span></span>
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- ========== 下层：导航菜单（主题色背景） ========== -->
    <nav class="header-nav" v-if="appStore.navigation.length > 0">
      <div class="header-nav-inner">
        <ul class="nav-list">
          <li class="nav-item" v-for="item in appStore.navigation" :key="item.id">
            <!-- 无子菜单 -->
            <router-link
              v-if="!item.children || item.children.length === 0"
              :to="item.url"
              class="nav-link"
            >
              {{ item.name }}
            </router-link>

            <!-- 有子菜单 -->
            <router-link v-else :to="item.url" class="nav-link" :class="{ active: isDropdownActive(item) }">
              {{ item.name }}
            </router-link>
            <dl class="dropdown-menu" v-if="item.children && item.children.length > 0">
              <dd v-for="child in item.children" :key="child.id">
                <router-link :to="child.url">{{ child.name }}</router-link>
              </dd>
            </dl>
          </li>
        </ul>
      </div>
    </nav>

    <!-- 导航加载骨架 -->
    <nav class="header-nav" v-else>
      <div class="header-nav-inner">
        <ul class="nav-list">
          <li class="nav-item" v-for="i in 7" :key="i">
            <div class="nav-skeleton"></div>
          </li>
        </ul>
      </div>
    </nav>

    <!-- ========== 移动端抽屉菜单 ========== -->
    <el-drawer v-model="mobileMenuOpen" direction="ltr" size="280px" class="mobile-drawer">
      <template #header>
        <div class="drawer-header">
          <img
            src="https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1770800772-a72d5fe8c5c55d8b.png"
            alt="建辉慈善基金会"
            class="drawer-logo"
          />
        </div>
      </template>
      <nav class="mobile-nav">
        <ul class="mobile-nav-list">
          <li class="mobile-nav-item" v-for="item in appStore.navigation" :key="item.id">
            <!-- 无子菜单 -->
            <router-link
              v-if="!item.children || item.children.length === 0"
              :to="item.url"
              class="mobile-nav-link"
              @click="mobileMenuOpen = false"
            >
              {{ item.name }}
            </router-link>

            <!-- 有子菜单 -->
            <div v-else class="mobile-nav-group">
              <div class="mobile-nav-main">
                <router-link
                  :to="item.url"
                  class="mobile-nav-link main-item"
                  @click="mobileMenuOpen = false"
                >
                  {{ item.name }}
                </router-link>
                <div
                  class="mobile-submenu-toggle"
                  @click="toggleSubmenu(item.id)"
                  :class="{ expanded: expandedMenus.includes(item.id) }"
                >
                  <span class="toggle-icon">&#9662;</span>
                </div>
              </div>
              <ul
                class="mobile-submenu"
                :class="{ expanded: expandedMenus.includes(item.id) }"
                v-show="expandedMenus.includes(item.id)"
              >
                <li v-for="child in item.children" :key="child.id">
                  <router-link
                    :to="child.url"
                    class="mobile-submenu-link"
                    @click="mobileMenuOpen = false"
                  >
                    {{ child.name }}
                  </router-link>
                </li>
              </ul>
            </div>
          </li>
        </ul>
      </nav>
    </el-drawer>
  </header>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAppStore } from '@/stores/app'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()
const mobileMenuOpen = ref(false)
const expandedMenus = ref<number[]>([])
const searchKeyword = ref('')

const isDropdownActive = (item: any) => {
  if (!item.children) return false
  const currentPath = route.path
  if (currentPath === item.url) return true
  return item.children.some((child: any) => currentPath === child.url)
}

const toggleSubmenu = (itemId: number) => {
  const index = expandedMenus.value.indexOf(itemId)
  if (index > -1) {
    expandedMenus.value.splice(index, 1)
  } else {
    expandedMenus.value.push(itemId)
  }
}

const doSearch = () => {
  if (searchKeyword.value.trim()) {
    router.push(`/search?q=${encodeURIComponent(searchKeyword.value.trim())}`)
    searchKeyword.value = ''
  }
}

onMounted(async () => {
  try {
    await appStore.loadNavigation()
  } catch (error) {
    console.warn('Failed to load navigation')
  }
})
</script>

<style scoped lang="scss">
.app-header {
  position: sticky;
  top: 0;
  z-index: 1000;
}

/* ========== 上层：白色背景 ========== */
.header-top {
  background: #ffffff;
  border-bottom: 1px solid #f0f0f0;
}

.header-top-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
  height: 170px;
  display: flex;
  align-items: center;
  gap: 28px;
}

// Logo
.header-logo {
  display: flex;
  align-items: center;
  flex-shrink: 0;

  .logo-image {
    height: 145px;
    width: auto;
    display: block;
    object-fit: contain;
  }
}

// Slogan
.header-slogan {
  font-size: 15px;
  color: #888;
  margin: 0;
  font-weight: 500;
  white-space: nowrap;
  letter-spacing: 3px;
  border-left: 1px solid #e0e0e0;
  padding-left: 28px;
  line-height: 1.8;
}

// 右侧操作区
.header-top-actions {
  display: flex;
  align-items: center;
  gap: 14px;
  margin-left: auto;
  flex-shrink: 0;
}

// 搜索框
.search-box {
  display: flex;
  align-items: center;
  border: 1px solid #ddd;
  border-radius: 50px;
  overflow: hidden;
  transition: border-color 0.2s;

  &:focus-within {
    border-color: var(--color-primary);
  }

  .search-input {
    width: 180px;
    height: 38px;
    padding: 0 12px;
    border: none;
    outline: none;
    font-size: 14px;
    color: #333;
    background: transparent;

    &::placeholder {
      color: #bbb;
    }
  }

  .search-btn {
    width: 38px;
    height: 38px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: none;
    background: transparent;
    color: #999;
    cursor: pointer;
    transition: color 0.2s;
    flex-shrink: 0;

    &:hover {
      color: var(--color-primary);
    }
  }
}

// 登录/注册按钮
.top-action-btn {
  padding: 8px 22px;
  border: 1px solid #ddd;
  border-radius: 50px;
  background: #fff;
  color: #555;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    border-color: var(--color-primary);
    color: var(--color-primary);
  }
}

.top-action-register {
  background: var(--color-primary);
  border-color: var(--color-primary);
  color: #fff;

  &:hover {
    background: var(--color-primary-dark);
    border-color: var(--color-primary-dark);
    color: #fff;
  }
}

/* ========== 下层：主题色导航菜单 ========== */
.header-nav {
  background: var(--color-primary);
}

.header-nav-inner {
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px;
}

.nav-list {
  display: flex;
  list-style: none;
  margin: 0;
  padding: 0;
  justify-content: center;
}

.nav-item {
  position: relative;
  box-sizing: border-box;
}

.nav-link {
  display: block;
  padding: 20px 28px;
  text-decoration: none;
  color: rgba(255, 255, 255, 0.9);
  font-size: 16px;
  font-weight: 600;
  transition: all 0.25s;
  letter-spacing: 1px;
  position: relative;
  cursor: pointer;

  &:hover,
  &.active,
  &.router-link-active {
    color: var(--color-primary);
    background: #ffffff;
  }
}

// 下拉菜单 - 纯 CSS hover 控制
.dropdown-menu {
  display: none;
  position: absolute;
  top: 100%;
  left: 0;
  width: 100%;
  background: #ffffff;
  box-shadow: 0 10px 32px rgba(0, 0, 0, 0.18);
  margin: 0;
  padding: 0;
  list-style: none;
  z-index: 100;
  box-sizing: border-box;
  max-height: 0;
  overflow: hidden;
  font-family: "Chiron GoRound TC WS", 'PingFang SC', 'Hiragino Sans GB', sans-serif !important;

  dd {
    margin: 0;

    a {
      display: block;
      padding: 14px 28px;
      text-decoration: none;
      color: #444;
      font-size: 15px;
      font-weight: 500;
      transition: all 0.2s;
      white-space: nowrap;
      font-family: "Chiron GoRound TC WS", 'PingFang SC', 'Hiragino Sans GB', sans-serif !important;
      position: relative;
      border-bottom: 1px solid #f5f5f5;

      &:last-child {
        border-bottom: none;
      }

      &:hover,
      &.router-link-active {
        color: var(--color-primary);
        background: var(--color-primary-lighter);
      }
    }
  }
}

// hover 时显示下拉 - 卷轴展开效果
.nav-item:hover > .dropdown-menu {
  display: block;
  max-height: 500px;
  overflow: hidden;
  animation: dropDown 0.4s ease-out;
}

@keyframes dropDown {
  from {
    max-height: 0;
  }
  to {
    max-height: 500px;
  }
}

// 骨架屏
.nav-skeleton {
  width: 72px;
  height: 22px;
  background: linear-gradient(90deg, rgba(255,255,255,0.2) 25%, rgba(255,255,255,0.35) 50%, rgba(255,255,255,0.2) 75%);
  background-size: 200% 100%;
  animation: skeleton-loading 1.5s ease-in-out infinite;
  border-radius: 50px;
}

@keyframes skeleton-loading {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ========== 移动端 ========== */
.mobile-menu-toggle {
  display: none;
  cursor: pointer;
  padding: 8px;

  .hamburger {
    display: flex;
    flex-direction: column;
    gap: 5px;
    width: 24px;

    span {
      display: block;
      height: 2px;
      background: #333;
      border-radius: 50px;
      transition: all 0.3s ease;
    }

    &.is-open {
      span:nth-child(1) {
        transform: rotate(45deg) translate(5px, 5px);
      }
      span:nth-child(2) {
        opacity: 0;
      }
      span:nth-child(3) {
        transform: rotate(-45deg) translate(5px, -5px);
      }
    }
  }
}

@media (max-width: 1200px) {
  .header-nav {
    display: none;
  }

  .header-slogan {
    display: none;
  }

  .search-box {
    display: none;
  }

  .mobile-menu-toggle {
    display: block;
  }

  .header-top-inner {
    height: 70px;
    gap: 16px;
  }

  .header-logo .logo-image {
    height: 50px;
  }
}

/* ========== 移动端抽屉 ========== */
.drawer-header {
  padding: 16px 24px;

  .drawer-logo {
    width: 100%;
    max-width: 200px;
    height: auto;
    max-height: 56px;
    object-fit: contain;
  }
}

.mobile-nav-list {
  list-style: none;
  padding: 0;
  margin: 0;

  .mobile-nav-item {
    border-bottom: 1px solid #f3f4f6;

    .mobile-nav-link {
      display: block;
      padding: 16px 24px;
      text-decoration: none;
      color: #374151;
      font-size: 16px;
      font-weight: 600;
      transition: color 0.2s;

      &:hover,
      &.router-link-active {
        color: var(--color-primary);
      }
    }

    .mobile-nav-group {
      .mobile-nav-main {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 16px 24px;

        .main-item {
          flex: 1;
          text-decoration: none;
          color: #374151;
          font-size: 16px;
          font-weight: 600;
          padding: 0;

          &:hover,
          &.router-link-active {
            color: var(--color-primary);
          }
        }

        .mobile-submenu-toggle {
          padding: 8px;
          cursor: pointer;

          .toggle-icon {
            font-size: 12px;
            color: #9ca3af;
            transition: transform 0.3s ease;
            display: inline-block;
          }

          &.expanded .toggle-icon {
            transform: rotate(180deg);
          }
        }
      }

      .mobile-submenu {
        list-style: none;
        padding: 0;
        margin: 0;
        background: #f9fafb;

        li {
          border-top: 1px solid #f3f4f6;

          .mobile-submenu-link {
            display: block;
            padding: 14px 24px 14px 40px;
            text-decoration: none;
            color: #6b7280;
            font-size: 15px;
            font-weight: 500;
            transition: color 0.2s;

            &:hover,
            &.router-link-active {
              color: var(--color-primary);
            }
          }
        }
      }
    }
  }
}

:deep(.mobile-drawer.el-drawer) {
  border-radius: 0 12px 12px 0;

  .el-drawer__header {
    margin-bottom: 0;
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
  }

  .el-drawer__body {
    padding: 0;
    overflow-y: auto;
  }
}
</style>
