@extends('admin.layouts.admin')

@section('title', '应用管理')

@php
    $searchConfig = $searchConfig ?? [];
    $hasSearchConfig = !empty($searchConfig['search_fields'] ?? []);
@endphp

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
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-1 fw-bold">应用管理</h6>
                <small class="text-muted">管理系统中的应用</small>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            @include('admin.components.data-table-with-columns', [
                'tableId' => 'addonsStoreTable',
                'storageKey' => 'addonsStoreTableColumns',
                'ajaxUrl' => admin_route('addons_store'),
                'searchFormId' => 'searchForm_addonsStoreTable',
                'searchPanelId' => 'searchPanel_addonsStoreTable',
                'searchConfig' => $searchConfig,
                'showSearch' => $hasSearchConfig,
                'showPagination' => true,
                'columns' => [
                    [
                        'index' => 0,
                        'label' => 'ID',
                        'field' => 'id',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 1,
                        'label' => '插件名称',
                        'field' => 'name',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 2,
                        'label' => '标识符ID',
                        'field' => 'identifier',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '120',
                        'class' => 'text-monospace'
                    ],
                    [
                        'index' => 3,
                        'label' => '版本',
                        'field' => 'version',
                        'type' => 'text',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 3,
                        'label' => '分类',
                        'field' => 'category',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonCategory',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 4,
                        'label' => '作者',
                        'field' => 'author',
                        'type' => 'text',
                        'visible' => true,
                    ],
                    [
                        'index' => 5,
                        'label' => '下载量',
                        'field' => 'downloads',
                        'type' => 'number',
                        'visible' => true,
                        'width' => '100',
                    ],
                    [
                        'index' => 6,
                        'label' => '状态',
                        'field' => 'status',
                        'type' => 'switch',
                        'onChange' => 'toggleStatus({id}, this, \'' . admin_route('addons_store') . '/{id}/toggle-status\', \'status\')',
                        'visible' => true,
                        'width' => '80',
                    ],
                    [
                        'index' => 7,
                        'label' => '创建时间',
                        'field' => 'created_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => true,
                        'width' => '150',
                    ],
                    [
                        'index' => 8,
                        'label' => '插件标识',
                        'field' => 'slug',
                        'type' => 'text',
                        'visible' => false,
                        'width' => '120',
                        'class' => 'text-monospace'
                    ],
                    [
                        'index' => 9,
                        'label' => '描述',
                        'field' => 'description',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonDescription',
                        'visible' => false,
                        'width' => '200'
                    ],
                    [
                        'index' => 10,
                        'label' => '标签',
                        'field' => 'tags',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonTags',
                        'visible' => false,
                        'width' => '150'
                    ],
                    [
                        'index' => 11,
                        'label' => '主页',
                        'field' => 'homepage',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonHomepage',
                        'visible' => false,
                        'width' => '120'
                    ],
                    [
                        'index' => 12,
                        'label' => '仓库地址',
                        'field' => 'repository',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonRepository',
                        'visible' => false,
                        'width' => '120'
                    ],
                    [
                        'index' => 13,
                        'label' => '许可证',
                        'field' => 'license',
                        'type' => 'text',
                        'visible' => false,
                        'width' => '100'
                    ],
                    [
                        'index' => 14,
                        'label' => '评分',
                        'field' => 'rating',
                        'type' => 'custom',
                        'renderFunction' => 'renderAddonRating',
                        'visible' => false,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 15,
                        'label' => '评论数',
                        'field' => 'reviews_count',
                        'type' => 'number',
                        'visible' => false,
                        'width' => '80',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 16,
                        'label' => '官方插件',
                        'field' => 'is_official',
                        'type' => 'custom',
                        'renderFunction' => 'renderBooleanIcon',
                        'visible' => false,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 17,
                        'label' => '推荐',
                        'field' => 'is_featured',
                        'type' => 'custom',
                        'renderFunction' => 'renderBooleanIcon',
                        'visible' => false,
                        'width' => '80',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 18,
                        'label' => '用户ID',
                        'field' => 'user_id',
                        'type' => 'number',
                        'visible' => false,
                        'width' => '80',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 19,
                        'label' => '是否免费',
                        'field' => 'is_free',
                        'type' => 'custom',
                        'renderFunction' => 'renderBooleanIcon',
                        'visible' => false,
                        'width' => '100',
                        'class' => 'text-center'
                    ],
                    [
                        'index' => 20,
                        'label' => '包文件路径',
                        'field' => 'package_path',
                        'type' => 'text',
                        'visible' => false,
                        'width' => '150',
                        'class' => 'text-truncate'
                    ],
                    [
                        'index' => 21,
                        'label' => '更新时间',
                        'field' => 'updated_at',
                        'type' => 'date',
                        'format' => 'Y-m-d H:i:s',
                        'visible' => false,
                        'width' => '150'
                    ],
                    [
                        'index' => 22,
                        'label' => '操作',
                        'type' => 'actions',
                        'actions' => [
                            [
                                'type' => 'link',
                                'href' => admin_route('addons_store/versions') . '?addon_id={id}',
                                'icon' => 'bi-journal-text',
                                'variant' => 'info',
                                'title' => '插件版本',
                                'attributes' => [
                                    'data-iframe-shell-trigger' => 'addon-versions-{id}',
                                    'data-iframe-shell-src' => admin_route('addons_store/versions') . '?addon_id={id}',
                                    'data-iframe-shell-title' => '版本管理 - {name}',
                                    'data-iframe-shell-channel' => 'addons-store',
                                    'data-iframe-shell-hide-actions' => 'true'
                                ]
                            ],
                            [
                                'type' => 'button',
                                'onclick' => 'deleteRow_addonsStoreTable({id})',
                                'icon' => 'bi-trash',
                                'variant' => 'danger',
                                'title' => '删除'
                            ]
                        ],
                        'visible' => true,
                        'width' => '120',
                        'class' => 'sticky-column',
                        'toggleable' => false,
                    ],
                ],
                'data' => [],
                'emptyMessage' => '暂无插件数据',
                'leftButtons' => [
                    [
                        'type' => 'button',
                        'text' => '上传插件',
                        'icon' => 'bi-upload',
                        'variant' => 'info',
                        'attributes' => [
                            'onclick' => 'uploadAddon()',
                            'id' => 'uploadAddonBtn'
                        ]
                    ]
                ],
            ])
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
@if ($hasSearchConfig)
    @include('components.admin-script', ['path' => '/js/components/search-form-renderer.js'])
