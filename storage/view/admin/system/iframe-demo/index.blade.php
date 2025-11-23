@extends('admin.layouts.admin')

@section('title', 'Iframe 模式体验中心')

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
.iframe-demo-page .card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.06);
}

.iframe-demo-page code {
    font-size: 0.85rem;
    background-color: #f3f4f6;
    color: #6366f1;
    padding: 0.1rem 0.4rem;
    border-radius: 4px;
}

.iframe-demo-page .feature-grid .card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.iframe-demo-page .feature-grid .card:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.09);
}

.iframe-demo-page .diagnostic-panel dl {
    margin-bottom: 0;
}

.iframe-demo-page .action-log {
    min-height: 120px;
    max-height: 220px;
    overflow-y: auto;
    font-size: 0.85rem;
}

.iframe-demo-page #iframe-nesting-level {
    font-size: 0.9rem;
    padding: 0.35rem 0.65rem;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.iframe-demo-page #nesting-challenge-card {
    border: 2px dashed #dee2e6;
    background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
}

.iframe-demo-page .code-example-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.iframe-demo-page .code-example-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08) !important;
}

.iframe-demo-page .code-example-card pre {
    overflow-x: auto;
    max-height: 300px;
}

.iframe-demo-page .code-example-card code {
    background: transparent;
    color: inherit;
    padding: 0;
}
</style>
@endpush

