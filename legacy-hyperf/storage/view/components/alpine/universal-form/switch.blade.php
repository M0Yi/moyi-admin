{{--
开关组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $value: 选中的值（开启时的值，默认: 1）
- $uncheckedValue: 关闭时的值（默认: 0）
- $checked: 是否开启（默认: false）
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $help: 帮助文本
- $error: 错误信息
- $size: 尺寸（default/sm/lg）

使用方式：
@include('components.alpine.universal-form.switch', [
    'name' => 'status',
    'label' => '状态',
    'checked' => true
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $value = $value ?? '1';
    $uncheckedValue = $uncheckedValue ?? '0';
    $checked = $checked ?? false;
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $size = $size ?? 'default';
    
    // 尺寸映射
    $sizeClass = match($size) {
        'sm' => 'form-switch-sm',
        'lg' => 'form-switch-lg',
        default => ''
    };
@endphp

<div class="mb-3">
    @if($label)
        <label class="form-label" for="{{ $id }}">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    
    <div class="form-check form-switch {{ $sizeClass }}">
        <input
            type="checkbox"
            name="{{ $name }}"
            id="{{ $id }}"
            class="form-check-input @if(is_array($error) && count($error) > 0) is-invalid @endif"
            value="{{ $value }}"
            @if($checked) checked @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            role="switch"
        >
        <input type="hidden" name="{{ $name }}_hidden" value="{{ $uncheckedValue }}">
    </div>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
