{{--
文本输入组件（支持 text, email, password）

参数:
- $field: 字段配置数组
  - name: 字段名
  - type: 字段类型 (text, email, password)
  - label: 标签文本
  - required: 是否必填
  - placeholder: 占位符
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
<input
    type="{{ $field['type'] }}"
    class="form-control @if($field['disabled'] ?? false) bg-light @endif"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}"
    placeholder="{{ $field['placeholder'] ?? '' }}"
    @if($field['required'] ?? false) required @endif
    @if($field['disabled'] ?? false) disabled @endif
    @if($field['readonly'] ?? false) readonly @endif
    value="{{ $value ?? ($field['default'] ?? '') }}"
>

