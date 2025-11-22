@extends('admin.layouts.admin')

@section('title', 'CRUD生成器 - 选择数据表')

@if (! $isEmbedded)
    @push('admin_sidebar')
        @include('admin.components.sidebar')
    @endpush

    @push('admin_navbar')
        @include('admin.components.navbar')
    @endpush
@endif

@section('content')
<div class="container-fluid {{ $isEmbedded ? 'py-3 px-2 px-md-4' : 'py-4' }}">
    <div class="mb-3">
        <h6 class="mb-1 fw-bold">选择数据表</h6>
        <small class="text-muted">挑选要生成 CRUD 代码的数据表</small>
    </div>

    <!-- 数据库连接选择器 -->
    @if(count($connections) > 1)
    <div class="card mb-3">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <label for="connectionSelect" class="form-label mb-0">
                        <i class="bi bi-database"></i> 选择数据库连接：
                    </label>
                </div>
                <div class="col-md-4">
                    <select id="connectionSelect" class="form-select" onchange="switchConnection(this.value)">
                        @foreach($connections as $conn)
                        <option value="{{ $conn['name'] }}" 
                                data-database="{{ $conn['database'] }}"
                                data-host="{{ $conn['host'] }}"
                                data-port="{{ $conn['port'] }}"
                                {{ $currentConnection === $conn['name'] ? 'selected' : '' }}>
                            {{ $conn['name'] }}
                            @if($conn['name'] !== $conn['database'])
                                ({{ $conn['database'] }})
                            @endif
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5">
                    @php
                        $currentConnInfo = $connections[$currentConnection] ?? null;
                    @endphp
                    @if($currentConnInfo)
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        <span class="badge bg-secondary me-1">{{ strtoupper($currentConnInfo['driver'] ?? 'mysql') }}</span>
                        数据库：<strong>{{ $currentConnInfo['database'] }}</strong> | 
                        主机：<strong>{{ $currentConnInfo['host'] }}:{{ $currentConnInfo['port'] }}</strong>
                    </small>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif

    <!-- 数据表列表 -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th style="width: 25%">表名</th>
                            <th style="width: 20%">注释</th>
                            <th style="width: 10%" class="text-center">数据量</th>
                            <th style="width: 15%" class="text-center">状态</th>
                            @if(count($connections) > 1)
                            <th style="width: 10%" class="text-center">连接</th>
                            <th style="width: 20%" class="text-center">操作</th>
                            @else
                            <th style="width: 20%" class="text-center">操作</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tables as $table)
                        <tr>
                            <td>
                                <code class="text-primary">{{ $table['name'] }}</code>
                            </td>
                            <td>
                                {{ $table['comment'] ?: '-' }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-info">{{ number_format($table['rows']) }}</span>
                            </td>
                            <td class="text-center">
                                @if(isset($configs[$table['name']]))
                                    @if($configs[$table['name']]->status == 1)
                                        <span class="badge bg-success">已生成</span>
                                    @else
                                        <span class="badge bg-warning">配置中</span>
                                    @endif
                                @else
                                    <span class="badge bg-secondary">未配置</span>
                                @endif
                            </td>
                            @if(count($connections) > 1)
                            <td class="text-center">
                                @php
                                    $connInfo = isset($connections[$currentConnection]) ? $connections[$currentConnection] : null;
                                @endphp
                                <span class="badge bg-primary">{{ $currentConnection }}</span>
                                @if($connInfo)
                                    <br>
                                    <small class="badge bg-secondary" style="font-size: 0.7rem;">
                                        {{ strtoupper($connInfo['driver'] ?? 'mysql') }}
                                    </small>
                                @endif
                            </td>
                            @endif
                            <td class="text-center">
                                @php
                                    $configUrl = admin_route('system/crud-generator/config/' . $table['name']) . '?connection=' . $currentConnection;
                                    if ($isEmbedded) {
                                        $configUrl .= '&_embed=1';
                                    }
                                @endphp
                                @php
                                    $isConfigured = isset($configs[$table['name']]);
                                    $shellTitle = ($isConfigured ? '编辑 CRUD：' : '开始配置：') . $table['name'];
                                @endphp
                                <a href="{{ $configUrl }}"
                                   class="btn btn-sm {{ $isConfigured ? 'btn-info' : 'btn-success' }}"
                                   data-iframe-shell-trigger="crud-generator-config-{{ $table['name'] }}"
                                   data-iframe-shell-src="{{ $configUrl }}"
                                   data-iframe-shell-title="{{ $shellTitle }}"
                                   data-iframe-shell-channel="crud-generator">
                                    @if($isConfigured)
                                        <i class="bi bi-pencil"></i> 编辑配置
                                    @else
                                        <i class="bi bi-plus-lg"></i> 开始配置
                                    @endif
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ count($connections) > 1 ? '6' : '5' }}" class="text-center py-4 text-muted">
                                <i class="bi bi-inbox fs-1"></i>
                                <p class="mt-2">未找到数据表</p>
                                @if(count($connections) > 1)
                                <p class="text-muted small">当前数据库连接：<strong>{{ $currentConnection }}</strong></p>
                                @endif
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 使用说明 -->
    <div class="card mt-3">
        <div class="card-header bg-light">
            <h6 class="mb-0"><i class="bi bi-info-circle"></i> 使用说明</h6>
        </div>
        <div class="card-body">
            <ol class="mb-0">
                <li><strong>选择数据表：</strong>点击"开始配置"按钮，进入配置页面</li>
                <li><strong>配置字段属性：</strong>选择要显示的字段、可搜索的字段、表单类型等</li>
                <li><strong>预览代码：</strong>查看生成的代码，确认无误后可以下载或直接生成到项目中</li>
                <li><strong>生成代码：</strong>点击"生成到项目"按钮，自动创建相关文件</li>
                <li><strong>手动操作：</strong>将生成的路由代码添加到 <code>config/routes.php</code>，执行菜单 SQL</li>
            </ol>
        </div>
    </div>
</div>
@endsection

@push('admin-styles')
<style>
.table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.refreshing {
    pointer-events: none;
    opacity: 0.6;
}
</style>
@endpush

@push('admin_scripts')
<script>
/**
 * 切换数据库连接
 */
function switchConnection(connection) {
    // 跳转到新的连接，保持 refresh 参数
    const url = new URL(window.location.href);
    url.searchParams.set('connection', connection);
    url.searchParams.set('refresh', '1');
    window.location.href = url.toString();
}


// 添加旋转动画
const style = document.createElement('style');
style.textContent = `
    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .rotate-animation {
        animation: rotate 1s linear infinite;
    }
`;
document.head.appendChild(style);
</script>
@endpush

