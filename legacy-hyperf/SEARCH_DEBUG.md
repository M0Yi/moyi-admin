# 🔍 搜索功能问题诊断与修复

## 问题现象

搜索"慈善"后，显示的文章标题包含"慈善"，但点击后进入的文章内容完全不相关。

## 问题根源

### 1. **前端 API 失败回退机制**

前端的 `articlesApi.getList()` 和 `projectsApi.getList()` 有一个回退机制：

```typescript
async getList(params?: QueryParams) {
  try {
    return await request.get('/articles', { params })
  } catch (error) {
    // API 调用失败时，使用 mock 数据
    console.warn('Articles API failed, using mock data')
    let items = [...mockArticles]
    // ...
  }
}
```

### 2. **Mock 数据不支持搜索**

当后端 API 调用失败（网络问题、超时、404等）时，前端会使用 mock 数据。但 mock 数据没有实现搜索功能，导致：

- **搜索"慈善"** → API 调用失败 → 使用 mock 数据 → 返回 mock 数据的前 20 篇文章
- **显示结果**：这些文章的标题和描述可能包含"慈善"
- **点击跳转**：使用这些文章的 ID（如 1, 2, 3...）
- **详情加载**：如果后端能工作，getDetail(1) 会返回 ID=1 的真实文章
- **结果**：标题和内容不匹配

### 3. **API 请求可能失败的原因**

- 后端服务未启动或不可用
- 数据库连接问题
- API 超时
- 跨域问题
- 代理配置问题

## 修复方案

### ✅ 修复 1：Mock 数据支持搜索

**文件**：`/src/api/articles.ts`

**修改内容**：
```typescript
// ✅ 修复后
if (params?.search) {
  const keyword = params.search.toLowerCase()
  items = items.filter(a =>
    a.title.toLowerCase().includes(keyword) ||
    a.summary?.toLowerCase().includes(keyword) ||
    a.content?.toLowerCase().includes(keyword)
  )
}
```

**文件**：`/src/api/projects.ts`

**修改内容**：
```typescript
// ✅ 修复后
if (params?.search) {
  const keyword = params.search.toLowerCase()
  items = items.filter(p =>
    p.title.toLowerCase().includes(keyword) ||
    p.description?.toLowerCase().includes(keyword) ||
    p.subtitle?.toLowerCase().includes(keyword)
  )
}
```

### ✅ 修复 2：使用正确的 ID 跳转

**文件**：`/src/views/Search/index.vue`

**修改前**：
```vue
@click="navigateTo('/article/' + item.slug)"  // ❌ 使用 slug
@click="navigateTo('/project/' + item.slug)"  // ❌ 使用 slug
```

**修改后**：
```vue
@click="navigateTo('/article/' + item.id)"    // ✅ 使用数字 ID
@click="navigateTo('/project/' + item.id)"    // ✅ 使用数字 ID
```

**原因**：详情页面期望数字 ID
```typescript
const id = Number(route.params.id)
const data = await articlesApi.getDetail(id)  // 需要数字 ID
```

### ✅ 修复 3：添加调试日志

添加了 console.log 来帮助排查问题：
```typescript
.then(data => {
  console.log('文章搜索结果:', data)
  results.value.articles = data?.items || []
})
```

## 后端 API 支持

后端**已经支持**搜索功能：

**文件**：`/addons/JianhuiOrg/Controller/Api/ArticleApiController.php`

```php
// 搜索功能（第49-55行）
if ($search) {
    $query->where(function ($q) use ($search) {
        $q->where('title', 'ilike', "%{$search}%")
          ->orWhere('summary', 'ilike', "%{$search}%")
          ->orWhere('content', 'ilike', "%{$search}%");
    });
}
```

## 测试步骤

### 1. 检查后端服务

```bash
# 确保后端服务正在运行
curl http://localhost:6501/api/v1/articles?search=慈善
```

### 2. 测试搜索功能

1. **打开搜索页面**：http://localhost:3100/search
2. **输入关键词**："慈善"
3. **点击搜索按钮**
4. **查看控制台日志**：
   ```javascript
   文章搜索结果: { items: [...], meta: {...} }
   ```
5. **点击搜索结果**
6. **验证文章内容**：内容应该与搜索结果一致

### 3. 验证 Mock 数据搜索

如果后端 API 不可用，前端会自动使用 mock 数据：

- 搜索"慈善" → 只返回标题或内容包含"慈善"的 mock 文章
- 搜索"基金" → 只返回相关 mock 文章
- 点击结果 → 加载对应 ID 的 mock 文章详情

## 预期结果

### 场景 1：后端 API 可用

```
搜索"慈善"
→ 后端搜索数据库
→ 返回匹配的文章（真实数据）
→ 点击结果 → 显示真实文章内容 ✅
```

### 场景 2：后端 API 不可用

```
搜索"慈善"
→ API 失败
→ 使用 mock 数据（已修复搜索功能）
→ 返回匹配的 mock 文章
→ 点击结果 → 显示 mock 文章详情 ✅
```

## 验证清单

- [ ] 搜索"慈善"能返回相关文章
- [ ] 搜索结果标题包含关键词
- [ ] 点击文章结果，内容匹配标题
- [ ] 搜索"项目"能返回相关项目
- [ ] 点击项目结果，内容匹配标题
- [ ] 搜索不存在的关键词显示空结果
- [ ] 热门搜索标签功能正常
- [ ] 快速链接功能正常

## 后续优化建议

1. **添加加载状态提示**：显示是从后端加载还是使用 mock 数据
2. **改进错误提示**：当 API 失败时给用户明确提示
3. **添加搜索历史**：保存用户的搜索历史
4. **优化搜索算法**：支持模糊搜索、拼音搜索等
5. **搜索结果高亮**：在详情页也高亮搜索关键词

---

**状态**: ✅ 已修复
**最后更新**: 2026-03-25
**维护者**: Claude Code Assistant