@section('content')
<div class="container-fluid py-4 iframe-demo-page">
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex flex-wrap align-items-start gap-3">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h5 class="mb-0">Iframe 模式体验中心</h5>
                    <span class="badge bg-primary-subtle text-primary">Beta</span>
                </div>
                <p class="text-muted mb-0">
                    在这里可以快速验证标签页系统、iframe shell 以及内页通信协议，帮助团队统一后台页面的交互体验。
                </p>
            </div>
        </div>
    </div>

    {{-- 事件日志窗口 --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div>
                    <h6 class="mb-1">事件日志</h6>
                    <small class="text-muted">实时显示 iframe 通信事件</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearEventLog()">
                    <i class="bi bi-trash me-1"></i>
                    清空日志
                </button>
            </div>
            @if ($isEmbedded)
            <div class="d-flex flex-wrap gap-2 mb-3">
                <button type="button" class="btn btn-success btn-sm"
                        data-iframe-demo-action="success"
                        title="发送成功通知，请求父页刷新标签">
                    <i class="bi bi-check2-circle me-1"></i>
                    success()
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm"
                        data-iframe-demo-action="notify"
                        title="发送自定义事件通知">
                    <i class="bi bi-broadcast me-1"></i>
                    notify()
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        data-iframe-demo-action="close"
                        title="请求父页关闭当前标签">
                    <i class="bi bi-x-circle me-1"></i>
                    close()
                </button>
                <button type="button" class="btn btn-info btn-sm"
                        data-iframe-demo-action="refresh-parent"
                        title="通知父页面刷新当前标签页">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    通知父页刷新
                </button>
                <button type="button" class="btn btn-warning btn-sm"
                        data-iframe-demo-action="notify-custom"
                        title="发送自定义消息给父页面">
                    <i class="bi bi-chat-dots me-1"></i>
                    通知父页自定义消息
                </button>
                <button type="button" class="btn btn-primary btn-sm"
                        data-iframe-demo-action="refresh-ajax"
                        title="刷新 AJAX 数据（模拟）">
                    <i class="bi bi-arrow-repeat me-1"></i>
                    刷新 AJAX 数据
                </button>
                <button type="button" class="btn btn-outline-dark btn-sm"
                        data-iframe-demo-action="refresh-main-frame"
                        title="刷新主框架（包含菜单等整体布局）">
                    <i class="bi bi-bootstrap-reboot me-1"></i>
                    刷新主框架
                </button>
            </div>
            @endif
            <div class="bg-light rounded-3 p-3 action-log"
                 data-iframe-demo-log>
                <div data-iframe-demo-placeholder class="text-muted text-center py-3">等待事件...</div>
            </div>
        </div>
    </div>

    {{-- 三个核心按钮的代码示例 --}}
    <div class="row g-4 mb-4">
        @php
            $iframeDemoUrl = admin_route('system/iframe-demo');
            $modalDemoUrl = admin_route('system/iframe-demo/modal-demo');
        @endphp

        {{-- 示例 4: 在 Iframe Shell 预览 --}}
        @include('components.iframe-demo.code-card', [
            'iconWrapperClass' => 'bg-info bg-opacity-10 text-info',
            'iconClass' => 'bi bi-bounding-box-circles',
            'title' => '在 Iframe Shell 预览',
            'description' => '在弹窗中预览页面，适用于弹窗式流程（如 CRUD 表单）。',
            'buttonHtml' => <<<HTML
<button class="btn btn-outline-primary btn-sm w-100"
        type="button"
        data-iframe-shell-trigger="iframe-demo"
        data-iframe-shell-src="{$iframeDemoUrl}"
        data-iframe-shell-title="Iframe Shell 体验"
        data-iframe-shell-channel="iframe-demo">
    <i class="bi bi-bounding-box-circles me-1"></i>
    在 Iframe Shell 预览
</button>
HTML,
            'code' => <<<'CODE'
<!-- HTML 方式：使用 data 属性 -->
<button
    data-iframe-shell-trigger="iframe-demo"
    data-iframe-shell-src="/admin/system/iframe-demo"
    data-iframe-shell-title="Iframe Shell 体验"
    data-iframe-shell-channel="iframe-demo">
    在 Iframe Shell 预览
</button>

<!-- JavaScript 方式：手动调用 -->
<script>
if (window.AdminIframeShell) {
    window.AdminIframeShell.open({
        src: '/admin/system/iframe-demo',
        title: 'Iframe Shell 体验',
        channel: 'iframe-demo'
    });
}
</script>
CODE,
        ])

        {{-- Iframe Shell 使用方式介绍 --}}
        <div class="col-md-12 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-2 mb-3">
                        <div class="bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-book"></i>
                        </div>
                        <h6 class="mb-0">Iframe Shell 使用方式</h6>
                    </div>
                    
                    <div class="row g-3">
                        {{-- HTML 属性方式 --}}
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-primary-subtle text-primary">方式 1</span>
                                <span class="small fw-semibold">HTML 属性方式（推荐）</span>
                            </div>
                            <div class="bg-dark rounded-3 p-3">
                                <pre class="text-white small mb-0" style="font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"><code>&lt;!-- 基础用法 --&gt;
&lt;button
    data-iframe-shell-trigger="my-trigger"
    data-iframe-shell-src="/admin/users/create"
    data-iframe-shell-title="新建用户"
    data-iframe-shell-channel="users"&gt;
    添加用户
&lt;/button&gt;

&lt;!-- 隐藏"新标签"和"新窗口"按钮（适用于表单页面） --&gt;
&lt;button
    data-iframe-shell-trigger="create-user"
    data-iframe-shell-src="/admin/users/create"
    data-iframe-shell-title="新建用户"
    data-iframe-shell-channel="users"
    data-iframe-shell-hide-actions="true"&gt;
    添加用户
&lt;/button&gt;</code></pre>
                            </div>
                        </div>
                        
                        {{-- JavaScript API 方式 --}}
                        <div class="col-md-6">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success-subtle text-success">方式 2</span>
                                <span class="small fw-semibold">JavaScript API 方式</span>
                            </div>
                            <div class="bg-dark rounded-3 p-3">
                                <pre class="text-white small mb-0" style="font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"><code>// 基础用法
if (window.Admin?.iframeShell) {
    window.Admin.iframeShell.open({
        src: '/admin/users/create',
        title: '新建用户',
        channel: 'users'
    });
}

// 隐藏"新标签"和"新窗口"按钮
window.Admin.iframeShell.open({
    src: '/admin/users/create',
    title: '新建用户',
    channel: 'users',
    hideActions: true
});

// 监听关闭事件
window.Admin.iframeShell.on('after-close', function(event) {
    console.log('已关闭', event.payload);
    // 可以在这里刷新列表等操作
});</code></pre>
                            </div>
                        </div>
                    </div>
                    
                    {{-- 常用属性说明 --}}
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="small fw-semibold mb-2">常用属性说明</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless align-middle mb-0 small">
                                <thead class="table-light">
                                    <tr>
                                        <th class="fw-semibold" style="width: 200px;">属性</th>
                                        <th class="fw-semibold">说明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><code>data-iframe-shell-trigger</code></td>
                                        <td>触发标识，用于区分不同的触发源（可选，建议设置唯一值）</td>
                                    </tr>
                                    <tr>
                                        <td><code>data-iframe-shell-src</code></td>
                                        <td>要打开的页面 URL（必填）</td>
                                    </tr>
                                    <tr>
                                        <td><code>data-iframe-shell-title</code></td>
                                        <td>弹窗标题（必填）</td>
                                    </tr>
                                    <tr>
                                        <td><code>data-iframe-shell-channel</code></td>
                                        <td>通信频道，用于区分不同的 iframe shell 实例（必填）</td>
                                    </tr>
                                    <tr>
                                        <td><code>data-iframe-shell-hide-actions</code></td>
                                        <td>设置为 <code>true</code> 时隐藏"新标签"和"新窗口"按钮（适用于表单页面）</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 自定义 Iframe Shell 示例：隐藏操作按钮 --}}
        @include('components.iframe-demo.code-card', [
            'iconWrapperClass' => 'bg-warning bg-opacity-10 text-warning',
            'iconClass' => 'bi bi-gear',
            'title' => '自定义 Iframe Shell（隐藏操作按钮）',
            'description' => '适用于表单页面，隐藏"新标签"和"新窗口"按钮，提供更简洁的界面。',
            'buttonHtml' => <<<HTML
<button class="btn btn-warning btn-sm w-100"
        type="button"
        data-iframe-shell-trigger="custom-demo"
        data-iframe-shell-src="{$iframeDemoUrl}"
        data-iframe-shell-title="自定义 Iframe Shell 示例"
        data-iframe-shell-channel="custom-demo"
        data-iframe-shell-hide-actions="true">
    <i class="bi bi-gear me-1"></i>
    自定义 Iframe Shell（隐藏操作按钮）
</button>
HTML,
            'code' => <<<'CODE'
<!-- HTML 方式：隐藏操作按钮 -->
<button
    data-iframe-shell-trigger="create-user"
    data-iframe-shell-src="/admin/users/create"
    data-iframe-shell-title="新建用户"
    data-iframe-shell-channel="users"
    data-iframe-shell-hide-actions="true">
    添加用户
</button>

<!-- JavaScript 方式：隐藏操作按钮 -->
<script>
if (window.Admin?.iframeShell) {
    window.Admin.iframeShell.open({
        src: '/admin/users/create',
        title: '新建用户',
        channel: 'users',
        hideActions: true  // 隐藏"新标签"和"新窗口"按钮
    });
}
</script>

<!-- 在表单提交成功后关闭弹窗 -->
<script>
// 在表单页面中，提交成功后调用
if (window.AdminIframeClient) {
    window.AdminIframeClient.success({
        message: '保存成功',
        refreshParent: true,   // 请求父页刷新当前标签（列表页）
        closeCurrent: true      // 关闭当前弹窗
    });
}
</script>
CODE,
        ])

        {{-- 示例 1: 在当前 iframe 载入 --}}
        @include('components.iframe-demo.code-card', [
            'iconWrapperClass' => 'bg-primary bg-opacity-10 text-primary',
            'iconClass' => 'bi bi-arrow-right-circle',
            'title' => '在当前 iframe 载入',
            'description' => '在当前 iframe 中导航到新页面，适用于页面内跳转。',
            'buttonHtml' => <<<HTML
<button class="btn btn-primary btn-sm w-100"
        type="button"
        onclick="window.location.href='{$modalDemoUrl}'">
    <i class="bi bi-arrow-right-circle me-1"></i>
    在当前iframe载入
</button>
HTML,
            'code' => <<<'CODE'
// 方式 1: 直接使用 window.location
window.location.href = '/admin/system/iframe-demo/modal-demo';

// 方式 2: 使用 location.assign()
location.assign('/admin/system/iframe-demo/modal-demo');

// 方式 3: 使用 location.replace() (不保留历史记录)
location.replace('/admin/system/iframe-demo/modal-demo');
CODE,
        ])

        {{-- 示例 2: 测试新标签（直接打开） --}}
        @include('components.iframe-demo.code-card', [
            'iconWrapperClass' => 'bg-success bg-opacity-10 text-success',
            'iconClass' => 'bi bi-collection',
            'title' => '测试新标签（直接打开）',
            'description' => '直接打开新标签，无需 shell。需要访问主框架的 TabManager。',
            'buttonHtml' => <<<HTML
<button class="btn btn-success btn-sm w-100"
        type="button"
        onclick="testOpenNewTab('{$modalDemoUrl}', 'Modal Demo')"
        title="从子iframe中直接打开新标签（需要访问主框架的TabManager）">
    <i class="bi bi-collection me-1"></i>
    测试新标签（直接打开）
</button>
HTML,
            'code' => <<<'CODE'
// 使用 AdminIframeShell.openTab() 方法
// 自动处理 TabManager 查找、URL 解析和错误处理

// 方式 1: 基础用法
if (window.Admin?.iframeShell?.openTab) {
    window.Admin.iframeShell.openTab(
        '/admin/system/iframe-demo/modal-demo',
        'Modal Demo'
    );
}

// 方式 2: 带选项（如果 TabManager 不可用，降级使用 window.open）
window.Admin.iframeShell.openTab(
    '/admin/system/iframe-demo/modal-demo',
    'Modal Demo',
    {
        fallbackToWindow: true
    }
);
CODE,
        ])

        {{-- 示例 3: 关闭当前标签页 --}}
        @include('components.iframe-demo.code-card', [
            'iconWrapperClass' => 'bg-danger bg-opacity-10 text-danger',
            'iconClass' => 'bi bi-x-circle',
            'title' => '关闭当前标签页',
            'description' => '关闭当前标签页。需要访问主框架的 TabManager。',
            'buttonHtml' => <<<HTML
<button class="btn btn-danger btn-sm w-100"
        type="button"
        onclick="testCloseCurrentTab()"
        title="关闭当前标签页（需要访问主框架的TabManager）">
    <i class="bi bi-x-circle me-1"></i>
    关闭当前标签页
</button>
HTML,
            'code' => <<<'CODE'
// 使用 AdminIframeShell.closeCurrentTab() 方法
// 自动处理 TabManager 查找和错误处理

// 方式 1: 基础用法
if (window.Admin?.iframeShell?.closeCurrentTab) {
    window.Admin.iframeShell.closeCurrentTab();
}

// 方式 2: 带选项（如果 TabManager 不可用，降级使用 history.back()）
window.Admin.iframeShell.closeCurrentTab({
    fallbackToHistory: true
});
CODE,
        ])
    </div>

    @php
        $iframeActionCards = [
            [
                'iconWrapperClass' => 'bg-success bg-opacity-10 text-success',
                'iconClass' => 'bi bi-check2-circle',
                'title' => 'success()：操作成功并刷新父页',
                'description' => '常用于表单提交成功后，刷新父页列表并可选关闭当前标签/弹窗。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-success btn-sm"
        data-iframe-demo-action="success">
    <i class="bi bi-check2-circle me-1"></i>
    触发 success()
</button>
HTML,
                'code' => <<<'CODE'
<button type="button" class="btn btn-success btn-sm"
        onclick="window.AdminIframeClient && window.AdminIframeClient.success({
            message: '操作成功',
            refreshParent: true,   // 请求父页刷新当前标签
            closeCurrent: false    // 是否关闭当前标签/弹窗
        });">
    保存成功
