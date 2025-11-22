
;(function () {
    'use strict';

    // 全局命名空间初始化（避免重复覆盖）
    window.Admin = window.Admin || {};
    window.Admin.utils = window.Admin.utils || {};

    /**
     * 生成后台路由路径
     * 自动拼接当前站点的后台入口路径
     *
     * @param {string} path - 相对路径（不带前缀），例如 'dashboard' 或 'users/create'
     * @returns {string} 完整的后台路由，例如 '/admin/dashboard' 或 '/manage/users/create'
     *
     * @example
     * adminRoute('dashboard')           // 返回 '/admin/dashboard'
     * adminRoute('users/create')        // 返回 '/admin/users/create'
     * adminRoute('/users')              // 返回 '/admin/users' (自动去除前导斜杠)
     * adminRoute('logs/operations')     // 返回 '/admin/logs/operations'
     */
    function adminRoute(path) {
        if (typeof path !== 'string') {
            path = String(path || '');
        }
        // 移除路径前后的斜杠
        path = path.replace(/^\/+|\/+$/g, '');
        // 拼接完整路径
        return window.ADMIN_ENTRY_PATH + (path ? '/' + path : '');
    }

    /**
     * 检查当前路径是否匹配指定的后台路由
     *
     * @param {string} path - 要匹配的路径（相对路径）
     * @param {boolean} exact - 是否精确匹配（默认为false，前缀匹配）
     * @returns {boolean}
     *
     * @example
     * isAdminRoute('dashboard')         // 当前在 /admin/dashboard 返回 true
     * isAdminRoute('users', false)      // 当前在 /admin/users/123 返回 true
     * isAdminRoute('users', true)       // 当前在 /admin/users/123 返回 false
     */
    function isAdminRoute(path, exact = false) {
        const currentPath = window.location.pathname;
        const targetPath = adminRoute(path);

        if (exact) {
            return currentPath === targetPath;
        }

        return currentPath === targetPath || currentPath.startsWith(targetPath + '/');
    }

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
    // 优先从 checkbox 的 data-field 属性读取字段名
    const actualField = checkbox.dataset.field || field;

    console.log(`[ToggleStatus] 函数被调用 (scripts.blade.php):`, {
        id: id,
        checkbox: checkbox,
        apiUrlTemplate: apiUrlTemplate,
        fieldParam: field,
        actualField: actualField,
        timestamp: new Date().toISOString(),
        checkboxChecked: checkbox.checked,
        checkboxDataset: {
            field: checkbox.dataset.field,
            onValue: checkbox.dataset.onValue,
            offValue: checkbox.dataset.offValue,
            rowId: checkbox.dataset.rowId,
            tableId: checkbox.dataset.tableId
        }
    });

    const originalChecked = checkbox.checked;
    // 如果 checkbox 有 data-on-value 和 data-off-value，使用它们
    const onValue = checkbox.dataset.onValue !== undefined ? parseInt(checkbox.dataset.onValue) : 1;
    const offValue = checkbox.dataset.offValue !== undefined ? parseInt(checkbox.dataset.offValue) : 0;
    const newValue = originalChecked ? onValue : offValue;

    console.log(`[ToggleStatus] 状态切换准备:`, {
        recordId: id,
        field: actualField,
        originalChecked: originalChecked,
        newValue: newValue,
        onValue: onValue,
        offValue: offValue,
        apiUrlTemplate: apiUrlTemplate
    });

    // 禁用开关，防止重复点击
    checkbox.disabled = true;
    console.log(`[ToggleStatus] 开关已禁用，防止重复点击`);

    try {
        const url = apiUrlTemplate.replace('{id}', id);

        console.log(`[ToggleStatus] 发送 PUT 请求:`, {
            url: url,
            method: 'PUT',
            field: actualField,
            value: newValue,
            requestBody: {
                [actualField]: newValue
            }
        });

        const response = await fetch(url, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                [actualField]: newValue
            })
        });

        console.log(`[ToggleStatus] 收到响应:`, {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            headers: Object.fromEntries(response.headers.entries())
        });

        const result = await response.json();

        console.log(`[ToggleStatus] 响应数据:`, result);

        if (result.code === 200) {
            console.log(`[ToggleStatus] 状态更新成功:`, {
                recordId: id,
                field: actualField,
                newValue: newValue,
                message: result.message || result.msg
            });

            // 更新成功
            showToast('success', result.message || result.msg || '状态更新成功', 1500);
            // 根据 onValue 和 offValue 更新 checkbox 状态
            checkbox.checked = newValue === onValue;

            // 更新开关样式
            if (checkbox.checked) {
                checkbox.classList.remove('bg-secondary');
                checkbox.classList.add('bg-success');
            } else {
                checkbox.classList.remove('bg-success');
                checkbox.classList.add('bg-secondary');
            }

            console.log(`[ToggleStatus] 开关状态已更新为:`, checkbox.checked, `(值: ${newValue})`);
        } else {
            console.warn(`[ToggleStatus] 状态更新失败:`, {
                recordId: id,
                field: actualField,
                errorCode: result.code,
                errorMessage: result.message || result.msg
            });

            // 更新失败，恢复原状态
            showToast('danger', result.message || result.msg || '状态更新失败');
            checkbox.checked = !originalChecked;

            console.log(`[ToggleStatus] 已恢复开关原状态:`, !originalChecked);
        }
    } catch (error) {
        console.error(`[ToggleStatus] 请求异常:`, {
            recordId: id,
            field: actualField,
            error: error,
            errorName: error.name,
            errorMessage: error.message,
            errorStack: error.stack
        });

        showToast('danger', '状态更新失败，请稍后重试');
        // 恢复原状态
        checkbox.checked = !originalChecked;

        console.log(`[ToggleStatus] 异常后已恢复开关原状态:`, !originalChecked);
    } finally {
        // 重新启用开关
        checkbox.disabled = false;
        console.log(`[ToggleStatus] 开关已重新启用`);
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

/* ==================== 侧边栏高亮工具 ==================== */
/**
 * 根据给定路径高亮侧边栏菜单
 * @param {string} pathname - 如 /admin/users 或 /manage/system/config
 */
function setSidebarActiveByUrl(pathname) {
    try {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar) {
            return;
        }

        const navLinks = sidebar.querySelectorAll('.nav-link[data-path]');

        // 先清空所有 active 状态
        navLinks.forEach(link => {
            link.classList.remove('active');
        });

        // 折叠所有子菜单
        const submenus = sidebar.querySelectorAll('.collapse');
        submenus.forEach(menu => {
            menu.classList.remove('show');
        });

        let matchedLink = null;

        navLinks.forEach(link => {
            const linkPath = link.getAttribute('data-path');
            if (!linkPath) {
                return;
            }

            const fullPath = adminRoute(linkPath);

            if (pathname === fullPath || (linkPath !== 'dashboard' && pathname.startsWith(fullPath + '/'))) {
                matchedLink = link;
            }
        });

        if (matchedLink) {
            matchedLink.classList.add('active');

            // 展开父菜单
            const submenu = matchedLink.closest('.collapse');
            if (submenu) {
                submenu.classList.add('show');
                const parentLink = document.querySelector(`[data-target="#${submenu.id}"]`);
                if (parentLink) {
                    const arrow = parentLink.querySelector('.submenu-arrow');
                    if (arrow) {
                        arrow.style.transform = 'rotate(180deg)';
                    }
                }
            }
        } else if (pathname.startsWith(window.ADMIN_ENTRY_PATH)) {
            // 没有找到匹配项时，且仍在后台路径下，默认高亮仪表盘
            const dashboardLink = sidebar.querySelector('.nav-link[data-path="dashboard"]');
            if (dashboardLink) {
                dashboardLink.classList.add('active');
            }
        }
    } catch (e) {
        // 安全兜底，避免因为菜单高亮影响主流程
        // console.warn('setSidebarActiveByUrl error:', e);
    }
}

