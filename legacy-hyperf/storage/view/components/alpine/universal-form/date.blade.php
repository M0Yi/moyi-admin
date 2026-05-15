{{--
日期选择器组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $value: 字段值
- $placeholder: 占位符
- $format: 日期格式（默认: YYYY-MM-DD）
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $readonly: 是否只读（默认: true）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.date', [
    'name' => 'created_at',
    'label' => '创建日期',
    'format' => 'YYYY-MM-DD'
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $value = $value ?? ($oldInput[$name] ?? '');
    $placeholder = $placeholder ?? '选择日期';
    $format = $format ?? 'YYYY-MM-DD';
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $readonly = $readonly ?? true;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $class = $class ?? '';
    $allowInput = $allowInput ?? false;
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
    
    <div class="input-group">
        <input
            type="text"
            name="{{ $name }}"
            id="{{ $id }}"
            class="form-control flatpickr @if(is_array($error) && count($error) > 0) is-invalid @endif {{ $class }}"
            value="{{ $value }}"
            placeholder="{{ $placeholder }}"
            data-format="{{ $format }}"
            @if($allowInput) data-allow-input="true" @endif
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
        >
        <button class="btn btn-outline-secondary" type="button" data-toggle>
            <i class="bi bi-calendar3"></i>
        </button>
        <button class="btn btn-outline-secondary" type="button" data-clear>
            <i class="bi bi-x-lg"></i>
        </button>
    </div>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
