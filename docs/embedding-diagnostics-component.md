# 嵌入态诊断组件使用说明

## 组件位置

`storage/view/components/embedding-diagnostics.blade.php`

## 功能说明

嵌入态诊断组件用于显示页面的嵌入状态信息，包括：
- 标准化地址（normalizedUrl）
- iframe 嵌套层级（自动计算）
- Iframe Channel
- Sec-Fetch-Dest
- Query 参数

组件会自动计算并显示 iframe 嵌套层级，帮助开发者了解页面是否在 iframe 中运行以及嵌套深度。

## 使用方法

### 基础用法

```blade
@include('components.embedding-diagnostics', [
    'isEmbedded' => $isEmbedded,
    'normalizedUrl' => $normalizedUrl,
    'diagnostics' => $diagnostics ?? []
])
```

### 完整参数示例

```blade
@include('components.embedding-diagnostics', [
    'isEmbedded' => $isEmbedded,              // 必填：是否处于嵌入模式
    'normalizedUrl' => $normalizedUrl,        // 必填：标准化地址
    'diagnostics' => [                        // 可选：诊断信息数组
        'channel' => 'user-management',       // Iframe Channel
        'sec_fetch_dest' => 'iframe',         // Sec-Fetch-Dest
        'query' => [                          // Query 参数
            'id' => 123,
            'action' => 'edit'
        ]
    ],
    'id' => 'my-diagnostics',                // 可选：组件唯一标识，默认为 'embedding-diagnostics'
    'showTitle' => true                      // 可选：是否显示标题，默认为 true
])
```

## 参数说明

| 参数 | 类型 | 必填 | 默认值 | 说明 |
|------|------|------|--------|------|
| `isEmbedded` | bool | 是 | - | 是否处于嵌入模式（由 `renderAdmin()` 自动注入） |
| `normalizedUrl` | string | 是 | - | 标准化地址（由 `renderAdmin()` 自动注入） |
| `diagnostics` | array | 否 | `[]` | 诊断信息数组，包含：<br>- `channel`: Iframe Channel<br>- `sec_fetch_dest`: Sec-Fetch-Dest<br>- `query`: Query 参数数组 |
| `id` | string | 否 | `'embedding-diagnostics'` | 组件唯一标识，用于生成唯一的 DOM ID。如果页面有多个诊断组件，需要设置不同的 `id` |
| `showTitle` | bool | 否 | `true` | 是否显示标题和状态徽章 |

## 使用场景

### 1. 在 iframe-demo 页面中使用

```blade
{{-- storage/view/admin/system/iframe-demo/index.blade.php --}}
<div class="col-xl-7">
    @include('components.embedding-diagnostics', [
        'isEmbedded' => $isEmbedded,
        'normalizedUrl' => $normalizedUrl,
        'diagnostics' => $diagnostics ?? [],
        'id' => 'iframe-demo-diagnostics'
    ])
</div>
```

### 2. 在开发调试页面中使用

```blade
{{-- 开发环境显示诊断信息 --}}
@if(config('app.env') === 'local')
<div class="row mb-4">
    <div class="col-12">
        @include('components.embedding-diagnostics', [
            'isEmbedded' => $isEmbedded ?? false,
            'normalizedUrl' => $normalizedUrl ?? request()->url(),
            'diagnostics' => [
                'channel' => request()->get('_channel'),
                'sec_fetch_dest' => request()->header('Sec-Fetch-Dest'),
                'query' => request()->query()
            ],
            'id' => 'dev-diagnostics'
        ])
    </div>
</div>
@endif
```

### 3. 在表单页面中使用（隐藏标题）

```blade
{{-- 表单页面中只显示诊断信息，不显示标题 --}}
@include('components.embedding-diagnostics', [
    'isEmbedded' => $isEmbedded,
    'normalizedUrl' => $normalizedUrl,
    'diagnostics' => $diagnostics ?? [],
    'showTitle' => false
])
```

## 嵌套层级说明

组件会自动计算并显示 iframe 嵌套层级：

- **L0** 🏠：在主框架中，不是 iframe
- **L1** 📦：第 1 层嵌套，正常的 iframe 模式
- **L2** 📦📦：第 2 层嵌套，开始套娃了！
- **L3** 📦📦📦：第 3 层嵌套，套娃进行中...
- **L4-L9** 📦📦📦📦：深度套娃！注意性能影响
- **L10+** 📦📦📦📦📦...：无限套娃模式！建议适可而止 😄

## 注意事项

1. **唯一 ID**：如果页面中有多个诊断组件，必须为每个组件设置不同的 `id` 参数，避免 DOM ID 冲突。

2. **必需参数**：`isEmbedded` 和 `normalizedUrl` 通常由 `renderAdmin()` 方法自动注入，如果手动使用组件，需要确保这些参数已正确传递。

3. **JavaScript 自动执行**：组件会自动在页面加载时计算并显示嵌套层级，无需手动调用。

4. **样式依赖**：组件使用 Bootstrap 5 的样式类，确保页面已加载 Bootstrap CSS。

## 相关文档

- [页面类型检测文档](./page-type-detection.md)
- [Iframe Shell 使用说明](./iframe-shell.md)

