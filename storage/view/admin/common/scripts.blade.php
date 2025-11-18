{{-- 后台管理系统统一 JavaScript 函数 --}}
<script>
/* ==================== Toast 通知函数 ==================== */
/**
 * 显示 Toast 通知
 * @param {string} type - 类型: success, danger, warning, info
 * @param {string} message - 消息内容
 * @param {number} delay - 显示时长（毫秒），默认 3000
 */
function showToast(type, message, delay = 3000) {
    // 创建 toast 容器（如果不存在）
    let toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    // 创建 toast 元素
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' :
                   type === 'danger' ? 'bg-danger' :
                   type === 'warning' ? 'bg-warning' : 'bg-info';

    const iconClass = type === 'success' ? 'bi-check-circle-fill' :
                     type === 'danger' ? 'bi-exclamation-circle-fill' :
                     type === 'warning' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill';

    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white ${bgClass} border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi ${iconClass} me-2"></i>
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    toastContainer.insertAdjacentHTML('beforeend', toastHtml);

    // 显示 toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: delay });
    toast.show();

    // 自动移除
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

/* ==================== 删除确认模态框函数 ==================== */
/**
 * 显示删除确认模态框
 * @param {number} id - 要删除的项目 ID
 * @param {string} name - 项目名称
 * @param {boolean} hasChildren - 是否有子项（可选）
 * @param {string} modalId - 模态框 ID（默认 'deleteModal'）
 * @param {string} nameElementId - 名称元素 ID（默认 'deleteItemName'）
 * @param {string} warningElementId - 警告元素 ID（可选）
 * @param {string} confirmBtnId - 确认按钮 ID（默认 'confirmDeleteBtn'）
 */
function showDeleteModal(id, name, hasChildren = false, modalId = 'deleteModal', nameElementId = 'deleteItemName', warningElementId = 'hasChildrenWarning', confirmBtnId = 'confirmDeleteBtn') {
    // 存储要删除的 ID
    window._deleteItemId = id;

    // 设置项目名称
    const nameElement = document.getElementById(nameElementId);
    if (nameElement) {
        nameElement.textContent = name;
    }

    // 处理子项警告
    if (warningElementId) {
        const warningElement = document.getElementById(warningElementId);
        const confirmBtn = document.getElementById(confirmBtnId);

        if (warningElement && confirmBtn) {
            if (hasChildren) {
                warningElement.style.display = 'block';
                confirmBtn.disabled = true;
                confirmBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i> 无法删除';
            } else {
                warningElement.style.display = 'none';
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = '<i class="bi bi-trash me-1"></i> 确认删除';
            }
        }
    }

    // 显示模态框
    const modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

/**
 * 执行删除操作
 * @param {string} apiUrl - API 地址（可以是函数或字符串）
 * @param {string} modalId - 模态框 ID（默认 'deleteModal'）
 * @param {string} confirmBtnId - 确认按钮 ID（默认 'confirmDeleteBtn'）
 * @param {function} successCallback - 成功回调（默认刷新页面）
 */
async function executeDelete(apiUrl, modalId = 'deleteModal', confirmBtnId = 'confirmDeleteBtn', successCallback = null) {
    if (!window._deleteItemId) {
        return;
    }

    const confirmBtn = document.getElementById(confirmBtnId);
    const originalHtml = confirmBtn.innerHTML;

    // 显示加载状态
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 删除中...';

    try {
        // 如果 apiUrl 是函数，则调用函数传入 ID
        const url = typeof apiUrl === 'function' ? apiUrl(window._deleteItemId) : apiUrl.replace('{id}', window._deleteItemId);

        const response = await fetch(url, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const result = await response.json();

        if (result.code === 200) {
            // 删除成功
            showToast('success', result.message || result.msg || '删除成功');

            // 关闭模态框
            const modal = bootstrap.Modal.getInstance(document.getElementById(modalId));
            if (modal) {
                modal.hide();
            }

            // 执行回调或默认刷新页面
            if (successCallback) {
                successCallback(result);
            } else {
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
            }
        } else {
            // 删除失败
            showToast('danger', result.message || result.msg || '删除失败');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalHtml;
        }
    } catch (error) {
        console.error('删除失败:', error);
        showToast('danger', '删除失败，请稍后重试');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalHtml;
    } finally {
        // 清理 ID
        window._deleteItemId = null;
    }
}

/* ==================== 表单提交函数 ==================== */
/**
 * 提交表单数据
 * @param {HTMLFormElement} form - 表单元素
 * @param {string} url - 提交地址
 * @param {string} method - HTTP 方法（POST, PUT, DELETE 等）
 * @param {string} submitBtnId - 提交按钮 ID
 * @param {function} successCallback - 成功回调
 * @param {function} dataTransform - 数据转换函数（可选）
 */
async function submitForm(form, url, method, submitBtnId, successCallback, dataTransform = null) {
    const submitBtn = document.getElementById(submitBtnId);
    const originalHtml = submitBtn.innerHTML;

    // 获取表单数据
    const formData = new FormData(form);
    let data = {};

    // 转换 FormData 为普通对象
    formData.forEach((value, key) => {
        // 处理数组字段（如 role_ids[]）
        if (key.endsWith('[]')) {
            const actualKey = key.slice(0, -2);
            if (!data[actualKey]) {
                data[actualKey] = [];
            }
            data[actualKey].push(value);
        } else {
            data[key] = value;
        }
    });

    // 如果有数据转换函数，则调用
    if (dataTransform) {
        data = dataTransform(data);
    }

    // 禁用提交按钮
    submitBtn.disabled = true;
    const loadingText = method === 'POST' ? '创建中...' :
                       method === 'PUT' ? '保存中...' : '提交中...';
    submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> ${loadingText}`;

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();

        if (result.code === 200) {
            // 成功
            showToast('success', result.message || result.msg || '操作成功');

            // 执行回调
            if (successCallback) {
                successCallback(result);
            }
        } else {
            // 失败
            showToast('danger', result.message || result.msg || '操作失败');

            // 恢复按钮
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    } catch (error) {
        console.error('提交失败:', error);
        showToast('danger', '提交失败，请稍后重试');

        // 恢复按钮
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    }
}

/* ==================== 状态切换函数 ==================== */
/**
 * 切换状态（通用开关切换）
 * @param {number} id - 项目 ID
 * @param {HTMLInputElement} checkbox - 复选框元素
 * @param {string} apiUrlTemplate - API 地址模板（使用 {id} 作为占位符）
 * @param {string} field - 要更新的字段名（默认 'status'）
 */
async function toggleStatus(id, checkbox, apiUrlTemplate, field = 'status') {
    const originalChecked = checkbox.checked;
    const newValue = originalChecked ? 1 : 0;

    // 禁用开关，防止重复点击
    checkbox.disabled = true;

    try {
        const url = apiUrlTemplate.replace('{id}', id);
        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                [field]: newValue
            })
        });

        const result = await response.json();

        if (result.code === 200) {
            // 更新成功
            showToast('success', result.message || result.msg || '状态更新成功', 1500);
            checkbox.checked = newValue === 1;
        } else {
            // 更新失败，恢复原状态
            showToast('danger', result.message || result.msg || '状态更新失败');
            checkbox.checked = !originalChecked;
        }
    } catch (error) {
        console.error('状态更新失败:', error);
        showToast('danger', '状态更新失败，请稍后重试');
        // 恢复原状态
        checkbox.checked = !originalChecked;
    } finally {
        // 重新启用开关
        checkbox.disabled = false;
    }
}

/* ==================== 防止自动填充函数 ==================== */
/**
 * 防止浏览器自动填充表单
 * @param {string} usernameFieldId - 用户名字段 ID
 * @param {string} passwordFieldId - 密码字段 ID
 */
function preventAutofill(usernameFieldId = 'username', passwordFieldId = 'password') {
    const usernameField = document.getElementById(usernameFieldId);
    const passwordField = document.getElementById(passwordFieldId);

    if (!usernameField || !passwordField) {
        return;
    }

    // 页面加载后短暂延迟清空字段
    setTimeout(() => {
        if (usernameField && usernameField.value && !usernameField.dataset.userInput) {
            usernameField.value = '';
        }
        if (passwordField && passwordField.value && !passwordField.dataset.userInput) {
            passwordField.value = '';
        }
    }, 300);

    // 用户名字段处理
    let usernameTouched = false;
    usernameField.addEventListener('focus', function() {
        if (!usernameTouched) {
            setTimeout(() => {
                if (this.value && !this.dataset.userInput) {
                    this.value = '';
                }
            }, 100);
        }
    });
    usernameField.addEventListener('input', function() {
        usernameTouched = true;
        this.dataset.userInput = 'true';
    });
    usernameField.addEventListener('keydown', function() {
        this.dataset.userInput = 'true';
    });

    // 密码字段处理
    let passwordTouched = false;
    passwordField.addEventListener('focus', function() {
        if (!passwordTouched) {
            setTimeout(() => {
                if (this.value && !this.dataset.userInput) {
                    this.value = '';
                }
            }, 100);
        }
    });
    passwordField.addEventListener('input', function() {
        passwordTouched = true;
        this.dataset.userInput = 'true';
    });
    passwordField.addEventListener('keydown', function() {
        this.dataset.userInput = 'true';
    });
}

/* ==================== 图标预览函数 ==================== */
/**
 * 图标输入实时预览
 * @param {string} inputId - 输入框 ID
 * @param {string} previewId - 预览元素 ID
 */
function initIconPreview(inputId = 'icon', previewId = 'iconPreview') {
    const iconInput = document.getElementById(inputId);
    const iconPreview = document.getElementById(previewId);

    if (iconInput && iconPreview) {
        iconInput.addEventListener('input', function() {
            const iconClass = this.value.trim();
            if (iconClass) {
                iconPreview.innerHTML = `<i class="${iconClass}"></i>`;
            } else {
                iconPreview.innerHTML = '<i class="bi bi-question-circle"></i>';
            }
        });
    }
}

/* ==================== 确认操作函数 ==================== */
/**
 * 显示确认对话框
 * @param {string} message - 确认消息
 * @returns {boolean} 用户是否确认
 */
function confirmAction(message) {
    return confirm(message);
}

/* ==================== 页面跳转函数 ==================== */
/**
 * 延迟跳转到指定页面
 * @param {string} url - 目标 URL
 * @param {number} delay - 延迟时间（毫秒），默认 1000
 */
function redirectTo(url, delay = 1000) {
    setTimeout(() => {
        window.location.href = url;
    }, delay);
}

/**
 * 刷新当前页面
 * @param {number} delay - 延迟时间（毫秒），默认 1000
 */
function reloadPage(delay = 1000) {
    setTimeout(() => {
        window.location.reload();
    }, delay);
}

/* ==================== 工具函数 ==================== */
/**
 * 获取 URL 参数
 * @param {string} name - 参数名
 * @returns {string|null} 参数值
 */
function getUrlParameter(name) {
    const params = new URLSearchParams(window.location.search);
    return params.get(name);
}

/**
 * 设置 URL 参数
 * @param {string} name - 参数名
 * @param {string} value - 参数值
 */
function setUrlParameter(name, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(name, value);
    window.history.pushState({}, '', url);
}

/**
 * 格式化日期
 * @param {Date|string} date - 日期对象或字符串
 * @param {string} format - 格式（默认 'YYYY-MM-DD HH:mm:ss'）
 * @returns {string} 格式化后的日期字符串
 */
function formatDate(date, format = 'YYYY-MM-DD HH:mm:ss') {
    const d = new Date(date);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');

    return format
        .replace('YYYY', year)
        .replace('MM', month)
        .replace('DD', day)
        .replace('HH', hours)
        .replace('mm', minutes)
        .replace('ss', seconds);
}
</script>

