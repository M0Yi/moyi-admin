{{--
菜单操作列

参数:
- $value: 无用（操作列不需要字段值）
- $column: 列配置
- $item: 完整菜单数据
--}}
<div class="d-flex gap-1">
    <a href="{{ admin_route('system/menus/' . $item['id'] . '/edit') }}"
       class="btn btn-sm btn-warning btn-action"
       title="编辑"
       data-iframe-shell-trigger="menu-edit-{{ $item['id'] }}"
       data-iframe-shell-src="{{ admin_route('system/menus/' . $item['id'] . '/edit') }}"
       data-iframe-shell-title="编辑菜单"
       data-iframe-shell-channel="menu">
        <i class="bi bi-pencil"></i>
    </a>
    <button class="btn btn-sm btn-danger btn-action"
            title="删除"
            onclick="deleteMenu({{ $item['id'] }}, '{{ addslashes($item['title'] ?? $item['name']) }}', false)">
        <i class="bi bi-trash"></i>
    </button>
</div>

