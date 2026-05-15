# 首页功能测试指南

## ✅ 首页已完成功能

### 1. 英雄区域（Hero Section）

**功能描述**:
- 轮播图展示（如果有轮播图数据）
- 默认渐变背景（当没有轮播图时）
- 响应式设计

**API 依赖**: `/api/v1/slides`

**数据结构**:
```typescript
{
  id: number
  title: string
  image: string | null  // 处理了空值情况
  link_url: string
  description: string
}
```

**容错处理**:
- ✅ 没有 slides 数据时显示默认英雄区域
- ✅ 轮播图 image 为 null 时使用渐变背景
- ✅ 图片加载失败时有备用方案

---

### 2. 统计卡片区域

**功能描述**:
- 4个统计卡片横向排列
- 历年累计捐赠金额
- 本年捐赠金额
- 爱心人士数量
- 受益人数

**API 依赖**: `/api/v1/stats/overview`

**数据结构**:
```typescript
{
  historical_total: {
    amount: number
    donor_count: number
    project_count: number
  }
  current_year: {
    amount: number
    donor_count: number
    project_count: number
  }
  beneficiaries: {
    total_count: number
    current_year_count: number
  }
}
```

**容错处理**:
- ✅ API 失败时显示 0 而不是空白
- ✅ 金额格式化（添加千分位）
- ✅ 响应式布局（移动端 2x2）

---

### 3. 精选项目区域

**功能描述**:
- 显示 6 个精选项目
- 使用 ProjectCard 组件
- "查看全部" 链接到项目列表页

**API 依赖**: `/api/v1/projects/featured`

**数据结构**:
```typescript
{
  items: Project[]
}
```

**容错处理**:
- ✅ 加载状态显示
- ✅ 空状态处理
- ✅ 项目卡片处理空图片

---

### 4. CTA 行动号召区域

**功能描述**:
- 渐变背景
- "立即捐赠" 主按钮
- "了解更多" 次要按钮
- 固定链接到相应页面

---

## 🧪 测试步骤

### 1. 本地开发测试

```bash
# 1. 确保后端运行
cd /Users/moyi/moyi-admin
php bin/hyperf.php start

# 2. 确保前端运行（新终端）
cd frontend
npm run dev

# 3. 访问首页
open http://localhost:3000/
```

### 2. 检查点清单

#### 英雄区域
- [ ] 有轮播图数据时显示轮播
- [ ] 无轮播图数据时显示默认英雄区域
- [ ] 点击"了解更多"按钮跳转正确
- [ ] 轮播自动播放（5秒间隔）
- [ ] 手动切换箭头可用

#### 统计卡片
- [ ] 显示 4 个统计卡片
- [ ] 金额格式化正确（例如：2,183,775.79元）
- [ ] 卡片悬停有动画效果
- [ ] 移动端响应式布局正常

#### 精选项目
- [ ] 显示 6 个项目卡片（如果有数据）
- [ ] 加载状态显示加载动画
- [ ] 点击项目卡片跳转到详情页
- [ ] 点击"立即捐赠"按钮跳转到捐赠页

#### CTA 区域
- [ ] 渐变背景显示正确
- [ ] 按钮点击跳转正确
- [ ] 响应式布局正常

---

## 🔍 调试技巧

### 检查 API 数据

**轮播图 API**:
```bash
curl http://localhost:6501/api/v1/slides
```

**精选项目 API**:
```bash
curl http://localhost:6501/api/v1/projects/featured
```

**统计数据 API**:
```bash
curl http://localhost:6501/api/v1/stats/overview
```

### 浏览器控制台

打开浏览器开发者工具（F12）：

1. **Console 标签页**
   - 查看是否有 JavaScript 错误
   - 查看网络请求错误

2. **Network 标签页**
   - 查看请求是否成功
   - 查看响应数据格式
   - 检查响应时间

3. **Vue DevTools**
   - 检查组件状态
   - 查看 Pinia store 数据
   - 查看路由信息

---

## 🎨 视觉效果

### 颜色方案

- **主渐变**: `linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- **主色**: #409eff (Element Plus Primary)
- **成功色**: #67c23a (绿色，用于进度)
- **统计图标背景**: 淡色背景（#ecf5ff, #f0f9ff, #fef0f0, #fdf6ec）

### 动画效果

- **卡片悬停**: `transform: translateY(-4px)`
- **阴影过渡**: `box-shadow` 动画
- **按钮悬停**: 颜色变化

---

## 📝 已知问题和解决方案

### 问题 1: 轮播图没有图片

**现象**: 轮播图的 image 字段为 null

**解决方案**:
- 使用渐变背景作为备用
- 已在代码中处理：`backgroundImage: slide.image ? url(...) : 'linear-gradient(...)'`

### 问题 2: 统计数据 API 可能失败

**现象**: API 返回 404 或 500

**解决方案**:
- 添加 try-catch 错误处理
- 设置默认统计数据对象
- 页面不会空白，显示 0 值

### 问题 3: 项目没有封面图

**现象**: `cover_image` 字段为空

**解决方案**:
- ProjectCard 组件中已处理
- 使用占位图：`/placeholder.jpg`（待添加）

---

## 🚀 下一步优化

1. **添加真实图片**
   - 上传轮播图到数据库
   - 为项目添加封面图

2. **统计数据 API**
   - 修复统计 API 路由问题
   - 确保数据正确返回

3. **图片优化**
   - 添加图片懒加载
   - 使用 WebP 格式
   - 添加图片压缩

4. **动画增强**
   - 添加数字滚动动画
   - 添加淡入动画
   - 添加骨架屏

---

**更新时间**: 2026-03-23
**状态**: 首页基础功能完成 ✅