</button>
CODE,
            ],
            [
                'iconWrapperClass' => 'bg-primary bg-opacity-10 text-primary',
                'iconClass' => 'bi bi-broadcast',
                'title' => 'notify()：发送自定义事件',
                'description' => '父页监听事件名，自行决定如何处理（刷新、弹窗、打点等）。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-outline-primary btn-sm"
        data-iframe-demo-action="notify">
    <i class="bi bi-broadcast me-1"></i>
    触发 notify()
</button>
HTML,
                'code' => <<<'CODE'
<button type="button" class="btn btn-outline-primary btn-sm"
        onclick="window.AdminIframeClient && window.AdminIframeClient.notify('demo-event', {
            triggeredAt: Date.now(),
            note: '这里可以放任意业务数据'
        });">
    发送自定义事件
</button>
CODE,
            ],
            [
                'iconWrapperClass' => 'bg-secondary bg-opacity-10 text-secondary',
                'iconClass' => 'bi bi-x-circle',
                'title' => 'close()：请求父页关闭当前标签',
                'description' => '常用于「取消」「返回」等场景，仅关闭当前标签/弹窗。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-outline-secondary btn-sm"
        data-iframe-demo-action="close">
    <i class="bi bi-x-circle me-1"></i>
    触发 close()
</button>
HTML,
                'code' => <<<'CODE'
<button type="button" class="btn btn-outline-secondary btn-sm"
        onclick="window.AdminIframeClient && window.AdminIframeClient.close({
            reason: '用户取消操作'
        });">
    关闭当前页
