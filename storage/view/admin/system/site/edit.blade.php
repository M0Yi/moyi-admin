@extends('admin.layouts.admin')

@section('title', '站点设置')

@if (! $isEmbedded)
@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush
@endif

@section('content')
@include('admin.common.styles')
<div class="container-fluid py-4">
    <!-- 页面标题 -->
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">站点设置</h6>
        <small class="text-muted">配置站点基本信息、外观、SEO等设置</small>
    </div>

    <!-- 表单卡片 -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 text-muted mb-3" id="siteFormLoading">
                        <div class="spinner-border spinner-border-sm" role="status"></div>
                        <span>表单配置加载中，请稍候...</span>
                    </div>
                    <form id="siteForm" class="d-none">
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="id" value="{{ $site->id }}">

                        <!-- 基本信息分组 -->
                        <div class="mb-4" data-form-group="基本信息">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-info-circle me-2"></i>
                                基本信息
                            </h6>
                            <div class="row" id="siteFormFields-basic"></div>
                        </div>

                        <!-- 外观设置分组 -->
                        <div class="mb-4" data-form-group="外观设置">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-palette me-2"></i>
                                外观设置
                            </h6>
                            <div class="row" id="siteFormFields-appearance"></div>
                        </div>

                        <!-- SEO设置分组 -->
                        <div class="mb-4" data-form-group="SEO设置">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-search me-2"></i>
                                SEO设置
                            </h6>
                            <div class="row" id="siteFormFields-seo"></div>
                        </div>

                        <!-- 联系信息分组 -->
                        <div class="mb-4" data-form-group="联系信息">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-envelope me-2"></i>
                                联系信息
                            </h6>
                            <div class="row" id="siteFormFields-contact"></div>
                        </div>

                        <!-- 自定义代码分组 -->
                        <div class="mb-4" data-form-group="自定义代码">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-code me-2"></i>
                                自定义代码
                            </h6>
                            <div class="row" id="siteFormFields-custom"></div>
                        </div>

                        <!-- 上传配置分组 -->
                        <div class="mb-4" data-form-group="上传配置">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-cloud-upload me-2"></i>
                                上传配置
                            </h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>说明：</strong>默认使用系统提供的上传接口。如需使用自定义对象存储，请开启下方开关并填写配置信息。
                                <br><small class="mt-1 d-block">支持的服务：<strong>AWS S3</strong>、<strong>阿里云 OSS</strong>、<strong>腾讯云 COS</strong>、<strong>MinIO</strong> 等兼容 S3 的对象存储服务</small>
                            </div>
                            
                            <!-- 上传配置开关和 S3 配置区域 -->
                            <div class="row" id="siteFormFields-upload"></div>
                            
                            <!-- S3 配置区域（默认隐藏） -->
                            <div id="s3ConfigArea" style="display: none;">
                                <div class="card border-primary mt-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-cloud me-2"></i>S3 兼容存储配置</h6>
                                        <small class="text-muted">支持 AWS S3、阿里云 OSS、Cloudflare R2、腾讯云 COS、七牛云、MinIO 等</small>
                                    </div>
                                    <div class="card-body">
                                        <!-- 快速配置选择 -->
                                        <div class="row mb-3">
                                            <div class="col-12">
                                                <label class="form-label"><strong>快速配置</strong></label>
                                                <div class="btn-group" role="group">
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('aws')">
                                                        <i class="bi bi-cloud me-1"></i>AWS S3
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('aliyun')">
                                                        <i class="bi bi-cloud me-1"></i>阿里云 OSS
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('r2')">
                                                        <i class="bi bi-cloud me-1"></i>Cloudflare R2
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('qcloud')">
                                                        <i class="bi bi-cloud me-1"></i>腾讯云 COS
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('qiniu')">
                                                        <i class="bi bi-cloud me-1"></i>七牛云
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.SiteForm && window.SiteForm.fillS3Config('minio')">
                                                        <i class="bi bi-cloud me-1"></i>MinIO
                                                    </button>
                                                </div>
                                                <small class="form-text text-muted d-block mt-1">
                                                    点击快速配置按钮可自动填充示例配置，您仍需填写实际的 Access Key、Secret 和 Bucket 信息
                                                </small>
                                            </div>
                                        </div>

                                        <!-- 阿里云 OSS 配置说明 -->
                                        <div class="alert alert-warning mb-3" id="aliyunOssHelp" style="display: none;">
                                            <h6 class="alert-heading"><i class="bi bi-exclamation-triangle me-2"></i>阿里云 OSS 配置说明</h6>
                                            <ol class="mb-0 small">
                                                <li><strong>开启 S3 兼容性：</strong>在阿里云 OSS 控制台，进入 Bucket 设置 → 数据管理 → S3 兼容性，开启 S3 兼容性开关</li>
                                                <li><strong>Access Key ID：</strong>阿里云 AccessKey ID（在 AccessKey 管理中创建）</li>
                                                <li><strong>Secret Access Key：</strong>阿里云 AccessKey Secret</li>
                                                <li><strong>Region：</strong>OSS 区域，如 <code>cn-beijing</code>、<code>cn-hangzhou</code>、<code>cn-shanghai</code> 等</li>
                                                <li><strong>Endpoint：</strong>格式为 <code>https://oss-{region}.aliyuncs.com</code>，例如：<code>https://oss-cn-beijing.aliyuncs.com</code></li>
                                                <li><strong>CDN 域名（必填）：</strong>填写 OSS Bucket 的访问域名，格式：<code>https://{bucket}.oss-{region}.aliyuncs.com</code> 或自定义域名</li>
                                                <li><strong>Path Style：</strong>建议开启（勾选）</li>
                                            </ol>
                                        </div>

                                        <!-- Cloudflare R2 配置说明 -->
                                        <div class="alert alert-info mb-3" id="r2Help" style="display: none;">
                                            <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>Cloudflare R2 配置说明</h6>
                                            <ol class="mb-0 small">
                                                <li><strong>Access Key ID：</strong>Cloudflare R2 API Token（在 Cloudflare Dashboard → R2 → Manage R2 API Tokens 创建）</li>
                                                <li><strong>Secret Access Key：</strong>R2 API Token Secret</li>
                                                <li><strong>Bucket 名称：</strong>您的 R2 Bucket 名称</li>
                                                <li><strong>Region：</strong>设置为 <code>auto</code> 或留空（R2 不依赖特定区域）</li>
                                                <li><strong>Endpoint：</strong>格式为 <code>https://{account-id}.r2.cloudflarestorage.com</code>，account-id 可以在 R2 API Token 页面查看</li>
                                                <li><strong>CDN 域名（必填）：</strong>填写 R2 Bucket 的访问域名。如果已绑定自定义域名，可填写自定义域名</li>
                                                <li><strong>Path Style：</strong>建议开启（勾选）</li>
                                            </ol>
                                        </div>

                                        <!-- 腾讯云 COS 配置说明 -->
                                        <div class="alert alert-success mb-3" id="qcloudCosHelp" style="display: none;">
                                            <h6 class="alert-heading"><i class="bi bi-check-circle me-2"></i>腾讯云 COS 配置说明</h6>
                                            <ol class="mb-0 small">
                                                <li><strong>Access Key ID：</strong>腾讯云 SecretId（在访问管理 → API 密钥管理中创建）</li>
                                                <li><strong>Secret Access Key：</strong>腾讯云 SecretKey</li>
                                                <li><strong>Bucket 名称：</strong>您的 COS Bucket 名称</li>
                                                <li><strong>Region：</strong>COS 区域，如 <code>ap-beijing</code>、<code>ap-shanghai</code>、<code>ap-guangzhou</code>、<code>ap-chengdu</code> 等</li>
                                                <li><strong>Endpoint：</strong>格式为 <code>https://cos.{region}.myqcloud.com</code>，例如：<code>https://cos.ap-beijing.myqcloud.com</code></li>
                                                <li><strong>CDN 域名（必填）：</strong>填写 COS Bucket 的访问域名或自定义域名</li>
                                                <li><strong>Path Style：</strong>建议开启（勾选）</li>
                                            </ol>
                                        </div>

                                        <!-- 七牛云配置说明 -->
                                        <div class="alert alert-primary mb-3" id="qiniuHelp" style="display: none;">
                                            <h6 class="alert-heading"><i class="bi bi-cloud me-2"></i>七牛云配置说明</h6>
                                            <ol class="mb-0 small">
                                                <li><strong>Access Key ID：</strong>七牛云 AccessKey（在密钥管理 → AccessKey 中创建）</li>
                                                <li><strong>Secret Access Key：</strong>七牛云 SecretKey</li>
                                                <li><strong>Bucket 名称：</strong>您的七牛云对象存储 Bucket 名称</li>
                                                <li><strong>Region：</strong>存储区域，如 <code>cn-east-1</code>（华东）、<code>cn-north-1</code>（华北）、<code>cn-south-1</code>（华南）、<code>us-east-1</code>（北美）等</li>
                                                <li><strong>Endpoint：</strong>格式为 <code>https://s3-{region}.qiniucs.com</code>，例如：<code>https://s3-cn-east-1.qiniucs.com</code></li>
                                                <li><strong>CDN 域名（必填）：</strong>填写七牛云存储空间的访问域名或自定义域名</li>
                                                <li><strong>Path Style：</strong>建议开启（勾选）</li>
                                            </ol>
                                        </div>

                                        <!-- S3 配置字段 -->
                                        <div class="row" id="siteFormFields-s3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI 配置分组（可折叠，默认折叠） -->
                        <div class="mb-4" data-form-group="AI 配置">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="mb-0 fw-bold text-primary">
                                    <i class="bi bi-robot me-2"></i>
                                    AI 配置
                                </h6>
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-link p-0 text-decoration-none"
                                    data-ai-config-toggle
                                    aria-expanded="false"
                                >
                                    <i class="bi bi-chevron-down"></i>
                                </button>
                            </div>
                            <div class="ai-config-content" data-ai-config-content style="display: none;">
                                <div class="row" id="siteFormFields-ai"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 固定在底部的操作栏 -->
