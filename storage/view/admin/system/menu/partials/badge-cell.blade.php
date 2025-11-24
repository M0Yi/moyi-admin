{{--
菜单徽章列

参数:
- $value: 徽章文本
- $column: 列配置
- $item: 完整菜单数据（包含 badge_type）
--}}
@if($value)
    @php
        $badgeType = $item['badge_type'] ?? 'primary';
    @endphp
    <span class="badge bg-{{ $badgeType }}">{{ $value }}</span>
@else
    <span class="text-muted">-</span>
@endif

