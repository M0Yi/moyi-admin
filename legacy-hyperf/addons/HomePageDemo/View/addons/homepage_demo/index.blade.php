@extends('layouts.app')

@section('title', $title)

@push('styles')
<style>
.demo-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 0;
    margin-bottom: 40px;
}

.demo-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 20px;
}

.feature-card {
    background: white;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
}

.plugin-info {
    background: #f8f9fa;
    border-radius: 10px;
    padding: 30px;
    margin-bottom: 40px;
}

.status-indicator {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #28a745;
    margin-right: 8px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.time-display {
    font-family: 'Courier New', monospace;
    background: #e9ecef;
    padding: 10px 15px;
    border-radius: 5px;
    display: inline-block;
}
</style>
@endpush

@section('content')
<!-- 头部区域 -->
<div class="demo-header">
    <div class="demo-content">
        <h1 class="display-4 font-weight-bold">{{ $title }}</h1>
        <p class="lead">{{ $description }}</p>
        <div class="mt-4">
            <span class="status-indicator"></span>
            <span class="text-white">插件运行正常 | 当前站点：{{ $site_name }}</span>
        </div>
    </div>
</div>

<!-- 主要内容 -->
<div class="demo-content">

    <!-- 插件信息 -->
    <div class="plugin-info">
        <h3 class="mb-3">
            <i class="fas fa-info-circle text-primary"></i>
            插件信息
        </h3>
        <div class="row">
            <div class="col-md-6">
                <p><strong>插件名称：</strong>{{ $plugin_info['name'] }}</p>
                <p><strong>版本号：</strong>{{ $plugin_info['version'] }}</p>
            </div>
            <div class="col-md-6">
                <p><strong>描述：</strong>{{ $plugin_info['description'] }}</p>
                <p><strong>当前时间：</strong><span class="time-display">{{ $current_time }}</span></p>
            </div>
        </div>
    </div>

    <!-- 功能特性 -->
    <h2 class="text-center mb-5">功能特性展示</h2>
    <div class="row">
        @foreach($features as $feature => $description)
        <div class="col-md-6 col-lg-3 mb-4">
            <div class="feature-card text-center">
                <div class="mb-3">
                    <i class="fas fa-star text-warning" style="font-size: 2rem;"></i>
                </div>
                <h5 class="card-title">{{ $feature }}</h5>
                <p class="card-text text-muted">{{ $description }}</p>
            </div>
        </div>
        @endforeach
    </div>

    <!-- 技术说明 -->
    <div class="mt-5">
        <h3 class="mb-4 text-center">技术实现说明</h3>
        <div class="row">
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-cogs text-primary" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">插件配置</h5>
                    <p class="text-muted">通过 config.php 配置 replace_homepage 字段实现首页替换</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-route text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">路由重写</h5>
                    <p class="text-muted">RouteLoader 自动检测并覆盖默认首页路由</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fab fa-laravel text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Blade 模板</h5>
                    <p class="text-muted">使用 Hyperf Blade 模板引擎渲染页面</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 操作按钮 -->
    <div class="text-center mt-5">
        <a href="/admin" class="btn btn-primary btn-lg mr-3">
            <i class="fas fa-tachometer-alt"></i>
            进入管理后台
        </a>
        <a href="/api/homepage_demo" class="btn btn-outline-primary btn-lg" target="_blank">
            <i class="fas fa-code"></i>
            查看 API 接口
        </a>
    </div>

</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 更新时间显示
    function updateTime() {
        const timeElements = document.querySelectorAll('.time-display');
        const now = new Date();
        const timeString = now.toLocaleString('zh-CN');

        timeElements.forEach(element => {
            if (element.textContent !== timeString) {
                element.textContent = timeString;
            }
        });
    }

    // 每秒更新一次时间
    setInterval(updateTime, 1000);

    // 页面加载时的欢迎提示
    console.log('🎉 欢迎使用首页替换演示插件！');
    console.log('📖 此插件演示了如何通过插件系统替换默认首页');
    console.log('🔧 配置位置：addons/HomePageDemo/config.php');
    console.log('🎨 视图位置：addons/HomePageDemo/View/addons/homepage_demo/index.blade.php');
});
</script>
@endpush
