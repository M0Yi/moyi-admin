/**
 * 插件管理页面 JavaScript
 *
 * 包含插件列表页面的所有交互逻辑
 */

(function() {
    'use strict';

    // 全局变量，存储路由配置
    let addonRoutes = {};

    /**
     * 初始化插件管理页面
     * @param {Object} options 配置选项
     */
    function initAddonPage(options = {}) {
        // 保存路由配置
        addonRoutes = options.routes || {};

        // 初始化数据表格
        if (window.DataTableManager && typeof window.DataTableManager.init === 'function') {
            window.DataTableManager.init('addonTable');
        }

        // 自定义渲染函数
        window.renderAddonStatus = function(value, row, column) {
            const source = row.source || 'local';

            if (source === 'store') {
                // 商店插件：显示安装状态
                const isInstalled = row.installed ? true : false;
                const status = isInstalled ? '已安装' : '未安装';
                const variant = isInstalled ? 'success' : 'secondary';
                return `<span class="badge bg-${variant}">${status}</span>`;
            } else {
                // 本地插件：显示启用状态
                const isEnabled = row.enabled ? true : false;
                const status = isEnabled ? '启用' : '禁用';
                const variant = isEnabled ? 'success' : 'secondary';
                return `<span class="badge bg-${variant}">${status}</span>`;
            }
        };

        // 来源渲染函数
        window.renderAddonSource = function(value, column, row) {
            const source = value || 'local';
            const labels = {
                'local': { text: '本地插件', variant: 'primary' },
                'store': { text: '应用商城', variant: 'info' }
            };

            const label = labels[source] || labels['local'];

            return `<span class="badge bg-${label.variant}">${label.text}</span>`;
        };

        // 安装状态渲染函数
        window.renderAddonInstallStatus = function(value, column, row) {
            const isInstalled = row.installed ? true : false;
            const canUpgrade = row.can_upgrade ? true : false;
            const currentVersion = row.current_version || '';
            const latestVersion = row.version || '';

            if (isInstalled) {
                if (canUpgrade) {
                    return `<div class="text-center">
                        <span class="badge bg-warning mb-1">可升级</span><br>
                        <small class="text-muted">${currentVersion} → ${latestVersion}</small>
                    </div>`;
                } else {
                    return `<div class="text-center">
                        <span class="badge bg-success">已安装</span><br>
                        <small class="text-muted">v${currentVersion}</small>
                    </div>`;
                }
            } else {
                return `<div class="text-center">
                    <span class="badge bg-secondary">未安装</span>
                </div>`;
            }
        };

        // 状态切换开关渲染函数
        window.renderAddonStatusToggle = function(value, column, row) {
            // 注意：数据表格组件传递参数顺序是 (value, column, row)

            // 使用id字段作为插件标识（从插件info.php中读取的唯一标识）
            const addonId = row.id || row.directory;

            const isEnabled = value ? true : false;
            const checked = isEnabled ? 'checked' : '';

            return `<div class="form-check form-switch">
                <input class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="addon-status-${addonId}"
                    ${checked}
                    onchange="toggleAddonStatus('${addonId}', this.checked)"
                    title="${isEnabled ? '点击禁用插件' : '点击启用插件'}">
            </div>`;
        };

        // 绑定事件
        bindAddonEvents();
    }

    /**
     * 绑定插件相关事件
     */
    function bindAddonEvents() {
        // 这里可以添加其他事件绑定逻辑
    }

    /**
     * 获取API路由
     * @param {string} path 路径
     * @returns {string} 完整URL
     */
    function getAddonRoute(path = '') {
        const baseUrl = addonRoutes.base || '/admin/admin/system/addons';
        return path ? `${baseUrl}/${path}` : baseUrl;
    }

    /**
     * 开启插件
     */
    window.enableAddon = function(addonId) {
        // 立即更新UI状态
        const switchElement = document.getElementById(`addon-status-${addonId}`);
        if (switchElement) {
            switchElement.checked = true;
            switchElement.disabled = true; // 防止重复点击
        }

        fetch(getAddonRoute(`${addonId}/enable`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '开启成功');
                }
                // 操作成功，刷新主框架
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: '插件启用成功，主框架即将刷新',
                        delay: 3000,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟3秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                // 操作失败，回滚UI状态
                if (switchElement) {
                    switchElement.checked = false;
                    switchElement.disabled = false;
                }
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '开启失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // 操作失败，回滚UI状态
            if (switchElement) {
                switchElement.checked = false;
                switchElement.disabled = false;
            }
            if (typeof showToast === 'function') {
                showToast('danger', '开启失败');
            }
        })
        .finally(() => {
            // 无论成功还是失败，都要恢复开关的可点击状态
            if (switchElement) {
                switchElement.disabled = false;
            }
        });
    };

    /**
     * 关闭插件
     */
    window.disableAddon = function(addonId) {
        // 立即更新UI状态
        const switchElement = document.getElementById(`addon-status-${addonId}`);
        if (switchElement) {
            switchElement.checked = false;
            switchElement.disabled = true; // 防止重复点击
        }

        fetch(getAddonRoute(`${addonId}/disable`), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '关闭成功');
                }
                // 操作成功，刷新主框架
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: '插件禁用成功，主框架即将刷新',
                        delay: 3000,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟3秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                // 操作失败，回滚UI状态
                if (switchElement) {
                    switchElement.checked = true;
                    switchElement.disabled = false;
                }
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || '关闭失败');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // 操作失败，回滚UI状态
            if (switchElement) {
                switchElement.checked = true;
                switchElement.disabled = false;
            }
            if (typeof showToast === 'function') {
                showToast('danger', '关闭失败');
            }
        })
        .finally(() => {
            // 无论成功还是失败，都要恢复开关的可点击状态
            if (switchElement) {
                switchElement.disabled = false;
            }
        });
    };

    /**
     * 切换插件状态（开关组件专用）
     */
    window.toggleAddonStatus = function(addonId, isChecked) {
        if (isChecked) {
            enableAddon(addonId);
        } else {
            disableAddon(addonId);
        }
    };

    /**
     * 切换插件状态（保留向后兼容）
     */
    window.toggleAddonStatusLegacy = function(addonId, currentStatus) {
        if (currentStatus) {
            disableAddon(addonId);
        } else {
            enableAddon(addonId);
        }
    };

    /**
     * 安装插件
     */
    window.installAddon = function() {
        // 创建文件选择对话框
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.zip';
        input.style.display = 'none';

        input.onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // 验证文件类型
            if (!file.name.toLowerCase().endsWith('.zip')) {
                if (typeof showToast === 'function') {
                    showToast('danger', '只支持zip格式的文件');
                }
                return;
            }

            // 验证文件大小（50MB）
            const maxSize = 50 * 1024 * 1024;
            if (file.size > maxSize) {
                if (typeof showToast === 'function') {
                    showToast('danger', '文件大小不能超过50MB');
                }
                return;
            }

            // 显示确认对话框
            if (!confirm(`确定要安装插件 "${file.name}" 吗？\n\n文件大小: ${(file.size / 1024 / 1024).toFixed(2)} MB`)) {
                return;
            }

            // 开始上传
            uploadAddonFile(file);
        };

        // 触发文件选择
        document.body.appendChild(input);
        input.click();
        document.body.removeChild(input);
    };

    /**
     * 上传插件文件
     */
    function uploadAddonFile(file) {
        // 显示上传进度
        if (typeof showToast === 'function') {
            showToast('info', '正在上传插件文件...', 0); // 0表示不自动关闭
        }

        const formData = new FormData();
        formData.append('addon_file', file);

        const importUrl = getAddonRoute('install');

        fetch(importUrl, {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', `插件 "${data.data.addon_name}" 安装成功`);
                }
                // 刷新主框架，给用户时间查看成功提示
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: `插件 "${data.data.addon_name}" 安装成功，主框架即将刷新`,
                        delay: 3000,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟3秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || data.message || '插件安装失败');
                }
            }
        })
        .catch(error => {
            console.error('安装失败:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '插件安装失败：' + error.message);
            }
        });
    }

    /**
     * 配置插件
     */
    window.configureAddon = function(addonId) {
        const configUrl = getAddonRoute(`${addonId}/config`);

        // 使用iframe shell打开配置页面
        if (window.Admin?.iframeShell?.open) {
            window.Admin.iframeShell.open({
                src: configUrl,
                title: '插件配置',
                channel: 'addon-config',
                autoCloseOnSuccess: true
            });
            } else {
            // 降级方案：新窗口打开
            window.open(configUrl, '_blank', 'width=800,height=600');
            }
    };

    /**
     * 导出插件
     */
    window.exportAddon = function(addonId, addonName, isEnabled, addonVersion) {
        // 将字符串转换为布尔值（从模板传来的 {enabled} 是字符串）
        const isEnabledBool = isEnabled === 'true' || isEnabled === true;

        // 添加详细调试日志
        console.log('[导出插件] ===== 开始导出插件 =====');
        console.log('[导出插件] 接收到的参数:', {
            addonId: addonId,
            addonName: addonName,
            addonVersion: addonVersion,
            isEnabled: isEnabled,
            isEnabledType: typeof isEnabled,
            isEnabledBool: isEnabledBool
        });

        // 检查参数有效性
        if (!addonId) {
            console.error('[导出插件] addonId 为空');
            return;
        }
        if (!addonName || addonName === 'undefined' || addonName === 'null') {
            console.warn('[导出插件] addonName 为空或无效，使用默认名称');
            addonName = '未命名插件';
        }

        // 检查插件状态，只有禁用状态才能导出
        if (isEnabledBool) {
            console.warn('[导出插件] 插件处于启用状态，无法导出');
            if (typeof showToast === 'function') {
                showToast('warning', '只能导出已禁用的插件');
            }
            return;
        }

        console.log('[导出插件] 插件处于禁用状态，开始导出流程');

        if (!confirm(`确定要导出插件 "${addonName}" 吗？\n\n导出的zip文件将包含插件的所有文件。`)) {
            return;
        }

        // 显示加载状态
        const originalText = '导出中...';
        if (typeof showToast === 'function') {
            showToast('info', originalText, 0); // 0表示不自动关闭
        }

        // 构建导出URL（不需要传递文件名参数）
        const exportUrl = getAddonRoute(`${addonId}/export`);

        console.log('[导出插件] 构建的导出URL:', exportUrl);

        // 使用前端控制下载的方式，避免URL参数编码问题
        fetch(exportUrl, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => {
            console.log('收到响应:', {
                status: response.status,
                ok: response.ok,
                contentType: response.headers.get('content-type'),
                url: response.url
            });

            // 检查内容类型
            const contentType = response.headers.get('content-type');

            // 如果是JSON响应（错误响应），解析为JSON
            if (contentType && contentType.includes('application/json')) {
                console.log('检测到JSON响应，开始解析...');
                return response.json().then(data => {
                    console.log('JSON解析成功:', data);
                    if (data.code !== 200) {
                        const errorMessage = data.msg || data.message || '导出失败';
                        console.log('检测到错误响应，抛出错误:', errorMessage);
                        throw new Error(errorMessage);
                    }
                    // 如果是成功的JSON响应，这是不正常的
                    console.warn('收到意外的成功JSON响应');
                    throw new Error('服务器返回了意外的响应格式');
                }).catch(jsonError => {
                    console.error('JSON解析失败:', jsonError);
                    console.error('原始响应:', response);
                    throw new Error('服务器响应格式错误: ' + jsonError.message);
                });
            }

            // 如果不是成功状态码
            if (!response.ok) {
                console.log('HTTP状态码不是成功状态:', response.status);
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // 前端直接构造文件名，避免后端编码问题
            // 格式：ID_插件名称_版本_日期.zip
            const now = new Date();
            const dateStr = now.getFullYear() + '-' +
                           String(now.getMonth() + 1).padStart(2, '0') + '-' +
                           String(now.getDate()).padStart(2, '0') + '_' +
                           String(now.getHours()).padStart(2, '0') + '-' +
                           String(now.getMinutes()).padStart(2, '0') + '-' +
                           String(now.getSeconds()).padStart(2, '0');

            // 简化文件名，避免中文编码问题
            const safeAddonName = addonName.replace(/[^\w\u4e00-\u9fff\-_]/g, '_'); // 只保留字母、数字、中文、横线、下划线
            const safeVersion = (addonVersion || 'unknown').replace(/[^\w\.\-_]/g, '_'); // 版本号清理
            const filename = `${addonId}_${safeAddonName}_${safeVersion}_${dateStr}.zip`;

            console.log('[导出插件] 前端构造文件名:', {
                addonId: addonId,
                addonName: addonName,
                addonVersion: addonVersion,
                safeAddonName: safeAddonName,
                safeVersion: safeVersion,
                dateStr: dateStr,
                finalFilename: filename
            });

            return response.blob().then(blob => ({ blob, filename }));
        })
        .then(({ blob, filename }) => {
            // 创建下载链接
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.style.display = 'none';
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();

            // 清理
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);

            if (typeof showToast === 'function') {
                showToast('success', '插件导出成功');
            }
        })
        .catch(error => {
            console.error('导出失败:', error);
            const errorMessage = error && typeof error === 'object' && error.message ? error.message :
                                error instanceof Error ? error.toString() :
                                typeof error === 'string' ? error :
                                '未知错误';

            console.log('错误消息:', errorMessage);
            console.log('错误类型:', typeof error);
            console.log('错误对象:', error);

            if (typeof showToast === 'function') {
                showToast('danger', '插件导出失败：' + errorMessage);
            } else {
                console.warn('showToast 函数不可用，使用 alert 代替');
                alert('插件导出失败：' + errorMessage);
            }
        });
    };

    /**
     * 从应用商城安装插件
     */
    window.installStoreAddon = function(addonId, addonName) {
        if (!confirm(`确定要从应用商城安装插件 "${addonName}" 吗？\n\n这将从远程服务器下载并安装插件。`)) {
            return;
        }

        // 显示加载状态
        if (typeof showToast === 'function') {
            showToast('info', '正在从应用商城下载插件...', 0); // 0表示不自动关闭
        }

        const installUrl = getAddonRoute(`install-store/${addonId}`);

        fetch(installUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', `插件 "${addonName}" 从应用商城安装成功`);
                }
                // 刷新主框架
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: `插件 "${addonName}" 安装成功，主框架即将刷新`,
                        delay: 3000,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟3秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || data.message || '插件安装失败');
                }
            }
        })
        .catch(error => {
            console.error('安装失败:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '插件安装失败：' + error.message);
            }
        });
    };

    /**
     * 从应用商城升级插件
     */
    window.upgradeStoreAddon = function(addonId, addonName, currentVersion, latestVersion) {
        if (!confirm(`确定要将插件 "${addonName}" 从 v${currentVersion} 升级到 v${latestVersion} 吗？\n\n这将从应用商城下载最新版本并覆盖当前安装。`)) {
            return;
        }

        // 显示加载状态
        if (typeof showToast === 'function') {
            showToast('info', `正在升级插件 ${addonName}...`, 0);
        }

        const upgradeUrl = getAddonRoute(`upgrade-store/${addonId}`);

        fetch(upgradeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', `插件 "${addonName}" 升级成功`);
                }
                // 刷新主框架
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: `插件 "${addonName}" 升级成功，主框架即将刷新`,
                        delay: 3000,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟3秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || data.message || '插件升级失败');
                }
            }
        })
        .catch(error => {
            console.error('升级失败:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '插件升级失败：' + error.message);
            }
        });
    };

    /**
     * 筛选应用商城插件
     */
    window.filterStoreAddons = function() {
        // 查找搜索表单中的source字段
        const sourceSelect = document.querySelector('select[name="filters[source]"]') ||
                           document.querySelector('#searchForm_addonTable select[name="filters[source]"]');

        if (sourceSelect) {
            // 设置source值为"store"
            sourceSelect.value = 'store';

            // 触发change事件以确保表单更新
            const changeEvent = new Event('change', { bubbles: true });
            sourceSelect.dispatchEvent(changeEvent);

            // 查找并点击搜索按钮
            const searchButton = document.querySelector('#searchForm_addonTable button[type="submit"]') ||
                               document.querySelector('#searchForm_addonTable .btn-primary');

            if (searchButton) {
                searchButton.click();
            } else {
                // 如果找不到搜索按钮，手动触发搜索
                if (window.DataTableManager && typeof window.DataTableManager.refresh === 'function') {
                    window.DataTableManager.refresh('addonTable');
                }
            }

            // 显示提示信息
            if (typeof showToast === 'function') {
                showToast('info', '正在加载应用商城插件...', 2000);
            }
        } else {
            console.warn('[插件筛选] 未找到source选择器');
            // 降级方案：刷新整个页面并传递参数
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('filters[source]', 'store');
            window.location.href = currentUrl.toString();
        }
    };

    /**
     * 删除插件
     */
    window.deleteAddon = function(addonId, addonName, isEnabled) {
        // 将字符串转换为布尔值（从模板传来的 {enabled} 是字符串）
        const isEnabledBool = isEnabled === 'true' || isEnabled === true;

        // 添加调试日志
        console.log('[删除插件] 参数信息:', {
            addonId: addonId,
            addonName: addonName,
            isEnabled: isEnabled,
            isEnabledType: typeof isEnabled,
            isEnabledBool: isEnabledBool
        });

        // 检查插件状态，只有禁用状态才能删除
        if (isEnabledBool) {
            console.warn('[删除插件] 插件处于启用状态，无法删除');
            if (typeof showToast === 'function') {
                showToast('warning', '只能删除已禁用的插件');
            }
            return;
        }

        console.log('[删除插件] 插件处于禁用状态，开始删除流程');

        if (!confirm(`⚠️ 危险操作！\n\n确定要删除插件 "${addonName}" 吗？\n\n这将永久删除插件的所有文件，此操作不可恢复！`)) {
            return;
        }

        // 显示加载状态
        if (typeof showToast === 'function') {
            showToast('info', '删除中...', 0); // 0表示不自动关闭
        }

        const deleteUrl = getAddonRoute(addonId);

        fetch(deleteUrl, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                if (typeof showToast === 'function') {
                    showToast('success', '插件删除成功');
                }
                // 刷新主框架或重新加载数据表格
                if (window.AdminIframeClient?.refreshMainFrame) {
                    window.AdminIframeClient.refreshMainFrame({
                        message: '插件删除成功，主框架即将刷新',
                        delay: 1500,
                        toastType: 'success'
                    });
                } else {
                    // 降级方案：延迟1.5秒刷新页面
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                }
            } else {
                if (typeof showToast === 'function') {
                    showToast('danger', data.msg || data.message || '插件删除失败');
                }
            }
        })
        .catch(error => {
            console.error('删除失败:', error);
            if (typeof showToast === 'function') {
                showToast('danger', '插件删除失败：' + error.message);
            }
        });
    };

    // 导出初始化函数
    window.initAddonPage = initAddonPage;

})();
