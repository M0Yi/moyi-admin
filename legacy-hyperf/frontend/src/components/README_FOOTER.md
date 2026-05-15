# Footer 组件使用说明

## 组件位置
`/src/components/Footer.vue`

## 功能特性
- 完整的网站页脚，包含联系信息、快速链接和社交媒体
- 白色LOGO垂直贯穿条设计
- 微信二维码悬停显示
- 使用 Font Awesome 图标（微信和微博）
- 响应式设计，支持移动端

## 如何在其他页面使用

### 1. 导入组件
```vue
<script setup lang="ts">
import Footer from '@/components/Footer.vue'
</script>
```

### 2. 在模板中使用
```vue
<template>
  <div class="page">
    <!-- 页面内容 -->
    <main>
      <h1>页面标题</h1>
      <!-- 其他内容 -->
    </main>

    <!-- 添加页脚 -->
    <Footer />
  </div>
</template>
```

## 示例：在 About 页面中使用

```vue
<template>
  <div class="about-page">
    <!-- 页面头部 -->
    <header class="page-header">
      <h1>关于我们</h1>
    </header>

    <!-- 页面内容 -->
    <section class="page-content">
      <p>这是关于我们页面的内容...</p>
    </section>

    <!-- 页脚 -->
    <Footer />
  </div>
</template>

<script setup lang="ts">
import Footer from '@/components/Footer.vue'
</script>

<style scoped lang="scss">
.about-page {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

.page-content {
  flex: 1;
}
</style>
```

## 页脚包含的内容

1. **联系信息**
   - 电话：+86 0755-83239875
   - 邮箱：contact@jianhuicishan.org
   - 地址：广东省深圳市福田区

2. **快速链接**
   - 关于我们
   - 公益项目
   - 生命故事
   - 新闻中心
   - 爱心捐赠
   - 信息公开

3. **社交媒体**
   - 微信（悬停显示二维码）
   - 微博（链接到官方微博）

4. **版权信息**
   - © 2025 建辉慈善基金会 版权所有
   - 粤ICP备16097782号

## 样式定制

页脚使用 scoped 样式，如需定制，可以：

1. 修改 `Footer.vue` 中的 CSS 变量
2. 通过 props 传入自定义配置（如需要可扩展）

## 注意事项

- 组件已经包含了 Font Awesome 图标库的依赖
- 确保 `main.ts` 中已注册 Font Awesome 组件
- 页脚会自动继承全局的主题色变量 `--color-primary`
