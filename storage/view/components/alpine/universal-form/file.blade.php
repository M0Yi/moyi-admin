{{--
文件上传组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $accept: 接受的文件类型（默认: *）
- $multiple: 是否多选（默认: false）
- $maxSize: 最大文件大小（MB，默认: 10）
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.file', [
    'name' => 'avatar',
    'label' => '头像',
    'accept' => 'image/*',
    'maxSize' => 5
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $accept = $accept ?? '*';
    $multiple = $multiple ?? false;
    $maxSize = $maxSize ?? 10;
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $help = $help ?? '支持 {{ $accept }} 格式，单文件最大 {{ $maxSize }}MB';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
    $showPreview = $showPreview ?? true;
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
    
    <input
        type="file"
        name="{{ $name }}{{ $multiple ? '[]' : '' }}"
        id="{{ $id }}"
        class="form-control @if(is_array($error) && count($error) > 0) is-invalid @endif"
        accept="{{ $accept }}"
        @if($multiple) multiple @endif
        @if($required) required @endif
        @if($disabled) disabled @endif
        data-max-size="{{ $maxSize }}"
    >
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{!! $help !!}</div>
    @endif
    
    {{-- 隐藏域存储已上传文件ID --}}
    @if(isset($uploadedId))
        <input type="hidden" name="{{ $name }}_id" value="{{ $uploadedId }}">
    @endif
</div>
