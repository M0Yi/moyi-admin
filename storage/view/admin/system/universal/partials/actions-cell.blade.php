{{--
/**
 * 操作单元格组件
 *
 * @param object|array $item 行数据
 * @param array $column 列配置
 * @param mixed $value 单元格值
 */
--}}

@php
    // 获取模型标识（从当前请求路径中提取）
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    preg_match('/\/universal\/([^\/\?]+)/', $currentPath, $matches);
    $model = $matches[1] ?? '';

    // 获取记录 ID
    $id = is_array($item) ? ($item['id'] ?? 0) : ($item->id ?? 0);
@endphp

<div class="d-flex gap-1">
    <a href="{{ admin_route("universal/{$model}/{$id}/edit") }}"
       class="btn btn-sm btn-warning btn-action"
       title="编辑">
        <i class="bi bi-pencil"></i>
    </a>
    <button class="btn btn-sm btn-danger btn-action"
            title="删除"
            onclick="deleteRow({{ $id }})">
        <i class="bi bi-trash"></i>
    </button>
</div>

