{{--
文本域组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - placeholder: 占位符
  - rows: 行数（默认 4）
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
<textarea
    class="form-control @if($field['disabled'] ?? false) bg-light @endif"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}"
    rows="{{ $field['rows'] ?? 4 }}"
    placeholder="{{ $field['placeholder'] ?? '' }}"
    @if($field['required'] ?? false) required @endif
    @if($field['disabled'] ?? false) disabled @endif
    @if($field['readonly'] ?? false) readonly @endif
>{{ $value ?? ($field['default'] ?? '') }}</textarea>

