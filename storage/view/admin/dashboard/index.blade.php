@extends('admin.layouts.admin')

@section('title', '控制台总览')

@if (! $isEmbedded)
    @push('admin_sidebar')
        @include('admin.components.sidebar')
    @endpush

    @push('admin_navbar')
        @include('admin.components.navbar')
    @endpush
@endif

@push('admin-styles')
<style>
.dashboard-stage {
    padding: 1.5rem;
    background: #f8fafc;
    border-radius: 24px;
}

:root {
    --dashboard-gap: 1.25rem;
    --dashboard-radius: 20px;
}

.dashboard-shell {
    display: flex;
    flex-direction: column;
    gap: var(--dashboard-gap);
}

.dashboard-card {
    border: none;
    border-radius: var(--dashboard-radius);
    background: #ffffff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    overflow: hidden;
}

.dashboard-card__body {
    padding: 1.5rem;
}

.touchpoint-chart {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.touchpoint-chart__canvas {
    position: relative;
    padding: 1.25rem;
    border-radius: 28px;
    background:
        radial-gradient(120% 120% at 80% 0%, rgba(99, 102, 241, 0.32), transparent 70%),
        radial-gradient(140% 120% at 0% 0%, rgba(14, 165, 233, 0.25), transparent 65%),
        linear-gradient(135deg, #0f172a, #1e1b4b);
    box-shadow: inset 0 0 0 1px rgba(148, 163, 184, 0.12), 0 20px 45px rgba(15, 23, 42, 0.14);
}

.touchpoint-chart__viewport {
    width: 100%;
    aspect-ratio: 16 / 7;
}

.touchpoint-chart__viewport svg {
    width: 100%;
    height: 100%;
}

.touchpoint-chart__grid line {
    stroke: rgba(226, 232, 240, 0.25);
    stroke-width: 0.5;
    vector-effect: non-scaling-stroke;
}

.touchpoint-chart__grid line[data-variant="strong"] {
    stroke: rgba(226, 232, 240, 0.45);
}

.touchpoint-chart__area {
    stroke: none;
}

.touchpoint-chart__line {
    fill: none;
    stroke-width: 1.8;
    vector-effect: non-scaling-stroke;
    stroke-linecap: round;
    stroke-linejoin: round;
    filter: drop-shadow(0 4px 12px rgba(15, 23, 42, 0.35));
}

.touchpoint-chart__dot {
    vector-effect: non-scaling-stroke;
}

.touchpoint-chart__axis {
    list-style: none;
    margin: 0;
    padding: 0;
    display: grid;
    gap: 0.5rem;
    text-align: center;
    color: #475569;
    font-size: 0.85rem;
}

.touchpoint-chart__axis li {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    align-items: center;
}

.touchpoint-chart__axis-bar {
    width: 2px;
    height: 10px;
    border-radius: 999px;
    background: #cbd5f5;
    opacity: 0.5;
}

.touchpoint-chart__legend {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.25rem;
}

.touchpoint-chart__legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.4rem 0.75rem;
    border-radius: 999px;
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
}

.touchpoint-chart__legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
}

.touchpoint-chart__legend-meta {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.85rem;
    color: #0f172a;
}

.touchpoint-chart__legend-meta small {
    color: #64748b;
}

.touchpoint-chart__legend-delta {
    font-weight: 600;
}

.touchpoint-chart__tooltip {
    --tooltip-bg: rgba(15, 23, 42, 0.98);
    --tooltip-accent: #6366f1;
    position: absolute;
    background: var(--tooltip-bg);
    color: #f8fafc;
    padding: 0.55rem 1rem 0.55rem 1.4rem;
    border-radius: 0.75rem;
    font-size: 0.8rem;
    line-height: 1.3;
    pointer-events: none;
    opacity: 0;
    transform: translate(-50%, -120%);
    transition: opacity 0.15s ease, transform 0.15s ease;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.35);
    border: 1px solid rgba(255, 255, 255, 0.08);
    min-width: 130px;
    z-index: 3;
}

.touchpoint-chart__tooltip::before {
    content: '';
    position: absolute;
    top: 10px;
    bottom: 10px;
    left: 10px;
    width: 3px;
    border-radius: 999px;
    background: var(--tooltip-accent);
    opacity: 0.9;
}

