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
                    <form id="editForm" onsubmit="submitForm(event)">
                        <input type="hidden" name="_method" value="PUT">
                        <input type="hidden" name="id" value="{{ $site->id }}">

                        <!-- 基本信息 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-info-circle me-2"></i>
                                基本信息
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'domain',
                                            'type' => 'text',
                                            'label' => '域名',
                                            'required' => true,
                                            'placeholder' => '例如：example.com',
                                            'disabled' => true,
                                            'help' => '域名创建后不可修改，如需修改请联系管理员',
                                        ],
                                        'value' => $site->domain ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'admin_entry_path',
                                            'type' => 'text',
                                            'label' => '后台入口路径',
                                            'required' => true,
                                            'placeholder' => '例如：admin',
                                            'disabled' => true,
                                            'help' => '后台入口路径创建后不可修改，如需修改请联系管理员',
                                        ],
                                        'value' => $site->admin_entry_path ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'name',
                                            'type' => 'text',
                                            'label' => '站点名称',
                                            'required' => true,
                                            'placeholder' => '请输入站点名称',
                                        ],
                                        'value' => $site->name ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'title',
                                            'type' => 'text',
                                            'label' => '站点标题',
                                            'placeholder' => '站点标题',
                                        ],
                                        'value' => $site->title ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'slogan',
                                            'type' => 'text',
                                            'label' => '站点口号',
                                            'placeholder' => '站点口号',
                                        ],
                                        'value' => $site->slogan ?? '',
                                    ])
                                </div>
                            </div>
                        </div>

                        <!-- 外观设置 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-palette me-2"></i>
                                外观设置
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'logo',
                                            'type' => 'image',
                                            'label' => 'Logo',
                                            'placeholder' => '上传Logo图片',
                                        ],
                                        'value' => $site->logo ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'favicon',
                                            'type' => 'image',
                                            'label' => 'Favicon',
                                            'placeholder' => '上传Favicon图标',
                                            'help' => '推荐使用 PNG 格式',
                                        ],
                                        'value' => $site->favicon ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'primary_color',
                                            'type' => 'color',
                                            'label' => '主题色',
                                            'placeholder' => '例如：#667eea',
                                            'help' => '十六进制颜色值',
                                        ],
                                        'value' => $site->primary_color ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'secondary_color',
                                            'type' => 'color',
                                            'label' => '辅助色',
                                            'placeholder' => '例如：#764ba2',
                                            'help' => '十六进制颜色值',
                                        ],
                                        'value' => $site->secondary_color ?? '',
                                    ])
                                </div>
                            </div>
                        </div>

                        <!-- SEO设置 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-search me-2"></i>
                                SEO设置
                            </h6>
                            <div class="row">
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'description',
                                            'type' => 'textarea',
                                            'label' => '站点描述',
                                            'placeholder' => '站点描述（用于SEO）',
                                        ],
                                        'value' => $site->description ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'keywords',
                                            'type' => 'text',
                                            'label' => 'SEO关键词',
                                            'placeholder' => '关键词，多个关键词用逗号分隔',
                                            'help' => '多个关键词用逗号分隔',
                                        ],
                                        'value' => $site->keywords ?? '',
                                    ])
                                </div>
                            </div>
                        </div>

                        <!-- 联系信息 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-envelope me-2"></i>
                                联系信息
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'contact_email',
                                            'type' => 'email',
                                            'label' => '联系邮箱',
                                            'placeholder' => '联系邮箱',
                                        ],
                                        'value' => $site->contact_email ?? '',
                                    ])
                                </div>
                                <div class="col-md-6">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'contact_phone',
                                            'type' => 'text',
                                            'label' => '联系电话',
                                            'placeholder' => '联系电话',
                                        ],
                                        'value' => $site->contact_phone ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'address',
                                            'type' => 'text',
                                            'label' => '地址',
                                            'placeholder' => '地址',
                                        ],
                                        'value' => $site->address ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'icp_number',
                                            'type' => 'text',
                                            'label' => 'ICP备案号',
                                            'placeholder' => 'ICP备案号',
                                        ],
                                        'value' => $site->icp_number ?? '',
                                    ])
                                </div>
                            </div>
                        </div>

                        <!-- 自定义代码 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-code me-2"></i>
                                自定义代码
                            </h6>
                            <div class="row">
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'analytics_code',
                                            'type' => 'textarea',
                                            'label' => '统计代码',
                                            'placeholder' => 'Google Analytics、百度统计等代码',
                                            'help' => '支持HTML代码，如Google Analytics、百度统计等',
                                        ],
                                        'value' => $site->analytics_code ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'custom_css',
                                            'type' => 'textarea',
                                            'label' => '自定义CSS',
                                            'placeholder' => '自定义CSS样式代码',
                                        ],
                                        'value' => $site->custom_css ?? '',
                                    ])
                                </div>
                                <div class="col-12">
                                    @include('admin.components.form.field', [
                                        'field' => [
                                            'name' => 'custom_js',
                                            'type' => 'textarea',
                                            'label' => '自定义JavaScript',
                                            'placeholder' => '自定义JavaScript代码',
                                        ],
                                        'value' => $site->custom_js ?? '',
                                    ])
                                </div>
                            </div>
                        </div>

                        <!-- 上传配置 -->
                        <div class="mb-4">
                            <h6 class="fw-bold mb-3 text-primary">
                                <i class="bi bi-cloud-upload me-2"></i>
                                上传配置
                            </h6>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                <strong>说明：</strong>默认使用系统提供的上传接口。如需使用自定义对象存储，请开启下方开关并填写配置信息。
                                <br><small class="mt-1 d-block">支持的服务：<strong>AWS S3</strong>、<strong>阿里云 OSS</strong>、<strong>腾讯云 COS</strong>、<strong>MinIO</strong> 等兼容 S3 的对象存储服务</small>
                            </div>
                            <div class="row mb-3">
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="useCustomUpload" 
                                               {{ ($site->upload_driver !== null || $site->upload_config !== null) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="useCustomUpload">
                                            <strong>使用自定义上传配置</strong>
                                        </label>
                                        <small class="form-text text-muted d-block">
                                            开启后将使用您配置的对象存储，而非系统默认配置
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- S3 配置区域（默认隐藏） -->
                            <div id="s3ConfigArea" style="display: {{ ($site->upload_driver !== null || $site->upload_config !== null) ? 'block' : 'none' }};">
                                <div class="card border-primary">
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
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('aws')">
                                                        <i class="bi bi-cloud me-1"></i>AWS S3
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('aliyun')">
                                                        <i class="bi bi-cloud me-1"></i>阿里云 OSS
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('r2')">
                                                        <i class="bi bi-cloud me-1"></i>Cloudflare R2
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('qcloud')">
                                                        <i class="bi bi-cloud me-1"></i>腾讯云 COS
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('qiniu')">
                                                        <i class="bi bi-cloud me-1"></i>七牛云
                                                    </button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillS3Config('minio')">
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

                                        <div class="row">
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_key',
                                                        'type' => 'text',
                                                        'label' => 'Access Key ID',
                                                        'placeholder' => '请输入 Access Key ID',
                                                        'required' => false,
                                                        'help' => '在对应云服务商的控制台中创建 AccessKey',
                                                    ],
                                                    'value' => $site->upload_config['s3']['key'] ?? $site->upload_config['s3']['credentials']['key'] ?? '',
                                                ])
                                            </div>
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_secret',
                                                        'type' => 'password',
                                                        'label' => 'Secret Access Key',
                                                        'placeholder' => '请输入 Secret Access Key',
                                                        'required' => false,
                                                        'help' => '与 Access Key ID 对应的密钥',
                                                    ],
                                                    'value' => '',
                                                ])
                                                <!-- 保存原始 Secret 值（用于当用户清空字段时保留原值） -->
                                                @if(isset($site->upload_config['s3']['secret']) || isset($site->upload_config['s3']['credentials']['secret']))
                                                    <input type="hidden" name="existing_s3_secret" 
                                                           value="{{ $site->upload_config['s3']['secret'] ?? $site->upload_config['s3']['credentials']['secret'] ?? '' }}">
                                                @endif
                                            </div>
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_bucket',
                                                        'type' => 'text',
                                                        'label' => 'Bucket 名称',
                                                        'placeholder' => '请输入存储桶名称',
                                                        'required' => false,
                                                        'help' => '在云服务商控制台创建的存储桶名称',
                                                    ],
                                                    'value' => $site->upload_config['s3']['bucket'] ?? $site->upload_config['s3']['bucket_name'] ?? '',
                                                ])
                                            </div>
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_region',
                                                        'type' => 'text',
                                                        'label' => 'Region（区域）',
                                                        'placeholder' => '请输入区域标识',
                                                        'required' => false,
                                                        'help' => '存储桶所在的区域',
                                                    ],
                                                    'value' => $site->upload_config['s3']['region'] ?? '',
                                                ])
                                            </div>
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_endpoint',
                                                        'type' => 'text',
                                                        'label' => 'Endpoint（可选）',
                                                        'placeholder' => '请输入访问端点地址',
                                                        'required' => false,
                                                        'help' => 'API 访问端点，通常根据 Region 自动生成',
                                                    ],
                                                    'value' => $site->upload_config['s3']['endpoint'] ?? '',
                                                ])
                                            </div>
                                            <div class="col-md-6">
                                                @include('admin.components.form.field', [
                                                    'field' => [
                                                        'name' => 's3_cdn',
                                                        'type' => 'text',
                                                        'label' => 'CDN 域名',
                                                        'placeholder' => '例如：https://cdn.example.com',
                                                        'required' => true,
                                                        'help' => '必填：用于访问图片的 CDN 域名或对象存储的访问域名（如 OSS 的 Bucket 域名）',
                                                    ],
                                                    'value' => $site->upload_config['s3']['cdn'] ?? '',
                                                ])
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch mt-4">
                                                    <input class="form-check-input" type="checkbox" id="s3_path_style" 
                                                           {{ ($site->upload_config['s3']['use_path_style_endpoint'] ?? false) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="s3_path_style">
                                                        使用路径样式端点（Path Style）
                                                    </label>
                                                    <small class="form-text text-muted d-block">
                                                        AWS：通常关闭；阿里云 OSS：建议开启；R2：建议开启；腾讯云 COS：建议开启；MinIO：必须开启
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
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
    'formId' => 'editForm',
    'submitBtnId' => 'submitBtn',
    'onSuccess' => [
        'close' => false,           // 不关闭当前页面
        'refreshParent' => false,   // 通过主框架刷新，无需单独刷新父级
        'message' => '保存成功',    // 成功提示消息
        'refreshMainFrame' => [
            'message' => '站点配置已更新，主框架即将重新载入最新菜单和权限',
            'delay' => 800,
            'toastType' => 'info'
        ]
    ]
])

