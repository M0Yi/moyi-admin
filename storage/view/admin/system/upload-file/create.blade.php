@extends('admin.layouts.admin')

@section('title', '上传文件')

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
        <h6 class="mb-1 fw-bold">上传文件</h6>
        <small class="text-muted">支持拖拽上传，支持所有文件类型</small>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="fileInput" class="form-label">选择文件</label>
                    <div class="upload-area border border-2 border-dashed rounded p-5 text-center" 
                         id="uploadArea"
                         style="cursor: pointer; transition: all 0.3s;">
                        <input type="file" 
                               id="fileInput" 
                               class="d-none" 
                               multiple
                               onchange="handleFileSelect(event)">
                        <i class="bi bi-cloud-upload fs-1 text-muted mb-3 d-block"></i>
                        <p class="mb-2">点击选择文件或拖拽文件到此处</p>
                        <small class="text-muted">支持多文件上传</small>
                    </div>
                </div>

                <div id="fileList" class="mb-3"></div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="cancelUpload()">取消</button>
                    <button type="button" class="btn btn-primary" id="uploadBtn" onclick="startUpload()" disabled>
                        <i class="bi bi-upload me-1"></i>开始上传
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="uploadProgress" class="mt-3" style="display: none;">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h6 class="mb-3">上传进度</h6>
                <div id="progressList"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('admin_scripts')
<script>
let selectedFiles = [];
let uploadInProgress = false;

// 初始化拖拽上传
document.addEventListener('DOMContentLoaded', function() {
    const uploadArea = document.getElementById('uploadArea');
    const fileInput = document.getElementById('fileInput');

    // 点击上传区域触发文件选择
    uploadArea.addEventListener('click', function() {
        if (!uploadInProgress) {
            fileInput.click();
        }
    });

    // 拖拽事件
    uploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.style.borderColor = '#0d6efd';
        uploadArea.style.backgroundColor = '#f0f8ff';
    });

    uploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.style.borderColor = '';
        uploadArea.style.backgroundColor = '';
    });

    uploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        uploadArea.style.borderColor = '';
        uploadArea.style.backgroundColor = '';

        const files = Array.from(e.dataTransfer.files);
        addFiles(files);
    });
});

// 处理文件选择
function handleFileSelect(event) {
    const files = Array.from(event.target.files);
    addFiles(files);
}

// 添加文件到列表
function addFiles(files) {
    files.forEach(file => {
        // 检查是否已存在
        if (selectedFiles.find(f => f.name === file.name && f.size === file.size)) {
            return;
        }

        selectedFiles.push(file);
    });

    renderFileList();
    updateUploadButton();
}