.touchpoint-chart__tooltip::after {
    content: '';
    position: absolute;
    left: 50%;
    bottom: -6px;
    transform: translateX(-50%);
    border-width: 6px 6px 0 6px;
    border-style: solid;
    border-color: var(--tooltip-bg) transparent transparent transparent;
}

.touchpoint-chart__tooltip strong {
    display: block;
    font-size: 0.7rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #cbd5f5;
}

.touchpoint-chart__tooltip span {
    display: block;
    font-size: 1.05rem;
    font-weight: 600;
    color: #fff;
    margin-top: 0.25rem;
}

.touchpoint-chart__tooltip[data-visible="true"] {
    opacity: 1;
    transform: translate(-50%, -140%);
}

@media (max-width: 768px) {
    .touchpoint-chart__canvas {
        padding: 1rem;
    }

    .touchpoint-chart__viewport {
        aspect-ratio: 4 / 3;
    }

    .touchpoint-chart__legend {
        flex-direction: column;
    }
}

.dashboard-hero {
    background: radial-gradient(circle at top right, #c7d2fe 0%, #eef2ff 45%, #ffffff 100%);
    position: relative;
    padding: 2rem 2.5rem;
}

.dashboard-hero::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: inherit;
    border: 1px solid rgba(99, 102, 241, 0.15);
    pointer-events: none;
}

.dashboard-hero__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    margin-top: 1.25rem;
}

.dashboard-hero__meta-item {
    min-width: 160px;
}

.hero-progress {
    width: 100%;
    height: 6px;
    background: rgba(99, 102, 241, 0.15);
    border-radius: 999px;
    overflow: hidden;
}

.hero-progress span {
    display: block;
    height: 100%;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
}

.metric-card {
    padding: 1.25rem;
    border-radius: 18px;
    background: #fff;
    border: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.metric-card__icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.2rem;
}