@push('admin_scripts')
@include('components.color-picker')
@endpush

@include('admin.common.scripts')

<script>
// 记录当前选择的存储类型
let currentStorageType = null;

/**
 * 使用统一的提示弹窗（Toast），若不可用则降级为 alert
 * @param {string} type 提示类型：success | danger | warning | info
 * @param {string} message 提示内容
 */
function showFeedbackToast(type = 'info', message = '') {
    const finalMessage = message || (type === 'success' ? '操作成功' : '操作失败');

    if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
        window.Admin.utils.showToast(type, finalMessage);
        return;
    }

    if (typeof window.showToast === 'function') {
        window.showToast(type, finalMessage);
        return;
    }

    alert(finalMessage);
}

// 自定义上传配置开关
document.addEventListener('DOMContentLoaded', function() {
    const useCustomUpload = document.getElementById('useCustomUpload');
    const s3ConfigArea = document.getElementById('s3ConfigArea');
    
    if (useCustomUpload && s3ConfigArea) {
        // 处理 required 属性的辅助函数
        const toggleS3FieldsRequired = (required) => {
            const s3Fields = ['s3_cdn']; // 只有 s3_cdn 是必填的
            s3Fields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (field) {
                    if (required) {
                        field.setAttribute('required', 'required');
                    } else {
                        field.removeAttribute('required');
                    }
                }
            });
        };
        
        // 初始化：根据开关状态设置 required 属性
        toggleS3FieldsRequired(useCustomUpload.checked);
        
        useCustomUpload.addEventListener('change', function() {
            if (this.checked) {
                s3ConfigArea.style.display = 'block';
                toggleS3FieldsRequired(true);
            } else {
                s3ConfigArea.style.display = 'none';
                toggleS3FieldsRequired(false);
            }
        });
    }

    // 检测当前配置类型（页面加载时）
    const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
    if (s3EndpointInput && s3EndpointInput.value) {
        const endpoint = s3EndpointInput.value.trim();
        if (endpoint.includes('oss-') && endpoint.includes('.aliyuncs.com')) {
            currentStorageType = 'aliyun';
            showHelp('aliyunOssHelp');
        } else if (endpoint.includes('.r2.cloudflarestorage.com')) {
            currentStorageType = 'r2';
            showHelp('r2Help');
        } else if (endpoint.includes('.myqcloud.com') || endpoint.includes('qcloud.com')) {
            currentStorageType = 'qcloud';
            showHelp('qcloudCosHelp');
        } else if (endpoint.includes('.qiniucs.com') || endpoint.includes('qiniu')) {
            currentStorageType = 'qiniu';
            showHelp('qiniuHelp');
        } else if (endpoint.includes('localhost') || endpoint.includes('minio')) {
            currentStorageType = 'minio';
        } else if (!endpoint || endpoint.includes('amazonaws.com')) {
            currentStorageType = 'aws';
        }
    }
});

