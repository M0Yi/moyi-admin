{{--
多行文本框组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $value: 字段值
- $placeholder: 占位符
- $rows: 行数（默认: 3）
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $readonly: 是否只读（默认: false）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.textarea', [
    'name' => 'remark',
    'label' => '备注',
    'rows' => 4,
    'placeholder' => '请输入备注信息'
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $value = $value ?? ($oldInput[$name] ?? '');
    $placeholder = $placeholder ?? '';
    $rows = $rows ?? 3;
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $readonly = $readonly ?? false;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $class = $class ?? '';
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
    
    <textarea
        name="{{ $name }}"
        id="{{ $id }}"
        class="form-control @if(is_array($error) && count($error) > 0) is-invalid @endif {{ $class }}"
        rows="{{ $rows }}"
        placeholder="{{ $placeholder }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        @if($readonly) readonly @endif
    >{{ $value }}</textarea>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
