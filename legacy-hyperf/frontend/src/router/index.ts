import { createRouter, createWebHistory } from 'vue-router'
import type { RouteRecordRaw } from 'vue-router'
import { isMobileDevice, isMobileWidth } from '@/utils/device'

console.log('Router module loaded')

const routes: RouteRecordRaw[] = [
  // 前台路由 - 移动端首页
  {
    path: '/m',
    name: 'MobileHome',
    component: () => import('@/views/Home/Mobile.vue'),
    meta: { title: '首页 - 建辉慈善基金会', isMobile: true }
  },
  // 前台路由 - 桌面端首页
  {
    path: '/',
    name: 'Home',
    component: () => import('@/views/Home/index.vue'),
    meta: { title: '首页 - 建辉慈善基金会', isDesktop: true }
  },
  {
    path: '/test-static',
    name: 'TestStatic',
    component: () => import('@/views/TestStatic.vue'),
    meta: { title: '静态测试 - 建辉慈善基金会' }
  },
  {
    path: '/test',
    name: 'Test',
    component: () => import('@/views/Home/index.vue'),
    meta: { title: '测试页 - 建辉慈善基金会' }
  },
  {
    path: '/projects',
    name: 'Projects',
    component: () => import('@/views/Projects/Index.vue'),
    meta: { title: '项目动态 - 建辉慈善基金会' }
  },
  // 项目动态子页面（与项目动态共用组件，自动选中对应子分类）
  {
    path: '/projects/:slug',
    name: 'ProjectsDetail',
    component: () => import('@/views/Projects/Index.vue'),
    meta: { title: '项目动态 - 建辉慈善基金会' }
  },
  {
    path: '/project/:id',
    name: 'ProjectDetail',
    component: () => import('@/views/Projects/Detail.vue'),
    meta: { title: '项目详情 - 建辉慈善基金会' }
  },
  {
    path: '/articles',
    name: 'Articles',
    component: () => import('@/views/Articles/Index.vue'),
    meta: { title: '新闻中心 - 建辉慈善基金会' }
  },
  {
    path: '/articles/:category',
    name: 'ArticlesByCategory',
    component: () => import('@/views/Articles/Category.vue'),
    meta: { title: '文章分类 - 建辉慈善基金会' }
  },
  {
    path: '/article/:id',
    name: 'ArticleDetail',
    component: () => import('@/views/Articles/Detail.vue'),
    meta: { title: '文章详情 - 建辉慈善基金会' }
  },
  {
    path: '/stories',
    name: 'Stories',
    component: () => import('@/views/Stories/Index.vue'),
    meta: { title: '生命故事 - 建辉慈善基金会' }
  },
  {
    path: '/story/:id',
    name: 'StoryDetail',
    component: () => import('@/views/Stories/Detail.vue'),
    meta: { title: '故事详情 - 建辉慈善基金会' }
  },
  {
    path: '/donate',
    name: 'Donate',
    component: () => import('@/views/Donate/Index.vue'),
    meta: { title: '爱心捐赠 - 建辉慈善基金会' }
  },
  {
    path: '/donation-disclosure',
    name: 'DonationDisclosure',
    component: () => import('@/views/Donate/DonationDisclosure.vue'),
    meta: { title: '捐赠披露 - 建辉慈善基金会' }
  },
  {
    path: '/about',
    name: 'About',
    component: () => import('@/views/About/index.vue'),
    meta: { title: '关于我们 - 建辉慈善基金会' }
  },
  // 关于我们子页面（与关于我们共用组件，自动选中对应子分类）
  {
    path: '/about/:slug',
    name: 'AboutDetail',
    component: () => import('@/views/About/index.vue'),
    meta: { title: '关于我们 - 建辉慈善基金会' }
  },
  {
    path: '/contact',
    name: 'Contact',
    component: () => import('@/views/Contact.vue'),
    meta: { title: '联系我们 - 建辉慈善基金会' }
  },
  {
    path: '/privacy',
    name: 'Privacy',
    component: () => import('@/views/Privacy.vue'),
    meta: { title: '隐私政策 - 建辉慈善基金会' }
  },
  {
    path: '/disclosure',
    name: 'Disclosure',
    component: () => import('@/views/Disclosure/Index.vue'),
    meta: { title: '信息公开 - 建辉慈善基金会' }
  },
  // 信息公开子页面（与信息公开共用组件，自动选中对应子分类）
  {
    path: '/disclosure/:slug',
    name: 'DisclosureDetail',
    component: () => import('@/views/Disclosure/Index.vue'),
    meta: { title: '信息公开 - 建辉慈善基金会' }
  },
  {
    path: '/life-stories',
    name: 'LifeStories',
    component: () => import('@/views/LifeStories/Index.vue'),
    meta: { title: '生命故事 - 建辉慈善基金会' }
  },
  {
    path: '/life-stories/:slug',
    name: 'LifeStoriesDetail',
    component: () => import('@/views/Articles/Subcategory.vue'),
    meta: { title: '生命故事详情 - 建辉慈善基金会' }
  },
  {
    path: '/join-us',
    name: 'JoinUs',
    component: () => import('@/views/JoinUs/Index.vue'),
    meta: { title: '加入我们 - 建辉慈善基金会' }
  },
  // 加入我们子页面（与加入我们共用组件，自动选中对应子分类）
  {
    path: '/join-us/:slug',
    name: 'JoinUsDetail',
    component: () => import('@/views/JoinUs/Index.vue'),
    meta: { title: '加入我们 - 建辉慈善基金会' }
  },
  {
    path: '/partners',
    name: 'Partners',
    component: () => import('@/views/Partners/Index.vue'),
    meta: { title: '合作伙伴 - 建辉慈善基金会' }
  },
  {
    path: '/find-good-people',
    name: 'FindGoodPeople',
    component: () => import('@/views/FindGoodPeople/Index.vue'),
    meta: { title: '发现行善者 - 建辉慈善基金会' }
  },
  {
    path: '/search',
    name: 'Search',
    component: () => import('@/views/Search/index.vue'),
    meta: { title: '搜索 - 建辉慈善基金会' }
  },

  // 后台管理路由
  {
    path: '/admin/login',
    name: 'AdminLogin',
    component: () => import('@/views/Admin/Login.vue'),
    meta: { title: '后台登录 - 建辉慈善基金会' }
  },
  {
    path: '/admin',
    component: () => import('@/layouts/AdminLayout.vue'),
    meta: { requiresAuth: true },
    redirect: '/admin/dashboard',
    children: [
      {
        path: 'dashboard',
        name: 'AdminDashboard',
        component: () => import('@/views/Admin/Dashboard.vue'),
        meta: { title: '控制面板 - 后台管理' }
      },
      {
        path: 'articles',
        name: 'AdminArticles',
        component: () => import('@/views/Admin/Articles.vue'),
        meta: { title: '文章管理 - 后台管理' }
      },
      {
        path: 'articles/new',
        name: 'AdminArticleNew',
        component: () => import('@/views/Admin/ArticleEditor.vue'),
        meta: { title: '新建文章 - 后台管理' }
      },
      {
        path: 'articles/:id',
        name: 'AdminArticleEdit',
        component: () => import('@/views/Admin/ArticleEditor.vue'),
        meta: { title: '编辑文章 - 后台管理' }
      },
      {
        path: 'projects',
        name: 'AdminProjects',
        component: () => import('@/views/Admin/Projects.vue'),
        meta: { title: '项目管理 - 后台管理' }
      },
      {
        path: 'slides',
        name: 'AdminSlides',
        component: () => import('@/views/Admin/Slides.vue'),
        meta: { title: '轮播图管理 - 后台管理' }
      },
      {
        path: 'navigation',
        name: 'AdminNavigation',
        component: () => import('@/views/Admin/Navigation.vue'),
        meta: { title: '导航管理 - 后台管理' }
      },
      {
        path: 'categories',
        name: 'AdminCategories',
        component: () => import('@/views/Admin/Categories.vue'),
        meta: { title: '分类管理 - 后台管理' }
      },
      {
        path: 'partners',
        name: 'AdminPartners',
        component: () => import('@/views/Admin/Partners.vue'),
        meta: { title: '合作伙伴管理 - 后台管理' }
      },
      {
        path: 'partner-categories',
        name: 'AdminPartnerCategories',
        component: () => import('@/views/Admin/PartnerCategories.vue'),
        meta: { title: '合作伙伴分类 - 后台管理' }
      },
      {
        path: 'debug-partners',
        name: 'DebugPartners',
        component: () => import('@/views/Admin/DebugPartners.vue'),
        meta: { title: '合作伙伴调试 - 后台管理' }
      },
      {
        path: 'test-api',
        name: 'TestApi',
        component: () => import('@/views/Admin/TestApi.vue'),
        meta: { title: 'API测试 - 后台管理' }
      },
      {
        path: 'donations',
        name: 'AdminDonations',
        component: () => import('@/views/Admin/Donations.vue'),
        meta: { title: '捐赠管理 - 后台管理' }
      },
      {
        path: 'donations/disclosure',
        name: 'AdminDonationDisclosure',
        component: () => import('@/views/Admin/DonationDisclosures.vue'),
        meta: { title: '捐赠披露 - 后台管理' }
      },
      {
        path: 'invoices/applications',
        name: 'AdminInvoiceApplications',
        component: () => import('@/views/Admin/InvoiceApplications.vue'),
        meta: { title: '开票申请 - 后台管理' }
      },
      {
        path: 'invoices/list',
        name: 'AdminInvoices',
        component: () => import('@/views/Admin/Invoices.vue'),
        meta: { title: '发票管理 - 后台管理' }
      },
      {
        path: 'settings',
        name: 'AdminSettings',
        component: () => import('@/views/Admin/ComingSoon.vue'),
        meta: { title: '基本设置 - 后台管理' }
      },
      {
        path: 'users',
        name: 'AdminUsers',
        component: () => import('@/views/Admin/ComingSoon.vue'),
        meta: { title: '用户管理 - 后台管理' }
      },
      {
        path: 'logs',
        name: 'AdminLogs',
        component: () => import('@/views/Admin/ComingSoon.vue'),
        meta: { title: '操作日志 - 后台管理' }
      },
      {
        path: 'pages',
        name: 'AdminPages',
        component: () => import('@/views/Admin/ComingSoon.vue'),
        meta: { title: '页面管理 - 后台管理' }
      }
    ]
  },

  // 404页面
  {
    path: '/:pathMatch(.*)*',
    name: 'NotFound',
    component: () => import('@/views/NotFound.vue'),
    meta: { title: '页面未找到 - 建辉慈善基金会' }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes,
  scrollBehavior() {
    return { top: 0 }
  }
})

// 路由守卫 - 设备检测和自动跳转
router.beforeEach((to, from, next) => {
  console.log('Navigating to:', to.path)

  // 检测是否为移动设备
  const isMobile = isMobileDevice() || isMobileWidth()

  // 访问首页时，根据设备类型自动跳转
  if (to.path === '/' || to.path === '/m') {
    // 自动检测并跳转
    if (isMobile && to.path === '/') {
      return next({ path: '/m' })
    } else if (!isMobile && to.path === '/m') {
      return next({ path: '/' })
    }
  }

  // 设置页面标题
  document.title = to.meta.title as string || '建辉慈善基金会'
  next()
})

// 路由加载完成
router.isReady().then(() => {
  console.log('Router is ready')
})

export default router