function showHelp(helpId) {
    const help = document.getElementById(helpId);
    if (help) {
        help.style.display = 'block';
    }
}

function hideAllHelp() {
    ['aliyunOssHelp', 'r2Help', 'qcloudCosHelp', 'qiniuHelp'].forEach(id => {
        const help = document.getElementById(id);
        if (help) {
            help.style.display = 'none';
        }
    });
}

// 更新 help 文本的辅助函数
function updateHelpText(inputName, helpText) {
    const input = document.querySelector(`[name="${inputName}"]`);
    if (input) {
        const formGroup = input.closest('.mb-3');
        if (formGroup) {
            const helpElement = formGroup.querySelector('.form-text');
            if (helpElement) {
                helpElement.textContent = helpText;
            }
        }
    }
}

// 快速配置函数
function fillS3Config(type) {
    const s3KeyInput = document.querySelector('[name="s3_key"]');
    const s3SecretInput = document.querySelector('[name="s3_secret"]');
    const s3BucketInput = document.querySelector('[name="s3_bucket"]');
    const s3RegionInput = document.querySelector('[name="s3_region"]');
    const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
    const s3CdnInput = document.querySelector('[name="s3_cdn"]');
    const s3PathStyleCheckbox = document.getElementById('s3_path_style');

    // 确保开关已开启
    const useCustomUpload = document.getElementById('useCustomUpload');
    if (!useCustomUpload.checked) {
        useCustomUpload.checked = true;
        document.getElementById('s3ConfigArea').style.display = 'block';
    }

    // 隐藏所有帮助提示
    hideAllHelp();

    switch(type) {
        case 'aws':
            currentStorageType = 'aws';
            if (s3RegionInput) s3RegionInput.value = 'us-east-1';
            if (s3EndpointInput) s3EndpointInput.value = '';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = false;
            updateHelpText('s3_key', '在 AWS IAM 中创建 Access Key');
            updateHelpText('s3_secret', '与 Access Key ID 对应的 Secret Key');
            updateHelpText('s3_bucket', '在 AWS S3 控制台创建的存储桶名称');
            updateHelpText('s3_region', '存储桶区域，如 us-east-1、ap-southeast-1 等');
            updateHelpText('s3_endpoint', '通常留空，AWS 会自动识别');
            updateHelpText('s3_cdn', '必填：AWS S3 Bucket 的访问域名，格式：https://{bucket}.s3.{region}.amazonaws.com');
            break;

        case 'aliyun':
            currentStorageType = 'aliyun';
            if (s3RegionInput) s3RegionInput.value = 'cn-beijing';
            if (s3EndpointInput) s3EndpointInput.value = 'https://oss-cn-beijing.aliyuncs.com';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = true;
            updateHelpText('s3_key', '在 AccessKey 管理中创建，需开启 S3 兼容性');
            updateHelpText('s3_secret', '与 AccessKey ID 对应的 Secret');
            updateHelpText('s3_bucket', 'OSS 存储桶名称');
            updateHelpText('s3_region', '如 cn-beijing、cn-hangzhou、cn-shanghai 等');
            updateHelpText('s3_endpoint', '格式：https://oss-{region}.aliyuncs.com');
            updateHelpText('s3_cdn', '必填：OSS Bucket 的访问域名，格式：https://{bucket}.oss-{region}.aliyuncs.com');
            showHelp('aliyunOssHelp');
            break;

        case 'r2':
            currentStorageType = 'r2';
            if (s3RegionInput) s3RegionInput.value = 'auto';
            if (s3EndpointInput) s3EndpointInput.value = '';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = true;
            updateHelpText('s3_key', '在 R2 → Manage R2 API Tokens 创建');
            updateHelpText('s3_secret', 'R2 API Token Secret');
            updateHelpText('s3_bucket', 'R2 存储桶名称');
            updateHelpText('s3_region', '设置为 auto 或留空');
            updateHelpText('s3_endpoint', '格式：https://{account-id}.r2.cloudflarestorage.com');
            updateHelpText('s3_cdn', '必填：R2 Bucket 的公共访问域名，可在 R2 控制台查看或使用自定义域名');
            showHelp('r2Help');
            break;

        case 'qcloud':
            currentStorageType = 'qcloud';
            if (s3RegionInput) s3RegionInput.value = 'ap-beijing';
            if (s3EndpointInput) s3EndpointInput.value = 'https://cos.ap-beijing.myqcloud.com';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = true;
            updateHelpText('s3_key', '在访问管理 → API 密钥管理中创建');
            updateHelpText('s3_secret', '与 SecretId 对应的 SecretKey');
            updateHelpText('s3_bucket', 'COS 存储桶名称');
            updateHelpText('s3_region', '如 ap-beijing、ap-shanghai、ap-guangzhou 等');
            updateHelpText('s3_endpoint', '格式：https://cos.{region}.myqcloud.com');
            updateHelpText('s3_cdn', '必填：COS Bucket 的访问域名，格式：https://{bucket}.cos.{region}.myqcloud.com');
            showHelp('qcloudCosHelp');
            break;

        case 'qiniu':
            currentStorageType = 'qiniu';
            if (s3RegionInput) s3RegionInput.value = 'cn-east-1';
            if (s3EndpointInput) s3EndpointInput.value = 'https://s3-cn-east-1.qiniucs.com';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = true;
            updateHelpText('s3_key', '在密钥管理 → AccessKey 中创建');
            updateHelpText('s3_secret', '与 AccessKey 对应的 SecretKey');
            updateHelpText('s3_bucket', '七牛云存储空间名称');
            updateHelpText('s3_region', '如 cn-east-1、cn-north-1、cn-south-1 等');
            updateHelpText('s3_endpoint', '格式：https://s3-{region}.qiniucs.com');
            updateHelpText('s3_cdn', '必填：七牛云存储空间的访问域名（测试域名或自定义域名）');
            showHelp('qiniuHelp');
            break;

        case 'minio':
            currentStorageType = 'minio';
            if (s3RegionInput) s3RegionInput.value = 'us-east-1';
            if (s3EndpointInput) s3EndpointInput.value = 'http://localhost:9000';
            if (s3PathStyleCheckbox) s3PathStyleCheckbox.checked = true;
            updateHelpText('s3_key', 'MinIO 访问密钥');
            updateHelpText('s3_secret', 'MinIO 密钥');
            updateHelpText('s3_bucket', 'MinIO 存储桶名称');
            updateHelpText('s3_region', '通常使用 us-east-1');
            updateHelpText('s3_endpoint', 'MinIO 服务地址，如 http://localhost:9000');
            updateHelpText('s3_cdn', '必填：MinIO 的公网访问地址（如果使用域名，填写域名，否则填写 Endpoint）');
            break;
    }

    // 聚焦到第一个输入框
    if (s3KeyInput) {
        s3KeyInput.focus();
    }
}