@endif
@include('components.admin-script', ['path' => '/js/components/refresh-parent-listener.js'])
<script>
// 渲染插件分类
function renderAddonCategory(value) {
    const labels = {
        'system': '系统',
        'tool': '工具',
        'theme': '主题',
        'other': '其他'
    };
    return labels[value] || value;
}

// 渲染插件描述
function renderAddonDescription(value) {
    if (!value) return '<span class="text-muted">无描述</span>';
    return value.length > 50 ? value.substring(0, 50) + '...' : value;
}

// 渲染插件标签
function renderAddonTags(value) {
    if (!value || !Array.isArray(value) || value.length === 0) {
        return '<span class="text-muted">无标签</span>';
    }
    return value.map(tag =>
        `<span class="badge bg-secondary me-1">${tag}</span>`
    ).join('');
}

// 渲染主页链接
function renderAddonHomepage(value) {
    if (!value) return '-';
    return `<a href="${value}" target="_blank" class="text-decoration-none">
        <i class="bi bi-link-45deg"></i> 访问
    </a>`;
}

// 渲染仓库地址
function renderAddonRepository(value) {
    if (!value) return '-';
    const icon = value.includes('github.com') ? 'bi-github' :
                value.includes('gitlab.com') ? 'bi-gitlab' :
                value.includes('bitbucket.org') ? 'bi-bitbucket' : 'bi-git';
    return `<a href="${value}" target="_blank" class="text-decoration-none">
        <i class="bi ${icon}"></i> 查看
    </a>`;
}