/* ==================== iframe 内页统一成功处理 ==================== */
/**
 * iframe 内页调用的统一成功处理函数
 *
 * 默认行为：
 * - Toast 提示成功
 * - 如果存在父窗口且有 Admin.tabManager：
 *   - 可选：刷新指定列表 URL 对应的标签页
 *   - 关闭当前活动标签页
 * - 如果没有父窗口（非 iframe 或异常）：回退为当前页刷新
 *
 * @param {Object} options
 * @param {string} [options.message='操作成功'] - 成功提示文案
 * @param {string|null} [options.refreshUrl=null] - 需要刷新的列表页 URL（相对后台入口的 path 或完整 URL）
 * @param {boolean} [options.closeCurrent=true] - 是否关闭当前标签
 * @param {number} [options.delay=800] - 关闭/刷新操作前的延迟（毫秒）
 */
function handleEmbeddedFormSuccess(options) {
    const opts = Object.assign({
        message: '操作成功',
        refreshUrl: null,
        closeCurrent: true,
        delay: 800
    }, options || {});

    try {
        showToast('success', opts.message);
    } catch (e) {
        // 如果 toast 不可用则忽略
    }

    // 非 iframe 场景，直接刷新当前页面
    if (window.parent === window) {
        if (opts.closeCurrent) {
            reloadPage(opts.delay);
        }
        return;
    }

    try {
        const parentWin = window.parent;
        const hasTabManager = parentWin && parentWin.Admin && parentWin.Admin.tabManager;

        if (!hasTabManager) {
            if (opts.closeCurrent) {
                reloadPage(opts.delay);
            }
            return;
        }

        const tabManager = parentWin.Admin.tabManager;

        // 刷新指定 URL 对应的标签页（如果提供）
        if (opts.refreshUrl && typeof tabManager.refreshTabByUrl === 'function') {
            // refreshUrl 既可以传 /admin/users 也可以传 users
            let targetUrl = opts.refreshUrl;
            if (!/^https?:\/\//i.test(targetUrl) && !targetUrl.startsWith(window.ADMIN_ENTRY_PATH)) {
                targetUrl = adminRoute(targetUrl);
            }
            tabManager.refreshTabByUrl(targetUrl);
        }

        if (opts.closeCurrent && typeof tabManager.closeCurrentTab === 'function') {
            // 给一点延迟让用户看到 toast
            setTimeout(function () {
                tabManager.closeCurrentTab();
            }, opts.delay);
        }
    } catch (e) {
        if (opts.closeCurrent) {
            reloadPage(opts.delay);
        }
    }
}

