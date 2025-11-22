@extends('admin.layouts.admin')

@section('title', 'CRUD 弹窗示范')

@if (! $isEmbedded)
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@push('admin-styles')
<style>
.iframe-modal-demo .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
}

.iframe-modal-demo .form-label {
    font-weight: 600;
    color: #0f172a;
}

.iframe-modal-demo .status-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    background: #ecfccb;
    color: #4d7c0f;
    border-radius: 999px;
    padding: 0.25rem 0.85rem;
    font-size: 0.85rem;
}

.iframe-modal-demo .action-log {
    min-height: 150px;
    max-height: 260px;
    overflow-y: auto;
    font-size: 0.85rem;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-4 iframe-modal-demo">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <h5 class="mb-1">模拟「创建 CRUD」流程</h5>
                            <small class="text-muted">
                                这是一个在 iframe shell 中展示的表单子页面，提交后会调用
                                <code>AdminIframeClient.success()</code> 并请求父页刷新。
                            </small>
                        </div>
                        <span class="status-pill">
                            <i class="bi bi-bounding-box-circles"></i>
                            {{ $isEmbedded ? 'Shell 模式' : '独立模式' }}
                        </span>
                    </div>

                    <form class="row g-4" data-modal-form>
                        <div class="col-md-6">
                            <label for="db_connection" class="form-label">数据库连接</label>
                            <select class="form-select" id="db_connection" name="db_connection" required>
                                <option value="default" {{ ($formDefaults['db_connection'] ?? '') === 'default' ? 'selected' : '' }}>default</option>
                                <option value="analytics">analytics</option>
                                <option value="logs">logs</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="module_name" class="form-label">模块名称</label>
                            <input type="text"
                                   class="form-control"
                                   id="module_name"
                                   name="module_name"
                                   required
                                   value="{{ $formDefaults['module_name'] ?? '' }}"
                                   placeholder="例如：System">
                        </div>
                        <div class="col-md-6">
                            <label for="table_name" class="form-label">表名</label>
                            <input type="text"
                                   class="form-control"
                                   id="table_name"
                                   name="table_name"
                                   required
                                   value="{{ $formDefaults['table_name'] ?? '' }}"
                                   placeholder="admin_users">
                        </div>
                        <div class="col-md-6">
                            <label for="model_name" class="form-label">模型名称</label>
                            <input type="text"
                                   class="form-control"
                                   id="model_name"
                                   name="model_name"
                                   required
                                   value="{{ $formDefaults['model_name'] ?? '' }}"
                                   placeholder="AdminUser">
                        </div>
                        <div class="col-12">
                            <label for="description" class="form-label">描述</label>
                            <textarea class="form-control"
                                      id="description"
                                      name="description"
                                      rows="3"
                                      placeholder="可选：说明这个 CRUD 配置的用途">用于演示弹窗流程，展示如何与父级标签通信。</textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-outline-secondary" data-modal-cancel>
                                <i class="bi bi-x-circle me-1"></i> 取消
                            </button>
                            <button type="submit" class="btn btn-warning" data-modal-submit>
                                <i class="bi bi-send-check me-1"></i> 提交示范
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-body d-flex flex-column">
                    <h6 class="fw-semibold mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-activity"></i>
                        内页通信日志
                    </h6>
                    <p class="text-muted small mb-3">
                        下面的日志会记录本页与父级标签的交互行为。可以对照 CRUD 生成器的创建按钮，
                        学习如何打通「打开弹窗 → 提交 → 刷新父页」的链路。
                    </p>
                    <div class="bg-light rounded-3 flex-grow-1 p-3 action-log" data-modal-log>
                        <div class="text-muted" data-modal-log-placeholder>等待操作...</div>
                    </div>
                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="text-muted small">最新 payload</span>
                            <code class="text-muted small">{{ $diagnostics['channel'] ?? 'channel: iframe-demo-modal' }}</code>
                        </div>
                        <pre class="bg-dark text-white rounded-3 p-3 small mt-2 mb-0" style="max-height: 180px; overflow-y: auto;" data-modal-preview>{}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const form = document.querySelector('[data-modal-form]');
    const submitBtn = document.querySelector('[data-modal-submit]');
    const cancelBtn = document.querySelector('[data-modal-cancel]');
    const preview = document.querySelector('[data-modal-preview]');
    const logContainer = document.querySelector('[data-modal-log]');

    const appendLog = (text) => {
        if (!logContainer) {
            return;
        }
        const row = document.createElement('div');
        row.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
        logContainer.prepend(row);
        const placeholder = logContainer.querySelector('[data-modal-log-placeholder]');
        if (placeholder) {
            placeholder.remove();
        }
    };

    const toJson = (formData) => {
        const payload = {};
        formData.forEach((value, key) => {
            payload[key] = value;
        });
        payload._demo = true;
        return payload;
    };

    const notifyParentSuccess = (payload) => {
        if (window.AdminIframeClient) {
            window.AdminIframeClient.success({
                message: '示范：CRUD 配置已提交',
                refreshParent: true,
                event: {
                    name: 'crud-created',
                    payload,
                },
            });
            appendLog('✓ 调用 success() 并请求父页刷新标签');
        } else {
            console.info('当前不在 iframe shell，模拟成功 payload：', payload);
            appendLog('⚠ 未检测到 AdminIframeClient，已在控制台输出 payload');
        }
    };

    const closeParentShell = () => {
        if (window.AdminIframeClient) {
            window.AdminIframeClient.close({
                reason: '用户取消示范流程',
            });
            appendLog('✓ 调用 close()，请求父页关闭弹窗');
        } else {
            appendLog('⚠ 未检测到 AdminIframeClient，执行 window.history.back()');
            window.history.back();
        }
    };

    if (cancelBtn) {
        cancelBtn.addEventListener('click', (event) => {
            event.preventDefault();
            closeParentShell();
        });
    }

    if (form && submitBtn) {
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>保存中...';

            const payload = toJson(new FormData(form));
            preview.textContent = JSON.stringify(payload, null, 2);
            appendLog('→ 准备提交示例数据...');

            setTimeout(() => {
                notifyParentSuccess(payload);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-send-check me-1"></i> 提交示范';
            }, 600);
        });
    }
});
</script>
@endpush