</button>
CODE,
            ],
            [
                'iconWrapperClass' => 'bg-info bg-opacity-10 text-info',
                'iconClass' => 'bi bi-arrow-clockwise',
                'title' => '通知父页刷新当前标签',
                'description' => '用于列表页「手动刷新」按钮，保持当前标签不关闭。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-info btn-sm"
        data-iframe-demo-action="refresh-parent">
    <i class="bi bi-arrow-clockwise me-1"></i>
    通知父页刷新
</button>
HTML,
                'code' => <<<'CODE'
<button type="button" class="btn btn-info btn-sm"
        onclick="window.AdminIframeClient && window.AdminIframeClient.success({
            message: '请求刷新',
            refreshParent: true,
            refreshUrl: window.location.href, // 刷新当前 URL
            closeCurrent: false
        });">
    刷新当前标签
</button>
CODE,
            ],
            [
                'iconWrapperClass' => 'bg-warning bg-opacity-10 text-warning',
                'iconClass' => 'bi bi-arrow-repeat',
                'title' => '刷新 AJAX 数据并通知父页',
                'description' => '适合「刷新当前卡片/局部」的场景，顺便告诉父页本页已更新。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-primary btn-sm"
        data-iframe-demo-action="refresh-ajax">
    <i class="bi bi-arrow-repeat me-1"></i>
    刷新 AJAX 数据
</button>
HTML,
                'code' => <<<'CODE'
<script>
// 示例：刷新局部数据后，通过 success() 告诉父页「我更新过了」
function refreshAjaxDataExample() {
    // TODO: 在这里发起实际的 AJAX 请求
    if (window.AdminIframeClient) {
        window.AdminIframeClient.success({
            message: '局部刷新完成',
            refreshParent: true,
            closeCurrent: false
        });
    }
}
</script>

<button type="button" class="btn btn-primary btn-sm"
        onclick="refreshAjaxDataExample();">
    刷新 AJAX 数据
</button>
CODE,
            ],
            [
                'iconWrapperClass' => 'bg-dark bg-opacity-10 text-dark',
                'iconClass' => 'bi bi-bootstrap-reboot',
                'title' => '刷新主框架（菜单等整体重载）',
                'description' => '适合修改菜单配置、权限后，让主框架整体重新载入。',
                'buttonHtml' => <<<HTML
<button type="button" class="btn btn-outline-dark btn-sm"
        data-iframe-demo-action="refresh-main-frame">
    <i class="bi bi-bootstrap-reboot me-1"></i>
    刷新主框架
</button>
HTML,
                'code' => <<<'CODE'
<button type="button" class="btn btn-outline-dark btn-sm"
        onclick="if (window.AdminIframeClient?.refreshMainFrame) {
            window.AdminIframeClient.refreshMainFrame({
                message: '示例：主框架即将刷新以载入最新配置',
                delay: 600,
                toastType: 'info'
            });
        } else {
            alert('AdminIframeClient.refreshMainFrame 不可用，请通过主框架标签页打开本页后再试。');
        }">
    刷新主框架