// 监听 Region 字段变化，自动更新 Endpoint
document.addEventListener('DOMContentLoaded', function() {
    const s3RegionInput = document.querySelector('[name="s3_region"]');
    const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
    
    if (s3RegionInput && s3EndpointInput) {
        s3RegionInput.addEventListener('input', function() {
            const region = this.value.trim();
            
            // 阿里云 OSS：自动更新 Endpoint
            if (currentStorageType === 'aliyun' && region && /^cn-[a-z]+$/.test(region)) {
                s3EndpointInput.value = `https://oss-${region}.aliyuncs.com`;
            }
            // 腾讯云 COS：自动更新 Endpoint
            else if (currentStorageType === 'qcloud' && region && /^ap-[a-z]+$/.test(region)) {
                s3EndpointInput.value = `https://cos.${region}.myqcloud.com`;
            }
            // 七牛云：自动更新 Endpoint
            else if (currentStorageType === 'qiniu' && region && /^(cn|us|eu|ap|na|sa)-[a-z]+-\d+$/.test(region)) {
                s3EndpointInput.value = `https://s3-${region}.qiniucs.com`;
            }
        });
    }
});

function submitForm(event) {
    event.preventDefault();

    const form = document.getElementById('editForm');
    const submitBtn = document.getElementById('submitBtn');
    
    // 处理 required 属性：如果自定义上传未启用，移除 S3 相关字段的 required
    const useCustomUpload = document.getElementById('useCustomUpload');
    const s3ConfigArea = document.getElementById('s3ConfigArea');
    
    if (!useCustomUpload.checked && s3ConfigArea) {
        // 自定义上传未启用，移除所有 S3 相关字段的 required 属性
        const s3Fields = ['s3_key', 's3_secret', 's3_bucket', 's3_region', 's3_endpoint', 's3_cdn'];
        s3Fields.forEach(fieldName => {
            const field = form.querySelector(`[name="${fieldName}"]`);
            if (field) {
                field.removeAttribute('required');
            }
        });
    }
    
    const formData = new FormData(form);
    const id = formData.get('id');

    // 统一使用 JSON 提交
    submitFormAsJson(form, formData, submitBtn, id);
}