.metric-card__trend {
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

.metric-card__trend.up {
    color: #16a34a;
}

.metric-card__trend.down {
    color: #dc2626;
}

.section-title {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 0.25rem;
}

.section-subtitle {
    color: #64748b;
    font-size: 0.95rem;
}

.list-stacked {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.timeline-dot {
    width: 11px;
    height: 11px;
    border-radius: 50%;
    background: #6366f1;
    position: relative;
}

.timeline-dot::after {
    content: '';
    position: absolute;
    top: 11px;
    left: 50%;
    width: 2px;
    height: calc(100% + 13px);
    background: rgba(99, 102, 241, 0.25);
    transform: translateX(-50%);
}

.list-stacked li:last-child .timeline-dot::after {
    display: none;
}

.health-pill {
    border-radius: 999px;
    padding: 0.2rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 600;
}

.health-pill--good {
    background: rgba(34, 197, 94, 0.15);
    color: #15803d;
}

.health-pill--warn {
    background: rgba(249, 115, 22, 0.15);
    color: #c2410c;
}

.capacity-bar {
    height: 6px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
}

.capacity-bar span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: linear-gradient(90deg, #6366f1, #8b5cf6);
}

.quick-action {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: border-color 0.2s ease, transform 0.2s ease;
}

.quick-action:hover {
    border-color: #c7d2fe;
    transform: translateY(-2px);
}

.quick-action__icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 1.25rem;
}

.analytics-tabs {
    display: flex;
    gap: 0.5rem;
}

.analytics-tabs button {
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    padding: 0.45rem 1.25rem;
    font-size: 0.9rem;
    font-weight: 600;
    color: #475569;
    transition: all 0.2s ease;
}

.analytics-tabs button.active,
.analytics-tabs button:hover {
    background: #1d4ed8;
    color: #fff;
    border-color: #1d4ed8;
}

@media (max-width: 992px) {
    .dashboard-hero {
        padding: 1.75rem;
    }

    .dashboard-hero__meta {
        flex-direction: column;
    }
}

@if ($isEmbedded)
.admin-embed-main {
    padding: 1.5rem;
}
@endif
</style>
@endpush

@section('content')
@php
    $insights = [
        [
            'title' => '新增用户',
            'value' => '1,284',
            'trend' => '+18% 同比',
            'direction' => 'up',
            'icon' => 'bi-people',
            'accent' => 'linear-gradient(135deg, #6366f1, #8b5cf6)'
        ],
        [
            'title' => '活跃站点',
            'value' => '342',
            'trend' => '+42 本周',
            'direction' => 'up',
            'icon' => 'bi-globe2',
            'accent' => 'linear-gradient(135deg, #0ea5e9, #38bdf8)'
        ],
        [
            'title' => '自动化任务',
            'value' => '96%',
            'trend' => '-4% 失败率',
            'direction' => 'down',
            'icon' => 'bi-lightning-charge',
            'accent' => 'linear-gradient(135deg, #f97316, #fb923c)'
        ],
        [
            'title' => '收入预估',
            'value' => '¥482k',
            'trend' => '+12% QoQ',
            'direction' => 'up',
            'icon' => 'bi-currency-yen',
            'accent' => 'linear-gradient(135deg, #22c55e, #4ade80)'
        ],
    ];

    $roadmap = [
        [
            'title' => 'AI 智能客服 2.0 发布',
            'time' => '今日 · 11:00',
            'owner' => '李倩 · 产品',
            'status' => '上线验证'
        ],
        [
            'title' => 'APM 全链路监控灰度',
            'time' => '周三 · 14:00',
            'owner' => '陈峰 · 后台',
            'status' => '阶段评审'
        ],
        [
            'title' => '数据湖与报表融合',
            'time' => '周五 · 09:30',
            'owner' => '蔡雨 · 数据',
            'status' => '方案冻结'
        ],
    ];

    $activityFeed = [
        [
            'user' => 'Lynn',
            'action' => '部署了新版本',
            'target' => 'site-admin-core',
            'time' => '8 分钟前'
        ],
        [
            'user' => 'Marco',
            'action' => '更新了权限策略',
            'target' => 'Global Role/OPS',
            'time' => '32 分钟前'
        ],
        [
            'user' => 'Eva',
            'action' => '同步了 3 份报表',
            'target' => '数据湖 · 周报',
            'time' => '1 小时前'
        ],
    ];

    $systemHealth = [
        ['service' => 'API 网关', 'uptime' => '99.98%', 'latency' => '112ms', 'status' => 'good'],
        ['service' => '消息队列', 'uptime' => '99.92%', 'latency' => '86ms', 'status' => 'good'],
        ['service' => 'ElasticSearch', 'uptime' => '99.31%', 'latency' => '210ms', 'status' => 'warn'],
    ];

    $teamCapacity = [
        ['name' => '核心后端', 'usage' => 0.78, 'slots' => '7 / 9 Sprint Slot'],
        ['name' => '设计体验', 'usage' => 0.52, 'slots' => '5 / 9 Sprint Slot'],
        ['name' => '数据智能', 'usage' => 0.64, 'slots' => '6 / 10 Sprint Slot'],
    ];

    $quickActions = [
        [
            'label' => '创建多品牌站点',
            'description' => '10 分钟完成一键模板和数据初始化',
            'icon' => 'bi-grid-3x3-gap-fill',
            'accent' => '#6366f1',
            'href' => admin_route('sites/create')
        ],
        [
            'label' => '批量导入运营账号',
            'description' => 'Excel/CSV 支持校验，自动关联权限',
            'icon' => 'bi-people-fill',
            'accent' => '#0ea5e9',
            'href' => admin_route('users/import')
        ],
        [
            'label' => '构建自动化流程',
            'description' => '通过可视化编排联动 10+ 服务',
            'icon' => 'bi-diagram-3-fill',
            'accent' => '#f97316',
            'href' => admin_route('automation/designer')
        ],
    ];

    $experienceLabels = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
    $experienceSeries = [
        [
            'label' => '转化路径',
            'color' => '#6366f1',
            'points' => [42, 48, 46, 53, 58, 55, 61],
        ],
        [
            'label' => '工单解决率',
            'color' => '#0ea5e9',
            'points' => [32, 36, 38, 40, 44, 45, 47],
            'dash' => '4 2',
        ],
    ];

    $experienceMaxValue = 0;
    foreach ($experienceSeries as $series) {
        $experienceMaxValue = max($experienceMaxValue, max($series['points']));
    }
    $experienceMaxValue = max($experienceMaxValue, 10);

    $chartSpace = [
        'width' => 160,
        'height' => 70,
        'top' => 6,
        'bottom' => 64,
        'dot_radius' => 1.05,
    ];
    $pointCount = count($experienceLabels);
    $horizontalStep = $pointCount > 1 ? $chartSpace['width'] / ($pointCount - 1) : 0;

    foreach ($experienceSeries as $index => $series) {
        $seriesPoints = [];
        foreach ($series['points'] as $key => $value) {
            $x = $pointCount > 1 ? round($key * $horizontalStep, 2) : 0;
            $y = round($chartSpace['bottom'] - ($value / $experienceMaxValue) * ($chartSpace['bottom'] - $chartSpace['top']), 2);
            $seriesPoints[] = [
                'x' => $x,
                'y' => $y,
                'value' => $value,
                'label' => $experienceLabels[$key] ?? '',
            ];
        }
        $polyline = implode(' ', array_map(static fn ($point) => "{$point['x']},{$point['y']}", $seriesPoints));
        $firstPoint = $seriesPoints[0] ?? ['x' => 0, 'y' => $chartSpace['bottom']];
        $lastPoint = $seriesPoints[array_key_last($seriesPoints)] ?? $firstPoint;
        $areaPath = trim($polyline . " {$lastPoint['x']},{$chartSpace['bottom']} {$firstPoint['x']},{$chartSpace['bottom']}") . ' Z';
        $latest = $series['points'][count($series['points']) - 1] ?? null;
        $previous = $series['points'][count($series['points']) - 2] ?? null;

        $experienceSeries[$index]['polyline'] = $polyline;
        $experienceSeries[$index]['area'] = $areaPath;
        $experienceSeries[$index]['points_meta'] = $seriesPoints;
        $experienceSeries[$index]['latest'] = $latest;
        $experienceSeries[$index]['delta'] = $previous !== null ? $latest - $previous : null;
        $experienceSeries[$index]['dash'] = $series['dash'] ?? '0';
    }
@endphp

<div class="dashboard-stage">
<div class="dashboard-shell">
    <section class="dashboard-card dashboard-hero">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
            <div>
                <p class="text-uppercase text-muted fw-semibold mb-2" style="letter-spacing: 0.2em;">Central Command</p>
                <h1 class="fw-bold mb-3" style="color: #0f172a;">智能运营总览</h1>
                <p class="text-muted mb-0" style="max-width: 460px;">
                    聚焦体验质量、自动化效率与业务增长。通过统一的 iframe-shell + 标签体系，随时切换关键模块而不中断上下文。
                </p>
                <div class="dashboard-hero__meta">
                    <div class="dashboard-hero__meta-item">
                        <small class="text-muted">本周 OKR 完成度</small>
                        <div class="d-flex align-items-baseline gap-2">
                            <strong class="fs-3">72%</strong>
                            <span class="badge bg-success-subtle text-success">+8%</span>
                        </div>
                        <div class="hero-progress mt-2"><span style="width: 72%"></span></div>
                    </div>
                    <div class="dashboard-hero__meta-item">
                        <small class="text-muted">自动化节省工时</small>
                        <div class="fs-4 fw-semibold">312 h</div>
                        <span class="text-muted small">过去 30 天 · 42 条流程</span>
                    </div>
                    <div class="dashboard-hero__meta-item">
                        <small class="text-muted">实时 SLA</small>
                        <div class="fs-4 fw-semibold">99.94%</div>
                        <span class="text-muted small">4 条核心链路全部稳定</span>
                    </div>
                </div>
            </div>
            <div class="text-center text-lg-end">
                <div class="mb-3">
                    <span class="badge rounded-pill bg-primary-subtle text-primary fw-semibold">Live Insight</span>
                </div>
                <h2 class="display-6 fw-bold mb-1" style="color: #1d4ed8;">¥2.84M</h2>
                <p class="text-muted mb-3">季度 MRR 预估 · 45% 来自自建生态插件</p>
                <div class="d-flex flex-wrap gap-2 justify-content-center justify-content-lg-end">
                    <button type="button" class="btn btn-dark d-flex align-items-center gap-2" data-demo="true">
                        <i class="bi bi-lightning-charge"></i>
                        快速巡检
                    </button>
                    <button type="button" class="btn btn-outline-dark d-flex align-items-center gap-2" data-demo="true">
                        <i class="bi bi-diagram-3"></i>
                        洞察配置
                    </button>
                </div>
            </div>
        </div>
    </section>

    <section class="dashboard-card">
        <div class="dashboard-card__body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
                <div>
                    <p class="section-title mb-1">实时关键指标</p>
                    <p class="section-subtitle mb-0">以弹性标签页形式读取，不打断当前工作流</p>
                </div>
                <div class="analytics-tabs">
                    <button type="button" class="active" data-demo="true">本周</button>
                    <button type="button" data-demo="true">本月</button>
                    <button type="button" data-demo="true">季度</button>
                </div>
            </div>
            <div class="metric-grid">
                @foreach ($insights as $item)
                    <div class="metric-card">
                        <div class="metric-card__icon" style="background: {{ $item['accent'] }};">
                            <i class="bi {{ $item['icon'] }}"></i>
                        </div>
                        <div>
                            <small class="text-muted text-uppercase">{{ $item['title'] }}</small>
                            <div class="d-flex align-items-baseline gap-2">
                                <span class="fs-3 fw-bold" style="color: #0f172a;">{{ $item['value'] }}</span>
                                <span class="metric-card__trend {{ $item['direction'] }}">
                                    <i class="bi {{ $item['direction'] === 'up' ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                                    {{ $item['trend'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-12 col-xl-7">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="section-title mb-1">体验触点趋势</p>
                            <p class="section-subtitle mb-0">整合 iframe shell 数据采集 · 每 5 分钟刷新</p>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark" data-demo="true">
                            导出 CSV
                        </button>
                    </div>
                    <div class="touchpoint-chart">
                        <div class="touchpoint-chart__canvas js-touchpoint-chart" role="img" aria-label="体验触点趋势图表">
                            <div class="touchpoint-chart__viewport">
                                <svg viewBox="0 0 {{ $chartSpace['width'] }} {{ $chartSpace['height'] }}" preserveAspectRatio="none">
                                    <defs>
                                        @foreach ($experienceSeries as $series)
                                            <linearGradient id="experience-fill-{{ $loop->index }}" x1="0" y1="0" x2="0" y2="1">
                                                <stop offset="0%" stop-color="{{ $series['color'] }}" stop-opacity="0.35"></stop>
                                                <stop offset="100%" stop-color="{{ $series['color'] }}" stop-opacity="0"></stop>
                                            </linearGradient>
                                        @endforeach
                                    </defs>
                                    <g class="touchpoint-chart__grid">
                                        @for ($line = 0; $line <= 4; $line++)
                                            @php
                                                $y = $chartSpace['bottom'] - ($line * ($chartSpace['bottom'] - $chartSpace['top']) / 4);
                                            @endphp
                                            <line x1="0" y1="{{ $y }}" x2="{{ $chartSpace['width'] }}" y2="{{ $y }}" data-variant="{{ $line === 0 ? 'strong' : 'soft' }}"></line>
                                        @endfor
                                        @foreach ($experienceLabels as $index => $label)
                                            @php
                                                $x = $pointCount > 1 ? round(($index / ($pointCount - 1)) * $chartSpace['width'], 2) : 0;
                                            @endphp
                                            <line x1="{{ $x }}" y1="{{ $chartSpace['top'] }}" x2="{{ $x }}" y2="{{ $chartSpace['bottom'] }}"></line>
                                        @endforeach
                                    </g>
                                    @foreach ($experienceSeries as $series)
                                        <path class="touchpoint-chart__area" d="M {{ $series['area'] }}" fill="url(#experience-fill-{{ $loop->index }})"></path>
                                        <polyline
                                            class="touchpoint-chart__line"
                                            points="{{ $series['polyline'] }}"
                                            style="stroke: {{ $series['color'] }}; stroke-dasharray: {{ $series['dash'] }};"
                                        ></polyline>
                                        @foreach ($series['points_meta'] as $point)
                                            <circle
                                                class="touchpoint-chart__dot"
                                                cx="{{ $point['x'] }}"
                                                cy="{{ $point['y'] }}"
                                                r="{{ $chartSpace['dot_radius'] }}"
                                                fill="#fff"
                                                stroke="{{ $series['color'] }}"
                                                stroke-width="0.5"
                                                tabindex="0"
                                                aria-label="{{ $series['label'] }} · {{ $point['label'] }}：{{ $point['value'] }}"
                                                data-series="{{ $series['label'] }}"
                                                data-label="{{ $point['label'] }}"
                                                data-value="{{ $point['value'] }}"
                                                data-color="{{ $series['color'] }}"
                                            >
                                                <title>{{ $series['label'] }} · {{ $point['label'] }}：{{ $point['value'] }}</title>
                                            </circle>
                                        @endforeach
                                    @endforeach
                                </svg>
                            </div>
                            <div class="touchpoint-chart__tooltip" role="tooltip" aria-hidden="true">
                                <strong data-tooltip-label>体验指标</strong>
                                <span data-tooltip-value>--</span>
                            </div>
                        </div>
                        <ul class="touchpoint-chart__axis" style="grid-template-columns: repeat({{ $pointCount }}, minmax(0, 1fr));">
                            @foreach ($experienceLabels as $label)
                                <li>
                                    <span class="touchpoint-chart__axis-bar"></span>
                                    <span>{{ $label }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <div class="touchpoint-chart__legend">
                            @foreach ($experienceSeries as $series)
                                <div class="touchpoint-chart__legend-item">
                                    <span class="touchpoint-chart__legend-dot" style="background: {{ $series['color'] }};"></span>
                                    <div class="touchpoint-chart__legend-meta">
                                        <span>{{ $series['label'] }}</span>
                                        <small>{{ $series['latest'] }}</small>
                                        @if ($series['delta'] !== null)
                                            <span class="touchpoint-chart__legend-delta {{ $series['delta'] >= 0 ? 'text-success' : 'text-danger' }}">
                                                <i class="bi {{ $series['delta'] >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right' }}"></i>
                                                {{ $series['delta'] >= 0 ? '+' : '' }}{{ $series['delta'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <p class="section-title mb-1">交付里程碑 / Roadmap</p>
                    <p class="section-subtitle">标签页可直接打开相关页面并固定</p>
                    <ul class="list-stacked mt-4">
                        @foreach ($roadmap as $index => $node)
                            <li class="d-flex gap-3">
                                <div class="timeline-dot mt-1 {{ $index === array_key_last($roadmap) ? 'last' : '' }}"></div>
                                <div>
                                    <div class="d-flex justify-content-between flex-wrap gap-2">
                                        <strong>{{ $node['title'] }}</strong>
                                        <span class="text-muted small">{{ $node['time'] }}</span>
                                    </div>
                                    <div class="text-muted small">{{ $node['owner'] }}</div>
                                    <span class="badge bg-primary-subtle text-primary mt-2">{{ $node['status'] }}</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <p class="section-title mb-1">系统健康分层</p>
                    <p class="section-subtitle">实时链路监控 + 自动自愈编排入口</p>
                    <div class="mt-4 d-flex flex-column gap-3">
                        @foreach ($systemHealth as $service)
                            <div class="p-3 border rounded-4 d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <strong>{{ $service['service'] }}</strong>
                                    <span class="health-pill {{ $service['status'] === 'good' ? 'health-pill--good' : 'health-pill--warn' }}">
                                        {{ $service['status'] === 'good' ? 'Healthy' : 'Observe' }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between text-muted small">
                                    <span>可用性 {{ $service['uptime'] }}</span>
                                    <span>延迟 {{ $service['latency'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-lg-7">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <p class="section-title mb-1">团队容量 / Sprint 负载</p>
                    <p class="section-subtitle">结合 iframe 标签的实际使用行为推算</p>
                    <div class="mt-4 d-flex flex-column gap-4">
                        @foreach ($teamCapacity as $team)
                            <div>
                                <div class="d-flex justify-content-between mb-2">
                                    <strong>{{ $team['name'] }}</strong>
                                    <span class="text-muted small">{{ $team['slots'] }}</span>
                                </div>
                                <div class="capacity-bar">
                                    <span style="width: {{ $team['usage'] * 100 }}%;"></span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <p class="section-title mb-1">标签 / Iframe 活跃事件</p>
                            <p class="section-subtitle mb-0">展示最近 10 条跨标签动作</p>
                        </div>
                        <button class="btn btn-sm btn-outline-dark" data-demo="true">查看全部</button>
                    </div>
                    <div class="list-group list-group-flush">
                        @foreach ($activityFeed as $activity)
                            <div class="list-group-item border-0 px-0 py-3 d-flex gap-3">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                                    {{ mb_substr($activity['user'], 0, 1) }}
                                </div>
                                <div>
                                    <div class="fw-semibold">
                                        {{ $activity['user'] }} <span class="text-muted">{{ $activity['action'] }}</span>
                                    </div>
                                    <div class="text-primary small fw-semibold">{{ $activity['target'] }}</div>
                                    <div class="text-muted small">{{ $activity['time'] }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-6">
            <section class="dashboard-card h-100">
                <div class="dashboard-card__body">
                    <p class="section-title mb-1">自动化快捷操作</p>
                    <p class="section-subtitle">所有入口都支持在标签中打开并保持状态</p>
                    <div class="mt-4 d-flex flex-column gap-3">
                        @foreach ($quickActions as $action)
                            <a class="quick-action text-decoration-none text-reset" href="{{ $action['href'] }}" target="_blank">
                                <div class="quick-action__icon" style="background: {{ $action['accent'] }};">
                                    <i class="bi {{ $action['icon'] }}"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold">{{ $action['label'] }}</div>
                                    <p class="text-muted small mb-0">{{ $action['description'] }}</p>
                                </div>
                                <i class="bi bi-arrow-up-right text-muted ms-auto"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const initExperienceChart = () => {
        document.querySelectorAll('.js-touchpoint-chart').forEach((chart) => {
            const tooltip = chart.querySelector('.touchpoint-chart__tooltip');
            const tooltipLabel = tooltip?.querySelector('[data-tooltip-label]');
            const tooltipValue = tooltip?.querySelector('[data-tooltip-value]');
            const points = chart.querySelectorAll('circle[data-series]');

            if (!tooltip || !tooltipLabel || !tooltipValue || !points.length) {
                return;
            }

            let activeCircle = null;

            const setTooltipPosition = (clientX, clientY) => {
                const rect = chart.getBoundingClientRect();
                const x = clamp(clientX - rect.left, 8, rect.width - 8);
                const y = clamp(clientY - rect.top, 8, rect.height - 8);
                tooltip.style.left = `${x}px`;
                tooltip.style.top = `${y}px`;
            };

            const showTooltip = (circle, event) => {
                activeCircle = circle;
                tooltipLabel.textContent = `${circle.dataset.series} · ${circle.dataset.label}`;
                tooltipValue.textContent = circle.dataset.value;
                tooltip.style.setProperty('--tooltip-accent', circle.dataset.color || '#6366f1');
                tooltip.setAttribute('data-visible', 'true');
                tooltip.setAttribute('aria-hidden', 'false');

                if (event?.clientX) {
                    setTooltipPosition(event.clientX, event.clientY);
                    return;
                }

                const circleRect = circle.getBoundingClientRect();
                setTooltipPosition(
                    circleRect.left + circleRect.width / 2,
                    circleRect.top + circleRect.height / 2
                );
            };

            const hideTooltip = () => {
                activeCircle = null;
                tooltip.removeAttribute('data-visible');
                tooltip.setAttribute('aria-hidden', 'true');
            };

            points.forEach((circle) => {
                const handleEnter = (event) => showTooltip(circle, event);
                const handleMove = (event) => {
                    if (activeCircle !== circle) {
                        return;
                    }
                    setTooltipPosition(event.clientX, event.clientY);
                };

                circle.addEventListener('pointerenter', handleEnter);
                circle.addEventListener('pointermove', handleMove);
                circle.addEventListener('pointerleave', hideTooltip);
                circle.addEventListener('focus', handleEnter);
                circle.addEventListener('blur', hideTooltip);
            });

            chart.addEventListener('pointerleave', hideTooltip);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initExperienceChart, { once: true });
    } else {
        initExperienceChart();
    }
})();
</script>
@endpush

@push('admin_scripts')
<script>
(function () {
    'use strict';

    document.querySelectorAll('[data-demo="true"]').forEach(function (el) {
        el.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            window.alert('演示交互：接入真实逻辑后即可生效');
        });
    });
})();
</script>
@endpush
