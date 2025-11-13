@extends('admin.layouts.admin')

@section('title', '仪表盘')

@section('content')

{{-- 页面标题 --}}
<div class="page-header mb-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center">
        <div>
            <h1 class="h2 mb-1">仪表盘</h1>
            <p class="text-white-50 mb-0">欢迎回来！这是您的工作台概览</p>
        </div>
        <div class="btn-toolbar">
            <div class="btn-group me-2">
                <button type="button" class="btn btn-sm btn-light">
                    <i class="bi bi-share"></i> 分享
                </button>
                <button type="button" class="btn btn-sm btn-light">
                    <i class="bi bi-download"></i> 导出
                </button>
            </div>
            <button type="button" class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-1">
                <i class="bi bi-calendar3"></i>
                本周
            </button>
        </div>
    </div>
</div>

{{-- 统计卡片 --}}
<div class="row g-4 mb-4">
    @foreach($stats as $key => $stat)
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stats-card h-100 border-0" style="animation-delay: {{ $loop->index * 0.1 }}s">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="flex-shrink-0">
                        <div class="rounded-3 d-flex align-items-center justify-content-center"
                             style="width: 56px; height: 56px; background: linear-gradient(135deg, {{ $key === 'users' ? '#667eea, #764ba2' : ($key === 'sites' ? '#f093fb, #f5576c' : ($key === 'activities' ? '#4facfe, #00f2fe' : '#43e97b, #38f9d7')) }});">
                            @if($key === 'users')
                                <i class="bi bi-people fs-3 text-white"></i>
                            @elseif($key === 'sites')
                                <i class="bi bi-globe fs-3 text-white"></i>
                            @elseif($key === 'activities')
                                <i class="bi bi-calendar-event fs-3 text-white"></i>
                            @else
                                <i class="bi bi-currency-dollar fs-3 text-white"></i>
                            @endif
                        </div>
                    </div>
                    @if(isset($stat['today']) || isset($stat['active']) || isset($stat['this_month']))
                    <span class="badge rounded-pill" style="background: linear-gradient(135deg, #a8edea, #fed6e3); color: #1e293b; font-weight: 600;">
                        @if(isset($stat['today']))
                            今日 +{{ $stat['today'] }}
                        @elseif(isset($stat['active']))
                            活跃 {{ $stat['active'] }}
                        @elseif(isset($stat['this_month']))
                            本月 +{{ $stat['this_month'] }}
                        @endif
                    </span>
                    @endif
                </div>
                <div>
                    <div class="small text-muted text-uppercase fw-semibold mb-2" style="letter-spacing: 0.5px;">
                        @if($key === 'users')
                            用户总数
                        @elseif($key === 'sites')
                            站点数量
                        @elseif($key === 'activities')
                            活动总数
                        @else
                            总收入
                        @endif
                    </div>
                    <div class="h2 mb-0 fw-bold" style="color: #1e293b;">{{ $stat['total'] }}</div>
                </div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- 图表区域 --}}
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="card-title mb-1">访问趋势</h5>
                    <p class="text-muted mb-0 small">最近7天的访问量统计</p>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary active">7天</button>
                    <button class="btn btn-outline-secondary">30天</button>
                    <button class="btn btn-outline-secondary">90天</button>
                </div>
            </div>
            <div class="card-body pt-2">
                <canvas class="my-2 w-100" id="myChart" width="900" height="250"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- 最近活动 --}}
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-transparent py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-1">最近活动</h5>
                        <p class="text-muted mb-0 small">实时动态更新</p>
                    </div>
                    <button class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    @forelse($recentActivities as $activity)
                    <div class="list-group-item border-0 py-3 px-4" style="transition: all 0.3s ease;">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 position-relative">
                                <div class="rounded-circle text-white d-flex align-items-center justify-content-center"
                                     style="width: 44px; height: 44px; font-weight: 600; background: linear-gradient(135deg, #667eea, #764ba2);">
                                    {{ mb_substr($activity['user'], 0, 1) }}
                                </div>
                                <span class="position-absolute bottom-0 end-0 p-1 bg-success border border-2 border-white rounded-circle" style="width: 12px; height: 12px;"></span>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="mb-1">
                                    <strong class="text-dark">{{ $activity['user'] }}</strong>
                                    <span class="text-muted ms-1">{{ $activity['action'] }}</span>
                                    @if($activity['target'])
                                        <span class="fw-semibold" style="color: #667eea;">{{ $activity['target'] }}</span>
                                    @endif
                                </div>
                                <small class="text-muted d-flex align-items-center">
                                    <i class="bi bi-clock me-1"></i> {{ $activity['time'] }}
                                </small>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="list-group-item border-0 text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-inbox fs-1" style="color: #cbd5e1;"></i>
                        </div>
                        <p class="text-muted mb-0">暂无活动记录</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- 快速操作 --}}
    <div class="col-12 col-lg-6">
        <div class="card border-0 h-100">
            <div class="card-header bg-transparent py-3">
                <h5 class="card-title mb-1">快速操作</h5>
                <p class="text-muted mb-0 small">常用功能快捷入口</p>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="{{ admin_route('users/create') }}" class="btn btn-lg text-start d-flex align-items-center" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); border: 2px solid rgba(102, 126, 234, 0.2); color: #667eea; font-weight: 600;">
                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #667eea, #764ba2);">
                            <i class="bi bi-person-plus text-white"></i>
                        </div>
                        <span>添加用户</span>
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </a>
                    <a href="{{ admin_route('sites/create') }}" class="btn btn-lg text-start d-flex align-items-center" style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.1), rgba(240, 147, 251, 0.1)); border: 2px solid rgba(245, 87, 108, 0.2); color: #f5576c; font-weight: 600;">
                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #f093fb, #f5576c);">
                            <i class="bi bi-globe-americas text-white"></i>
                        </div>
                        <span>创建站点</span>
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </a>
                    <a href="{{ admin_route('settings') }}" class="btn btn-lg text-start d-flex align-items-center" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.1), rgba(0, 242, 254, 0.1)); border: 2px solid rgba(79, 172, 254, 0.2); color: #4facfe; font-weight: 600;">
                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #4facfe, #00f2fe);">
                            <i class="bi bi-gear text-white"></i>
                        </div>
                        <span>系统设置</span>
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </a>
                    <a href="{{ admin_route('logs/operations') }}" class="btn btn-lg text-start d-flex align-items-center" style="background: linear-gradient(135deg, rgba(67, 233, 123, 0.1), rgba(56, 249, 215, 0.1)); border: 2px solid rgba(67, 233, 123, 0.2); color: #43e97b; font-weight: 600;">
                        <div class="rounded-3 d-flex align-items-center justify-content-center me-3" style="width: 40px; height: 40px; background: linear-gradient(135deg, #43e97b, #38f9d7);">
                            <i class="bi bi-file-text text-white"></i>
                        </div>
                        <span>查看日志</span>
                        <i class="bi bi-arrow-right ms-auto"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- 系统信息 --}}