function submitFormAsJson(form, formData, submitBtn, id) {
    // 禁用提交按钮
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>保存中...';

    // 转换为 JSON 对象
    const jsonData = {};
    for (const [key, value] of formData.entries()) {
        // 跳过 _method 和 _token
        if (key === '_method' || key === '_token') {
            continue;
        }

        // 跳过保护字段：domain 和 admin_entry_path（这些字段是 disabled，不应提交）
        if (key === 'domain' || key === 'admin_entry_path') {
            continue;
        }

        jsonData[key] = value;
    }

    // 处理上传配置
    const useCustomUpload = document.getElementById('useCustomUpload').checked;
    
    if (useCustomUpload) {
        // 使用自定义上传配置，收集 S3 配置
        const s3Key = document.querySelector('[name="s3_key"]')?.value || '';
        const s3Secret = document.querySelector('[name="s3_secret"]')?.value || '';
        const s3Bucket = document.querySelector('[name="s3_bucket"]')?.value || '';
        const s3Region = document.querySelector('[name="s3_region"]')?.value || '';
        const s3Endpoint = document.querySelector('[name="s3_endpoint"]')?.value || '';
        const s3Cdn = document.querySelector('[name="s3_cdn"]')?.value?.trim() || '';
        const s3PathStyle = document.getElementById('s3_path_style')?.checked || false;

        // 验证必填字段
        if (!s3Cdn) {
            showFeedbackToast('danger', '请填写 CDN 域名，这是必填项，用于访问图片');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '保存';
            const cdnInput = document.querySelector('[name="s3_cdn"]');
            if (cdnInput) {
                cdnInput.focus();
            }
            return;
        }

        // 如果 Secret 为空，则从现有配置中获取（不更新密码）
        let finalSecret = s3Secret;
        if (!finalSecret && jsonData['existing_s3_secret']) {
            finalSecret = jsonData['existing_s3_secret'];
        }

        // 构建 upload_config
        const uploadConfig = {
            s3: {
                key: s3Key,
                secret: finalSecret,
                bucket: s3Bucket,
                region: s3Region,
                endpoint: s3Endpoint || null,
                cdn: s3Cdn,
                use_path_style_endpoint: s3PathStyle,
            }
        };

        jsonData['upload_driver'] = 's3';
        jsonData['upload_config'] = uploadConfig;
    } else {
        // 不使用自定义配置，设为 null（使用系统默认）
        jsonData['upload_driver'] = null;
        jsonData['upload_config'] = null;
    }

    // 移除临时字段（不在表单中提交）
    delete jsonData['s3_key'];
    delete jsonData['s3_secret'];
    delete jsonData['s3_bucket'];
    delete jsonData['s3_region'];
    delete jsonData['s3_endpoint'];
    delete jsonData['s3_cdn'];
    delete jsonData['existing_s3_secret'];

    // 发送 PUT 请求
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
            // 只触发表单提交成功事件，让 fixed-bottom-actions 组件统一处理成功逻辑
            // 这样可以避免重复提示
            const message = data.msg || '保存成功';
            
            const successEvent = new CustomEvent('submit-success', {
                bubbles: true,
                cancelable: true,
                detail: {
                    message: message,
                    redirect: null, // 不重定向，停留在当前页面
                }
            });
            form.dispatchEvent(successEvent);
            
            // 恢复按钮状态
            submitBtn.disabled = false;
            submitBtn.innerHTML = '保存';
        } else {
            showFeedbackToast('danger', data.msg || data.message || '保存失败');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '保存';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showFeedbackToast('danger', '保存失败：' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = '保存';
    });
}
</script>
@endsection

