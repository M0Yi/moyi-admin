{{--
数字区间组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - default: 默认值（JSON字符串，如 {"min": 10, "max": 100}）
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    // 解析当前值
    $minValue = '';
    $maxValue = '';
    $rangeValue = $value ?? ($field['default'] ?? '');
    if (!empty($rangeValue)) {
        if (is_string($rangeValue)) {
            $decoded = json_decode($rangeValue, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $minValue = $decoded['min'] ?? '';
                $maxValue = $decoded['max'] ?? '';
            }
        } elseif (is_array($rangeValue)) {
            $minValue = $rangeValue['min'] ?? '';
            $maxValue = $rangeValue['max'] ?? '';
        }
    }
@endphp
<div class="row">
    <div class="col-md-6">
        <label for="{{ $field['name'] }}_min" class="form-label">最小值</label>
        <input
            type="number"
            class="form-control"
            id="{{ $field['name'] }}_min"
            name="{{ $field['name'] }}_min"
            placeholder="最小值"
            @if($field['required'] ?? false) required @endif
            value="{{ $minValue }}"
        >
    </div>
    <div class="col-md-6">
        <label for="{{ $field['name'] }}_max" class="form-label">最大值</label>
        <input
            type="number"
            class="form-control"
            id="{{ $field['name'] }}_max"
            name="{{ $field['name'] }}_max"
            placeholder="最大值"
            @if($field['required'] ?? false) required @endif
            value="{{ $maxValue }}"
        >
    </div>
</div>
<input type="hidden" id="{{ $field['name'] }}" name="{{ $field['name'] }}">

