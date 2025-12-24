@extends('admin.layouts.admin')

@section('title', '文件详情')

@if (! ($isEmbedded ?? false))
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
        <h6 class="mb-1 fw-bold">文件详情</h6>
        <small class="text-muted">查看文件的详细信息</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">ID</label>
                        <div class="fs-6">{{ $file->id }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">原始文件名</label>
                        <div class="fs-6">{{ $file->original_filename }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">文件名</label>
                        <div class="fs-6">{{ $file->filename }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">文件类型</label>
                        <div class="fs-6">{{ $file->content_type }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">文件大小</label>
                        <div class="fs-6">{{ $fileData['file_size_formatted'] }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">存储驱动</label>
                        <div class="fs-6">{{ $file->storage_driver }}</div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">状态</label>
                        <div>
                            <span class="badge bg-{{ $file->status == 0 ? 'secondary' : ($file->status == 1 ? 'success' : ($file->status == 2 ? 'danger' : 'dark')) }}">
                                {{ $fileData['status_text'] }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">审核状态</label>
                        <div>
                            <span class="badge bg-{{ $file->check_status == 0 ? 'warning' : ($file->check_status == 1 ? 'success' : 'danger') }}">
                                {{ $fileData['check_status_text'] }}
                            </span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">上传用户</label>
                        <div class="fs-6">{{ $file->username }}</div>
                        @if($file->user && $file->user->real_name)
                            <small class="text-muted">（{{ $file->user->real_name }}）</small>
                        @endif
                    </div>
                    @if($file->site)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">所属站点</label>
                        <div class="fs-6">{{ $file->site->name }}</div>
                    </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">创建时间</label>
                        <div class="fs-6">{{ $file->created_at }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">更新时间</label>
                        <div class="fs-6">{{ $file->updated_at }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">上传时间</label>
                        <div class="fs-6">{{ $file->uploaded_at ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">审核时间</label>
                        <div class="fs-6">{{ $file->checked_at ?? '-' }}</div>
                    </div>
                </div>

                <div class="col-12 mb-3">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">IP地址</label>
                        <div class="fs-6">{{ $file->ip_address ?? '-' }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">User Agent</label>
                        <div class="fs-6 small text-break">{{ $file->user_agent ?? '-' }}</div>
                    </div>
                    @if($file->violation_reason)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">违规原因</label>
                        <div class="fs-6 text-danger">{{ $file->violation_reason }}</div>
                    </div>
                    @endif
                    @if($file->check_result)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">审核意见</label>
                        <div class="fs-6">{{ $file->check_result }}</div>
                    </div>
                    @endif
                    @if($file->file_url)
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">文件URL</label>
                        <div class="fs-6">
                            <a href="{{ $file->file_url }}" target="_blank" class="text-break">
                                {{ $file->file_url }}
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection






