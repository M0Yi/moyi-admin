@extends('admin.layouts.admin')

@section('title', '文件预览')

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
    <div class="mb-3 d-flex align-items-center justify-content-between">
        <div>
            <h6 class="mb-1 fw-bold">文件预览</h6>
            <small class="text-muted">{{ $file->original_filename }}</small>
        </div>
        <div class="d-flex gap-2">
            @if($file->file_url)
            <a href="{{ $file->file_url }}" class="btn btn-primary btn-sm" download="{{ $file->original_filename }}">
                <i class="bi bi-download me-1"></i>下载
            </a>
            @endif
            <a href="{{ admin_route('system/upload-files') }}/{{ $file->id }}" 
               class="btn btn-secondary btn-sm"
               data-iframe-shell-trigger="file-detail-from-preview-{{ $file->id }}"
               data-iframe-shell-src="{{ admin_route('system/upload-files') }}/{{ $file->id }}"
               data-iframe-shell-title="文件详情 - {{ $file->original_filename }}"
               data-iframe-shell-channel="upload-files">
                <i class="bi bi-info-circle me-1"></i>详情
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body text-center">
            @if($file->file_url)
                @php
                    $contentType = $file->content_type ?? '';
                    $isImage = str_starts_with($contentType, 'image/');
                    $isVideo = str_starts_with($contentType, 'video/');
                    $isAudio = str_starts_with($contentType, 'audio/');
                    $isPdf = str_contains($contentType, 'pdf');
                @endphp

                @if($isImage)
                    <img src="{{ $file->file_url }}" alt="{{ $file->original_filename }}" 
                         class="img-fluid" style="max-height: 70vh;">
                @elseif($isVideo)
                    <video controls class="w-100" style="max-height: 70vh;">
                        <source src="{{ $file->file_url }}" type="{{ $contentType }}">
                        您的浏览器不支持视频播放。
                    </video>
                @elseif($isAudio)
                    <audio controls class="w-100">
                        <source src="{{ $file->file_url }}" type="{{ $contentType }}">
                        您的浏览器不支持音频播放。
                    </audio>
                @elseif($isPdf)
                    <iframe src="{{ $file->file_url }}" class="w-100" style="height: 70vh; border: none;"></iframe>
                @else
                    <div class="py-5">
                        <i class="bi bi-file-earmark fs-1 text-muted"></i>
                        <p class="mt-3 text-muted">不支持在线预览此文件类型</p>
                        <p class="text-muted small">文件名：{{ $file->original_filename }}</p>
                        <a href="{{ $file->file_url }}" class="btn btn-primary mt-3" download="{{ $file->original_filename }}">
                            <i class="bi bi-download me-1"></i>下载文件
                        </a>
                    </div>
                @endif
            @else
                <div class="py-5">
                    <i class="bi bi-exclamation-circle fs-1 text-muted"></i>
                    <p class="mt-3 text-muted">文件URL不存在</p>
                </div>
            @endif
        </div>
    </div>

    {{-- 审核功能（只有待审核状态才显示） --}}
    @if($file->check_status == 0)
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-warning bg-opacity-10">
            <h6 class="mb-0">
                <i class="bi bi-exclamation-triangle me-2"></i>待审核
            </h6>
        </div>
        <div class="card-body">
            <form id="checkForm" method="POST" action="{{ admin_route('system/upload-files') }}/{{ $file->id }}/check">
                <input type="hidden" name="_token" value="{{ $csrfToken ?? '' }}">
                <input type="hidden" name="_iframe" value="1">
                <input type="hidden" name="check_status" id="checkStatus" value="1">
                
                <div class="mb-3">
                    <label class="form-label fw-semibold">审核结果 <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="check_status_radio" id="checkPassed" value="1" 
                               checked onchange="document.getElementById('checkStatus').value = '1';">
                        <label class="btn btn-outline-success" for="checkPassed">
                            <i class="bi bi-check-circle me-1"></i>审核通过
                        </label>
                        
                        <input type="radio" class="btn-check" name="check_status_radio" id="checkViolation" value="2"
                               onchange="document.getElementById('checkStatus').value = '2';">
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

    @include('admin.components.fixed-bottom-actions', [
        'formId' => 'checkForm',
        'submitText' => '提交审核',
        'cancelText' => '取消',
        'infoText' => '选择审核结果并填写审核意见后提交',
    ])
    @endif
</div>
@endsection

@push('admin_scripts')
@if($file->check_status == 0)
<script>
document.addEventListener('DOMContentLoaded', function () {
    // 表单提交处理
    const form = document.getElementById('checkForm');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            
            const checkStatusValue = document.getElementById('checkStatus').value;
            const checkResult = document.getElementById('checkResult').value.trim();
            
            // 验证
            if (!checkStatusValue || checkStatusValue === '0') {
                alert('请选择审核结果');
                return false;
            }
            
            if (!checkResult) {
                alert('请填写审核意见');
                return false;
            }
            
            // 获取提交按钮并更新状态
            const submitBtn = document.querySelector('[data-role="fixed-action-submit"]');
            const originalHtml = submitBtn ? submitBtn.innerHTML : '';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>提交中...';
            }
            
            // 发送 AJAX 请求
            const formData = new FormData(form);
            const data = {
                check_status: parseInt(checkStatusValue),
                check_result: checkResult,
                _iframe: '1'
            };
            
            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.code === 200) {
                    const message = result.msg || result.message || '审核成功';
                    
                    // 触发表单提交成功事件（由 fixed-bottom-actions 组件处理）
                    const successEvent = new CustomEvent('submit-success', {
                        bubbles: true,
                        cancelable: true,
                        detail: {
                            message: message,
                            data: result.data || {}
                        }
                    });
                    form.dispatchEvent(successEvent);
                } else {
                    // 失败，恢复按钮状态
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalHtml;
                    }
                    
                    const errorMsg = result.msg || result.message || '审核失败';
                    if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                        window.Admin.utils.showToast('danger', errorMsg);
                    } else {
                        alert(errorMsg);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                
                // 恢复按钮状态
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalHtml;
                }
                
                const errorMsg = '审核失败：' + error.message;
                if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                    window.Admin.utils.showToast('danger', errorMsg);
                } else {
                    alert(errorMsg);
                }
            });
        });
    }
});
</script>
@endif
@endpush

