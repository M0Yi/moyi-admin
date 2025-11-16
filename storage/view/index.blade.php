<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ site()?->name ?? '现代化CMS' }}</title>
    @include('components.vendor.bootstrap-css')
    @include('components.vendor.bootstrap-icons')
    <style>
        :root { --brand: #4f46e5; --accent: #22c55e; --bg: #f8fafc; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--bg); }
        .navbar { box-shadow: 0 2px 10px rgba(0,0,0,.06); background: #fff; }
        .hero { background: linear-gradient(135deg, #eef2ff 0%, #f0fdf4 100%); }
        .hero .display-5 { font-weight: 800; letter-spacing: .5px; }
        .hero-sub { color: #475569; }
        .logo-mark { width: 64px; height: 64px; border-radius: 16px; background: radial-gradient(circle at 30% 30%, #a5b4fc, #60a5fa); display: inline-flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 24px; }
        .stat { border-radius: 16px; background: #fff; box-shadow: 0 6px 20px rgba(15,23,42,.06); padding: 1.25rem; }
        .feature-card { border: 0; border-radius: 16px; box-shadow: 0 6px 20px rgba(15,23,42,.06); }
        .feature-card .card-body { padding: 1.25rem; }
        .section-title { font-weight: 700; }
        .footer { background: #0f172a; color: #cbd5e1; }
        .footer a { color: #cbd5e1; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
        .dropdown-menu { border-radius: 12px; padding: .75rem; box-shadow: 0 12px 32px rgba(15,23,42,.15); }
        .dropdown-item { border-radius: 8px; padding: .5rem .75rem; }
        .dropdown-item:hover { background: #eef2ff; }
        .badge { border-radius: 999px; padding: .5rem .75rem; }
        .feature-card { transition: all .25s ease; }
        .feature-card:hover { transform: translateY(-3px); box-shadow: 0 12px 28px rgba(15,23,42,.12); }
        .carousel { border-radius: 16px; overflow: hidden; box-shadow: 0 12px 28px rgba(15,23,42,.12); }
        .slide-panel { min-height: 420px; border-radius: 0; display: flex; align-items: center; }
        .slide-panel .content { max-width: 720px; }
        @keyframes fadeUp { 0%{opacity:0; transform:translateY(12px);} 100%{opacity:1; transform:translateY(0);} }
        @keyframes floatY { 0%{transform:translateY(0);} 50%{transform:translateY(-4px);} 100%{transform:translateY(0);} }
        .animate-up { animation: fadeUp .6s ease both; }
        .anim-delay-1 { animation-delay: .1s; }
        .anim-delay-2 { animation-delay: .2s; }
        .hero-badge { animation: floatY 3s ease-in-out infinite; }
        .btn-primary { transition: transform .2s ease, box-shadow .2s ease; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(79,70,229,.2); }
        .info-card { border-radius: 16px; background: #fff; box-shadow: 0 10px 24px rgba(15,23,42,.08); padding: 1.25rem; }
        .feature-list { list-style: none; padding: 0; margin: 0; }
        .feature-list li { display: flex; align-items: center; gap: .5rem; padding: .5rem 0; }
        .feature-list .bi { font-size: 1.1rem; }
        .info-banner { border-radius: 16px; background: linear-gradient(135deg, #eef2ff 0%, #dbeafe 100%); padding: 1.5rem; box-shadow: 0 10px 24px rgba(15,23,42,.08); }
        .info-banner .title { font-weight: 700; }
        .info-banner .desc { color: #475569; }
        .anim-delay-3 { animation-delay: .3s; }
        .anim-delay-4 { animation-delay: .4s; }
        .anim-delay-5 { animation-delay: .5s; }
        .anim-delay-6 { animation-delay: .6s; }
        .code-block { background: #0f172a; color: #e2e8f0; border-radius: 12px; padding: 1rem 1.25rem; box-shadow: 0 10px 24px rgba(15,23,42,.12); font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .code-block code { white-space: pre; display: block; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                @if(site()?->logo)
                    <img src="{{ site()->logo }}" alt="logo" class="me-2" style="width:40px;height:40px;border-radius:8px;object-fit:cover">
                @else
                    <span class="logo-mark me-2">C</span>
                @endif
                <span class="fw-bold">{{ site()?->name ?? '现代化CMS' }}</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                    <li class="nav-item"><a class="nav-link" href="#top">首页</a></li>
                    <li class="nav-item"><a class="nav-link" href="#section-crud">一键CRUD</a></li>
                    <li class="nav-item"><a class="nav-link" href="#section-ai">AI编程</a></li>
                    <li class="nav-item"><a class="nav-link" href="#section-experience">体验项目</a></li>
                    <li class="nav-item"><a class="nav-link" href="#footer">联系</a></li>
                
                </ul>
            </div>
        </div>
    </nav>

    <header id="top" class="hero py-5 py-lg-6">
        <div class="container">
            <div class="row align-items-center g-4">
                <div class="col-md-8">
                    <h1 class="display-5 mb-3 animate-up">全栈 PHP 开源后台管理系统</h1>
                    <p class="hero-sub mb-3 animate-up anim-delay-1">前后端不分离，纯 PHP 运行，无 Node / npm 依赖；基于 Bootstrap 5 构建现代化 UI。</p>
                    <div class="d-flex flex-wrap gap-2 mb-4 animate-up anim-delay-2">
                        <span class="badge bg-light text-dark hero-badge">纯 PHP</span>
                        <span class="badge bg-light text-dark hero-badge">Blade 模板</span>
                        <span class="badge bg-light text-dark hero-badge">Bootstrap 5</span>
                        <span class="badge bg-light text-dark hero-badge">无 Node/npm</span>
                        <span class="badge bg-light text-dark hero-badge">一体化部署</span>
                    </div>
                    <div class="d-flex gap-3">
                        <a href="#section-experience" class="btn btn-lg btn-primary"><i class="bi bi-lightning-charge"></i> 立即体验</a>
                        <a href="#section-experience" class="btn btn-lg btn-outline-secondary"><i class="bi bi-box-arrow-in-right"></i> 快速入门</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="info-card animate-up anim-delay-2">
                        <div class="fw-semibold mb-2">基于 Hyperf 的全栈运行时</div>
                        <ul class="feature-list text-muted">
                            <li><i class="bi bi-speedometer2 text-primary"></i> 协程并发与高性能</li>
                            <li><i class="bi bi-cpu text-success"></i> Swoole/Swow 驱动</li>
                            <li><i class="bi bi-diagram-3 text-danger"></i> 依赖注入与组件化</li>
                            <li><i class="bi bi-shield-lock text-warning"></i> 稳健中间件与安全</li>
                            <li><i class="bi bi-gear text-info"></i> 任务、队列、定时器支持</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>

    


    <section id="section-crud" class="py-5">
        <div class="container">
            <div class="info-banner animate-up">
                <div class="title mb-1">即时 CRUD 与权限全栈</div>
                <div class="desc">纯 PHP 热更新，无需重启；多数据库连接；菜单增删改查；多站点管理员与完整权限模型。</div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark">热更新</span>
                    <span class="badge bg-light text-dark">多数据库</span>
                    <span class="badge bg-light text-dark">菜单管理</span>
                    <span class="badge bg-light text-dark">权限配置</span>
                    <span class="badge bg-light text-dark">多站点管理员</span>
                </div>
            </div>
            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <div class="card feature-card animate-up anim-delay-1">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-lightning-charge text-success fs-4 me-2"></i><div class="fw-semibold">热更新 CRUD</div></div>
                            <div class="text-muted">无需重启服务，模型与菜单改动即时生效，保障高效迭代。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card feature-card animate-up anim-delay-2">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-shield-lock text-warning fs-4 me-2"></i><div class="fw-semibold">权限配置管理</div></div>
                            <div class="text-muted">按资源与操作维度进行权限控制，支持角色、用户与审计日志。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card feature-card animate-up anim-delay-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-database text-primary fs-4 me-2"></i><div class="fw-semibold">多数据库支持</div></div>
                            <div class="text-muted">灵活连接与切换，适配复杂业务分库需求。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card feature-card animate-up anim-delay-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-list-check text-danger fs-4 me-2"></i><div class="fw-semibold">菜单管理</div></div>
                            <div class="text-muted">快速增删改查菜单，构建清晰的信息架构。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card feature-card animate-up anim-delay-5">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-people text-info fs-4 me-2"></i><div class="fw-semibold">多站点管理员</div></div>
                            <div class="text-muted">站点间权限隔离，集中化运维与管理。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card feature-card animate-up anim-delay-6">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-diagram-3 text-secondary fs-4 me-2"></i><div class="fw-semibold">完整模型</div></div>
                            <div class="text-muted">模型驱动配置，CRUD 全流程可扩展与复用。</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="section-ai" class="py-5">
        <div class="container">
            <div class="info-banner animate-up">
                <div class="title mb-1">本项目由 AI 全量编写</div>
                <div class="desc">从页面到逻辑均由 AI 生成与迭代，遵循现代工程实践与安全规范，支持快速演化与模块化扩展。</div>
                <div class="mt-2 d-flex flex-wrap gap-2">
                    <span class="badge bg-light text-dark">自然语言到代码</span>
                    <span class="badge bg-light text-dark">自动重构与优化</span>
                    <span class="badge bg-light text-dark">多语言多框架</span>
                    <span class="badge bg-light text-dark">测试与验证</span>
                </div>
            </div>
            <div class="row g-4 mt-4">
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-1">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-chat-dots text-primary fs-4 me-2"></i><div class="fw-semibold">自然语言到代码</div></div>
                            <div class="text-muted">以需求描述驱动生成代码结构与实现，快速搭建功能模块。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-2">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-magic text-success fs-4 me-2"></i><div class="fw-semibold">自动重构与优化</div></div>
                            <div class="text-muted">根据约定与上下文进行重构，改善可维护性与性能表现。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-3">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-grid text-danger fs-4 me-2"></i><div class="fw-semibold">跨语言与多框架</div></div>
                            <div class="text-muted">涵盖 PHP/JS 等语言与主流框架，统一工程风格与质量标准。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-4">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-clipboard2-check text-warning fs-4 me-2"></i><div class="fw-semibold">测试与验证</div></div>
                            <div class="text-muted">支持生成用例与自检脚本，保障改动可靠性与可回归性。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-5">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-braces text-info fs-4 me-2"></i><div class="fw-semibold">上下文与约定驱动</div></div>
                            <div class="text-muted">理解项目结构与约定，遵循现有风格与安全规范进行生成。</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card animate-up anim-delay-6">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2"><i class="bi bi-arrow-repeat text-secondary fs-4 me-2"></i><div class="fw-semibold">协作与自主迭代</div></div>
                            <div class="text-muted">基于反馈持续迭代，自动化完成多步改动与联动优化。</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="section-experience" class="py-5">
        <div class="container">
            <div class="info-banner animate-up">
                <div class="title mb-1">快速体验项目</div>
                <div class="desc">通过 Git 与 Docker Compose 一键运行本项目，零门槛上手。</div>
            </div>
            <div class="row g-4 mt-4">
                <div class="col-md-6">
                    <div class="card feature-card animate-up anim-delay-1">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">获取代码</div>
                            <div class="code-block"><code>git clone https://github.com/M0Yi/moyi-admin.git
cd moyi-admin</code></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card feature-card animate-up anim-delay-2">
                        <div class="card-body">
                            <div class="fw-semibold mb-2">启动服务</div>
                            <div class="code-block"><code>docker-compose up</code></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    

    

    


    <footer id="footer" class="footer py-4">
        <div class="container">
            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                <div>© {{ date('Y') }} {{ site()?->name ?? '现代化CMS' }}</div>
                <div>备案号：<a href="#">{{ site()?->icp_number ?? site_config('compliance.icp', 'ICP备XXXXXXX号') }}</a></div>
            </div>
        </div>
    </footer>

    @include('components.vendor.bootstrap-js')

    <script>
        document.querySelectorAll('a').forEach(function(el){
            el.addEventListener('click', function(e){
                if (el.getAttribute('data-bs-toggle')) return;
                var href = el.getAttribute('href') || '';
                if (href.startsWith('#')) return;
                if (el.dataset.disabled === 'true') {
                    e.preventDefault();
                    alert('功能暂未开通');
                }
            });
        });
    </script>
</body>
</html>
