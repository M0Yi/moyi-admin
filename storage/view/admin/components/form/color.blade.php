@php
    $inputId = $field['id'] ?? $field['name'];
    $previewId = $field['preview_id'] ?? $inputId . '_preview';
    $currentValue = $value ?? ($field['default'] ?? '');
    $normalizedValue = is_string($currentValue) && $currentValue !== ''
        ? (str_starts_with($currentValue, '#') ? $currentValue : '#' . ltrim($currentValue, '#'))
        : '';
@endphp

<div class="color-input-group">
    <div class="input-group">
        <span class="input-group-text">
            <span
                id="{{ $previewId }}"
                class="color-preview-swatch"
                style="background-color: {{ $normalizedValue ?: '#f8f9fa' }}"
                title="当前颜色预览"
            ></span>
        </span>
        <input
            type="text"
            class="form-control"
            id="{{ $inputId }}"
            name="{{ $field['name'] }}"
            value="{{ $currentValue }}"
            placeholder="{{ $field['placeholder'] ?? '#667eea' }}"
            {{ ($field['required'] ?? false) ? 'required' : '' }}
            autocomplete="off"
            data-color-input="true"
            data-color-preview="{{ $previewId }}"
        >
        <button
            class="btn btn-outline-primary"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#colorPickerModal"
            data-target-input="{{ $inputId }}"
            data-preview-target="{{ $previewId }}"
        >
            <i class="bi bi-palette me-1"></i> 选择颜色
        </button>
    </div>
</div>

