{{--
日期时间输入组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    // 处理日期时间值：转换为 datetime-local 格式
    $datetimeValue = '';
    if (!empty($value)) {
        $datetimeValue = is_numeric($value) ? date('Y-m-d\TH:i', $value) : date('Y-m-d\TH:i', strtotime($value));
    } elseif (!empty($field['default'])) {
        $datetimeValue = is_numeric($field['default']) ? date('Y-m-d\TH:i', $field['default']) : date('Y-m-d\TH:i', strtotime($field['default']));
    }
@endphp
<input
    type="datetime-local"
    class="form-control"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}"
    @if($field['required'] ?? false) required @endif
    value="{{ $datetimeValue }}"
>

