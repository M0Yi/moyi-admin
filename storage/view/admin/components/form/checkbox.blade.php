{{--
复选框组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - options: 选项数组（必需）
  - default: 默认值
  - layout: 布局方式，'horizontal'（水平，默认）或 'vertical'（垂直）
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    // 处理已选值（可能是数组或 JSON 字符串）
    $checkedValues = [];
    if (!empty($value)) {
        $checkedValues = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : []);
    } elseif (!empty($field['default'])) {
        $checkedValues = is_array($field['default']) ? $field['default'] : (is_string($field['default']) ? json_decode($field['default'], true) : []);
    }
    $checkedValues = $checkedValues ?: [];
    
    // 处理 options 格式
    $optionsArray = [];
    if (!empty($field['options'])) {
        foreach ($field['options'] as $key => $optionValue) {
            if (is_array($optionValue)) {
                $optionsArray[] = $optionValue;
            } else {
                $optionsArray[] = ['value' => $key, 'label' => $optionValue];
            }
        }
    }
    
    // 布局方式：horizontal（水平）或 vertical（垂直）
    $layout = $field['layout'] ?? 'horizontal';
    $isHorizontal = $layout === 'horizontal';
@endphp
<div class="checkbox-group {{ $isHorizontal ? 'd-flex flex-wrap' : 'd-flex flex-column' }}" style="gap: {{ $isHorizontal ? '1.5rem' : '0.75rem' }}; align-items: {{ $isHorizontal ? 'center' : 'flex-start' }};">
    @foreach($optionsArray as $option)
    <div class="form-check" style="margin-bottom: 0;">
        <input
            class="form-check-input"
            type="checkbox"
            name="{{ $field['name'] }}[]"
            id="{{ $field['name'] }}_{{ $option['value'] }}"
            value="{{ $option['value'] }}"
            @if(in_array($option['value'], $checkedValues)) checked @endif
            style="cursor: pointer; width: 1.25rem; height: 1.25rem; margin-top: 0.125rem; flex-shrink: 0;"
        >
        <label 
            class="form-check-label" 
            for="{{ $field['name'] }}_{{ $option['value'] }}"
            style="cursor: pointer; user-select: none; padding-left: 0.5rem; font-weight: 400; color: #495057; line-height: 1.5;"
        >
            {{ $option['label'] }}
        </label>
    </div>
    @endforeach
</div>