<div class="row g-4 mt-0">
    <div class="col-12">
        <div class="card border-0">
            <div class="card-header bg-transparent py-3">
                <h5 class="card-title mb-1">系统信息</h5>
                <p class="text-muted mb-0 small">服务器运行状态</p>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-3" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));">
                            <div class="small text-muted mb-1">站点名称</div>
                            <div class="fw-bold" style="color: #1e293b;">{{ site()?->name ?? '站点名称' }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-3" style="background: linear-gradient(135deg, rgba(245, 87, 108, 0.05), rgba(240, 147, 251, 0.05));">
                            <div class="small text-muted mb-1">PHP 版本</div>
                            <div class="fw-bold" style="color: #1e293b;">{{ PHP_VERSION }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-3" style="background: linear-gradient(135deg, rgba(79, 172, 254, 0.05), rgba(0, 242, 254, 0.05));">
                            <div class="small text-muted mb-1">Hyperf 版本</div>
                            <div class="fw-bold" style="color: #1e293b;">3.1.x</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 col-lg-3">
                        <div class="p-3 rounded-3" style="background: linear-gradient(135deg, rgba(67, 233, 123, 0.05), rgba(56, 249, 215, 0.05));">
                            <div class="small text-muted mb-1">服务器时间</div>
                            <div class="fw-bold" style="color: #1e293b;">{{ date('H:i:s') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
{{-- Chart.js --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
<script>
(function () {
    'use strict'

    // 图表配置
    const ctx = document.getElementById('myChart')
    if (ctx) {
        const myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['周一', '周二', '周三', '周四', '周五', '周六', '周日'],
                datasets: [{
                    label: '访问量',
                    data: [15339, 21345, 18483, 24003, 23489, 24092, 12034],
                    lineTension: 0.3,
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 2,
                    pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: 'rgba(99, 102, 241, 1)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        intersect: false,
                        mode: 'index'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString()
                            }
                        }
                    }
                }
            }
        })
    }
})()
</script>
@endpush
@endsection
