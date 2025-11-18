{{--
日期输入组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
<input
    type="date"
    class="form-control"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}"
    @if($field['required'] ?? false) required @endif
    value="{{ $value ?? ($field['default'] ?? '') }}"
>