// ===== 将公共函数挂载到 Admin 命名空间，并保持向后兼容的全局访问 =====
window.adminRoute = adminRoute;
window.isAdminRoute = isAdminRoute;

window.Admin.route = adminRoute;
window.Admin.isAdminRoute = isAdminRoute;

// 工具函数集合
window.Admin.utils.showToast = showToast;
// 保持向后兼容：挂载为全局函数
window.showToast = showToast;
window.Admin.utils.showDeleteModal = showDeleteModal;
window.Admin.utils.executeDelete = executeDelete;
window.Admin.utils.submitForm = submitForm;
window.Admin.utils.toggleStatus = toggleStatus;
window.Admin.utils.preventAutofill = preventAutofill;
window.Admin.utils.initIconPreview = initIconPreview;
window.Admin.utils.confirmAction = confirmAction;
window.Admin.utils.redirectTo = redirectTo;
window.Admin.utils.reloadPage = reloadPage;
window.Admin.utils.getUrlParameter = getUrlParameter;
window.Admin.utils.setUrlParameter = setUrlParameter;
window.Admin.utils.formatDate = formatDate;
window.Admin.utils.setSidebarActiveByUrl = setSidebarActiveByUrl;
window.Admin.utils.handleEmbeddedFormSuccess = handleEmbeddedFormSuccess;

// 便于 iframe 内直接调用的别名
window.Admin.handleEmbeddedFormSuccess = handleEmbeddedFormSuccess;

})(); // 结束 IIFE 包裹