// 渲染文件列表
function renderFileList() {
    const fileList = document.getElementById('fileList');
    
    if (selectedFiles.length === 0) {
        fileList.innerHTML = '';
        return;
    }

    let html = '<div class="list-group">';
    selectedFiles.forEach((file, index) => {
        const fileSize = formatFileSize(file.size);
        html += `
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div class="flex-grow-1">
                    <i class="bi bi-file-earmark me-2"></i>
                    <strong>${escapeHtml(file.name)}</strong>
                    <small class="text-muted ms-2">${fileSize}</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeFile(${index})">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
    });
    html += '</div>';

    fileList.innerHTML = html;
}

// 移除文件
function removeFile(index) {
    selectedFiles.splice(index, 1);
    renderFileList();
    updateUploadButton();
}

// 更新上传按钮状态
function updateUploadButton() {
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = selectedFiles.length === 0 || uploadInProgress;
}

// 取消上传
function cancelUpload() {
    if (uploadInProgress) {
        if (!confirm('上传正在进行中，确定要取消吗？')) {
            return;
        }
    }
    
    // 关闭 iframe（如果在 iframe 中）
    if (window.AdminIframeClient && window.AdminIframeClient.close) {
        window.AdminIframeClient.close();
    } else if (window.parent !== window) {
        // 如果在 iframe 中，通知父窗口关闭
        window.parent.postMessage({ type: 'closeIframe' }, '*');
    } else {
        // 否则返回上一页
        window.history.back();
    }
}

// 开始上传
async function startUpload() {
    if (selectedFiles.length === 0 || uploadInProgress) {
        return;
    }

    uploadInProgress = true;
    updateUploadButton();

    // 显示进度区域
    document.getElementById('uploadProgress').style.display = 'block';
    const progressList = document.getElementById('progressList');
    progressList.innerHTML = '';

    let successCount = 0;
    let failCount = 0;

    // 逐个上传文件
    for (let i = 0; i < selectedFiles.length; i++) {
        const file = selectedFiles[i];
        const progressId = `progress_${i}`;
        
        // 创建进度项
        progressList.innerHTML += `
            <div id="${progressId}" class="mb-3">
                <div class="d-flex justify-content-between mb-1">
                    <span>${escapeHtml(file.name)}</span>
                    <span class="progress-text">准备中...</span>
                </div>
                <div class="progress" style="height: 20px;">
                    <div class="progress-bar" role="progressbar" style="width: 0%">0%</div>
                </div>
            </div>
        `;

        try {
            await uploadFile(file, progressId);
            successCount++;
        } catch (error) {
            console.error('上传失败:', error);
            failCount++;
            updateProgress(progressId, 0, '上传失败: ' + (error.message || '未知错误'), 'danger');
        }
    }

    // 上传完成
    uploadInProgress = false;
    updateUploadButton();

    // 显示结果
    setTimeout(() => {
        if (successCount > 0) {
            alert(`上传完成！成功: ${successCount} 个，失败: ${failCount} 个`);
            
            // 如果在 iframe 中，通知父页面刷新
            if (window.AdminIframeClient && window.AdminIframeClient.refreshParent) {
                window.AdminIframeClient.refreshParent();
            } else if (window.parent !== window) {
                window.parent.postMessage({ type: 'refreshParent', channel: 'upload-files' }, '*');
            }
            
            // 关闭 iframe（如果设置了自动关闭）
            if (window.AdminIframeClient && window.AdminIframeClient.close) {
                setTimeout(() => {
                    window.AdminIframeClient.close();
                }, 1000);
            }
        } else {
            alert('上传失败，请重试');
        }
    }, 500);
}

// 上传单个文件
async function uploadFile(file, progressId) {
    const uploadTokenUrl = '{{ admin_route("system/upload-files") }}/token';
    const uploadUrl = '{{ admin_route("system/upload-files") }}/upload';

    // 1. 获取上传凭证
    updateProgress(progressId, 10, '获取上传凭证...', 'info');
    
    const tokenResponse = await fetch(uploadTokenUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({
            filename: file.name,
            content_type: file.type || 'application/octet-stream',
            file_size: file.size,
            sub_path: 'files',
        }),
    });

    if (!tokenResponse.ok) {
        const errorData = await tokenResponse.json();
        throw new Error(errorData.msg || errorData.message || '获取上传凭证失败');
    }

    const tokenData = await tokenResponse.json();
    if (tokenData.code !== 200) {
        throw new Error(tokenData.msg || tokenData.message || '获取上传凭证失败');
    }

    const { token, path, url, upload_url, final_url, method, headers } = tokenData.data;

    // 2. 上传文件
    updateProgress(progressId, 30, '上传文件中...', 'info');

    // 读取文件内容
    const fileContent = await readFileAsArrayBuffer(file);

    // 判断是 S3 直传还是服务器上传
    // S3 返回的是 url 字段，本地存储没有 url 字段
    const s3UploadUrl = url || upload_url;
    const isS3Upload = s3UploadUrl && (s3UploadUrl.includes('amazonaws.com') || s3UploadUrl.includes('s3.') || s3UploadUrl.includes('s3-'));
    const targetUrl = isS3Upload ? s3UploadUrl : (uploadUrl + '/' + encodeURIComponent(path));
    
    // 构建上传请求头
    const uploadHeaders = {
        'Content-Type': file.type || 'application/octet-stream',
        'Content-Length': file.size.toString(),
    };
    
    // 如果是 S3 直传，使用 tokenData 中的 headers（预签名 URL 的签名信息）
    if (isS3Upload && headers) {
        Object.assign(uploadHeaders, headers);
    } else {
        // 服务器上传需要上传令牌
        uploadHeaders['X-Upload-Token'] = token;
    }

    // 上传文件
    const uploadResponse = await fetch(targetUrl, {
        method: method || 'PUT',
        headers: uploadHeaders,
        body: fileContent,
    });

    if (!uploadResponse.ok) {
        // S3 上传失败时，响应可能是 XML 格式
        if (isS3Upload) {
            const errorText = await uploadResponse.text();
            throw new Error('上传到 S3 失败: ' + (errorText || uploadResponse.statusText));
        }
        
        // 服务器上传失败时，响应是 JSON 格式
        let errorData = {};
        try {
            errorData = await uploadResponse.json();
        } catch (e) {
            // 忽略解析错误
        }
        throw new Error(errorData.msg || errorData.message || '上传失败');
    }

    // S3 上传成功后，需要通知服务器更新文件状态
    if (isS3Upload) {
        updateProgress(progressId, 90, '更新文件状态...', 'info');
        
        // 通知服务器文件已上传（使用 final_url）
        const notifyResponse = await fetch(uploadUrl + '/' + encodeURIComponent(path), {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-Upload-Token': token,
            },
            body: JSON.stringify({
                file_url: final_url || s3UploadUrl,
            }),
        });

        if (!notifyResponse.ok) {
            let errorData = {};
            try {
                errorData = await notifyResponse.json();
            } catch (e) {
                // 忽略解析错误
            }
            throw new Error(errorData.msg || errorData.message || '更新文件状态失败');
        }

        const notifyResult = await notifyResponse.json();
        if (notifyResult.code !== 200) {
            throw new Error(notifyResult.msg || notifyResult.message || '更新文件状态失败');
        }

        updateProgress(progressId, 100, '上传成功', 'success');
        return;
    }

    // 服务器上传需要解析响应
    const uploadResult = await uploadResponse.json();
    if (uploadResult.code !== 200) {
        throw new Error(uploadResult.msg || uploadResult.message || '上传失败');
    }

    updateProgress(progressId, 100, '上传成功', 'success');
}

// 读取文件为 ArrayBuffer
function readFileAsArrayBuffer(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(reader.result);
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

// 更新进度
function updateProgress(progressId, percent, text, variant = 'primary') {
    const progressItem = document.getElementById(progressId);
    if (!progressItem) return;

    const progressBar = progressItem.querySelector('.progress-bar');
    const progressText = progressItem.querySelector('.progress-text');

    progressBar.style.width = percent + '%';
    progressBar.textContent = percent + '%';
    progressBar.className = `progress-bar bg-${variant}`;
    progressText.textContent = text;
}

// 格式化文件大小
function formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

// HTML 转义
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endpush

