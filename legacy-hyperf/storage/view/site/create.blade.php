<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建独立站点 - MoYi Admin</title>
    <style>
        :root {
            color-scheme: light;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top, #6C63FF 0%, #512DA8 45%, #231942 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 12px;
            color: #1f2933;
        }

        .wizard-container {
            width: 100%;
            max-width: 960px;
        }

        .wizard-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(16, 24, 40, 0.35);
            overflow: hidden;
        }

        .wizard-header {
            padding: 40px 48px 24px;
            background: linear-gradient(120deg, rgba(108, 99, 255, 0.1), rgba(81, 45, 168, 0.05));
        }

        .wizard-header h1 {
            margin: 0 0 12px;
            font-size: 32px;
            color: #1f2933;
        }

        .wizard-header p {
            margin: 0;
            font-size: 16px;
            color: #4b5563;
            line-height: 1.6;
        }

        .steps {
            margin-top: 24px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .step-item {
            background: #fff;
            border-radius: 16px;
            border: 1px solid #e5e7eb;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6C63FF, #512DA8);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }

        .step-text {
            font-size: 14px;
            color: #374151;
            line-height: 1.5;
        }

        .wizard-body {
            padding: 32px 48px 40px;
        }

        .form-section {
            margin-bottom: 32px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title span {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px 24px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 14px;
            color: #374151;
            font-weight: 600;
            margin-bottom: 8px;
        }

        label .required {
            color: #dc2626;
            margin-left: 4px;
        }

        input {
            border-radius: 12px;
            border: 1.5px solid #e5e7eb;
            padding: 12px 14px;
            font-size: 15px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            outline: none;
            border-color: #6C63FF;
            box-shadow: 0 0 0 3px rgba(108, 99, 255, 0.2);
        }

        input.is-invalid {
            border-color: #dc2626;
        }

        .form-hint {
            margin-top: 6px;
            font-size: 13px;
            color: #6b7280;
        }

        .invalid-feedback {
            margin-top: 6px;
            font-size: 13px;
            color: #dc2626;
            display: none;
        }

        input.is-invalid + .invalid-feedback {
            display: block;
        }

        .submit-area {
            margin-top: 12px;
        }

        .btn-primary {
            width: 100%;
            border: none;
            border-radius: 14px;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, #6C63FF 0%, #512DA8 100%);
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 15px 30px rgba(108, 99, 255, 0.35);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 18px 35px rgba(108, 99, 255, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.65;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .agreement {
            margin-top: 18px;
            font-size: 13px;
            color: #6b7280;
            line-height: 1.6;
        }

        .wizard-footer {
            padding: 18px 48px 32px;
            color: #6b7280;
            font-size: 13px;
            border-top: 1px solid #f3f4f6;
        }

        .alert {
            padding: 14px 16px;
            border-radius: 12px;
            font-size: 14px;
            margin-bottom: 20px;
            display: none;
        }

        .alert.show {
            display: block;
        }

        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .result-card {
            margin-top: 20px;
            padding: 20px;
            border-radius: 16px;
            background: #f5f5ff;
            border: 1px dashed #c7c5ff;
            display: none;
        }

        .result-card.show {
            display: block;
        }

        .result-title {
            font-weight: 600;
            margin-bottom: 12px;
            color: #312e81;
        }

        .result-list {
            display: grid;
            gap: 8px;
            font-size: 14px;
            color: #4338ca;
        }

        .result-list span {
            font-weight: 600;
            color: #111827;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
            padding: 24px;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            width: 100%;
            max-width: 520px;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 30px 80px rgba(15, 23, 42, 0.55);
            transform: translateY(15px);
            opacity: 0;
            transition: opacity 0.2s ease, transform 0.2s ease;
        }

        .modal.show {
            opacity: 1;
            transform: translateY(0);
        }

        .modal-header {
            padding: 24px 28px 8px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            color: #111827;
        }

        .modal-body {
            padding: 12px 28px 24px;
        }

        .credential-list {
            display: grid;
            gap: 10px;
            font-size: 15px;
            color: #1f2937;
        }

        .credential-list span {
            font-weight: 600;
            color: #111827;
        }

        .security-hint {
            margin-top: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            color: #9a3412;
            font-size: 13px;
            line-height: 1.6;
        }

        .modal-footer {
            padding: 0 28px 28px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-secondary {
            border: none;
            border-radius: 12px;
            background: #e0e7ff;
            color: #312e81;
            padding: 12px 18px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-secondary:hover {
            background: #c7d2fe;
        }

        .btn-accent {
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #6C63FF 0%, #512DA8 100%);
            color: #fff;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 12px 25px rgba(108, 99, 255, 0.35);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .btn-accent:hover {
            transform: translateY(-1px);
            box-shadow: 0 15px 30px rgba(108, 99, 255, 0.45);
        }

        .btn-accent:disabled {
            opacity: 0.75;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        @media (max-width: 640px) {
            .wizard-card {
                border-radius: 18px;
            }

            .wizard-header,
            .wizard-body,
            .wizard-footer {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-card">
            <div class="wizard-header">
                <h1>创建专属站点</h1>
                <p>
                    通过几个简单步骤即可创建属于自己的后台站点，系统会为你准备默认的菜单、权限以及超级管理员账号。
                    提交完成后请把域名解析到当前服务器，并使用生成的后台地址登录管理。
                </p>

                <div class="steps">
                    <div class="step-item">
                        <div class="step-number">1</div>
                        <div class="step-text">填写站点信息，确保域名可用并准备好管理员账号资料。</div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">2</div>
                        <div class="step-text">系统自动创建站点、权限、菜单及超级管理员账号。</div>
                    </div>
                    <div class="step-item">
                        <div class="step-number">3</div>
                        <div class="step-text">根据结果提示配置域名解析，并使用后台入口登录管理。</div>
                    </div>
                </div>
            </div>

            <div class="wizard-body">
                <div id="alertBox" class="alert"></div>

                <form id="siteCreateForm">
                    <div class="form-section">
                        <div class="section-title">
                            站点信息
                            <span>用于识别和展示给用户</span>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="site_name">站点名称 <span class="required">*</span></label>
                                <input type="text" id="site_name" name="site_name" placeholder="例如：MoYi Studio" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="site_domain">站点域名 <span class="required">*</span></label>
                                <div style="display: flex; gap: 8px;">
                                    <input type="text"
                                           id="site_domain"
                                           name="site_domain"
                                           placeholder="example.com，不要带 http://"
                                           value="{{ $requestedDomain ?? '' }}"
                                           required>
                                    <button type="button" class="btn btn-secondary" id="verifyDomainBtn">验证域名</button>
                                </div>
                                <div class="form-hint">提交前请先完成域名验证</div>
                                <div class="invalid-feedback"></div>
                            </div>
                            <input type="hidden" id="domain_token" name="domain_token">
                            <div class="form-group">
                                <label for="site_title">站点标题</label>
                                <input type="text" id="site_title" name="site_title" placeholder="可选，用于浏览器标题">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            管理员信息
                            <span>默认会创建超级管理员账号</span>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">管理员账号 <span class="required">*</span></label>
                                <input type="text" id="username" name="username" placeholder="仅支持字母、数字、下划线" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="password">登录密码 <span class="required">*</span></label>
                                <input type="password" id="password" name="password" placeholder="至少 6 位" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation">确认密码 <span class="required">*</span></label>
                                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="再次输入密码" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="real_name">真实姓名 <span class="required">*</span></label>
                                <input type="text" id="real_name" name="real_name" placeholder="请输入真实姓名" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="mobile">手机号码 <span class="required">*</span></label>
                                <input type="text" id="mobile" name="mobile" placeholder="用于联系与安全验证" required>
                                <div class="invalid-feedback"></div>
                            </div>
                            <div class="form-group">
                                <label for="email">联系邮箱</label>
                                <input type="email" id="email" name="email" placeholder="可选，用于找回密码">
                                <div class="invalid-feedback"></div>
                            </div>
                        </div>
                    </div>

                    <div class="submit-area">
                        <button type="submit" class="btn-primary" id="submitBtn">
                            🚀 立即创建站点
                        </button>
                        <div class="agreement">
                            提交代表你已知晓该站点会共用当前系统资源，请确保域名可用并遵守所在地区的法律法规。
                        </div>
                    </div>
                </form>

                <div id="resultCard" class="result-card">
                    <div class="result-title">站点创建成功 🎉</div>
                    <div class="result-list" id="resultList"></div>
                </div>
            </div>

            <div id="resultModalOverlay" class="modal-overlay">
                <div id="resultModal" class="modal">
                    <div class="modal-header">
                        <h2>站点创建成功 🎉</h2>
                    </div>
                    <div class="modal-body">
                        <div class="credential-list" id="modalInfoList"></div>
                        <div class="security-hint">
                            ⚠️ 以上信息仅显示一次，请立即保存，尤其是初始密码。若遗失需联系平台管理员重置。
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-secondary" id="modalCopyBtn">复制全部信息</button>
                        <button type="button" class="btn-accent" id="modalEnterBtn">进入后台</button>
                    </div>
                </div>
            </div>

            <div class="wizard-footer">
                提示：若长时间未完成 DNS 解析或没有人访问，该站点可能会被管理员清理。
                如需关闭自助创建功能，可让管理员将 <code>ENABLE_PUBLIC_SITE_CREATION</code> 设置为 false。
            </div>
        </div>
    </div>

    <script>
        const form = document.getElementById('siteCreateForm');
        const alertBox = document.getElementById('alertBox');
        const submitBtn = document.getElementById('submitBtn');
        const resultCard = document.getElementById('resultCard');
        const resultList = document.getElementById('resultList');
        const verifyDomainBtn = document.getElementById('verifyDomainBtn');
        const domainTokenInput = document.getElementById('domain_token');
        const siteDomainInput = document.getElementById('site_domain');
        const modalOverlay = document.getElementById('resultModalOverlay');
        const modal = document.getElementById('resultModal');
        const modalInfoList = document.getElementById('modalInfoList');
        const modalCopyBtn = document.getElementById('modalCopyBtn');
        const modalEnterBtn = document.getElementById('modalEnterBtn');
        let latestResult = null;

        const showAlert = (message, type = 'success') => {
            alertBox.textContent = message;
            alertBox.className = `alert alert-${type} show`;
        };

        const clearAlert = () => {
            alertBox.className = 'alert';
            alertBox.textContent = '';
        };

        const setFieldError = (name, message) => {
            const field = form.querySelector(`[name="${name}"]`);
            if (!field) return;
            const feedback = field.nextElementSibling;
            field.classList.add('is-invalid');
            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = message;
            }
        };

        const clearFieldErrors = () => {
            form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        };

        const buildCredentialRows = (data) => {
            return [
                `<div>站点名称：<span>${data.site_name ?? '-'}</span></div>`,
                `<div>站点域名：<span>${data.site_domain ?? '-'}</span></div>`,
                `<div>后台入口：<span>${data.login_url ?? '-'}</span></div>`,
                `<div>管理员账号：<span>${data.username ?? '-'}</span></div>`,
                `<div>登录密码：<span>${data.password ?? '-'}</span></div>`
            ];
        };

        const renderResult = (data) => {
            const rows = buildCredentialRows(data);
            resultList.innerHTML = rows.join('');
            resultCard.classList.add('show');
        };

        const openResultModal = (data) => {
            latestResult = data;
            modalInfoList.innerHTML = buildCredentialRows(data).join('');
            modalOverlay.classList.add('show');
            modal.classList.add('show');
        };

        const copyCredentials = async () => {
            if (!latestResult) {
                return false;
            }
            const lines = [
                `站点名称：${latestResult.site_name ?? '-'}`,
                `站点域名：${latestResult.site_domain ?? '-'}`,
                `后台入口：${latestResult.login_url ?? '-'}`,
                `管理员账号：${latestResult.username ?? '-'}`,
                `登录密码：${latestResult.password ?? '-'}`
            ];
            const text = lines.join('\n');

            if (navigator.clipboard && navigator.clipboard.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                    return true;
                } catch (error) {
                    console.error('Clipboard API failed:', error);
                }
            }

            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.top = '-1000px';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                return true;
            } catch (error) {
                console.error('Fallback copy failed:', error);
                return false;
            }
        };

        modalCopyBtn.addEventListener('click', async () => {
            const success = await copyCredentials();
            if (success) {
                modalCopyBtn.textContent = '已复制 ✔️';
                setTimeout(() => {
                    modalCopyBtn.textContent = '复制全部信息';
                }, 1800);
            } else {
                modalCopyBtn.textContent = '复制失败，请重试';
                setTimeout(() => {
                    modalCopyBtn.textContent = '复制全部信息';
                }, 2000);
            }
        });

        modalEnterBtn.addEventListener('click', () => {
            if (!latestResult || !latestResult.login_url) {
                return;
            }
            window.location.href = latestResult.login_url;
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearAlert();
            clearFieldErrors();
            resultCard.classList.remove('show');

            submitBtn.disabled = true;
            submitBtn.textContent = '⏳ 正在创建，请稍候...';

            const formData = Object.fromEntries(new FormData(form).entries());

            try {
                const response = await fetch('/site/register', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.code === 200) {
                    const resultData = result.data || {};
                    showAlert(result.msg || '站点创建成功', 'success');
                    renderResult(resultData);
                    openResultModal(resultData);
                    form.reset();
                    domainTokenInput.value = '';
                } else {
                    showAlert(result.msg || '站点创建失败', 'error');
                    const errors = result.data || result.errors || {};
                    Object.keys(errors).forEach((key) => setFieldError(key, errors[key]));
                }
            } catch (error) {
                console.error(error);
                showAlert('请求失败，请稍后重试', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = '🚀 立即创建站点';
            }
        });

        verifyDomainBtn.addEventListener('click', async () => {
            clearAlert();
            clearFieldErrors();
            domainTokenInput.value = '';

            const domain = siteDomainInput.value.trim();
            if (domain === '') {
                setFieldError('site_domain', '请输入域名');
                return;
            }

            verifyDomainBtn.disabled = true;
            verifyDomainBtn.textContent = '验证中...';

            try {
                const response = await fetch('/site/register/verify-domain', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ site_domain: domain })
                });

                const result = await response.json();
                if (result.code === 200) {
                    domainTokenInput.value = result.data?.token || '';
                    showAlert(result.msg || '域名验证成功', 'success');
                } else {
                    showAlert(result.msg || '域名验证失败', 'error');
                    const errors = result.data || result.errors || {};
                    Object.keys(errors).forEach((key) => setFieldError(key, errors[key]));
                }
            } catch (error) {
                console.error(error);
                showAlert('域名验证请求失败，请稍后重试', 'error');
            } finally {
                verifyDomainBtn.disabled = false;
                verifyDomainBtn.textContent = '验证域名';
            }
        });
    </script>
</body>
</html>

