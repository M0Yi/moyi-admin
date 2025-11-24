# 通用刷新父页面监听器使用文档

## 概述

`refresh-parent-listener.js` 是一个通用的 JavaScript 组件，用于在列表页面中自动监听来自 iframe 的 `refreshParent` 消息，并自动调用对应的数据表格刷新函数。

## 功能特性

1. **自动检测刷新函数**：根据 `tableId` 自动查找并调用 `loadData_{tableId}` 函数
2. **频道过滤**：支持可选的频道过滤，只处理指定频道的消息
3. **多种消息格式支持**：支持 postMessage 和自定义事件两种消息格式
4. **自动降级**：如果找不到指定的函数，会自动检测所有 `loadData_*` 函数
5. **统一日志**：提供统一的日志输出，便于调试

## 使用方法

### 1. 引入脚本文件

在列表页面的 `@push('admin_scripts')` 部分引入脚本：

```blade
@push('admin_scripts')
{{-- 引入通用刷新父页面监听器 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
    // 初始化监听器
    initRefreshParentListener('menuTable', {
        logPrefix: '[Menu]'
    });
</script>
@endpush
```

### 2. 基本用法

#### 方式1：最简单的用法（推荐）

```javascript
// 自动检测 loadData_menuTable 函数
initRefreshParentListener('menuTable');
```

#### 方式2：带日志前缀

```javascript
// 添加日志前缀，便于调试
initRefreshParentListener('menuTable', {
    logPrefix: '[Menu]'
});
```

#### 方式3：带频道过滤

```javascript
// 只处理指定频道的消息
initRefreshParentListener('menuTable', {
    channel: 'menu-channel',
    logPrefix: '[Menu]'
});
```

#### 方式4：多个 tableId

```javascript
// 支持多个 tableId，按顺序查找
initRefreshParentListener(['menuTable', 'dataTable'], {
    logPrefix: '[Menu]'
});
```

#### 方式5：带回调函数

```javascript
// 在刷新前执行自定义逻辑
initRefreshParentListener('menuTable', {
    logPrefix: '[Menu]',
    onRefresh: function(tableId, functionName) {
        console.log('即将刷新表格:', tableId, functionName);
        // 可以在这里执行一些预处理操作
    }
});
```

### 3. 自动初始化（可选）

如果页面中有 `data-refresh-parent-table-id` 属性，组件会自动初始化：

```blade
<div data-refresh-parent-table-id="menuTable" 
     data-refresh-parent-channel="menu-channel"
     data-refresh-parent-log-prefix="[Menu]">
    <!-- 页面内容 -->
</div>
```

## 参数说明

### `initRefreshParentListener(tableId, options)`

#### `tableId` (string | string[])

数据表格的 ID，可以是：
- 单个字符串：`'menuTable'`
- 字符串数组：`['menuTable', 'dataTable']`

组件会根据 `tableId` 查找对应的 `loadData_{tableId}` 函数。

#### `options` (Object, 可选)

配置选项：

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `channel` | string | `null` | 消息频道，如果设置则只处理该频道的消息 |
| `logPrefix` | string | `'[RefreshParentListener]'` | 日志前缀，用于调试 |
| `onRefresh` | Function | `null` | 刷新前的回调函数，参数：`(tableId, functionName)` |

## 工作原理

### 消息流程

```
编辑页面（iframe overlay）
  ↓ 提交成功
  ↓ AdminIframeClient.success({ refreshParent: true })
  ↓ postMessage
列表页面（父窗口）
  ↓ refresh-parent-listener.js 监听消息
  ↓ 检测 loadData_{tableId} 函数
  ↓ 调用函数刷新数据表
```

### 函数查找逻辑

1. **优先查找指定函数**：根据 `tableId` 查找 `loadData_{tableId}` 函数
2. **自动降级**：如果找不到，自动检测所有 `loadData_*` 函数并使用第一个
3. **错误提示**：如果都找不到，输出警告日志

## 实际应用示例

### 示例1：菜单管理页面

```blade
{{-- storage/view/admin/system/menu/index.blade.php --}}
@push('admin_scripts')
{{-- 引入通用刷新父页面监听器 --}}
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
(function() {
    'use strict';
    
    // 初始化刷新父页面监听器
    initRefreshParentListener('menuTable', {
        logPrefix: '[Menu]'
    });
})();
</script>
@endpush
```

### 示例2：Universal CRUD 页面

```blade
{{-- storage/view/admin/system/universal/index.blade.php --}}
@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
    // 初始化刷新父页面监听器（使用通用组件）
    initRefreshParentListener('dataTable', {
        channel: '{{ $shellChannel }}',  // 使用配置的频道
        logPrefix: '[UniversalCrud]'     // 日志前缀
    });
</script>
@endpush
```

### 示例3：带频道过滤的页面

```blade
@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
    // 只处理指定频道的消息
    initRefreshParentListener('userTable', {
        channel: 'user-management-channel',
        logPrefix: '[UserManagement]',
        onRefresh: function(tableId, functionName) {
            console.log('用户列表即将刷新');
            // 可以在这里执行一些预处理操作
        }
    });
</script>
@endpush
```

## 与全局监听器的区别

### 全局监听器（iframe-shell.js）

- **位置**：`layouts/admin.blade.php` 中全局加载
- **功能**：处理所有页面的消息，但只检测 `loadData_dataTable` 函数
- **限制**：如果页面使用其他 `tableId`（如 `menuTable`），不会被检测到

### 通用监听器（refresh-parent-listener.js）

- **位置**：在需要使用的页面中单独引入
- **功能**：专门处理当前页面的消息，自动检测对应的 `loadData_*` 函数
- **优势**：支持任意 `tableId`，更灵活

## 最佳实践

1. **统一使用通用监听器**：在所有列表页面中使用通用监听器，而不是自定义代码
2. **设置日志前缀**：为每个页面设置独特的日志前缀，便于调试
3. **频道过滤**：如果页面有特定的频道，建议设置频道过滤，避免消息冲突
4. **错误处理**：组件已经内置了错误处理和日志输出，无需额外处理

## 注意事项

1. **函数名约定**：确保数据表格组件创建的刷新函数名为 `loadData_{tableId}`
2. **脚本加载顺序**：确保在数据表格组件初始化之后再初始化监听器
3. **频道一致性**：如果设置了频道，确保编辑页面发送消息时使用相同的频道
4. **同源安全**：组件只处理同源消息，确保安全性

## 故障排查

### 问题1：消息收到但表格没有刷新

**可能原因**：
- `loadData_{tableId}` 函数不存在
- 函数名不匹配

**解决方法**：
1. 检查浏览器控制台的日志，查看是否找到对应的函数
2. 确认 `tableId` 是否正确
3. 检查数据表格组件是否正确初始化

### 问题2：收到多个页面的消息

**可能原因**：
- 没有设置频道过滤
- 频道设置不一致

**解决方法**：
1. 为每个页面设置独特的频道
2. 确保编辑页面和列表页面使用相同的频道

### 问题3：函数找不到

**可能原因**：
- 数据表格组件未正确初始化
- `tableId` 配置错误

**解决方法**：
1. 检查数据表格组件的初始化代码
2. 确认 `tableId` 配置是否正确
3. 在浏览器控制台手动检查 `window.loadData_{tableId}` 是否存在

## 更新日志

### v1.0.0 (2024-01-XX)

- 初始版本
- 支持基本的消息监听和函数调用
- 支持频道过滤
- 支持自动降级检测

