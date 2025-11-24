/**
 * 颜色选择器组件
 * 参考图标选择器的交互方式，用于统一管理 HEX 颜色选择与预览
 */

(function () {
    'use strict';

    const HEX_COLOR_REGEX = /^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/;
    const presetColors = (window.ColorPickerPresetColors || []).map(color => normalizeColor(color)).filter(Boolean);

    let colorPickerTargetInput = null;
    let colorPickerTargetPreview = null;
    let selectedColor = '';

    function initColorPicker() {
        const modal = document.getElementById('colorPickerModal');
        if (!modal) {
            return;
        }

        const colorGrid = document.getElementById('colorGrid');
        const confirmBtn = document.getElementById('confirmColorBtn');
        const customInput = document.getElementById('customColorInput');
        const customApplyBtn = document.getElementById('customColorApplyBtn');

        renderColorGrid(colorGrid, presetColors);

        colorGrid.addEventListener('click', function (event) {
            const swatch = event.target.closest('[data-color-value]');
            if (!swatch) {
                return;
            }
            selectColor(swatch.getAttribute('data-color-value'));
        });

        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            colorPickerTargetInput = button?.getAttribute('data-target-input') || null;
            colorPickerTargetPreview = button?.getAttribute('data-preview-target') || null;

            const targetInput = colorPickerTargetInput ? document.getElementById(colorPickerTargetInput) : null;
            const currentValue = targetInput?.value || '';

            if (currentValue) {
                selectColor(currentValue, { silent: true });
            } else {
                clearSelection();
            }
        });

        confirmBtn.addEventListener('click', function () {
            if (!selectedColor) {
                return;
            }

            const targetInput = colorPickerTargetInput ? document.getElementById(colorPickerTargetInput) : null;
            if (targetInput) {
                targetInput.value = selectedColor;
                targetInput.dispatchEvent(new Event('input', { bubbles: true }));
                targetInput.dispatchEvent(new Event('change', { bubbles: true }));
            }

            const targetPreview = colorPickerTargetPreview ? document.getElementById(colorPickerTargetPreview) : null;
            if (targetPreview) {
                targetPreview.style.backgroundColor = selectedColor;
            }

            const modalInstance = bootstrap.Modal.getInstance(modal);
            modalInstance?.hide();
        });

        function applyCustomColor() {
            const rawValue = customInput.value.trim();
            if (!rawValue) {
                return;
            }

            if (!isValidHexColor(rawValue)) {
                customInput.classList.add('is-invalid');
                return;
            }

            customInput.classList.remove('is-invalid');
            selectColor(rawValue);
            focusConfirmButton(confirmBtn);
        }

        customApplyBtn.addEventListener('click', function () {
            applyCustomColor();
        });

        customInput.addEventListener('input', function () {
            if (this.classList.contains('is-invalid') && isValidHexColor(this.value)) {
                this.classList.remove('is-invalid');
            }
        });

        customInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                applyCustomColor();
            }
        });
    }

    function renderColorGrid(container, colors) {
        if (!container) {
            return;
        }

        if (!colors.length) {
            container.innerHTML = '<div class="text-muted text-center py-4">暂无预设颜色</div>';
            return;
        }

        container.innerHTML = colors.map(color => `
            <button
                type="button"
                class="color-swatch btn p-0"
                style="background: ${color};"
                data-color-value="${color}"
                aria-label="${color}"
            ></button>
        `).join('');
    }

    function selectColor(value, options = {}) {
        const normalized = normalizeColor(value);
        if (!normalized) {
            return;
        }

        selectedColor = normalized;
        highlightSelectedSwatch(normalized);
        updateSelectedColorPreviewText(normalized);

        if (!options.silent) {
            const customInput = document.getElementById('customColorInput');
            if (customInput) {
                customInput.value = normalized.replace('#', '');
                customInput.classList.remove('is-invalid');
            }
        }

        const confirmBtn = document.getElementById('confirmColorBtn');
        if (confirmBtn) {
            confirmBtn.disabled = false;
        }
    }

    function clearSelection() {
        selectedColor = '';
        highlightSelectedSwatch('');
        updateSelectedColorPreviewText('未选择');
        const confirmBtn = document.getElementById('confirmColorBtn');
        if (confirmBtn) {
            confirmBtn.disabled = true;
        }
        const customInput = document.getElementById('customColorInput');
        if (customInput) {
            customInput.value = '';
            customInput.classList.remove('is-invalid');
        }
    }

    function highlightSelectedSwatch(color) {
        document.querySelectorAll('#colorGrid .color-swatch').forEach((element) => {
            if (!color) {
                element.classList.remove('selected');
                return;
            }

            const swatchColor = normalizeColor(element.getAttribute('data-color-value'));
            if (swatchColor === color) {
                element.classList.add('selected');
            } else {
                element.classList.remove('selected');
            }
        });
    }

    function updateSelectedColorPreviewText(text) {
        const label = document.getElementById('selectedColorPreviewText');
        if (!label) {
            return;
        }

        if (text === '未选择') {
            label.textContent = text;
            label.style.color = '';
            return;
        }

        label.innerHTML = `<span class="color-preview-swatch me-2" style="background:${text}"></span>${text}`;
        label.style.color = '#0d6efd';
    }

    function focusConfirmButton(button) {
        if (!button) {
            return;
        }
        setTimeout(() => button.focus(), 0);
    }

    function normalizeColor(value) {
        if (!value || typeof value !== 'string') {
            return null;
        }

        const trimmed = value.trim();
        if (!isValidHexColor(trimmed)) {
            return null;
        }

        const withoutHash = trimmed.startsWith('#') ? trimmed.substring(1) : trimmed;
        return `#${withoutHash.toLowerCase()}`;
    }

    function isValidHexColor(value) {
        if (!value || typeof value !== 'string') {
            return false;
        }
        return HEX_COLOR_REGEX.test(value.trim());
    }

    function updateColorPreviewFromInput(inputElement) {
        const previewId = inputElement.getAttribute('data-color-preview');
        if (!previewId) {
            return;
        }

        const previewElement = document.getElementById(previewId);
        if (!previewElement) {
            return;
        }

        const normalized = normalizeColor(inputElement.value);
        if (normalized) {
            previewElement.style.backgroundColor = normalized;
            inputElement.classList.remove('is-invalid');
        } else if (inputElement.value.trim() === '') {
            previewElement.style.backgroundColor = '#f8f9fa';
            inputElement.classList.remove('is-invalid');
        } else {
            previewElement.style.backgroundColor = '#f8f9fa';
        }
    }

    document.addEventListener('input', function (event) {
        if (!event.target.matches('[data-color-input="true"]')) {
            return;
        }
        updateColorPreviewFromInput(event.target);
    });

    document.addEventListener('DOMContentLoaded', function () {
        initColorPicker();
        document.querySelectorAll('[data-color-input="true"]').forEach(updateColorPreviewFromInput);
    });
})();


