/**
 * 渐变颜色选择器组件
 * 用于选择线性渐变的两个颜色和角度
 */

(function () {
    'use strict';

    const HEX_COLOR_REGEX = /^#?([0-9A-Fa-f]{3}|[0-9A-Fa-f]{4}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/;
    const GRADIENT_REGEX = /linear-gradient\s*\(\s*(\d+)deg\s*,\s*(#[0-9A-Fa-f]{3,8})\s+(\d+)%\s*,\s*(#[0-9A-Fa-f]{3,8})\s+(\d+)%\s*\)/i;

    let gradientPickerTargetInput = null;
    let gradientPickerTargetPreview = null;
    let selectedGradient = {
        angle: 135,
        color1: '#667eea',
        color1Stop: 0,
        color2: '#764ba2',
        color2Stop: 100
    };

    function initGradientPicker() {
        const modal = document.getElementById('gradientPickerModal');
        if (!modal) {
            return;
        }

        const confirmBtn = document.getElementById('confirmGradientBtn');
        const angleInput = document.getElementById('gradientAngleInput');
        const color1InputEl = document.getElementById('gradientColor1Input');
        const color2InputEl = document.getElementById('gradientColor2Input');
        const color1StopInput = document.getElementById('gradientColor1StopInput');
        const color2StopInput = document.getElementById('gradientColor2StopInput');
        const preview = document.getElementById('gradientPreview');
        const outputInput = document.getElementById('gradientOutputInput');

        // 角度输入（滑块）
        if (angleInput) {
            angleInput.addEventListener('input', function() {
                updateAngleDisplay(this.value);
                updateGradient();
            });
            angleInput.addEventListener('change', function() {
                updateAngleDisplay(this.value);
                updateGradient();
            });
        }

        // 颜色输入
        if (color1InputEl) {
            color1InputEl.addEventListener('input', function() {
                updateColor1(this.value);
            });
            color1InputEl.addEventListener('change', function() {
                updateColor1(this.value);
            });
        }

        if (color2InputEl) {
            color2InputEl.addEventListener('input', function() {
                updateColor2(this.value);
            });
            color2InputEl.addEventListener('change', function() {
                updateColor2(this.value);
            });
        }

        // 颜色停止点输入
        if (color1StopInput) {
            color1StopInput.addEventListener('input', updateGradient);
            color1StopInput.addEventListener('change', updateGradient);
        }

        if (color2StopInput) {
            color2StopInput.addEventListener('input', updateGradient);
            color2StopInput.addEventListener('change', updateGradient);
        }

        // 颜色选择器按钮
        const color1PickerBtn = document.getElementById('gradientColor1PickerBtn');
        const color2PickerBtn = document.getElementById('gradientColor2PickerBtn');

        if (color1PickerBtn) {
            color1PickerBtn.addEventListener('click', function() {
                openColorPickerForColor1();
            });
        }

        if (color2PickerBtn) {
            color2PickerBtn.addEventListener('click', function() {
                openColorPickerForColor2();
            });
        }

        // 确认按钮
        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                applyGradient();
            });
        }

        // 模态框显示时初始化
        modal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            gradientPickerTargetInput = button?.getAttribute('data-target-input') || null;
            gradientPickerTargetPreview = button?.getAttribute('data-preview-target') || null;

            const targetInput = gradientPickerTargetInput ? document.getElementById(gradientPickerTargetInput) : null;
            const currentValue = targetInput?.value || '';

            if (currentValue) {
                parseGradient(currentValue);
            } else {
                resetToDefault();
            }
            // 初始化角度显示
            if (angleInput) {
                updateAngleDisplay(angleInput.value);
            }
            updateGradient();
        });

        // 预设渐变按钮
        const presetButtons = document.querySelectorAll('[data-preset-gradient]');
        presetButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const preset = this.getAttribute('data-preset-gradient');
                if (preset) {
                    parseGradient(preset);
                    updateGradient();
                }
            });
        });

        function updateColor1(color) {
            const normalized = normalizeColor(color);
            if (normalized) {
                selectedGradient.color1 = normalized;
                if (color1InputEl) {
                    color1InputEl.value = normalized;
                }
                // 更新预览
                const color1Preview = document.getElementById('gradientColor1Preview');
                if (color1Preview) {
                    color1Preview.style.backgroundColor = normalized;
                }
                updateGradient();
            }
        }

        function updateColor2(color) {
            const normalized = normalizeColor(color);
            if (normalized) {
                selectedGradient.color2 = normalized;
                if (color2InputEl) {
                    color2InputEl.value = normalized;
                }
                // 更新预览
                const color2Preview = document.getElementById('gradientColor2Preview');
                if (color2Preview) {
                    color2Preview.style.backgroundColor = normalized;
                }
                updateGradient();
            }
        }

        function updateAngleDisplay(angle) {
            const angleValue = parseInt(angle) || 135;
            const angleDisplay = document.getElementById('gradientAngleDisplay');
            const angleDescription = document.getElementById('gradientAngleDescription');
            
            if (angleDisplay) {
                angleDisplay.textContent = angleValue + '°';
            }
            
            if (angleDescription) {
                let description = '';
                if (angleValue === 0 || angleValue === 360) {
                    description = '从下到上（0°/360°）';
                } else if (angleValue === 90) {
                    description = '从左到右（90°）';
                } else if (angleValue === 180) {
                    description = '从上到下（180°）';
                } else if (angleValue === 270) {
                    description = '从右到左（270°）';
                } else if (angleValue > 0 && angleValue < 90) {
                    description = `从左下到右上（${angleValue}°）`;
                } else if (angleValue > 90 && angleValue < 180) {
                    description = `从左上到右下（${angleValue}°）`;
                } else if (angleValue > 180 && angleValue < 270) {
                    description = `从右上到左下（${angleValue}°）`;
                } else if (angleValue > 270 && angleValue < 360) {
                    description = `从右下到左上（${angleValue}°）`;
                } else {
                    description = `${angleValue}°`;
                }
                angleDescription.textContent = description;
            }
        }

        function updateGradient() {
            if (angleInput) {
                selectedGradient.angle = parseInt(angleInput.value) || 135;
            }
            if (color1StopInput) {
                selectedGradient.color1Stop = parseInt(color1StopInput.value) || 0;
            }
            if (color2StopInput) {
                selectedGradient.color2Stop = parseInt(color2StopInput.value) || 100;
            }

            const gradientValue = generateGradientValue();
            
            if (preview) {
                preview.style.background = gradientValue;
            }
            
            if (outputInput) {
                outputInput.value = gradientValue;
            }
        }

        function generateGradientValue() {
            return `linear-gradient(${selectedGradient.angle}deg, ${selectedGradient.color1} ${selectedGradient.color1Stop}%, ${selectedGradient.color2} ${selectedGradient.color2Stop}%)`;
        }

        function parseGradient(value) {
            if (!value) {
                resetToDefault();
                return;
            }

            const match = value.match(GRADIENT_REGEX);
            if (match) {
                selectedGradient.angle = parseInt(match[1]) || 135;
                selectedGradient.color1 = normalizeColor(match[2]) || '#667eea';
                selectedGradient.color1Stop = parseInt(match[3]) || 0;
                selectedGradient.color2 = normalizeColor(match[4]) || '#764ba2';
                selectedGradient.color2Stop = parseInt(match[5]) || 100;
            } else {
                // 尝试解析其他格式，如果失败则使用默认值
                resetToDefault();
            }

            // 更新输入框
            if (angleInput) {
                angleInput.value = selectedGradient.angle;
                updateAngleDisplay(selectedGradient.angle);
            }
            if (color1InputEl) {
                color1InputEl.value = selectedGradient.color1;
            }
            if (color2InputEl) {
                color2InputEl.value = selectedGradient.color2;
            }
            if (color1StopInput) {
                color1StopInput.value = selectedGradient.color1Stop;
            }
            if (color2StopInput) {
                color2StopInput.value = selectedGradient.color2Stop;
            }
            
            // 更新颜色预览
            const color1Preview = document.getElementById('gradientColor1Preview');
            const color2Preview = document.getElementById('gradientColor2Preview');
            if (color1Preview) {
                color1Preview.style.backgroundColor = selectedGradient.color1;
            }
            if (color2Preview) {
                color2Preview.style.backgroundColor = selectedGradient.color2;
            }
        }

        function resetToDefault() {
            selectedGradient = {
                angle: 135,
                color1: '#667eea',
                color1Stop: 0,
                color2: '#764ba2',
                color2Stop: 100
            };
        }

        function applyGradient() {
            const gradientValue = generateGradientValue();
            
            if (gradientPickerTargetInput) {
                const targetInput = document.getElementById(gradientPickerTargetInput);
                if (targetInput) {
                    targetInput.value = gradientValue;
                    // 触发 change 事件
                    targetInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            if (gradientPickerTargetPreview) {
                const preview = document.getElementById(gradientPickerTargetPreview);
                if (preview) {
                    preview.style.background = gradientValue;
                }
            }

            // 关闭模态框
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }

        function openColorPickerForColor1() {
            // 颜色选择器会通过 change 事件自动更新，这里不需要额外处理
        }

        function openColorPickerForColor2() {
            // 颜色选择器会通过 change 事件自动更新，这里不需要额外处理
        }
    }

    function normalizeColor(color) {
        if (!color) {
            return null;
        }

        let normalized = color.trim();
        if (!normalized.startsWith('#')) {
            normalized = '#' + normalized;
        }

        if (HEX_COLOR_REGEX.test(normalized)) {
            return normalized;
        }

        return null;
    }

    // 初始化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initGradientPicker);
    } else {
        initGradientPicker();
    }

    // 导出到全局
    window.GradientPicker = {
        init: initGradientPicker
    };
})();