</button>
CODE,
            ],
        ];
    @endphp

    {{-- AdminIframeClient 按钮一览：一个按钮一段代码 --}}
    <div class="row g-4 mb-4">
        @foreach ($iframeActionCards as $card)
            @php
                $columnClass = $card['columnClass'] ?? 'col-md-4';
                $iconWrapperClass = $card['iconWrapperClass'] ?? 'bg-primary bg-opacity-10 text-primary';
                $iconClass = $card['iconClass'] ?? 'bi bi-info-circle';
                $title = $card['title'] ?? '示例标题';
                $description = $card['description'] ?? '';
                $buttonHtml = $card['buttonHtml'] ?? '';
                $code = $card['code'] ?? '';
            @endphp
            <div class="{{ $columnClass }}">
                <div class="card border-0 shadow-sm h-100 code-example-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <div class="{{ $iconWrapperClass }} rounded-circle d-flex align-items-center justify-content-center"
                                 style="width: 36px; height: 36px;">
                                <i class="{{ $iconClass }}"></i>
                            </div>
                            <h6 class="mb-0">{{ $title }}</h6>
                        </div>
                        @if($description !== '')
                            <p class="small text-muted mb-3">
                                {{ $description }}
                            </p>
                        @endif

                        @if($buttonHtml !== '')
                            <div class="mb-3">
                                {!! $buttonHtml !!}
                            </div>
                        @endif

                        @if($code !== '')
                            <div class="bg-dark rounded-3 p-3">
                                <pre class="text-white small mb-0"
                                     style="font-family: SFMono-Regular, Consolas, 'Liberation Mono', Menlo, monospace; font-size: 0.75rem; line-height: 1.5; white-space: pre-wrap; word-wrap: break-word;"><code>{{ $code }}</code></pre>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h6 class="mb-1">嵌入态诊断</h6>
                            <small class="text-muted">renderAdmin() 自动注入的上下文信息</small>
                        </div>
                        <span class="badge {{ $isEmbedded ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">
                            {{ $isEmbedded ? 'Iframe / 内嵌模式' : 'Shell / 主框架模式' }}
                        </span>
                    </div>
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4 text-muted">标准化地址</dt>
                        <dd class="col-sm-8 mb-2">
                            <code class="d-inline-block text-truncate" style="max-width: 100%;">{{ $normalizedUrl }}</code>
                        </dd>

                        <dt class="col-sm-4 text-muted">嵌套层级</dt>
                        <dd class="col-sm-8 mb-2">
                            <span id="iframe-nesting-level" class="badge bg-secondary-subtle text-secondary">计算中...</span>
                            <small class="text-muted d-block mt-1" id="iframe-nesting-hint"></small>
                        </dd>

                        <dt class="col-sm-4 text-muted">Iframe Channel</dt>
                        <dd class="col-sm-8 mb-2">
                            {{ $diagnostics['channel'] ?? '未携带（主框架模式）' }}
                        </dd>

                        <dt class="col-sm-4 text-muted">Sec-Fetch-Dest</dt>
                        <dd class="col-sm-8 mb-2">
                            {{ $diagnostics['sec_fetch_dest'] ?? '无' }}
                        </dd>

                        <dt class="col-sm-4 text-muted">Query 参数</dt>
                        <dd class="col-sm-8 mb-0">
                            @if(!empty($diagnostics['query']))
                                <ul class="list-unstyled mb-0">
                                    @foreach($diagnostics['query'] as $key => $value)
                                    <li class="text-break">
                                        <span class="text-muted">{{ $key }}</span> =
                                        <code>
                                            @if(is_scalar($value))
                                                {{ $value }}
                                            @else
                                                {{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}
                                            @endif
                                        </code>
                                    </li>
                                    @endforeach
                                </ul>
                            @else
                                <span class="text-muted">无</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>

        </div>

        <div class="col-xl-5">
            @if ($isEmbedded)
            <div class="alert alert-success border-0 shadow-sm">
                <h6 class="fw-semibold mb-1">已处于 iframe 模式</h6>
                <p class="mb-0 small text-muted">
                    下面的按钮会直接调用 <code>window.AdminIframeClient</code>，帮助验证与你的父级标签页之间的通信。
                </p>
            </div>

            <div class="card border-0 shadow-sm mb-3" id="nesting-challenge-card" style="display: none;">
                <div class="card-body">
                    <div class="d-flex align-items-start gap-2">
                        <div class="fs-4">🎯</div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1">套娃挑战</h6>
                            <p class="small text-muted mb-2">
                                点击下方按钮可以在当前 iframe 中再打开一个 iframe，实现无限嵌套！
                                试试看能嵌套多少层？每层都会显示不同的颜色和提示。
                            </p>
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                    data-iframe-shell-trigger="nesting-challenge"
                                    data-iframe-shell-src="{{ admin_route('system/iframe-demo') }}"
                                    data-iframe-shell-title="套娃挑战 L<span id='next-level'>?</span>"
                                    data-iframe-shell-channel="nesting-challenge">
                                <i class="bi bi-box-arrow-in-down-right me-1"></i>
                                再嵌套一层
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @else
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="mb-2">如何体验 iframe API？</h6>
                    <ol class="ps-3 small text-muted mb-3">
                        <li>点击上方任意按钮，以标签页或 iframe shell 方式打开本页面。</li>
                        <li>查看“嵌入态诊断”面板，确认 <code>isEmbedded</code> 状态。</li>
                        <li>触发内页交互示例，即可看到父页面收到的消息与行为。</li>
                    </ol>
                    <div class="alert alert-info border-0 mb-0 small">
                        <strong>提示：</strong>菜单中已新增"Iframe 模式体验中心"，团队成员可以直接通过侧边栏访问该示例。
                        <br><br>
                        <strong>关于"测试新标签"功能：</strong>
                        <ul class="mb-0 mt-2">
                            <li>如果当前页面在子 iframe 中，该功能会尝试访问主框架的 TabManager 来打开新标签。</li>
                            <li>如果提示"TabManager 未找到"，请确保：</li>
                            <li style="list-style: none; margin-left: 1.5rem;">
                                ✓ 页面是通过管理后台主框架打开的（通过侧边栏菜单或直接访问主框架 URL）<br>
                                ✓ 主框架的 TabManager 已初始化完成<br>
                                ✓ 没有跨域限制（页面与主框架在同一域名下）
                            </li>
                            <li>建议：从侧边栏菜单"系统管理" → "Iframe 模式体验"打开此页面，确保在主框架中运行。</li>
                        </ul>
                    </div>
                </div>
            </div>
            @endif

            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body">
                    <h6 class="mb-1">常见属性备忘</h6>
                    <table class="table table-sm table-borderless align-middle mb-0 small text-muted">
                        <tbody>
                            <tr>
                                <td class="fw-semibold text-dark">data-admin-tab</td>
                                <td>在主框架打开标签页，自动注入 <code>_embed=1</code></td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-dark">data-iframe-shell-*</td>
                                <td>调用轻量级 iframe shell，常用于弹窗式流程</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-dark">AdminIframeClient.success()</td>
                                <td>通知父页流程成功，常用于保存或提交后关闭</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-dark">AdminIframeClient.notify()</td>
                                <td>发送自定义事件通知，用于自定义通信场景</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-dark">AdminIframeClient.close()</td>
                                <td>请求父页关闭当前标签/弹窗</td>
                            </tr>
                            <tr>
                                <td class="fw-semibold text-dark">normalizedUrl</td>
                                <td>renderAdmin() 注入，用于标签页去重与刷新</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

@if ($isEmbedded)
    @push('admin_scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        'use strict';

        const buttons = document.querySelectorAll('[data-iframe-demo-action]');
        const logContainer = document.querySelector('[data-iframe-demo-log]');

        const appendLog = (text) => {
            if (!logContainer) {
                return;
            }
            const row = document.createElement('div');
            row.className = 'mb-2 pb-2 border-bottom';
            row.style.fontSize = '0.85rem';
            row.innerHTML = `<span class="text-muted">[${new Date().toLocaleTimeString()}]</span> <span class="text-dark">${text}</span>`;
            logContainer.prepend(row);
            const placeholder = logContainer.querySelector('[data-iframe-demo-placeholder]');
            if (placeholder) {
                placeholder.remove();
            }
        };

        // 清空日志函数
        window.clearEventLog = function() {
            if (!logContainer) {
                return;
            }
            logContainer.innerHTML = '<div data-iframe-demo-placeholder class="text-muted text-center py-3">等待事件...</div>';
        };

        /**
         * 刷新 AJAX 数据的标准函数
         * 模拟刷新数据后，通知父页面刷新成功
         */
        window.refreshAjaxData = function() {
            appendLog('→ 开始刷新 AJAX 数据...');
            
            // 模拟 AJAX 请求
            setTimeout(function() {
                // 模拟数据刷新完成
                const refreshTime = new Date().toLocaleTimeString();
                appendLog('✓ AJAX 数据刷新完成 - ' + refreshTime);
                
                // 通知父页面刷新成功
                if (window.AdminIframeClient) {
                    window.AdminIframeClient.success({
                        message: '刷新成功',
                        refreshParent: true,
                        closeCurrent: false
                    });
                    appendLog('✓ 已通知父页面刷新成功');
                } else {
                    // 如果没有 iframe client，直接显示提示
                    showRefreshSuccess();
                }
            }, 500);
        };

        /**
         * 刷新主框架（包含侧边栏菜单等整体布局）
         * 适用于调整了后端菜单配置后，需要让主框架整体重载的场景
         */
        window.refreshMainFrame = function() {
            appendLog('→ 请求刷新主框架（包含菜单等整体布局）...');

            if (window.AdminIframeClient && typeof window.AdminIframeClient.refreshMainFrame === 'function') {
                window.AdminIframeClient.refreshMainFrame({
                    message: '示例：主框架即将刷新以载入最新配置',
                    delay: 600,
                    toastType: 'info'
                });
                appendLog('✓ 通过 AdminIframeClient.refreshMainFrame() 请求刷新主框架');
                return;
            }

            appendLog('⚠ AdminIframeClient.refreshMainFrame 不可用，请通过主框架打开此页面后再试');

            if (window.Admin?.utils?.showToast) {
                window.Admin.utils.showToast('warning', '请在主框架标签页中打开此页面后再尝试刷新主框架');
            } else if (window.showToast) {
                window.showToast('warning', '请在主框架标签页中打开此页面后再尝试刷新主框架');
            } else {
                alert('AdminIframeClient.refreshMainFrame 不可用，请在主框架标签页中打开此页面后再尝试。');
            }
        };

        /**
         * 显示刷新成功提示（模拟收到消息后的处理）
         */
        function showRefreshSuccess() {
            // 尝试使用 Admin 的 toast
            if (window.Admin && typeof window.Admin.utils?.showToast === 'function') {
                window.Admin.utils.showToast('success', '刷新成功');
            } else if (window.showToast && typeof window.showToast === 'function') {
                window.showToast('success', '刷新成功');
            } else {
                // 降级使用 alert
                alert('刷新成功');
            }
            appendLog('✓ 显示刷新成功提示');
        }

        if (!buttons.length) {
            return;
        }

        // 监听来自父页面的消息（用于显示双向通信）
        window.addEventListener('message', function(event) {
            // 安全检查：只处理同源消息
            if (event.origin !== window.location.origin) {
                return;
            }

            const data = event.data;
            if (!data || typeof data !== 'object') {
                return;
            }

            // 显示接收到的所有消息（用于调试和演示）
            if (data.action) {
                const actionName = data.action;
                let logText = '← 收到父页消息 [' + actionName + ']';
                
                if (data.payload) {
                    if (typeof data.payload === 'object') {
                        logText += ': ' + JSON.stringify(data.payload, null, 2);
                    } else {
                        logText += ': ' + data.payload;
                    }
                }
                
                appendLog(logText);
            }
        });

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                if (!window.AdminIframeClient) {
                    alert('AdminIframeClient 尚未注入，请确认通过标签页或 shell 打开。');
                    return;
                }

                const action = button.dataset.iframeDemoAction;

                if (action === 'success') {
                    window.AdminIframeClient.success({
                        message: '示例：操作成功 - ' + new Date().toLocaleTimeString(),
                        refreshParent: true,
                    });
                    appendLog('✓ 调用 success()，请求父页刷新标签');
                } else if (action === 'notify') {
                    window.AdminIframeClient.notify('demo-event', {
                        triggeredAt: Date.now(),
                        note: '自定义事件 Payload',
                        source: 'iframe-demo'
                    });
                    appendLog('✓ 发送 notify("demo-event") 自定义事件');
                } else if (action === 'close') {
                    window.AdminIframeClient.close({
                        reason: '用户主动关闭示例',
                    });
                    appendLog('✓ 触发 close() 请求父页关闭当前标签');
                } else if (action === 'refresh-parent') {
                    // 通知父页面刷新当前标签页
                    window.AdminIframeClient.success({
                        message: '刷新请求 - ' + new Date().toLocaleTimeString(),
                        refreshParent: true,
                        refreshUrl: window.location.href, // 刷新当前页面
                        closeCurrent: false, // 不关闭标签页
                    });
                    appendLog('✓ 通知父页刷新当前标签页（不关闭）');
                } else if (action === 'notify-custom') {
                    // 发送自定义消息给父页面
                    const customData = {
                        type: 'custom-message',
                        timestamp: Date.now(),
                        message: '这是一条自定义消息',
                        data: {
                            userId: 123,
                            action: 'test',
                            metadata: {
                                source: 'iframe-demo',
                                version: '1.0.0'
                            }
                        }
                    };
                    window.AdminIframeClient.notify('custom-message', customData);
                    appendLog('✓ 发送自定义消息给父页面: ' + JSON.stringify(customData, null, 2));
                } else if (action === 'refresh-ajax') {
                    // 刷新 AJAX 数据（模拟）
                    refreshAjaxData();
                } else if (action === 'refresh-main-frame') {
                    // 刷新主框架（包括菜单、标签栏等）
                    refreshMainFrame();
                }
            });
        });
    });
    </script>
    @endpush
