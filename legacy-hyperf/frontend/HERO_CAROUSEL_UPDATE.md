# 首页轮播图优化说明

**更新时间**: 2026-03-23

---

## ✅ 已完成的优化

### 从文字+渐变 → 全图片背景

#### 修改前
```vue
<div
  class="carousel-item"
  :style="{
    backgroundImage: slide.image
      ? `url(${slide.image})`
      : 'linear-gradient(135deg, #C8161D 0%, #E63946 100%)'
  }"
>
  <div class="carousel-content">
    <h1>{{ slide.title }}</h1>
    <p>{{ slide.description }}</p>
  </div>
</div>
```

#### 修改后
```vue
<div class="carousel-item">
  <!-- 图片背景层 -->
  <div class="carousel-bg" :style="{ backgroundImage: `url(${slide.image})` }"></div>

  <!-- 遮罩层 -->
  <div class="carousel-overlay"></div>

  <!-- 内容层 -->
  <div class="carousel-content">
    <div class="content-wrapper">
      <h1>{{ slide.title }}</h1>
      <p>{{ slide.description }}</p>
      <el-button class="cta-button">了解更多</el-button>
    </div>
  </div>
</div>
```

---

## 🖼️ 图片资源

### Unsplash图片（已配置）

#### 轮播图1 - 基金会主题
```
https://images.unsplash.com/photo-1532629345422-7515f3d16bb6?w=1920&q=80
```
- 主题：慈善、公益、温暖
- 色调：温暖、明亮

#### 轮播图2 - 行善者主题
```
https://images.unsplash.com/photo-1488521787991-ed7bba154777?w=1920&q=80
```
- 主题：志愿者、社区服务
- 色调：人文、关怀

#### 轮播图3 - 爱心传递主题
```
https://images.unsplash.com/photo-1469571486292-0ba58a3f068b?w=1920&q=80
```
- 主题：团队合作、互助
- 色调：团结、希望

---

## 🎨 设计特点

### 1. **三层结构**

```
┌─────────────────────────┐
│  ① 背景图片层           │
├─────────────────────────┤
│  ② 遮罩层 (渐变透明)    │
├─────────────────────────┤
│  ③ 内容层 (标题+描述)   │
└─────────────────────────┘
```

#### 层级说明

**① 背景图片层** - `.carousel-bg`
- 绝对定位，覆盖整个区域
- `background-size: cover` - 完全覆盖
- `background-position: center` - 居中显示

**② 遮罩层** - `.carousel-overlay`
- 渐变半透明黑色遮罩
- 确保白色文字在图片上清晰可读
- 三层渐变：70% → 40% → 60%透明度

**③ 内容层** - `.carousel-content`
- 相对定位，z-index: 2
- 文字白色 + 阴影
- 包含标题、描述、CTA按钮

### 2. **遮罩层设计**

```scss
background: linear-gradient(
  135deg,
  rgba(0, 0, 0, 0.7) 0%,   // 左上深色
  rgba(0, 0, 0, 0.4) 50%,  // 中间稍浅
  rgba(0, 0, 0, 0.6) 100%   // 右下中等
);
```

**优势**：
- ✅ 不完全遮挡图片
- ✅ 文字清晰可读
- ✅ 保持图片的视觉冲击力
- ✅ 渐变效果更自然

### 3. **文字样式**

**标题**：
- 字号：64px（桌面）
- 字重：800 (Extra Bold)
- 文字阴影：`0 4px 20px rgba(0, 0, 0, 0.3)`

**描述**：
- 字号：22px
- 字重：400 (Regular)
- 透明度：0.95
- 行高：1.6

### 4. **CTA按钮**

```scss
background: #ffffff;
color: var(--color-primary);  // 使用主题色
padding: 16px 48px;
font-size: 18px;
font-weight: 700;
border-radius: var(--radius-lg);
```

---

## 📱 响应式设计

### 断点设计

