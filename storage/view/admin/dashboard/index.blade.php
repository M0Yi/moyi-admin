@extends('admin.layouts.admin')

@section('title', '仪表盘')

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@push('admin_styles')
<style>
.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 1.5rem 2rem;
    margin: -1rem -2rem 1.5rem -2rem;
    border-radius: 0 0 24px 24px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.2);
}
.page-header h1 { font-weight: 700; margin: 0; font-size: 1.75rem; }

.card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
    transition: box-shadow 0.3s ease, transform 0.3s ease;
    overflow: visible;
}
.card:hover { box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); transform: translateY(-4px); }
.card-header { background: transparent; border: none; padding: 1.25rem 1.5rem; font-weight: 600; }
.card-body { padding: 1.5rem; }

.btn { padding: 0.6rem 1.5rem; border-radius: 10px; font-weight: 500; transition: all 0.3s ease; border: none; }
.btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
.btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4); }
.btn-outline-secondary:hover { transform: translateY(-2px); }

@keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.stats-card { animation: fadeInUp 0.6s ease-out; }
.delay-0 { animation-delay: 0s; }
.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }
.delay-5 { animation-delay: 0.5s; }
.delay-6 { animation-delay: 0.6s; }
.delay-7 { animation-delay: 0.7s; }
.delay-8 { animation-delay: 0.8s; }
.delay-9 { animation-delay: 0.9s; }
.bg-grad-users { background: linear-gradient(135deg, #667eea, #764ba2); }
.bg-grad-sites { background: linear-gradient(135deg, #f093fb, #f5576c); }
.bg-grad-activities { background: linear-gradient(135deg, #4facfe, #00f2fe); }
.bg-grad-default { background: linear-gradient(135deg, #43e97b, #38f9d7); }
</style>
@endpush

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
                <button type="button" class="btn btn-sm btn-light" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">
                    <i class="bi bi-share"></i> 分享
                </button>
                <button type="button" class="btn btn-sm btn-light" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">
                    <i class="bi bi-download"></i> 导出
                </button>
            </div>
            <button type="button" class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-1" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">
                <i class="bi bi-calendar3"></i>
                本周
            </button>
        </div>
    </div>
</div>

<div class="alert alert-info d-flex align-items-center mb-4" role="alert" style="border: 0; color: #0f172a; background: linear-gradient(135deg, #e0f2fe, #e2e8f0);">
    <i class="bi bi-info-circle me-2"></i>
    <span>演示说明：当前仪表盘为展示版，部分按钮与交互仅作为示例，不提供实际功能。</span>
    <span class="ms-2 text-muted">如需启用，请在后台接入真实逻辑。</span>
</div>

{{-- 统计卡片 --}}
<div class="row g-4 mb-4">
    @foreach($stats as $key => $stat)
    <div class="col-12 col-sm-6 col-lg-3">
        <div class="card stats-card h-100 border-0 delay-{{ $loop->index }}">
            <div class="card-body p-4">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="flex-shrink-0">
                        <div class="rounded-3 d-flex align-items-center justify-content-center{{ $key === 'users' ? ' bg-grad-users' : ($key === 'sites' ? ' bg-grad-sites' : ($key === 'activities' ? ' bg-grad-activities' : ' bg-grad-default')) }}"
                             style="width: 56px; height: 56px;">
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
                    <button class="btn btn-outline-secondary active" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">7天</button>
                    <button class="btn btn-outline-secondary" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">30天</button>
                    <button class="btn btn-outline-secondary" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">90天</button>
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
                    <button class="btn btn-sm btn-outline-primary" data-demo="true" data-bs-toggle="tooltip" title="演示功能，暂不可用">
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

@push('admin_scripts')
{{-- Chart.js
    类型：图表 / 数据可视化
    作用：在仪表盘中渲染访问趋势折线图等统计图表
--}}
@include('components.plugin.chart-js')
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

    const demoMsg = '演示提示：该交互为展示效果，暂无实际功能'
    if (window.bootstrap && typeof bootstrap.Tooltip === 'function') {
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
            new bootstrap.Tooltip(el)
        })
    }

    function showDemoNotice() {
        const msg = demoMsg
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Toast === 'function') {
            let container = document.getElementById('demoToastContainer')
            if (!container) {
                container = document.createElement('div')
                container.id = 'demoToastContainer'
                container.className = 'toast-container position-fixed bottom-0 end-0 p-3'
                document.body.appendChild(container)
            }

            const toastEl = document.createElement('div')
            toastEl.className = 'toast align-items-center text-bg-info border-0'
            toastEl.setAttribute('role', 'alert')
            toastEl.setAttribute('aria-live', 'assertive')
            toastEl.setAttribute('aria-atomic', 'true')

            const wrapper = document.createElement('div')
            wrapper.className = 'd-flex'

            const body = document.createElement('div')
            body.className = 'toast-body'
            body.textContent = msg

            const closeBtn = document.createElement('button')
            closeBtn.type = 'button'
            closeBtn.className = 'btn-close btn-close-white me-2 m-auto'
            closeBtn.setAttribute('data-bs-dismiss', 'toast')
            closeBtn.setAttribute('aria-label', 'Close')

            wrapper.appendChild(body)
            wrapper.appendChild(closeBtn)
            toastEl.appendChild(wrapper)
            container.appendChild(toastEl)

            const bsToast = new bootstrap.Toast(toastEl, { delay: 2500, autohide: true })
            bsToast.show()
            toastEl.addEventListener('hidden.bs.toast', function () { toastEl.remove() })
        } else {
            window.alert(msg)
        }
    }

    document.querySelectorAll('[data-demo="true"]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault()
            e.stopPropagation()
            showDemoNotice()
        })
    })
})()
</script>
@endpush
@endsection
