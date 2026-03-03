{{--
复选框组件

参数：
- $name: 字段名称（必需）
- $label: 标签文本
- $options: 选项数组 [{value, label}]（必需）
- $checkedValues: 已选中的值数组
- $required: 是否必填（默认: false）
- $disabled: 是否禁用（默认: false）
- $inline: 是否内联显示（默认: true）
- $help: 帮助文本
- $error: 错误信息

使用方式：
@include('components.alpine.universal-form.checkbox', [
    'name' => 'roles',
    'label' => '角色',
    'options' => [
        ['value' => '1', 'label' => '管理员'],
        ['value' => '2', 'label' => '编辑'],
        ['value' => '3', 'label' => '访客']
    ],
    'required' => true
])
--}}

@php
    $name = $name ?? '';
    $label = $label ?? '';
    $options = $options ?? [];
    $checkedValues = $checkedValues ?? ($oldInput[$name] ?? []);
    $required = $required ?? false;
    $disabled = $disabled ?? false;
    $inline = $inline ?? true;
    $help = $help ?? '';
    $error = $error ?? ($errors[$name] ?? []);
    $id = $id ?? $name;
@endphp

<div class="mb-3">
    @if($label)
        <label class="form-label">
            {{ $label }}
            @if($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif
    
    <div class="{{ $inline ? 'd-flex gap-3' : '' }}">
        @foreach($options as $index => $option)
            @php
                $checkboxId = $id . '_' . $index;
                $checked = in_array($option['value'], (array)$checkedValues);
            @endphp
            <div class="form-check">
                <input
                    type="checkbox"
                    name="{{ $name }}[]"
                    id="{{ $checkboxId }}"
                    class="form-check-input @if(is_array($error) && count($error) > 0) is-invalid @endif"
                    value="{{ $option['value'] }}"
                    @if($checked) checked @endif
                    @if($required) required @endif
                    @if($disabled) disabled @endif
                >
                <label class="form-check-label" for="{{ $checkboxId }}">
                    {{ $option['label'] }}
                </label>
            </div>
        @endforeach
    </div>
    
    @if(is_array($error) && count($error) > 0)
        <div class="invalid-feedback d-block">{{ $error[0] }}</div>
    @endif
    
    @if($help)
        <div class="form-text">{{ $help }}</div>
    @endif
</div>
