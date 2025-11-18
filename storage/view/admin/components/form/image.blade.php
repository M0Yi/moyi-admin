{{--
单图上传组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - default: 默认值（当前图片URL）
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    $currentImage = $value ?? ($field['default'] ?? '');
    $fieldId = $field['name'];
    $hasImage = !empty($currentImage);
@endphp
<div class="modern-image-upload" data-field-id="{{ $fieldId }}">
    <input
        type="file"
        class="modern-image-input"
        id="{{ $fieldId }}"
        accept="image/*"
        @if($field['required'] ?? false) required @endif
    >
    {{-- 隐藏字段，存储上传后的图片URL --}}
    <input type="hidden" name="{{ $fieldId }}" id="{{ $fieldId }}_url" value="{{ $currentImage }}">
    
    <div class="modern-image-container">
        @if($hasImage)
        <div class="modern-image-item" id="{{ $fieldId }}_item">
            <div class="modern-image-preview">
                <img src="{{ $currentImage }}" alt="预览图片" class="modern-image-img">
                <div class="modern-image-overlay">
                    <button type="button" class="modern-image-delete" onclick="removeImage('{{ $fieldId }}')" title="删除图片">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="modern-image-view" onclick="viewImage('{{ $currentImage }}')" title="查看大图">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            </div>
        </div>
        @endif
        
        {{-- 上传按钮（始终显示） --}}
        <div class="modern-image-item modern-image-upload-btn" 
             id="{{ $fieldId }}_upload_area"
             data-field-id="{{ $fieldId }}">
            @if(!$hasImage)
            <div class="modern-image-empty">
                <div class="modern-image-icon">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <p class="modern-image-text">添加图片</p>
                <p class="modern-image-hint">点击或拖拽</p>
            </div>
            @else
            <div class="modern-image-empty">
                <div class="modern-image-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <p class="modern-image-text">替换图片</p>
                <p class="modern-image-hint">点击或拖拽</p>
            </div>
            @endif
        </div>
    </div>
</div>

<style>
.modern-image-upload {
    position: relative;
}

.modern-image-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}

.modern-image-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
}

.modern-image-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    text-align: center;
    width: 100%;
    height: 100%;
}

.modern-image-upload-btn .modern-image-empty {
    padding: 1.5rem 1rem;
}

.modern-image-icon {
    width: 48px;
    height: 48px;
    min-height: 48px;
    max-height: 48px;
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.5rem;
    color: white;
    font-size: 1.25rem;
    flex-shrink: 0;
    flex-basis: 48px;
    line-height: 1;
    box-sizing: border-box;
}

.modern-image-upload-btn .modern-image-icon {
    width: 40px;
    height: 40px;
    min-height: 40px;
    max-height: 40px;
    font-size: 1rem;
    margin-bottom: 0.25rem;
    border-radius: 50%;
    flex-shrink: 0;
    flex-basis: 40px;
    line-height: 1;
    box-sizing: border-box;
}

.modern-image-text {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark-color);
    margin: 0 0 0.125rem 0;
}

.modern-image-hint {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
}

.modern-image-item {
    position: relative;
    border-radius: var(--border-radius);
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
    aspect-ratio: 1;
}

.modern-image-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modern-image-upload-btn {
    border: 2px dashed var(--border-color);
    background: #fafafa;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modern-image-upload-btn:hover {
    border-color: var(--primary-color);
    background: #f5f5f5;
}

.modern-image-upload-btn.drag-over {
    border-color: var(--primary-color);
    background: rgba(102, 126, 234, 0.05);
    transform: scale(1.02);
}

.modern-image-preview {
    position: relative;
    width: 100%;
    height: 100%;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modern-image-img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    display: block;
}

.modern-image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-image-preview:hover .modern-image-overlay {
    opacity: 1;
}

.modern-image-delete,
.modern-image-view {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    color: var(--danger-color);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.875rem;
}

.modern-image-view {
    color: var(--primary-color);
}

.modern-image-delete:hover {
    background: var(--danger-color);
    color: white;
    transform: scale(1.1);
}

.modern-image-view:hover {
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.modern-image-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    color: var(--primary-color);
}

