{{--
数字输入组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - placeholder: 占位符
  - min: 最小值（可选）
  - max: 最大值（可选）
  - step: 步长（可选）
  - disabled: 是否禁用（可选）
  - readonly: 是否只读（可选）
  - default: 默认值
- $value: 当前值（可选，用于编辑页面）
--}}
<input
    type="number"
    class="form-control @if($field['disabled'] ?? false) bg-light @endif"
    id="{{ $field['name'] }}"
    name="{{ $field['name'] }}"
    placeholder="{{ $field['placeholder'] ?? '' }}"
    @if(isset($field['min'])) min="{{ $field['min'] }}" @endif
    @if(isset($field['max'])) max="{{ $field['max'] }}" @endif
    @if(isset($field['step'])) step="{{ $field['step'] }}" @endif
    @if($field['required'] ?? false) required @endif
    @if($field['disabled'] ?? false) disabled @endif
    @if($field['readonly'] ?? false) readonly @endif
    value="{{ $value ?? ($field['default'] ?? '') }}"
>

