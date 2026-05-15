@extends('admin.layouts.admin')

@section('title', '文件审核')

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
        <h6 class="mb-1 fw-bold">文件审核</h6>
        <small class="text-muted">审核文件：{{ $file->original_filename }}</small>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">文件信息</label>
                        <div class="fs-6">{{ $file->original_filename }}</div>
                        <small class="text-muted">{{ $file->content_type }} · {{ number_format($file->file_size / 1024, 2) }} KB</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-muted small">当前审核状态</label>
                        <div>
                            @php
                                $checkStatusText = match($file->check_status) {
                                    0 => '待审核',
                                    1 => '通过',
                                    2 => '违规',
                                    default => '未知'
                                };
                                $checkStatusBadge = match($file->check_status) {
                                    0 => 'warning',
                                    1 => 'success',
                                    2 => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-{{ $checkStatusBadge }}">{{ $checkStatusText }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form id="checkForm" method="POST" action="{{ admin_route('system/upload-files') }}/{{ $file->id }}/check">
                <input type="hidden" name="_token" value="{{ $csrfToken ?? '' }}">
                <input type="hidden" name="_iframe" value="1">
                <input type="hidden" name="check_status" id="checkStatus" value="{{ $checkStatus }}">
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">审核结果 <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="check_status_radio" id="checkPassed" value="1" 
                               {{ $checkStatus == 1 ? 'checked' : '' }} onchange="document.getElementById('checkStatus').value = '1';">
                        <label class="btn btn-outline-success" for="checkPassed">
                            <i class="bi bi-check-circle me-1"></i>审核通过
                        </label>
                        
                        <input type="radio" class="btn-check" name="check_status_radio" id="checkViolation" value="2"
                               {{ $checkStatus == 2 ? 'checked' : '' }} onchange="document.getElementById('checkStatus').value = '2';">
                        <label class="btn btn-outline-danger" for="checkViolation">
                            <i class="bi bi-x-circle me-1"></i>审核违规
                        </label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="checkResult" class="form-label fw-semibold">审核意见 <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="checkResult" name="check_result" rows="4" 
                              placeholder="请输入审核意见" required>{{ $file->check_result ?? '' }}</textarea>
                    <small class="form-text text-muted">审核违规时，此意见将作为违规原因</small>
                </div>
            </form>
        </div>
    </div>
</div>

@include('admin.components.fixed-bottom-actions', [
    'formId' => 'checkForm',
    'submitText' => '提交审核',
    'cancelText' => '取消',
    'infoText' => '选择审核结果并填写审核意见后提交',
])
@endsection

@push('admin_scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 初始化审核状态
    const checkStatus = {{ $checkStatus }};
    if (checkStatus > 0) {
        document.getElementById('checkStatus').value = checkStatus;
    }
    
    // 表单提交处理
    const form = document.getElementById('checkForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            const checkStatusValue = document.getElementById('checkStatus').value;
            const checkResult = document.getElementById('checkResult').value.trim();
            
            if (!checkStatusValue || checkStatusValue === '0') {
                e.preventDefault();
                alert('请选择审核结果');
                return false;
            }
            
            if (!checkResult) {
                e.preventDefault();
                alert('请填写审核意见');
                return false;
            }
        });
    }
});
</script>
@endpush

