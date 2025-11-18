{{--
多图上传组件

参数:
- $field: 字段配置数组
  - name: 字段名
  - label: 标签文本
  - required: 是否必填
  - default: 默认值（当前图片URL数组或JSON字符串）
- $value: 当前值（可选，用于编辑页面）
--}}
@php
    // 处理当前图片数组
    $currentImages = [];
    $imageValue = $value ?? ($field['default'] ?? '');
    if (!empty($imageValue)) {
        if (is_string($imageValue)) {
            $decoded = json_decode($imageValue, true);
            $currentImages = json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [$imageValue];
        } elseif (is_array($imageValue)) {
            $currentImages = $imageValue;
        }
    }
    $fieldId = $field['name'];
    $hasImages = !empty($currentImages);
@endphp
<div class="modern-images-upload" data-field-id="{{ $fieldId }}">
    <input
        type="file"
        class="modern-images-input"
        id="{{ $fieldId }}"
        accept="image/*"
        multiple
        @if($field['required'] ?? false) required @endif
    >
    {{-- 隐藏字段，存储上传后的图片URL数组（JSON格式） --}}
    <input type="hidden" name="{{ $fieldId }}" id="{{ $fieldId }}_urls" value="{{ $hasImages ? json_encode($currentImages) : '' }}">
    
    <div class="modern-images-list" id="{{ $fieldId }}_list">
        @if($hasImages)
        @foreach($currentImages as $index => $img)
        <div class="modern-images-item" data-image-url="{{ $img }}" draggable="true">
            <div class="modern-images-item-preview">
                <img src="{{ $img }}" alt="图片 {{ $index + 1 }}">
                <div class="modern-images-item-overlay">
                    <button type="button" class="modern-images-item-delete" onclick="removeImageItem('{{ $fieldId }}', '{{ $img }}')" title="删除">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <button type="button" class="modern-images-item-view" onclick="viewImage('{{ $img }}')" title="查看大图">
                        <i class="bi bi-zoom-in"></i>
                    </button>
                </div>
            </div>
            <div class="modern-images-item-number">{{ $index + 1 }}</div>
            <button type="button" class="modern-images-item-handle" title="拖拽排序">
                <i class="bi bi-grip-vertical"></i>
            </button>
        </div>
        @endforeach
        @endif
        
        {{-- 始终显示的上传按钮 --}}
        <div class="modern-images-item modern-images-upload-btn" id="{{ $fieldId }}_upload_area">
            <div class="modern-images-empty">
                <div class="modern-images-icon">
                    <i class="bi bi-plus-lg"></i>
                </div>
                <p class="modern-images-text">添加图片</p>
                <p class="modern-images-hint">点击或拖拽</p>
            </div>
        </div>
    </div>
</div>

<style>
.modern-images-upload {
    position: relative;
}

.modern-images-input {
    position: absolute;
    width: 0;
    height: 0;
    opacity: 0;
    overflow: hidden;
    z-index: -1;
}


.modern-images-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    text-align: center;
    width: 100%;
    height: 100%;
}

.modern-images-upload-btn .modern-images-empty {
    padding: 1.5rem 1rem;
}

