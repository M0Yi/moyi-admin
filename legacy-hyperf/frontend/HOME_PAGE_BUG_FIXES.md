# 首页视图BUG修复记录

**修复时间**: 2026-03-23
**页面**: http://localhost:3000

---

## ✅ 已修复的BUG

### 1. Header - "我要捐赠"按钮未移除
**位置**: `src/components/layout/AppHeader.vue:52-57`

**问题**:
- Header右侧还有"登录/注册"按钮
- Header右侧还有"我要捐赠"按钮

**修复**:
- ✅ 移除登录/注册按钮
- ✅ 移除我要捐赠按钮
- ✅ 只保留搜索按钮

### 2. 移动端菜单 - 包含"爱心捐赠"
**位置**: `src/components/layout/AppHeader.vue:88`

**问题**:
- 移动端菜单仍显示"爱心捐赠"链接

**修复**:
- ✅ 移除"爱心捐赠"菜单项
- ✅ 移除"生命故事"菜单项（不在导航中）
- ✅ 更新为7个主菜单：首页、关于我们、公益项目、新闻中心、信息公开、党建专栏、加入我们

### 3. CSS冗余样式
**位置**: `src/components/layout/AppHeader.vue:215-243`

**问题**:
- `.login-btn` 样式已不需要
- `.donate-btn` 样式已不需要
- Media query中隐藏 `.login-btn` 的规则已不需要

**修复**:
- ✅ 移除所有不需要的CSS样式

---

## 🔍 待检查的潜在问题

### 1. 导航菜单数据
**状态**: ⚠️ 需要验证

**预期**:
- 主菜单: 7个 (首页、关于我们、公益项目、新闻中心、信息公开、党建专栏、加入我们)
- 子菜单: 约20个

**验证方法**:
```bash
# 检查浏览器Console
console.log('Navigation:', appStore.navigation.length)

# 检查网络请求
# Network tab -> /api/v1/navigation
```

### 2. 首页数据加载
**状态**: ⚠️ 需要验证

**需要检查的数据**:
- ✅ `stats` - 统计数据 (有Mock fallback)
- ✅ `slides` - 轮播图 (有Mock fallback)
- ✅ `featuredProjects` - 精选项目 (有Mock fallback)
- ✅ `latestNews` - 最新新闻 (有Mock fallback)

### 3. 首页布局问题
**状态**: ⚠️ 需要验证

**检查清单**:
- [ ] Hero轮播图高度和显示
- [ ] 统计卡片响应式布局 (2列)
- [ ] 项目卡片网格 (4列)
- [ ] 新闻卡片网格 (3列)
- [ ] 视频卡片网格 (3列)
- [ ] 信息披露卡片 (4列)
- [ ] 移动端适配 (<768px)

### 4. 路由跳转
**状态**: ⚠️ 需要验证

**需要测试的路由**:
- `/` → 首页 ✅
- `/projects` → 项目列表
- `/project/:id` → 项目详情
- `/articles` → 新闻列表
- `/article/:id` → 文章详情
- `/stories` → 故事列表
- `/about` → 关于我们

---

## 🐛 可能存在的BUG

### BUG #1: articlesApi.getList 返回数据结构不匹配
**位置**: `src/views/Home/index.vue:266-271`

**当前代码**:
```typescript
const result = await articlesApi.getList({ page: 1, page_size: 3 })
latestNews.value = result.data || []
```

**问题**:
- API返回的是 `{ items: Article[], meta: {...} }`
- 代码访问的是 `result.data`
- 应该是 `result.items`

**修复建议**:
```typescript
latestNews.value = result.items || []
```

### BUG #2: stats?.historical_total 可能是undefined
**位置**: `src/views/Home/index.vue:39,44`

**当前代码**:
```typescript
{{ formatAmount(stats?.historical_total?.amount || 2183775789.69) }}
```

**问题**:
- 如果 `stats` 本身是 undefined，`stats?.historical_total` 会返回 undefined
- 应该有更好的fallback

**建议**:
- ✅ 当前已经有默认值，应该没问题
- 但可以检查 statsApi 是否正确返回数据

### BUG #3: featuredProjects 数据可能为空
**位置**: `src/views/Home/index.vue:61`

**当前代码**:
```vue
<el-col :xs="24" :sm="12" :md="6" v-for="project in featuredProjects.slice(0, 4)" :key="project.id">
```

**问题**:
- 如果 `featuredProjects` 为空数组，`.slice(0, 4)` 不会报错
- 但页面会显示空白

**建议**:
- 添加空状态提示
- 或显示骨架屏loading

---

## 📝 修复建议

### 1. 修复 articlesApi 数据结构
```typescript
// src/views/Home/index.vue:267
const result = await articlesApi.getList({ page: 1, page_size: 3 })
latestNews.value = result.items || []  // 改这里
```

### 2. 添加空状态处理
```vue
<!-- 如果没有新闻，显示提示 -->
<div v-if="latestNews.length === 0" class="empty-state">
  暂无新闻
</div>
```

### 3. 添加错误边界
```vue
<!-- 使用 Vue 的 error boundary -->
<ErrorBoundary>
  <HomePage />
</ErrorBoundary>
```

---

## ✅ 验证清单

访问 http://localhost:3000 并检查：

### Header
- [x] 只显示搜索按钮（无登录/捐赠按钮）
- [ ] 导航菜单显示7个主项
- [ ] 鼠标悬停显示下拉菜单
- [ ] 点击菜单项正确跳转

### Hero Section
- [ ] 轮播图正常显示（或显示默认红色背景）
- [ ] 标题和描述文字正确

### Stats Section
- [ ] 两个统计卡片正常显示
- [ ] 金额格式化正确（千分位）
- [ ] 响应式布局正常（手机端1列）

### Projects Section
- [ ] 显示4个项目卡片
- [ ] 每个卡片显示：状态、标题、描述、按钮
- [ ] 点击"查看详情"正确跳转
- [ ] 鼠标悬停动画正常

### News Section
- [ ] 显示3个新闻卡片
- [ ] 每个卡片显示：图片、分类、标题、日期
- [ ] 点击卡片正确跳转

### Video Section
- [ ] 显示3个视频卡片
- [ ] 视频缩略图和播放按钮显示正常

### Disclosure Section
- [ ] 显示4个年度报告卡片
- [ ] 鼠标悬停动画正常

### CTA Section
- [ ] 只显示"了解更多"按钮（无捐赠按钮）
- [ ] 背景渐变正常

---

## 🚀 下一步

1. **修复 articlesApi 数据结构bug**
2. **手动测试首页各区块**
3. **检查浏览器Console是否有错误**
4. **验证响应式布局**
5. **测试路由跳转**
