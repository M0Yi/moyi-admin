{{-- 删除确认模态框 --}}
@if($showDeleteModal)
    <div class="modal fade" id="{{ $deleteModalId }}" tabindex="-1" aria-labelledby="{{ $deleteModalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $deleteModalId }}Label">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        {{ $deleteModalTitle }}
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i>
                        <strong>{{ $deleteWarningMessage }}</strong>
                    </div>
                    <p class="mb-0">{{ $deleteConfirmMessage }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>
                        {{ $deleteCancelButtonText }}
                    </button>
                    <button type="button" class="btn btn-danger" id="{{ $deleteModalId }}ConfirmBtn">
                        <i class="bi bi-trash me-1"></i>
                        {{ $deleteConfirmButtonText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

@if($showBatchDeleteModal)
{{-- 批量删除确认模态框 --}}
<div class="modal fade" id="{{ $batchDeleteModalId }}" tabindex="-1" aria-labelledby="{{ $batchDeleteModalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="{{ $batchDeleteModalId }}Label">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    {{ $batchDeleteModalTitle }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="关闭"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <strong>{{ $batchDeleteWarningMessage }}</strong>
                </div>
                <p class="mb-0" id="{{ $batchDeleteModalId }}Message">{{ $batchDeleteConfirmMessage }}</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>
                    {{ $batchDeleteCancelButtonText }}
                </button>
                <button type="button" class="btn btn-danger" id="{{ $batchDeleteModalId }}ConfirmBtn">
                    <i class="bi bi-trash me-1"></i>
                    {{ $batchDeleteConfirmButtonText }}
                </button>
            </div>
        </div>
    </div>
</div>
@endif

