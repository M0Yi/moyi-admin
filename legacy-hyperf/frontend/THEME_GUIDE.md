# 建辉慈善基金会 - 主题配置指南

## 📋 概述

本网站使用CSS变量实现全局主题管理，所有颜色、间距、字体等都可以通过修改一个文件来实现全局变更。

---

## 🎨 主题配置文件

**文件位置**: `src/styles/theme.scss`

这个文件定义了所有的CSS变量，修改后会自动应用到全站。

---

## 🚀 快速修改主题

### 1. 修改主品牌色

找到 `:root` 中的这几行：

```scss
--color-primary: #ff5000;           // 主色 - 导航栏背景、主按钮
--color-primary-light: #ff6a1f;     // 主色亮色 - hover状态
--color-primary-dark: #e64500;      // 主色深色 - 点击状态
--color-primary-lighter: #fff0ea;   // 主色极浅色 - 背景高亮
```

**示例：改成蓝色主题**

```scss
--color-primary: #3b82f6;           // 蓝色
--color-primary-light: #60a5fa;     // 亮蓝色
--color-primary-dark: #2563eb;      // 深蓝色
--color-primary-lighter: #eff6ff;   // 浅蓝色背景
```

### 2. 修改辅助色

```scss
--color-secondary: #C8161D;         // 辅助色
--color-accent: #E63946;            // 强调色
```

### 3. 修改文字颜色

```scss
--color-text-primary: #111827;      // 主要文字
--color-text-secondary: #374151;    // 次要文字
--color-text-tertiary: #6b7280;     // 三级文字
```

### 4. 修改背景色

```scss
--color-bg-primary: #ffffff;        // 主背景
--color-bg-secondary: #f9fafb;      // 次背景
--color-bg-tertiary: #f3f4f6;       // 三级背景
```

---

## 📐 其他可配置项

### 圆角大小

```scss
--radius-sm: 4px;
--radius: 8px;
--radius-md: 12px;
--radius-lg: 16px;
--radius-xl: 20px;
```

### 间距系统

```scss
--spacing-xs: 4px;
--spacing-sm: 8px;
--spacing: 12px;
--spacing-md: 16px;
--spacing-lg: 24px;
--spacing-xl: 32px;
```

### 字体大小

```scss
--font-xs: 12px;
--font-sm: 13px;
--font-base: 14px;
--font-md: 15px;
--font-lg: 16px;
--font-xl: 18px;
```

---

## 💡 使用主题变量

### 在SCSS中使用

```scss
.my-component {
  color: var(--color-primary);
  background: var(--color-bg-primary);
  padding: var(--spacing-md);
  border-radius: var(--radius);
}
```

### 在Vue组件中使用

```vue
<template>
  <div class="my-box">使用主题色</div>
</template>

<style scoped lang="scss">
.my-box {
  background: var(--color-primary);
  color: #ffffff;
  padding: var(--spacing-lg);
  border-radius: var(--radius-md);
}
</style>
```

---

## 🎯 当前主题色

**主品牌色**: `#ff5000` (橙红色)

- **导航栏背景**: #ff5000
- **主按钮背景**: #ff5000
- **链接颜色**: #ff5000
- **Hover背景**: rgba(255, 80, 0, 0.06)

这个颜色传递了：
- ✨ 活力与热情
- ❤️ 关怀与温暖
- 🔥 能量与希望

非常适合慈善基金会的定位。

---

## 🔄 预设主题方案

### 方案1: 温暖橙红（当前）
```scss
--color-primary: #ff5000;
```

### 方案2: 专业深蓝
```scss
--color-primary: #1e40af;
--color-primary-light: #3b82f6;
--color-primary-dark: #1e3a8a;
--color-primary-lighter: #eff6ff;
```

### 方案3: 清新绿色
```scss
--color-primary: #059669;
--color-primary-light: #10b981;
--color-primary-dark: #047857;
--color-primary-lighter: #ecfdf5;
```

### 方案4: 典雅紫红
```scss
--color-primary: #9f1239;
--color-primary-light: #be123c;
--color-primary-dark: #881337;
--color-primary-lighter: #fff1f2;
```

---

## ⚠️ 注意事项

1. **颜色组合要协调**
   - 主色、亮色、深色应该是同一色系
   - 使用在线工具生成配色：https://uicolors.app/create

2. **考虑可访问性**
   - 文字与背景对比度至少 4.5:1
   - 使用对比度检查工具：https://webaim.org/resources/contrastchecker/

3. **测试所有页面**
   - 修改主题后检查所有页面
   - 确保没有颜色冲突

---

## 📝 实时预览

修改 `theme.scss` 后，开发服务器会自动热更新，可以立即看到效果。

如果没有自动更新，可以：
1. 保存文件
2. 刷新浏览器
3. 或按 Cmd/Ctrl + R 强制刷新

---

## 🛠️ 高级定制

### 覆盖Element Plus默认颜色

```scss
// 在 theme.scss 中添加
:root {
  // Element Plus 覆盖
  --el-color-primary: var(--color-primary);
  --el-color-success: var(--color-success);
  --el-color-warning: var(--color-warning);
  --el-color-error: var(--color-error);
  --el-color-info: var(--color-info);
}
```

### 添加自定义渐变

```scss
.gradient-hero {
  background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-light) 100%);
}
```

---

## 📚 相关文件

- `src/styles/theme.scss` - 主题变量定义
- `src/styles/global.scss` - 全局样式
- `src/main.ts` - 引入全局样式
- `src/App.vue` - 应用根组件

---

## 🎨 设计资源

- **Coolors**: https://coolors.co/ - 配色方案生成器
- **Adobe Color**: https://color.adobe.com/ - 专业配色工具
- **UI Gradients**: https://uigradients.com/ - 渐变色库
- **Material Design Colors**: https://materialui.co/colors/ - Material配色

---

**更新日期**: 2026-03-23
