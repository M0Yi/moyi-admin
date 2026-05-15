@extends('admin.layouts.admin')

@section('title', '数据导入')

@php
    $isEmbedded = $isEmbedded ?? false;
@endphp

@if (! $isEmbedded)
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
<div class="container-fluid py-4">
    <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-bold">数据导入</h6>
                <small class="text-muted">从外部系统导入博客数据</small>
            </div>
            <div>
                <a href="{{ admin_route('blog/posts') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i>返回文章管理
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                            <i class="bi bi-upload text-primary"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Ghost 数据导入</h6>
                            <small class="text-muted">从 Ghost 导出的数据文件导入博客内容</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <div class="bg-light rounded p-3 mb-3">
                            <div class="d-flex align-items-start">
                                <i class="bi bi-info-circle text-info me-2 mt-1"></i>
                                <div>
                                    <strong>导入说明：</strong>
                                    <p class="mb-1 text-muted small">从 Ghost 导出的数据文件导入博客文章、标签等内容。</p>
                                    <code class="bg-white px-2 py-1 rounded small">ghost/content/data/my-designe.ghost.2024-06-22-11-07-48.json</code>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10">
                            <div class="d-flex">
                                <i class="bi bi-exclamation-triangle text-warning me-2"></i>
                                <div>
                                    <strong>重要提醒：</strong>
                                    <ul class="mb-0 mt-2 small">
                                        <li>导入操作会创建新的文章和标签，已存在的 Slug 会被更新</li>
                                        <li>Ghost 用户会根据邮箱匹配到现有的管理员用户</li>
                                        <li>建议在导入前备份数据库</li>
                                        <li>导入过程可能需要一些时间，请耐心等待</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center justify-content-between">
                        <button id="import-btn" class="btn btn-primary" onclick="startImport()">
                            <i class="bi bi-play-circle me-1"></i>开始导入
                        </button>
                        <div id="import-status" class="text-muted small d-none">
                            <i class="bi bi-clock me-1"></i>准备开始...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 rounded-circle p-2 me-3">
                            <i class="bi bi-bar-chart text-success"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">导入进度</h6>
                            <small class="text-muted">实时显示导入状态</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="import-progress" class="d-none">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="small fw-medium">导入进度</span>
                                <span id="progress-percent" class="small text-primary fw-bold">0%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div id="progress-bar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                            </div>
                        </div>
                        <div id="progress-text" class="small text-muted">
                            <i class="bi bi-hourglass-split me-1"></i>准备导入...
                        </div>
                    </div>

                    <div id="import-result" class="d-none">
                        <h6 class="mb-3 fw-bold">
                            <i class="bi bi-check-circle text-success me-1"></i>导入结果
                        </h6>
                        <div id="result-content">
                            <!-- 结果内容将在这里显示 -->
                        </div>
                    </div>

                    <div id="import-error" class="d-none">
                        <div class="alert alert-danger border-0 bg-danger bg-opacity-10">
                            <div class="d-flex">
                                <i class="bi bi-x-circle text-danger me-2"></i>
                                <div>
                                    <strong>导入失败</strong>
                                    <div id="error-message" class="mt-1 small"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 开始导入
    window.startImport = function() {
        const btn = document.getElementById('import-btn');
        const status = document.getElementById('import-status');
        const progress = document.getElementById('import-progress');
        const result = document.getElementById('import-result');
        const error = document.getElementById('import-error');

        // 禁用按钮
        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>导入中...';

        // 重置状态
        status.classList.add('d-none');
        progress.classList.add('d-none');
        result.classList.add('d-none');
        error.classList.add('d-none');

        // 显示进度
        progress.classList.remove('d-none');
        updateProgress(10, '正在初始化...');

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch('{{ admin_route('blog/import/ghost') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            updateProgress(100, '导入完成');

            setTimeout(() => {
                progress.classList.add('d-none');

                if (data.code === 200) {
                    result.classList.remove('d-none');
                    showResult(data.data);
                } else {
                    error.classList.remove('d-none');
                    showError(data.msg || data.message || '导入失败');
                }
            }, 1000);
        })
        .catch(err => {
            updateProgress(100, '导入失败');
            setTimeout(() => {
                progress.classList.add('d-none');
                error.classList.remove('d-none');
                showError('网络错误或服务器错误');
            }, 1000);
        })
        .finally(() => {
            // 恢复按钮
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-circle me-1"></i>开始导入';
        });
    };

    // 更新进度
    function updateProgress(percent, text) {
        const progressBar = document.getElementById('progress-bar');
        const progressPercent = document.getElementById('progress-percent');
        const progressText = document.getElementById('progress-text');

        progressBar.style.width = percent + '%';
        progressPercent.textContent = percent + '%';
        progressText.innerHTML = `<i class="bi bi-${percent < 100 ? 'hourglass-split' : 'check-circle'} me-1"></i>${text}`;
    }

    // 显示结果
    function showResult(data) {
        const resultContent = document.getElementById('result-content');
        resultContent.innerHTML = '';

        const stats = [
            { label: '文章导入', value: data.posts_imported, icon: 'bi-file-earmark-text', color: 'success' },
            { label: '标签导入', value: data.tags_imported, icon: 'bi-tags', color: 'info' },
            { label: '用户映射', value: data.users_mapped, icon: 'bi-people', color: 'primary' }
        ];

        stats.forEach(stat => {
            resultContent.innerHTML += `
                <div class="d-flex align-items-center mb-2">
                    <div class="bg-${stat.color} bg-opacity-10 rounded-circle p-1 me-2">
                        <i class="bi ${stat.icon} text-${stat.color}" style="font-size: 0.8rem;"></i>
                    </div>
                    <span class="small">${stat.label}：<strong class="text-${stat.color}">${stat.value}</strong></span>
                </div>
            `;
        });

        if (data.errors && data.errors.length > 0) {
            resultContent.innerHTML += `
                <div class="mt-3 p-3 bg-warning bg-opacity-10 rounded border border-warning">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-exclamation-triangle text-warning me-2 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong class="text-warning">发现 ${data.errors.length} 个错误：</strong>
                            <ul class="mb-0 mt-2 small">
                                ${data.errors.map(error => `<li class="text-danger">${error}</li>`).join('')}
                            </ul>
                        </div>
                    </div>
                </div>
            `;

            resultContent.innerHTML += `
                <div class="alert alert-warning border-0 bg-warning bg-opacity-10 mt-3">
                    <i class="bi bi-info-circle text-warning me-1"></i>
                    <strong>导入完成，但存在一些错误，请检查上述信息。</strong>
                </div>
            `;
        } else {
            resultContent.innerHTML += `
                <div class="alert alert-success border-0 bg-success bg-opacity-10 mt-3">
                    <i class="bi bi-check-circle text-success me-1"></i>
                    <strong>导入成功完成！</strong>
                </div>
            `;
        }
    }

    // 显示错误
    function showError(message) {
        const errorMessage = document.getElementById('error-message');
        errorMessage.textContent = message;
    }
});
</script>
@endpush
