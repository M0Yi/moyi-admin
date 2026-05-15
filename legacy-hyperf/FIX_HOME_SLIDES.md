# 首页轮播图数据加载修复

## ✅ 修复完成

首页轮播图现在会从数据库加载真实数据！

### 🔧 修复内容

#### 1. 后端API字段名修复

**文件**: `/Users/moyi/moyi-admin/addons/JianhuiOrg/Controller/Api/CommonApiController.php`

**问题**: 使用了错误的字段名 `image_url`（数据库中不存在）

**修复**: 改为正确的字段名 `bg_image`

```php
// ❌ 修复前
'image' => $slide->image_url,  // 字段不存在

// ✅ 修复后
'image' => $slide->bg_image,  // 正确的字段名
'link_text' => $slide->link_text,  // 同时添加链接文字字段
```

#### 2. 前端Store优化

**文件**: `/Users/moyi/moyi-admin/frontend/src/stores/app.ts`

**改进**:
- 只加载启用的轮播图 (`is_active = true`)
- 添加成功日志
- 移除失败时的mock数据降级

```typescript
const loadSlides = async () => {
  try {
    const data = await commonApi.getSlides()
    // 只显示启用的轮播图
    slides.value = (data.items || []).filter((slide: Slide) => slide.is_active)
    console.log('加载轮播图成功:', slides.value.length, '条')
  } catch (error) {
    console.error('Failed to load slides:', error)
    slides.value = []  // 失败时显示空轮播，不使用mock
  }
}
```

### 📊 数据流向

```
数据库表: jianhui_org_hero_slides
    ↓
字段: bg_image (存储图片URL)
    ↓
后端API: /api/v1/slides
    ↓
转换: bg_image → image
    ↓
前端Store: appStore.slides
    ↓
首页组件: <el-carousel>显示
```

### 🎯 当前轮播图数据

**数据库中的3条轮播图**:

1. **腾讯公益乐捐致敬困境中的行善者**
   - 图片: http://image-dev.gongyila.com/67/Swiper/2022/05/04/a5f743d499cb4c22b59f9db3198dd014.png
   - 链接: https://gongyi.qq.com/succor/detail.htm?id=20955
   - 状态: ✅ 启用

2. **让善行温暖世界**
   - 图片: http://image-dev.gongyila.com/67/Swiper/2022/05/04/a5f743d499cb4c22b59f9db3198dd014.png
   - 链接: /jianhui/stories
   - 状态: ✅ 启用

3. **致敬困境中的行善者**
   - 图片: http://image-dev.gongyila.com/67/Swiper/2022/05/04/a5f743d499cb4c22b59f9db3198dd014.png
   - 链接: /jianhui/about
   - 状态: ✅ 启用

### 🖼️ 图片字段说明

| 数据库字段 | API返回 | 前端使用 | 说明 |
|-----------|---------|---------|------|
| `bg_image` | `image` | `slide.image` | 轮播图背景图片 |
| `link_url` | `link_url` | `slide.link_url` | 点击轮播图跳转的链接 |
| `link_text` | `link_text` | - | 链接文字（可选） |
| `is_active` | `is_active` | - | 是否启用（true=显示） |
| `sort_order` | `sort_order` | - | 排序号（升序） |

### 🔄 刷新浏览器查看

**重要**: 必须刷新才能看到修复后的轮播图！

1. **硬刷新**: `Ctrl + Shift + R` (Windows) 或 `Cmd + Shift + R` (Mac)
2. 访问首页: http://localhost:3100
3. 应该看到3张轮播图自动切换

### 🎨 轮播图配置

- **自动切换**: 每5秒切换一次
- **切换方式**: 鼠标悬停时显示箭头
- **高度**: 700px
- **响应式**: 移动端和桌面端自动适配

### 📝 管理轮播图

如需修改轮播图，可以：

1. **后台管理**: http://localhost:3100/admin/slides
   - 新建轮播图
   - 编辑现有轮播图
   - 上传新图片
   - 启用/禁用轮播图
   - 调整显示顺序

2. **数据库**:
   ```sql
   SELECT id, title, bg_image, link_url, is_active, sort_order
   FROM jianhui_org_hero_slides
   WHERE is_active = true
   ORDER BY sort_order ASC;
   ```

### ✨ 验证步骤

1. ✅ 访问首页: http://localhost:3100
2. ✅ 查看3张轮播图是否正常显示
3. ✅ 点击轮播图是否能正确跳转
4. ✅ 自动切换功能是否正常工作

---

**首页轮播图现在使用数据库中的真实数据！** 🎉

所有3条轮播图都会从数据库加载并正确显示。如需添加或修改轮播图，请访问后台管理页面。
