{{--
菜单名称列（树形结构）

参数:
- $value: 菜单名称
- $column: 列配置
- $item: 完整菜单数据
- $level: 层级（从 $item['level'] 获取）
--}}
<div class="d-flex align-items-center menu-level-{{ $item['level'] ?? 0 }}">
    @if(($item['level'] ?? 0) > 0)
        <span class="text-muted me-2">{{ str_repeat('└─', $item['level'] ?? 0) }}</span>
    @endif
    <span class="fw-medium">{{ $value ?? '-' }}</span>
</div>