@include('admin.components.fixed-bottom-actions', [
    'infoText' => '修改完成后点击保存按钮提交',
    'cancelUrl' => admin_route('dashboard'),
    'submitText' => '保存',
    'formId' => 'siteForm',
    'submitBtnId' => 'submitBtn',
    'onSuccess' => [
        'close' => false,
        'refreshParent' => false,
        'message' => '保存成功',
        'refreshMainFrame' => [
            'message' => '站点配置已更新，主框架即将重新载入最新菜单和权限',
            'delay' => 800,
            'toastType' => 'info'
        ]
    ]
])

@push('admin_scripts')
@include('components.color-picker')
@include('components.gradient-picker')

<!-- 引入通用表单渲染器 -->
@php
    $universalFormJsVersion = file_exists(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        ? filemtime(BASE_PATH . '/public/js/components/universal-form-renderer.js')
        : time();
@endphp
@include('components.admin-script', ['path' => '/js/components/universal-form-renderer.js', 'version' => $universalFormJsVersion])

<!-- 站点表单特殊逻辑 -->
@include('components.admin-script', ['path' => '/js/admin/system/site-form.js'])

<script>
window.SiteFormPage = {
    formSchema: {!! $formSchemaJson ?? '{}' !!}
};

document.addEventListener('DOMContentLoaded', function () {
    if (typeof window.UniversalFormRenderer !== 'function') {
        console.error('[SiteForm] UniversalFormRenderer 未正确加载');
        return;
    }
    
    // 初始化通用表单渲染器（先渲染到默认容器）
    const renderer = new UniversalFormRenderer({
        schema: window.SiteFormPage.formSchema,
        config: {},
        formId: 'siteForm',
        fieldsWrapperSelector: '#siteFormFields-basic', // 先渲染到基本信息容器
        submitButtonId: 'submitBtn',
        loadingIndicatorId: 'siteFormLoading'
    });
    
    // 渲染完成后，按分组移动字段到对应容器
    setTimeout(() => {
        const fields = window.SiteFormPage.formSchema.fields || [];
        const groupContainers = {
            '基本信息': document.getElementById('siteFormFields-basic'),
            '外观设置': document.getElementById('siteFormFields-appearance'),
            'SEO设置': document.getElementById('siteFormFields-seo'),
            '联系信息': document.getElementById('siteFormFields-contact'),
            '自定义代码': document.getElementById('siteFormFields-custom'),
            '上传配置': document.getElementById('siteFormFields-upload'),
            'AI 配置': document.getElementById('siteFormFields-ai'),
        };
        
        // 按分组移动字段
        fields.forEach((field) => {
            const group = field.group || '基本信息';
            const container = groupContainers[group];
            if (!container) return;
            
            // 查找对应的字段元素（通过 data-field-name）
            const fieldElement = document.querySelector(`[data-field-name="${field.name}"]`);
            if (fieldElement) {
                // 查找包含字段的 col-* 容器
                let colContainer = fieldElement.closest('.col-12, .col-md-6, .col-md-4, .col-md-3');
                if (!colContainer) {
                    // 如果没有找到，查找父级
                    colContainer = fieldElement.parentElement;
                }
                
                if (colContainer && colContainer !== container) {
                    // 如果字段不在目标容器中，移动它
                    if (group === '基本信息' && colContainer.parentElement === container) {
                        // 已经在正确位置，跳过
                        return;
                    }
                    container.appendChild(colContainer);
                }
            }
        });
        
        // 将 S3 配置字段移动到 s3ConfigArea
        const s3FieldsContainer = document.getElementById('siteFormFields-s3');
        if (s3FieldsContainer) {
            const s3FieldNames = ['s3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_cdn', 's3_path_style'];
            s3FieldNames.forEach(fieldName => {
                const fieldElement = document.querySelector(`[data-field-name="${fieldName}"]`);
                if (fieldElement) {
                    let colContainer = fieldElement.closest('.col-12, .col-md-6, .col-md-4, .col-md-3');
                    if (!colContainer) {
                        colContainer = fieldElement.parentElement;
                    }
                    if (colContainer && colContainer !== s3FieldsContainer) {
                        s3FieldsContainer.appendChild(colContainer);
                    }
                }
            });
        }
        
        // 初始化 AI 配置分组折叠功能
        const aiConfigToggle = document.querySelector('[data-ai-config-toggle]');
        const aiConfigContent = document.querySelector('[data-ai-config-content]');
        if (aiConfigToggle && aiConfigContent) {
            aiConfigToggle.addEventListener('click', function() {
                const isExpanded = aiConfigContent.style.display !== 'none';
                aiConfigContent.style.display = isExpanded ? 'none' : 'block';
                aiConfigToggle.setAttribute('aria-expanded', !isExpanded);
                const icon = aiConfigToggle.querySelector('i');
                if (icon) {
                    icon.className = isExpanded ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                }
            });
        }

        // 初始化站点表单特殊逻辑
        if (window.SiteForm && window.SiteForm.init) {
            window.SiteForm.init();
        }
    }, 300);
    
    // 处理表单提交
    const form = document.getElementById('siteForm');
    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            
            const formData = new FormData(form);
            
            try {
                // 使用 SiteForm 处理提交数据
                const jsonData = window.SiteForm && window.SiteForm.prepareSubmitData 
                    ? window.SiteForm.prepareSubmitData(formData)
                    : Object.fromEntries(formData);
                
                // 发送 PUT 请求
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>保存中...';
                
                fetch('{{ admin_route("system/sites") }}', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                    body: JSON.stringify(jsonData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.code === 200) {
                        const message = data.msg || '保存成功';
                        
                        const successEvent = new CustomEvent('submit-success', {
                            bubbles: true,
                            cancelable: true,
                            detail: {
                                message: message,
                                redirect: null,
                            }
                        });
                        form.dispatchEvent(successEvent);
                        
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '保存';
                    } else {
                        const errorMsg = data.msg || data.message || '保存失败';
                        if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                            window.Admin.utils.showToast('danger', errorMsg);
                        } else {
                            alert(errorMsg);
                        }
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '保存';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    const errorMsg = '保存失败：' + error.message;
                    if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                        window.Admin.utils.showToast('danger', errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '保存';
                });
            } catch (error) {
                console.error('Error:', error);
                const errorMsg = error.message || '表单数据验证失败';
                if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                    window.Admin.utils.showToast('danger', errorMsg);
                } else {
                    alert(errorMsg);
                }
            }
        });
    }
});
</script>
@endpush

@include('admin.common.scripts')
@endsection