// 渲染评分
function renderAddonRating(value) {
    if (!value || value == 0) return '<span class="text-muted">-</span>';

    const rating = parseFloat(value);
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    const emptyStars = 5 - fullStars - (hasHalfStar ? 1 : 0);

    let stars = '';

    // 满星
    for (let i = 0; i < fullStars; i++) {
        stars += '<i class="bi bi-star-fill text-warning"></i>';
    }

    // 半星
    if (hasHalfStar) {
        stars += '<i class="bi bi-star-half text-warning"></i>';
    }

    // 空星
    for (let i = 0; i < emptyStars; i++) {
        stars += '<i class="bi bi-star text-warning"></i>';
    }

    return `${stars} <small class="text-muted ms-1">${rating.toFixed(1)}</small>`;
}

// 渲染布尔值图标
function renderBooleanIcon(value) {
    if (value === true || value === 1 || value === '1') {
        return '<i class="bi bi-check-circle-fill text-success" title="是"></i>';
    } else {
        return '<i class="bi bi-x-circle-fill text-muted" title="否"></i>';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // 删除单行函数
    window.deleteRow_addonsStoreTable = function(id) {
        if (!confirm('确定要删除该插件吗？此操作不可恢复。')) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

        fetch(`{{ admin_route("addons_store") }}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 200) {
                alert(data.msg || '删除成功');
                const table = window['_dataTable_addonsStoreTable'];
                if (table) {
                    table.reload();
                }
            } else {
                alert(data.msg || data.message || '删除失败');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('删除失败');
        });
    };

    // 上传插件
    window.uploadAddon = function() {
        // 创建文件输入元素
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.zip,.tar.gz,.tgz';
        input.style.display = 'none';

        input.onchange = function(e) {
            const file = e.target.files[0];
            if (!file) return;

            // 验证文件类型
            const allowedTypes = ['application/zip', 'application/x-zip-compressed', 'application/gzip', 'application/x-tar'];
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(zip|tar\.gz|tgz)$/i)) {
                alert('请选择有效的插件安装包文件（.zip, .tar.gz, .tgz）');
                return;
            }

            // 验证文件大小（限制为50MB）
            const maxSize = 50 * 1024 * 1024; // 50MB
            if (file.size > maxSize) {
                alert('文件大小不能超过50MB');
                return;
            }

            // 显示上传进度
            const btn = document.getElementById('uploadAddonBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> 上传中...';
            btn.disabled = true;

            // 创建FormData
            const formData = new FormData();
            formData.append('addon_package', file);

            // 发送上传请求
            fetch('{{ $uploadUrl ?? admin_route('addons_store/upload') }}', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Upload response:', data); // 调试日志
                if (data.code === 200) {
                    showToast('success', '插件上传成功！');
                    // 刷新表格
                    const table = window['_dataTable_addonsStoreTable'];
                    if (table) {
                        table.reload();
                    } else {
                        location.reload();
                    }
                } else {
                    showToast('danger', data.msg || '上传失败');
                }
            })
            .catch(error => {
                console.error('Upload network error:', error);
                showToast('danger', '网络错误，上传失败，请重试');
            })
            .finally(() => {
                // 恢复按钮状态
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        };

        // 触发文件选择
        input.click();
    };

    @if ($hasSearchConfig)
    // 搜索表单渲染器
    const config = @json($searchConfig);
    if (config && config.search_fields && config.search_fields.length) {
        if (typeof window.SearchFormRenderer === 'function') {
            const renderer = new window.SearchFormRenderer({
                config,
                formId: 'searchForm_addonsStoreTable',
                panelId: 'searchPanel_addonsStoreTable',
                tableId: 'addonsStoreTable'
            });

            window['_searchFormRenderer_addonsStoreTable'] = renderer;
            if (typeof window.createSearchFormResetFunction === 'function') {
                window.resetSearchForm_addonsStoreTable = window.createSearchFormResetFunction('addonsStoreTable');
            } else {
                window.resetSearchForm_addonsStoreTable = function () {
                    if (renderer && typeof renderer.reset === 'function') {
                        renderer.reset();
                    }
                };
            }
        } else {
            console.warn('[AddonsStorePage] SearchFormRenderer 未加载');
        }
    }
    @endif
});
</script>
@endpush