@endif

@push('admin_scripts')
<script>
/**
 * 计算 iframe 嵌套层级（套娃深度）
 * @returns {number} 嵌套层级，0 表示在主框架中
 */
function calculateNestingLevel() {
    if (window === window.top) {
        return 0; // 在主框架中
    }
    
    let level = 0;
    let currentWindow = window;
    const maxDepth = 20; // 防止无限循环
    
    try {
        while (currentWindow !== window.top && level < maxDepth) {
            try {
                if (currentWindow.parent === currentWindow) {
                    // 已到达顶层
                    break;
                }
                currentWindow = currentWindow.parent;
                level++;
            } catch (error) {
                // 跨域限制，无法继续向上查找
                break;
            }
        }
    } catch (error) {
        // 无法访问父窗口
    }
    
    return level;
}

/**
 * 显示嵌套层级信息
 */
function displayNestingLevel() {
    const levelEl = document.getElementById('iframe-nesting-level');
    const hintEl = document.getElementById('iframe-nesting-hint');
    
    if (!levelEl || !hintEl) {
        return;
    }
    
    const level = calculateNestingLevel();
    
    // 根据层级设置不同的样式和提示
    let badgeClass = 'bg-secondary-subtle text-secondary';
    let icon = '';
    let hint = '';
    
    if (level === 0) {
        badgeClass = 'bg-primary-subtle text-primary';
        icon = '🏠';
        hint = '当前在主框架中，不是 iframe';
    } else if (level === 1) {
        badgeClass = 'bg-info-subtle text-info';
        icon = '📦';
        hint = '第 1 层嵌套，正常的 iframe 模式';
    } else if (level === 2) {
        badgeClass = 'bg-warning-subtle text-warning';
        icon = '📦📦';
        hint = '第 2 层嵌套，开始套娃了！';
    } else if (level === 3) {
        badgeClass = 'bg-warning-subtle text-warning';
        icon = '📦📦📦';
        hint = '第 3 层嵌套，套娃进行中...';
    } else if (level >= 4 && level < 10) {
        badgeClass = 'bg-danger-subtle text-danger';
        icon = '📦'.repeat(Math.min(level, 5));
        hint = `第 ${level} 层嵌套，深度套娃！${level >= 5 ? '注意性能影响' : ''}`;
    } else {
        badgeClass = 'bg-dark text-white';
        icon = '📦'.repeat(5) + '...';
        hint = `第 ${level} 层嵌套，无限套娃模式！建议适可而止 😄`;
    }
    
    levelEl.className = `badge ${badgeClass}`;
    levelEl.textContent = `${icon} L${level}`;
    hintEl.textContent = hint;
}

