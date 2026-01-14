@extends('admin.layouts.admin')

@section('title', '插件详情 - ' . ($addon['name'] ?? ''))

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
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <h6 class="mb-1 fw-bold">插件详情</h6>
                <small class="text-muted">查看插件详细信息</small>
            </div>
            <div>
                <a href="{{ admin_route('system/addons') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi-arrow-left me-1"></i>返回列表
                </a>
            </div>
        </div>
    </div>

    @if($addon)
    <div class="row">
        <div class="col-12">
            <!-- 插件基本信息 -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">{{ $addon['name'] }}</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>插件标识：</strong><br>
                            <code>{{ $addon['id'] }}</code>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>版本：</strong><br>
                            <span class="badge bg-primary">{{ $addon['version'] }}</span>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>作者：</strong><br>
                            {{ $addon['author'] }}
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>邮箱：</strong><br>
                            {{ $addon['email'] ?? '未设置' }}
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>类型：</strong><br>
                            <span class="badge bg-info">{{ $addon['type'] }}</span>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>分类：</strong><br>
                            <span class="badge bg-secondary">{{ $addon['category'] }}</span>
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>状态：</strong><br>
                            @if($addon['enabled'])
                                <span class="badge bg-success">启用</span>
                            @else
                                <span class="badge bg-secondary">禁用</span>
                            @endif
                        </div>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <strong>安装状态：</strong><br>
                            @if($addon['installed'])
                                <span class="badge bg-success">已安装</span>
                            @else
                                <span class="badge bg-warning">未安装</span>
                            @endif
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <strong>插件目录：</strong><br>
                            <code>{{ $addon['directory'] }}</code>
                        </div>
                        <div class="col-md-6 mb-3">
                            @if(isset($addon['moyi_admin_version']))
                            <strong>支持版本：</strong><br>
                            <span class="badge bg-light text-dark">{{ $addon['moyi_admin_version'] }}</span>
                            @endif
                        </div>
                    </div>

                    <div class="mb-3">
                        <strong>描述：</strong>
                        <p class="mt-2">{{ $addon['description'] }}</p>
                    </div>

                    @if(isset($addon['require']) && is_array($addon['require']))
                    <div class="mb-3">
                        <strong>依赖要求：</strong>
                        <div class="mt-2">
                            @foreach($addon['require'] as $package => $version)
                            <code class="d-inline-block me-2 mb-1">{{ $package }}: {{ $version }}</code>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if(isset($addon['homepage']))
                    <div class="mb-3">
                        <strong>主页：</strong>
                        <a href="{{ $addon['homepage'] }}" target="_blank" class="text-primary">{{ $addon['homepage'] }}</a>
                    </div>
                    @endif

                    @if(isset($addon['license']))
                    <div class="mb-3">
                        <strong>许可证：</strong>
                        <span class="badge bg-light text-dark">{{ $addon['license'] }}</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- 插件配置 -->
            @if(isset($addon['config']) && is_array($addon['config']))
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">插件配置</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($addon['config'] as $key => $value)
                        <div class="col-md-6 mb-3">
                            <small class="text-muted">{{ $key }}:</small><br>
                            <code class="d-block">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value }}</code>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- 插件结构 -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">插件结构</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>目录结构</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-folder{{ $addon['has_controller'] ? '-fill text-success' : ' text-muted' }}"></i> Controller/</li>
                                <li><i class="bi bi-folder{{ $addon['has_views'] ? '-fill text-success' : ' text-muted' }}"></i> View/</li>
                                <li><i class="bi bi-folder{{ $addon['has_public'] ? '-fill text-success' : ' text-muted' }}"></i> Public/</li>
                                <li><i class="bi bi-folder{{ $addon['has_manager'] ? '-fill text-success' : ' text-muted' }}"></i> Manager/</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>配置文件</h6>
                            <ul class="list-unstyled">
                                <li><i class="bi bi-file-earmark-check-fill text-success"></i> info.php</li>
                                <li><i class="bi bi-file-earmark-code{{ $addon['has_routes'] ? '-fill text-success' : ' text-muted' }}"></i> routes.php</li>
                                <li><i class="bi bi-file-earmark-richtext{{ $addon['has_config'] ? '-fill text-success' : ' text-muted' }}"></i> config.php</li>
                                <li><i class="bi bi-file-earmark-spreadsheet{{ isset($addon['menus_permissions']) ? '-fill text-success' : ' text-muted' }}"></i> menus_permissions.json</li>
                            </ul>
                        </div>
                    </div>

                    @if(isset($addon['menus_permissions']))
                    <div class="mt-4 pt-3 border-top">
                        <h6>菜单权限配置</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <strong>菜单数量：</strong>
                                <span class="badge bg-primary">{{ isset($addon['menus_permissions']['menus']) ? count($addon['menus_permissions']['menus']) : 0 }}</span>
                </div>
                            <div class="col-md-6">
                                <strong>权限数量：</strong>
                                <span class="badge bg-info">{{ isset($addon['menus_permissions']['permissions']) ? count($addon['menus_permissions']['permissions']) : 0 }}</span>
            </div>
        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- 后台菜单 -->
            @if(isset($addon['menus_permissions']) && is_array($addon['menus_permissions']))
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0 fw-bold">后台菜单</h6>
                </div>
                <div class="card-body">
                    @if(isset($addon['menus_permissions']['menus']) && is_array($addon['menus_permissions']['menus']))
                        <div class="mb-4">
                            <strong>菜单列表：</strong>
                            <div class="mt-3">
                                @foreach($addon['menus_permissions']['menus'] as $menu)
                                <div class="card card-body p-3 mb-2">
                                    <div class="d-flex align-items-center">
                                        <i class="bi {{ $menu['icon'] ?? 'bi-circle' }} me-2"></i>
                                        <div class="flex-grow-1">
                                            <strong>{{ $menu['title'] ?? '未命名菜单' }}</strong>
                                            @if(isset($menu['parent_slug']) && $menu['parent_slug'])
                                            <span class="badge bg-secondary ms-2">{{ $menu['parent_slug'] }}</span>
                                            @endif
                                            @if(isset($menu['path']) && $menu['path'])
                                            <br><small class="text-muted">{{ $menu['path'] }}</small>
                                            @endif
                                        </div>
                                        <small class="text-muted">{{ $menu['name'] ?? '' }}</small>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(isset($addon['menus_permissions']['permissions']) && is_array($addon['menus_permissions']['permissions']))
                        <div class="mb-4">
                            <strong>权限配置：</strong>
                            <div class="mt-3">
                                @foreach($addon['menus_permissions']['permissions'] as $permission)
                                <div class="card card-body p-3 mb-2">
                                    <div class="d-flex align-items-start">
                                        <i class="bi bi-shield-check me-2 mt-1"></i>
                                        <div class="flex-grow-1">
                                            <strong>{{ $permission['name'] ?? '未命名权限' }}</strong>
                                            <span class="badge bg-info ms-2">{{ $permission['slug'] ?? '' }}</span>
                                            @if(isset($permission['children']) && is_array($permission['children']))
                                            <div class="mt-2">
                                                @foreach($permission['children'] as $child)
                                                <div class="ms-3 mb-1">
                                                    <small class="text-muted">
                                                        └ {{ $child['name'] ?? '未命名子权限' }}
                                                        <span class="badge bg-light text-dark ms-1">{{ $child['slug'] ?? '' }}</span>
                                                    </small>
                                                </div>
                                                @endforeach
                                            </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
            @endif
        </div>
    </div>
    @else
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center">
            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
            <h5 class="mt-3">插件不存在</h5>
            <p class="text-muted">无法找到指定的插件信息</p>
            <a href="{{ admin_route('system/addons') }}" class="btn btn-primary">返回列表</a>
        </div>
    </div>
    @endif
</div>
@endsection

@push('admin_scripts')
@include('components.addon.addon-detail-js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.initAddonDetailPage === 'function') {
        window.initAddonDetailPage({
            routes: {
                base: '{{ admin_route('system/addons') }}'
            }
        });
    }
});
</script>
@endpush