.modern-images-icon {
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

.modern-images-upload-btn .modern-images-icon {
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

.modern-images-text {
    font-size: 0.875rem;
    font-weight: 500;
    color: var(--dark-color);
    margin: 0 0 0.125rem 0;
}

.modern-images-hint {
    font-size: 0.75rem;
    color: #6b7280;
    margin: 0;
}

.modern-images-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 1rem;
}

.modern-images-item {
    position: relative;
    border-radius: var(--border-radius);
    overflow: hidden;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: var(--transition);
    aspect-ratio: 1;
    cursor: move;
}

.modern-images-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.modern-images-item.dragging {
    opacity: 0.5;
    transform: scale(0.95);
}

.modern-images-item.drag-over {
    border: 2px solid var(--primary-color);
    transform: scale(1.05);
}

.modern-images-upload-btn {
    border: 2px dashed var(--border-color);
    background: #fafafa;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modern-images-upload-btn:hover {
    border-color: var(--primary-color);
    background: #f5f5f5;
}

.modern-images-upload-btn.drag-over {
    border-color: var(--primary-color);
    background: rgba(102, 126, 234, 0.05);
    transform: scale(1.05);
}

.modern-images-item-preview {
    position: relative;
    width: 100%;
    height: 100%;
    overflow: hidden;
    background: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modern-images-item-preview img {
    max-width: 100%;
    max-height: 100%;
    width: auto;
    height: auto;
    object-fit: contain;
    display: block;
}

.modern-images-item-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.modern-images-item:hover .modern-images-item-overlay {
    opacity: 1;
}

.modern-images-item-handle {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    width: 24px;
    height: 24px;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    border: none;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
    opacity: 0;
    transition: opacity 0.3s ease;
    font-size: 0.75rem;
    z-index: 10;
    pointer-events: auto;
}

.modern-images-item:hover .modern-images-item-handle {
    opacity: 1;
}

.modern-images-item-handle:active {
    cursor: grabbing;
}

.modern-images-item-delete,
.modern-images-item-view {
    pointer-events: auto;
}

.modern-images-item-delete,
.modern-images-item-view {
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

.modern-images-item-view {
    color: var(--primary-color);
}

.modern-images-item-delete:hover {
    background: var(--danger-color);
    color: white;
    transform: scale(1.1);
}

.modern-images-item-view:hover {
    background: var(--primary-color);
    color: white;
    transform: scale(1.1);
}

.modern-images-item-number {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background: rgba(0, 0, 0, 0.6);
    color: white;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
    z-index: 10;
}

.modern-images-loading {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    color: var(--primary-color);
}

.modern-images-loading .spinner-border {
    width: 1.5rem;
    height: 1.5rem;
    border-width: 0.15em;
}

/* 响应式 */
@media (max-width: 768px) {
    .modern-images-upload-area {
        min-height: 100px;
    }
    
    .modern-images-empty {
        padding: 1.5rem 1rem;
    }
    
    .modern-images-icon {
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
    
    .modern-images-list {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: 0.75rem;
    }
}
</style>

<script>
(function() {
    const fieldId = '{{ $fieldId }}';
    const input = document.getElementById(fieldId);
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    const imagesList = document.getElementById(fieldId + '_list');
    
    if (!input || !uploadArea || !imagesList) return;
    
    // 点击上传区域触发文件选择
    uploadArea.addEventListener('click', function() {
        input.click();
    });
    
    // 文件选择事件
    input.addEventListener('change', function(e) {
        const files = Array.from(e.target.files);
        if (files.length > 0) {
            handleFilesSelect(files, fieldId);
            // 清空input，允许重复选择相同文件
            input.value = '';
        }
    });
    
    // 文件拖拽上传事件
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
        
        const files = Array.from(e.dataTransfer.files).filter(file => file.type.startsWith('image/'));
        if (files.length > 0) {
            handleFilesSelect(files, fieldId);
        }
    });
    
    // 初始化拖拽排序
    initDragSort(fieldId);
})();

async function handleFilesSelect(files, fieldId) {
    const validFiles = files.filter(file => {
        if (!file.type.startsWith('image/')) {
            showToast('danger', `文件 ${file.name} 不是图片格式`);
            return false;
        }
        if (file.size > 10 * 1024 * 1024) {
            showToast('danger', `图片 ${file.name} 大小超过 10MB`);
            return false;
        }
        return true;
    });
    
    if (validFiles.length === 0) return;
    
    const imagesList = document.getElementById(fieldId + '_list');
    const uploadArea = document.getElementById(fieldId + '_upload_area');
    
    // 添加加载状态（插入到上传按钮之前）
    const loadingItem = document.createElement('div');
    loadingItem.className = 'modern-images-item modern-images-loading';
    loadingItem.innerHTML = '<div class="spinner-border" role="status"></div>';
    imagesList.insertBefore(loadingItem, uploadArea);
    
    const uploadedUrls = [];
    let completedCount = 0;
    let failedCount = 0;
    
    // 逐个上传文件
    for (const file of validFiles) {
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
            uploadedUrls.push(finalUrl);
            
            // 4. 创建图片项（插入到上传按钮之前）
            const item = createImageItem(fieldId, finalUrl);
            imagesList.insertBefore(item, uploadArea);
            
            completedCount++;
            
        } catch (error) {
            console.error('Upload error:', error);
            failedCount++;
            showToast('danger', `文件 ${file.name} 上传失败: ${error.message || '未知错误'}`);
        }
    }
    
    // 移除加载项
    if (loadingItem.parentNode) {
        loadingItem.remove();
    }
    
    // 更新隐藏字段
    if (uploadedUrls.length > 0) {
        updateCurrentImagesField(fieldId);
        // 更新编号
        updateImageNumbers(fieldId);
        // 重新初始化拖拽排序
        initDragSort(fieldId);
        showToast('success', `成功上传 ${completedCount} 张图片${failedCount > 0 ? `，失败 ${failedCount} 张` : ''}`);
    } else if (failedCount > 0) {
        showToast('danger', '所有图片上传失败');
    }
}

function createImageItem(fieldId, imageUrl) {
    const item = document.createElement('div');
    item.className = 'modern-images-item';
    item.setAttribute('data-image-url', imageUrl);
    item.setAttribute('draggable', 'true');
    item.innerHTML = `
        <div class="modern-images-item-preview">
            <img src="${imageUrl}" alt="图片">
            <div class="modern-images-item-overlay">
                <button type="button" class="modern-images-item-delete" onclick="removeImageItem('${fieldId}', '${imageUrl}')" title="删除">
                    <i class="bi bi-x-lg"></i>
                </button>
                <button type="button" class="modern-images-item-view" onclick="viewImage('${imageUrl}')" title="查看大图">
                    <i class="bi bi-zoom-in"></i>
                </button>
            </div>
        </div>
        <div class="modern-images-item-number"></div>
        <button type="button" class="modern-images-item-handle" title="拖拽排序">
            <i class="bi bi-grip-vertical"></i>
        </button>
    `;
    return item;
}

function removeImageItem(fieldId, imageUrl) {
    const imagesList = document.getElementById(fieldId + '_list');
    const item = imagesList.querySelector(`[data-image-url="${imageUrl}"]`);
    
    if (item) {
        item.style.transition = 'all 0.3s ease';
        item.style.opacity = '0';
        item.style.transform = 'scale(0.8)';
        
        setTimeout(() => {
            item.remove();
            
            // 更新编号
            updateImageNumbers(fieldId);
            
            // 更新当前图片的隐藏字段
            updateCurrentImagesField(fieldId);
        }, 300);
    }
}

function updateImageNumbers(fieldId) {
    const imagesList = document.getElementById(fieldId + '_list');
    // 排除上传按钮和加载项
    const items = Array.from(imagesList.querySelectorAll('.modern-images-item'))
        .filter(item => !item.classList.contains('modern-images-upload-btn') && !item.classList.contains('modern-images-loading'));
    items.forEach((item, index) => {
        const numberEl = item.querySelector('.modern-images-item-number');
        if (numberEl) {
            numberEl.textContent = index + 1;
        }
    });
}

function updateCurrentImagesField(fieldId) {
    const imagesList = document.getElementById(fieldId + '_list');
    // 排除上传按钮
    const items = Array.from(imagesList.querySelectorAll('.modern-images-item'))
        .filter(item => !item.classList.contains('modern-images-upload-btn') && !item.classList.contains('modern-images-loading'));
    const imageUrls = items.map(item => item.getAttribute('data-image-url')).filter(url => url);
    
    const urlsInput = document.getElementById(fieldId + '_urls');
    
    if (imageUrls.length > 0) {
        if (!urlsInput) {
            const newInput = document.createElement('input');
            newInput.type = 'hidden';
            newInput.name = fieldId;
            newInput.id = fieldId + '_urls';
            document.querySelector(`[data-field-id="${fieldId}"]`).appendChild(newInput);
            newInput.value = JSON.stringify(imageUrls);
        } else {
            urlsInput.value = JSON.stringify(imageUrls);
        }
    } else {
        if (urlsInput) {
            urlsInput.value = '';
        }
    }
}

function initDragSort(fieldId) {
    const imagesList = document.getElementById(fieldId + '_list');
    const items = imagesList.querySelectorAll('.modern-images-item:not(.modern-images-upload-btn):not(.modern-images-loading)');
    
    items.forEach(item => {
        // 如果已经有拖拽属性，跳过（避免重复绑定）
        if (item.getAttribute('draggable') === 'true' && item.hasAttribute('data-drag-initialized')) {
            return;
        }
        
        // 设置拖拽属性
        item.setAttribute('draggable', 'true');
        item.setAttribute('data-drag-initialized', 'true');
        
        // 拖拽开始
        item.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', this.getAttribute('data-image-url'));
        });
        
        // 拖拽结束
        item.addEventListener('dragend', function(e) {
            this.classList.remove('dragging');
            // 移除所有 drag-over 类
            const allItems = imagesList.querySelectorAll('.modern-images-item:not(.modern-images-upload-btn):not(.modern-images-loading)');
            allItems.forEach(item => item.classList.remove('drag-over'));
        });
        
        // 拖拽经过
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            
            const draggingItem = imagesList.querySelector('.dragging');
            if (draggingItem && this !== draggingItem && !this.classList.contains('modern-images-upload-btn') && !this.classList.contains('modern-images-loading')) {
                this.classList.add('drag-over');
                
                const afterElement = getDragAfterElement(imagesList, e.clientY);
                const uploadBtn = imagesList.querySelector('.modern-images-upload-btn');
                
                if (afterElement == null) {
                    imagesList.insertBefore(draggingItem, uploadBtn);
                } else {
                    imagesList.insertBefore(draggingItem, afterElement);
                }
            }
        });
        
        // 拖拽离开
        item.addEventListener('dragleave', function(e) {
            this.classList.remove('drag-over');
        });
        
        // 放置
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.classList.remove('drag-over');
            
            // 更新编号和隐藏字段
            updateImageNumbers(fieldId);
            updateCurrentImagesField(fieldId);
            
            return false;
        });
    });
}

function getDragAfterElement(container, y) {
    const draggableElements = [...container.querySelectorAll('.modern-images-item:not(.dragging):not(.modern-images-upload-btn):not(.modern-images-loading)')];
    
    return draggableElements.reduce((closest, child) => {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;
        
        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        } else {
            return closest;
        }
    }, { offset: Number.NEGATIVE_INFINITY }).element;
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