// 页面加载时显示嵌套层级
document.addEventListener('DOMContentLoaded', () => {
    displayNestingLevel();
    
    // 如果嵌套层级 >= 1，显示套娃挑战卡片
    const level = calculateNestingLevel();
    const challengeCard = document.getElementById('nesting-challenge-card');
    const nextLevelEl = document.getElementById('next-level');
    
    if (challengeCard && level >= 1) {
        challengeCard.style.display = 'block';
    }
    
    if (nextLevelEl) {
        nextLevelEl.textContent = level + 1;
    }
});

/**
 * 测试新标签功能（直接打开，不通过 shell）
 * 使用 AdminIframeShell.openTab() 方法
 * @param {string} url - 要打开的 URL
 * @param {string} title - 标签页标题
 */
function testOpenNewTab(url, title) {
    // 使用 AdminIframeShell.openTab() 方法
    if (window.Admin?.iframeShell?.openTab) {
        const success = window.Admin.iframeShell.openTab(url, title, {
            fallbackToWindow: true // 如果 TabManager 不可用，降级使用 window.open
        });
        
        if (success) {
            console.log('[testOpenNewTab] 新标签已打开:', { url, title });
        } else {
            console.warn('[testOpenNewTab] 打开新标签失败');
        }
    } else {
        alert('AdminIframeShell.openTab 方法不可用。\n\n请确保已加载 iframe-shell.js 组件。');
        console.error('[testOpenNewTab] AdminIframeShell.openTab 方法不存在');
    }
}

/**
 * 测试关闭当前标签页功能
 * 使用 AdminIframeShell.closeCurrentTab() 方法
 */
function testCloseCurrentTab() {
    // 使用 AdminIframeShell.closeCurrentTab() 方法
    if (window.Admin?.iframeShell?.closeCurrentTab) {
        const success = window.Admin.iframeShell.closeCurrentTab({
            fallbackToHistory: true // 如果 TabManager 不可用，降级使用 history.back()
        });
        
        if (success) {
            console.log('[testCloseCurrentTab] 关闭标签页请求已发送');
        } else {
            console.warn('[testCloseCurrentTab] 关闭标签页失败');
        }
    } else {
        alert('AdminIframeShell.closeCurrentTab 方法不可用。\n\n请确保已加载 iframe-shell.js 组件。');
        console.error('[testCloseCurrentTab] AdminIframeShell.closeCurrentTab 方法不存在');
    }
}
</script>
@endpush

@endsection