#### 桌面端 (>768px)
- 标题：64px
- 描述：22px
- 按钮：18px
- 轮播高度：700px

#### 平板/手机 (≤768px)
- 标题：42px
- 描述：18px
- 按钮：16px
- 轮播高度：700px

#### 小手机 (≤480px)
- 标题：32px
- 描述：16px
- 按钮：15px

---

## 🎬 动画效果

### fadeInUp动画

```scss
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(40px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
```

**应用对象**：
- 标题和描述内容
- 从下方淡入向上移动
- 持续0.9秒

---

## 🎨 视觉效果

### 整体风格
- ✅ 全屏图片背景
- ✅ 电影感的遮罩效果
- ✅ 清晰的文字层级
- ✅ 明确的行动号召

### 色彩对比
- 图片：丰富多彩
- 遮罩：深色半透明
- 文字：纯白色
- 按钮：白底橙字

---

## 🔄 轮播配置

### Element Plus Carousel

```vue
<el-carousel
  height="700px"
  :interval="5000"  // 5秒自动切换
  arrow="hover"     // 鼠标悬停显示箭头
>
```

### 轮播行为
- 自动播放：5秒间隔
- 箭头显示：鼠标悬停时
- 无限循环
- 平滑过渡

---

## 🖼️ 图片选择建议

### 主题相关性

选择图片时应考虑：

1. **品牌调性**
   - 温暖、关怀、希望
   - 人文、公益、慈善

2. **色彩协调**
   - 不与主题色冲突
   - 避免过于花哨
   - 保持专业感

3. **内容匹配**
   - 与标题文字相符
   - 传达正确情绪
   - 符合基金会定位

### 推荐图片来源

**免费高质量图片**：
- Unsplash: https://unsplash.com
- Pexels: https://pexels.com
- Pixabay: https://pixabay.com

**搜索关键词**：
- charity, nonprofit, volunteering
- community, helping hands
- compassion, kindness
- teamwork, support

---

## ✨ 效果对比

### 优化前
- ❌ 使用渐变色背景
- ❌ 视觉冲击力不足
- ❌ 缺乏真实感

### 优化后
- ✅ 全屏高质量图片
- ✅ 电影感视觉冲击
- ✅ 真实感人
- ✅ 专业品质

---

## 🔧 自定义图片

### 修改Mock数据

编辑 `src/api/mock.ts`：

```typescript
export const mockSlides = [
  {
    id: 1,
    title: '你的标题',
    description: '你的描述',
    image: 'https://your-image-url.jpg',  // 修改这里
    link_url: '/your-link',
    sort_order: 1
  },
  // ...
]
```

### 图片规格建议

- **尺寸**: 1920px × 1080px 或更大
- **比例**: 16:9 横屏
- **格式**: JPG 或 PNG
- **大小**: < 500KB (优化后)
- **质量**: 高质量 (80-90%)

---

## 📝 待优化建议

### 1. **图片优化**
- [ ] 图片压缩和格式转换
- [ ] 使用WebP格式
- [ ] 响应式图片(srcset)

### 2. **加载优化**
- [ ] 图片懒加载
- [ ] 预加载首屏图片
- [ ] 占位符优化

### 3. **交互增强**
- [ ] 视差滚动效果
- [ ] 图片缩放动画
- [ ] 更丰富的过渡效果

### 4. **内容个性化**
- [ ] 根据用户偏好显示
- [ ] A/B测试不同图片
- [ ] 节假日主题切换

---

## 🎯 设计目标

✅ **视觉冲击** - 全屏图片震撼效果
✅ **可读性** - 遮罩层确保文字清晰
✅ **情感共鸣** - 图片传递温暖和希望
✅ **专业品质** - 电影级的视觉效果
✅ **响应式** - 全设备完美显示

---

**完成时间**: 2026-03-23
**相关文件**:
- `src/api/mock.ts` - 轮播图数据
- `src/views/Home/index.vue` - 轮播图组件