.modern-image-loading .spinner-border {
    width: 2rem;
    height: 2rem;
    border-width: 0.2em;
}

/* 响应式 */
@media (max-width: 768px) {
    .modern-image-container {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.75rem;
    }
    
    .modern-image-empty {
        padding: 1.5rem 1rem;
    }
    
    .modern-image-icon {
        width: 48px;
        height: 48px;
        min-height: 48px;
        max-height: 48px;
        font-size: 1.25rem;
        border-radius: 50%;
        flex-shrink: 0;
        flex-basis: 48px;
        line-height: 1;
        box-sizing: border-box;
    }
}
</style>

<script>
(function() {
    const fieldId = '{{ $fieldId }}';
    const input = document.getElementById(fieldId);
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    const container = uploadArea?.closest('.modern-image-container');
    
    if (!input || !uploadArea) return;
    
    // 命名函数，用于点击上传区域触发文件选择
    function handleUploadAreaClick(e) {
        if (!e.target.closest('.modern-image-delete') && !e.target.closest('.modern-image-view')) {
            input.click();
        }
    }
    
    // 点击上传区域触发文件选择
    uploadArea.addEventListener('click', handleUploadAreaClick);
    
    // 将事件处理器存储到元素上，以便后续移除
    uploadArea._uploadClickHandler = handleUploadAreaClick;
    
    // 文件选择事件
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            handleFileSelect(file, fieldId);
        }
    });
    
    // 拖拽事件
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.add('drag-over');
    });
    
    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('drag-over');
    });
    
    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.classList.remove('drag-over');
        
        const files = e.dataTransfer.files;
        if (files.length > 0 && files[0].type.startsWith('image/')) {
            input.files = files;
            handleFileSelect(files[0], fieldId);
        }
    });
})();

async function handleFileSelect(file, fieldId) {
    // 验证文件类型
    if (!file.type.startsWith('image/')) {
        showToast('danger', '请选择图片文件');
        return;
    }
    
    // 验证文件大小（10MB）
    if (file.size > 10 * 1024 * 1024) {
        showToast('danger', '图片大小不能超过 10MB');
        return;
    }
    
    const container = document.querySelector(`[data-field-id="${fieldId}"] .modern-image-container`);
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    
    if (!container || !uploadArea) return;
    
    // 显示加载状态
    uploadArea.innerHTML = '<div class="modern-image-loading"><div class="spinner-border" role="status"><span class="visually-hidden">上传中...</span></div></div>';
    
    try {
        // 1. 获取上传令牌
        const tokenResponse = await fetch('{{ admin_route("api/admin/upload/token") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                filename: file.name,
                content_type: file.type,
                file_size: file.size,
                sub_path: 'images'
            })
        });
        
        const tokenResult = await tokenResponse.json();
        
        if (tokenResult.code !== 200) {
            throw new Error(tokenResult.msg || '获取上传令牌失败');
        }
        
        const tokenData = tokenResult.data;
        
        // 2. 使用PUT方法上传文件到服务器
        const uploadResponse = await fetch(tokenData.url, {
            method: tokenData.method || 'PUT',
            headers: {
                ...tokenData.headers,
                'Content-Type': file.type,
            },
            body: file
        });
        
        if (!uploadResponse.ok) {
            throw new Error('文件上传失败');
        }
        
        // 3. 上传成功，获取最终URL
        const finalUrl = tokenData.final_url;
        
        // 4. 更新隐藏字段
        const urlInput = document.getElementById(fieldId + '_url');
        if (urlInput) {
            urlInput.value = finalUrl;
        }
        
        // 5. 显示预览图片
        updateImagePreview(fieldId, finalUrl);
        
        showToast('success', '图片上传成功');
        
    } catch (error) {
        console.error('Upload error:', error);
        showToast('danger', error.message || '图片上传失败，请稍后重试');
        
        // 恢复上传按钮
        const hasImage = document.getElementById(fieldId + '_item');
        uploadArea.innerHTML = `
            <div class="modern-image-empty">
                <div class="modern-image-icon">
                    <i class="bi ${hasImage ? 'bi-arrow-repeat' : 'bi-plus-lg'}"></i>
                </div>
                <p class="modern-image-text">${hasImage ? '替换图片' : '添加图片'}</p>
                <p class="modern-image-hint">点击或拖拽</p>
            </div>
        `;
    }
}

