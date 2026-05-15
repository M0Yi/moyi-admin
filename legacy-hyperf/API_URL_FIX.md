# API URL 重复拼接问题修复

## 问题
用户报告错误：`GET http://localhost:3100/api/v1/api/v1/slides 500 (Internal Server Error)`

URL中出现了重复的 `/api/v1/api/v1/`，导致请求失败。

## 根本原因
在 `/Users/moyi/moyi-admin/frontend/src/api/admin.ts` 文件中，所有API端点的URL都包含了 `/api/v1` 前缀：

```typescript
// 错误的写法 ❌
url: '/api/v1/admin/stats'
url: '/api/v1/slides'
url: '/api/v1/admin/articles'
```

但是，`@/utils/request.ts` 中的 `baseURL` 已经配置为 `/api/v1`：

```typescript
const service: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api/v1',  // 已经是 /api/v1
  ...
})
```

这导致最终URL被拼接成：
- baseURL: `/api/v1`
- 请求URL: `/api/v1/slides`
- **最终URL**: `/api/v1/api/v1/slides` ❌

## 修复方案
移除 `admin.ts` 中所有URL的 `/api/v1` 前缀：

```typescript
// 正确的写法 ✅
url: '/admin/stats'       // → /api/v1/admin/stats
url: '/slides'            // → /api/v1/slides
url: '/admin/articles'    // → /api/v1/admin/articles
```

## 修复的文件
✅ `/Users/moyi/moyi-admin/frontend/src/api/admin.ts` - 移除所有 `/api/v1` 前缀

## 修复的API列表
- `/admin/stats` (原: `/api/v1/admin/stats`)
- `/admin/articles` (原: `/api/v1/admin/articles`)
- `/admin/slides` (原: `/api/v1/admin/slides`)
- `/admin/projects` (原: `/api/v1/admin/projects`)
- `/slides` (原: `/api/v1/slides`)
- `/navigation` (原: `/api/v1/navigation`)
- `/projects` (原: `/api/v1/projects`)
- `/stories` (原: `/api/v1/stories`)
- `/donations/disclosure` (原: `/api/v1/donations/disclosure`)
- 所有其他CRUD操作的API端点

## 验证
所有API现在应该正常工作：

```bash
# 测试统计API
curl http://localhost:3100/api/v1/admin/stats
# ✅ 返回: {"code":200,"data":{"articles":1357,"projects":3,...}}

# 测试轮播图API
curl http://localhost:3100/api/v1/slides
# ✅ 返回: {"code":200,"data":{"items":[...]}}

# 测试导航API
curl http://localhost:3100/api/v1/navigation
# ✅ 返回: {"code":200,"data":{"items":[...]}}
```

## 注意事项
1. **不要在URL中包含 `/api/v1` 前缀**，因为 baseURL 已经包含了
2. 使用相对路径，如 `/admin/stats`、`/slides` 等
3. axios 会自动将 baseURL 和请求URL拼接

## 相关架构
```
前端请求代码              拼接后                  实际访问
/admin/stats    +    /api/v1    =    /api/v1/admin/stats
/slides         +    /api/v1    =    /api/v1/slides
/admin/articles +    /api/v1    =    /api/v1/admin/articles
```

## 测试步骤
1. 清除浏览器缓存 (Ctrl+Shift+Delete)
2. 刷新页面 (F5)
3. 检查浏览器控制台 Network 标签
4. 确认所有API请求的URL格式正确（只有一个 `/api/v1`）
