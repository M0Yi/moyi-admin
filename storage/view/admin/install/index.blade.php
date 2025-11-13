<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统初始化 - MoYi Admin</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }

        .install-container {
            width: 100%;
            max-width: 600px;
            margin: 20px;
        }

        .install-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }

        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }

        .install-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 600;
        }

        .install-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .install-body {
            padding: 40px 30px;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: block;
        }

        @media (min-width: 992px) {
            .form-row {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .form-group-full {
                grid-column: 1 / -1;
            }
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label .required {
            color: #e74c3c;
            margin-left: 4px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            font-size: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-control.is-invalid {
            border-color: #e74c3c;
        }

        .invalid-feedback {
            color: #e74c3c;
            font-size: 13px;
            margin-top: 6px;
            display: none;
        }

        .form-control.is-invalid + .invalid-feedback {
            display: block;
        }

        .form-text {
            color: #888;
            font-size: 13px;
            margin-top: 6px;
            display: block;
        }

        .btn {
            padding: 14px 32px;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .progress-container {
            display: none;
            margin-top: 20px;
        }

        .progress-bar {
            height: 4px;
            background-color: #f0f0f0;
            border-radius: 2px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0;
            transition: width 0.3s;
            animation: progress-animation 1.5s ease-in-out infinite;
        }

        @keyframes progress-animation {
            0% { width: 0%; }
            50% { width: 100%; }
            100% { width: 0%; }
        }

        .loading-text {
            text-align: center;
            color: #666;
            margin-top: 10px;
            font-size: 14px;
        }

        .install-footer {
            background-color: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 13px;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal {
            width: 100%;
            max-width: 720px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            font-size: 16px;
            font-weight: 600;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
        }

        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-actions {
            padding: 16px 20px;
            background: #f8f9fa;
        }

        /* 环境检查样式 */
        .env-check-section {
            margin-bottom: 30px;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
        }

        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(108, 117, 125, 0.4);
        }

        #envCheckResults {
            margin-top: 20px;
            display: none;
        }

        .check-item {
            padding: 12px;
            margin-bottom: 8px;
            background: white;
            border-radius: 6px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .check-item-name {
            color: #333;
            font-weight: 500;
        }

        .check-item-value {
            color: #666;
            font-size: 13px;
        }

        .check-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
        }

        .check-status.passed {
            background-color: #d4edda;
            color: #155724;
        }

        .check-status.failed {
            background-color: #f8d7da;
            color: #721c24;
        }

        .check-category {
            font-size: 16px;
            font-weight: 600;
            color: #495057;
            margin-top: 20px;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #dee2e6;
        }

        .check-category:first-child {
            margin-top: 0;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-card">
            <div class="install-header">
                <h1>🚀 系统初始化</h1>
                <p>欢迎使用 MoYi Admin，请填写以下信息完成初始化</p>
            </div>

            <div class="install-body">
                <div id="alertContainer"></div>

                <!-- 环境检查 -->
                <div class="env-check-section">
                    <button type="button" class="btn btn-secondary" id="checkEnvBtn" onclick="checkEnvironment()">
                        🔍 检查系统环境
                    </button>
                </div>

                <form id="installForm">
                    <!-- 站点信息 -->
                    <div class="form-section">
                        <div class="section-title">站点信息</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="site_name">
                                    站点名称
                                    <span class="required">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="site_name"
                                       name="site_name"
                                       placeholder="请输入站点名称，例如：我的管理系统"
                                       required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="site_domain">
                                    站点域名
                                    <span class="required">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="site_domain"
                                       name="site_domain"
                                       placeholder="例如：example.com"
                                       required>
                                <div class="invalid-feedback"></div>
                                <small class="form-text">请输入不带协议的域名</small>
                            </div>

                            <div class="form-group form-group-full">
                                <label for="site_title">站点标题（可选）</label>
                                <input type="text"
                                       class="form-control"
                                       id="site_title"
                                       name="site_title"
                                       placeholder="留空则使用站点名称">
                            </div>
                        </div>
                    </div>

                    <!-- 管理员账号 -->
                    <div class="form-section">
                        <div class="section-title">管理员账号</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="username">
                                    用户名
                                    <span class="required">*</span>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="username"
                                       name="username"
                                       placeholder="请输入管理员用户名"
                                       required>
                                <div class="invalid-feedback"></div>
                                <small class="form-text">只能包含字母、数字和下划线，至少3位</small>
                            </div>

                            <div class="form-group">
                                <label for="password">
                                    密码
                                    <span class="required">*</span>
                                </label>
                                <input type="password"
                                       class="form-control"
                                       id="password"
                                       name="password"
                                       placeholder="请输入管理员密码"
                                       required>
                                <div class="invalid-feedback"></div>
                                <small class="form-text">密码长度至少6位</small>
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">
                                    确认密码
                                    <span class="required">*</span>
                                </label>
                                <input type="password"
                                       class="form-control"
                                       id="password_confirmation"
                                       name="password_confirmation"
                                       placeholder="请再次输入密码"
                                       required>
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="real_name">真实姓名（可选）</label>
                                <input type="text"
                                       class="form-control"
                                       id="real_name"
                                       name="real_name"
                                       placeholder="请输入真实姓名">
                            </div>

                            <div class="form-group">
                                <label for="email">邮箱（可选）</label>
                                <input type="email"
                                       class="form-control"
                                       id="email"
                                       name="email"
                                       placeholder="请输入邮箱地址">
                                <div class="invalid-feedback"></div>
                            </div>

                            <div class="form-group">
                                <label for="mobile">手机号（可选）</label>
                                <input type="text"
                                       class="form-control"
                                       id="mobile"
                                       name="mobile"
                                       placeholder="请输入手机号">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        开始初始化
                    </button>

                    <div class="progress-container" id="progressContainer">
                        <div class="progress-bar">
                            <div class="progress-bar-fill"></div>
                        </div>
                        <div class="loading-text">正在初始化系统，请稍候...</div>
                    </div>
                </form>
            </div>

            <div class="install-footer">

                Powered by MoYi Admin &copy; 2025
            </div>
        </div>
    </div>

    <script>
        async function checkEnvironment() {
            const checkBtn = document.getElementById('checkEnvBtn');
            const modal = document.getElementById('envCheckModal');
            const resultsDiv = document.getElementById('modalEnvResults');

            checkBtn.disabled = true;
            checkBtn.textContent = '检查中...';
            openModal('envCheckModal');

            try {
                const response = await fetch('/install/check-environment');
                const result = await response.json();

                if (result.code === 200) {
                    displayEnvironmentResults(result.data);
                } else {
                    resultsDiv.innerHTML = `<div class="alert alert-danger">${result.message || '环境检查失败'}</div>`;
                    resultsDiv.style.display = 'block';
                }
            } catch (error) {
                resultsDiv.innerHTML = `<div class="alert alert-danger">网络错误：${error.message}</div>`;
                resultsDiv.style.display = 'block';
            } finally {
                checkBtn.disabled = false;
                checkBtn.textContent = '🔍 检查系统环境';
            }
        }

        function displayEnvironmentResults(data) {
            const resultsDiv = document.getElementById('modalEnvResults');
            let html = '';

            // PHP 版本
            if (data.php_version) {
                html += '<div class="check-category">PHP 环境</div>';
                html += `
                    <div class="check-item">
                        <div>
                            <div class="check-item-name">${data.php_version.name}</div>
                            <div class="check-item-value">要求：${data.php_version.required} | 当前：${data.php_version.current}</div>
                        </div>
                        <span class="check-status ${data.php_version.passed ? 'passed' : 'failed'}">
                            ${data.php_version.passed ? '✓ 通过' : '✗ 不通过'}
                        </span>
                    </div>
                `;
            }

            // 扩展检查
            if (data.extensions && Object.keys(data.extensions).length > 0) {
                html += '<div class="check-category">PHP 扩展</div>';
                for (const [key, ext] of Object.entries(data.extensions)) {
                    html += `
                        <div class="check-item">
                            <div class="check-item-name">${ext.name}</div>
                            <span class="check-status ${ext.passed ? 'passed' : 'failed'}">
                                ${ext.passed ? '✓ 已安装' : '✗ 未安装'}
                            </span>
                        </div>
                    `;
                }
            }

            // 目录权限
            if (data.directories && Object.keys(data.directories).length > 0) {
                html += '<div class="check-category">目录权限</div>';
                for (const [key, dir] of Object.entries(data.directories)) {
                    html += `
                        <div class="check-item">
                            <div>
                                <div class="check-item-name">${dir.name}</div>
                                <div class="check-item-value">${dir.path}</div>
                            </div>
                            <span class="check-status ${dir.writable ? 'passed' : 'failed'}">
                                ${dir.writable ? '✓ 可写' : '✗ 不可写'}
                            </span>
                        </div>
                    `;
                }
            }

            // 函数检查
            if (data.functions && Object.keys(data.functions).length > 0) {
                html += '<div class="check-category">PHP 函数</div>';
                for (const [key, func] of Object.entries(data.functions)) {
                    html += `
                        <div class="check-item">
                            <div class="check-item-name">${func.name}</div>
                            <span class="check-status ${func.passed ? 'passed' : 'failed'}">
                                ${func.passed ? '✓ 可用' : '✗ 不可用'}
                            </span>
                        </div>
                    `;
                }
            }

            if (data.database) {
                html += '<div class="check-category">数据库状态</div>';
                const db = data.database;
                const statusClass = db.passed ? 'passed' : 'failed';
                let statusText = '';
                if (db.passed) {
                    statusText = '✓ 空数据库（推荐）';
                } else if (db.error) {
                    statusText = '✗ 数据库连接失败';
                } else {
                    statusText = '✗ 非空数据库';
                }

                html += `
                    <div class="check-item">
                        <div>
                            <div class="check-item-name">当前数据库</div>
                            <div class="check-item-value">${db.database || '未知'}</div>
                        </div>
                        <span class="check-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="check-item">
                        <div class="check-item-name">数据表数量</div>
                        <span class="check-status ${statusClass}">${typeof db.table_count === 'number' ? db.table_count : '-'}</span>
                    </div>
                `;

                if (!db.passed) {
                    const warnMsg = db.error
                        ? `数据库检测异常：${db.error}`
                        : `检测到当前数据库已有 ${typeof db.table_count === 'number' ? db.table_count : '未知'} 张表。建议在空数据库上安装，以避免与现有表冲突或覆盖数据。`;
                    html += `
                        <div class="alert alert-danger">${warnMsg}</div>
                    `;
                } else {
                    html += `
                        <div class="alert alert-success">${db.suggest || '当前数据库为空，适合进行安装'}</div>
                    `;
                }
            }

            resultsDiv.innerHTML = html;
            resultsDiv.style.display = 'block';
        }

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'flex';
            }
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
            }
        }

        function showErrorModal(message, errors) {
            const container = document.getElementById('errorModalContent');
            if (!container) return;
            let html = `<div class="alert alert-danger">${message}</div>`;
            if (errors && typeof errors === 'object') {
                const items = Object.entries(errors).map(([field, msg]) => {
                    return `
                        <div class="check-item">
                            <div>
                                <div class="check-item-name">${field}</div>
                                <div class="check-item-value">${msg}</div>
                            </div>
                        </div>
                    `;
                }).join('');
                html += items;
            }
            container.innerHTML = html;
            openModal('errorModal');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('installForm');
            const submitBtn = document.getElementById('submitBtn');
            const progressContainer = document.getElementById('progressContainer');
            const alertContainer = document.getElementById('alertContainer');
            const siteDomainInput = document.getElementById('site_domain');

            if (siteDomainInput && !siteDomainInput.value) {
                const hostname = window.location.hostname || '';
                const port = window.location.port;
                const isStandardPort = !port || port === '80' || port === '443';
                siteDomainInput.value = isStandardPort ? hostname : `${hostname}:${port}`;
            }

            form.addEventListener('submit', async function(e) {
                e.preventDefault();

                // 清除之前的错误提示
                clearErrors();
                hideAlert();

                // 获取表单数据
                const formData = new FormData(form);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });

                // 禁用提交按钮，显示进度
                submitBtn.disabled = true;
                submitBtn.textContent = '初始化中...';
                progressContainer.style.display = 'block';

                try {
                    const response = await fetch('/install', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data),
                    });

                    const result = await response.json();

                    if (result.code === 200) {
                        showAlert('success', '初始化成功！正在跳转到登录页面...');
                        setTimeout(() => {
                            const adminPath = result && result.data && result.data.admin_path ? result.data.admin_path : '';
                            const target = adminPath ? `/admin/${adminPath}/login` : '/admin/login';
                            window.location.href = target;
                        }, 3000);
                    } else {
                        const msg = result.message || '初始化失败，请重试';
                        showAlert('danger', msg);
                        showErrorModal(msg, result.errors || null);
                        if (result.errors) {
                            showFieldErrors(result.errors);
                        }
                        submitBtn.disabled = false;
                        submitBtn.textContent = '开始初始化';
                        progressContainer.style.display = 'none';
                    }
                } catch (error) {
                    const msg = '网络错误：' + error.message;
                    showAlert('danger', msg);
                    showErrorModal(msg, null);
                    submitBtn.disabled = false;
                    submitBtn.textContent = '开始初始化';
                    progressContainer.style.display = 'none';
                }
            });

            function showAlert(type, message) {
                alertContainer.innerHTML = `
                    <div class="alert alert-${type}">
                        ${message}
                    </div>
                `;
            }

            function hideAlert() {
                alertContainer.innerHTML = '';
            }

            function showFieldErrors(errors) {
                for (const [field, message] of Object.entries(errors)) {
                    const input = document.getElementById(field);
                    if (input) {
                        input.classList.add('is-invalid');
                        const feedback = input.nextElementSibling;
                        if (feedback && feedback.classList.contains('invalid-feedback')) {
                            feedback.textContent = message;
                        } else {
                            // 如果没有 invalid-feedback 元素，找到下一个
                            let nextEl = input.nextElementSibling;
                            while (nextEl && !nextEl.classList.contains('invalid-feedback')) {
                                nextEl = nextEl.nextElementSibling;
                            }
                            if (nextEl) {
                                nextEl.textContent = message;
                            }
                        }
                    }
                }
            }

            function clearErrors() {
                document.querySelectorAll('.is-invalid').forEach(el => {
                    el.classList.remove('is-invalid');
                });
                document.querySelectorAll('.invalid-feedback').forEach(el => {
                    el.textContent = '';
                });
            }
        });
    </script>

    <div id="envCheckModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div>系统环境检查</div>
                <button type="button" class="modal-close" onclick="closeModal('envCheckModal')">×</button>
            </div>
            <div class="modal-body">
                <div id="modalEnvResults"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('envCheckModal')">关闭</button>
            </div>
        </div>
    </div>

    <div id="errorModal" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <div>错误提示</div>
                <button type="button" class="modal-close" onclick="closeModal('errorModal')">×</button>
            </div>
            <div class="modal-body">
                <div id="errorModalContent"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('errorModal')">关闭</button>
            </div>
        </div>
    </div>
</body>
</html>