function updateImagePreview(fieldId, imageUrl) {
    const container = document.querySelector(`[data-field-id="${fieldId}"] .modern-image-container`);
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    
    if (!container || !uploadArea) return;
    
    // 检查是否已有图片项
    let existingItem = document.getElementById(fieldId + '_item');
    
    if (existingItem) {
        // 更新现有图片
        const preview = existingItem.querySelector('.modern-image-preview');
        if (preview) {
            preview.innerHTML = `
                <img src="${imageUrl}" alt="预览图片" class="modern-image-img">
                <div class="modern-image-overlay">
                    <button type="button" class="modern-image-delete" onclick="removeImage('${fieldId}')" title="删除图片">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="modern-image-view" onclick="viewImage('${imageUrl}')" title="查看大图">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            `;
        }
    } else {
        // 创建新的图片项
        existingItem = document.createElement('div');
        existingItem.className = 'modern-image-item';
        existingItem.id = fieldId + '_item';
        existingItem.innerHTML = `
            <div class="modern-image-preview">
                <img src="${imageUrl}" alt="预览图片" class="modern-image-img">
                <div class="modern-image-overlay">
                    <button type="button" class="modern-image-delete" onclick="removeImage('${fieldId}')" title="删除图片">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="modern-image-view" onclick="viewImage('${imageUrl}')" title="查看大图">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            </div>
        `;
        container.insertBefore(existingItem, uploadArea);
    }
    
    // 更新上传按钮显示"替换图片"
    uploadArea.innerHTML = `
        <div class="modern-image-empty">
            <div class="modern-image-icon">
                <i class="bi bi-arrow-repeat"></i>
            </div>
            <p class="modern-image-text">替换图片</p>
            <p class="modern-image-hint">点击或拖拽</p>
        </div>
    `;
    
    // 重新绑定点击事件
    const input = document.getElementById(fieldId);
    if (uploadArea._uploadClickHandler) {
        uploadArea.removeEventListener('click', uploadArea._uploadClickHandler);
    }
    
    function handleUploadClick(e) {
        if (!e.target.closest('.modern-image-delete') && !e.target.closest('.modern-image-view')) {
            input.click();
        }
    }
    
    uploadArea.addEventListener('click', handleUploadClick);
    uploadArea._uploadClickHandler = handleUploadClick;
}

function removeImage(fieldId) {
    const input = document.getElementById(fieldId);
    const container = document.querySelector(`[data-field-id="${fieldId}"] .modern-image-container`);
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    const item = document.getElementById(fieldId + '_item');
    const urlInput = document.getElementById(fieldId + '_url');
    
    if (!input || !uploadArea || !container) return;
    
    // 清空文件输入
    input.value = '';
    
    // 清空URL字段
    if (urlInput) {
        urlInput.value = '';
    }
    
    // 移除图片项
    if (item) {
        item.remove();
    }
    
    // 恢复上传按钮为空状态
    uploadArea.innerHTML = `
        <div class="modern-image-empty">
            <div class="modern-image-icon">
                <i class="bi bi-plus-lg"></i>
            </div>
            <p class="modern-image-text">添加图片</p>
            <p class="modern-image-hint">点击或拖拽</p>
        </div>
    `;
    
    // 重新绑定点击事件
    if (uploadArea._uploadClickHandler) {
        uploadArea.removeEventListener('click', uploadArea._uploadClickHandler);
    }
    
    function handleUploadClick(e) {
        input.click();
    }
    
    uploadArea.addEventListener('click', handleUploadClick);
    uploadArea._uploadClickHandler = handleUploadClick;
}

function viewImage(imageUrl) {
    // 创建模态框查看大图
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="background: transparent; border: none;">
                <div class="modal-header" style="border: none; justify-content: flex-end;">
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <img src="${imageUrl}" alt="预览" style="width: 100%; height: auto; border-radius: var(--border-radius-lg);">
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
    
    modal.addEventListener('hidden.bs.modal', function() {
        document.body.removeChild(modal);
    });
}
</script>

