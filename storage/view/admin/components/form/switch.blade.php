{{--
开关组件（使用 Bootstrap form-switch 实现）

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - onValue: 开启时的值（可选，默认 '1'）
  - offValue: 关闭时的值（可选，默认 '0'）
  - default: 默认值（可选，默认 '0'）
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    // 处理开关的值
    $onValue = $field['onValue'] ?? '1';
    $offValue = $field['offValue'] ?? '0';
    $currentValue = $value ?? ($field['default'] ?? $offValue);
    
    // 判断是否选中（开启）
    $isChecked = (string)$currentValue === (string)$onValue;
    
    // 获取标签文本（从配置中读取，如果配置中没有则不显示）
    $onLabel = $field['onLabel'] ?? null;
    $offLabel = $field['offLabel'] ?? null;
    $showLabel = !empty($onLabel) || !empty($offLabel);
    
    // 生成隐藏字段用于提交关闭时的值
    $hiddenInputId = $field['name'] . '_hidden';
@endphp
<div class="d-flex flex-column" style="gap: 0.5rem;">
    {{-- 字段标签 --}}
    <label for="{{ $field['name'] }}" class="form-label mb-0" style="font-weight: 500;">
        {{ $field['label'] }}
        @if($field['required'] ?? false)
        <span class="text-danger">*</span>
        @endif
    </label>
    
    {{-- 开关控件 --}}
    <div class="form-check form-switch mb-0 d-flex align-items-center" style="gap: 0.75rem;">
        {{-- 隐藏字段：用于提交关闭时的值 --}}
        <input 
            type="hidden" 
            name="{{ $field['name'] }}" 
            id="{{ $hiddenInputId }}" 
            value="{{ $isChecked ? $onValue : $offValue }}"
        >
        {{-- 开关 checkbox --}}
        <input
            class="form-check-input"
            type="checkbox"
            role="switch"
            id="{{ $field['name'] }}"
            @if($isChecked) checked @endif
            @if($field['required'] ?? false) required @endif
            style="cursor: pointer; width: 3rem; height: 1.5rem; flex-shrink: 0;"
            onchange="handleSwitchChange('{{ $field['name'] }}', '{{ $hiddenInputId }}', '{{ $onValue }}', '{{ $offValue }}', this.checked, {{ json_encode($onLabel) }}, {{ json_encode($offLabel) }})"
        >
        @if($showLabel)
        <label class="form-check-label mb-0" for="{{ $field['name'] }}" style="cursor: pointer; user-select: none; font-weight: 500; color: #495057;">
            <span id="{{ $field['name'] }}_label">{{ $isChecked ? ($onLabel ?? '') : ($offLabel ?? '') }}</span>
        </label>
        @endif
    </div>
</div>

<script>
// 确保函数只定义一次
if (typeof handleSwitchChange === 'undefined') {
    window.handleSwitchChange = function(fieldName, hiddenInputId, onValue, offValue, isChecked, onLabel, offLabel) {
        const hiddenInput = document.getElementById(hiddenInputId);
        if (hiddenInput) {
            hiddenInput.value = isChecked ? onValue : offValue;
        }
        
        // 更新标签文本（仅当标签存在且配置了标签文本时）
        const label = document.getElementById(fieldName + '_label');
        if (label) {
            const labelText = isChecked ? (onLabel || '') : (offLabel || '');
            label.textContent = labelText;
        }
    };
}
</script>

