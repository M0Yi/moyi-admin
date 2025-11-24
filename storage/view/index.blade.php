<!DOCTYPE html>
<html lang="zh-CN">
@php
    $siteFavicon = site()?->favicon ?: '/favicon.ico';
    if (! empty($siteFavicon) && ! preg_match('/^(https?:)?\/\//i', $siteFavicon) && ! str_starts_with($siteFavicon, 'data:')) {
        $siteFavicon = '/' . ltrim($siteFavicon, '/');
    }
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ site()?->name ?? 'Moyi Admin' }}</title>
    @if(!empty($siteFavicon))
        <link rel="icon" href="{{ $siteFavicon }}" type="image/x-icon">
    @endif
    @include('components.plugin.bootstrap-css')
    @include('components.plugin.bootstrap-icons')
    <style>
        :root {
            --brand: #4f46e5;
            --accent: #22c55e;
            --dark: #0f172a;
            --bg: #f4f6fb;
        }
        body { font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; background: var(--bg); color: #0f172a; }
        .navbar { background: #fff; box-shadow: 0 8px 24px rgba(15,23,42,.08); }
        .hero { background: linear-gradient(125deg, rgba(79,70,229,.08), rgba(34,197,94,.1)); }
        .hero__badge { background: rgba(255,255,255,.5); border-radius: 999px; padding: .35rem .85rem; font-weight: 600; color: var(--brand); }
        .hero__title { font-weight: 800; line-height: 1.1; }
        .hero__sub { color: #475569; }
        .hero__cta .btn { min-width: 180px; }
        .logo-mark { width: 48px; height: 48px; border-radius: 14px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: inline-flex; align-items: center; justify-content: center; font-weight: 700; color: #fff; }
        .section-title { font-size: 1.75rem; font-weight: 700; }
        .section-desc { color: #475569; max-width: 640px; }
        .card-feature { border: 0; border-radius: 18px; box-shadow: 0 12px 32px rgba(15,23,42,.08); background: #fff; height: 100%; transition: transform .2s ease, box-shadow .2s ease; }
        .card-feature:hover { transform: translateY(-4px); box-shadow: 0 16px 40px rgba(15,23,42,.12); }
        .card-feature .icon { width: 48px; height: 48px; border-radius: 12px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 1rem; }
        .stat-item { background: #fff; border-radius: 18px; padding: 1.25rem; box-shadow: 0 10px 24px rgba(15,23,42,.06); }
        .stat-item span { display: block; color: #94a3b8; font-size: .9rem; }
        .stat-item strong { font-size: 2rem; line-height: 1; display: block; }
        .code-block { background: var(--dark); color: #e2e8f0; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; border-radius: 16px; padding: 1rem 1.25rem; box-shadow: 0 18px 40px rgba(15,23,42,.25); }
        .timeline { border-left: 3px solid rgba(99,102,241,.2); margin-left: 1rem; padding-left: 1.5rem; }
        .timeline-item { margin-bottom: 1.25rem; }
        .timeline-item strong { display: block; color: var(--brand); }
        .tech-card { border: 0; border-radius: 16px; background: #fff; padding: 1.5rem; box-shadow: 0 10px 28px rgba(15,23,42,.06); height: 100%; }
        .tech-card ul { padding-left: 1.2rem; color: #475569; }
        .pill { border-radius: 999px; padding: .35rem .9rem; font-size: .9rem; border: 1px solid rgba(15,23,42,.1); display: inline-flex; align-items: center; gap: .35rem; }
        .gallery { display: grid; grid-template-columns: repeat(auto-fit,minmax(220px,1fr)); gap: 1rem; }
        .gallery-card { border-radius: 16px; padding: 1rem; background: #fff; box-shadow: 0 10px 26px rgba(15,23,42,.06); }
        .gallery-card figure { border-radius: 12px; background: rgba(148,163,184,.12); height: 140px; margin: 0 0 .75rem; display: flex; align-items: center; justify-content: center; color: #94a3b8; font-size: .95rem; }
        .footer { background: var(--dark); color: #cbd5e1; }
        .footer a { color: inherit; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light py-3">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#top">
            @if(site()?->logo)
                <img src="{{ site()->logo }}" alt="logo" class="me-2" style="width:48px;height:48px;border-radius:12px;object-fit:cover">
            @else
                <span class="logo-mark me-2">MA</span>
            @endif
            <div>
                <div class="fw-bold">{{ site()?->name ?? 'Moyi Admin' }}</div>
                <small class="text-muted">Hyperf 3.1 · CRUD · AI 驱动</small>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto gap-lg-3">
                <li class="nav-item"><a class="nav-link" href="#features">核心优势</a></li>
                <li class="nav-item"><a class="nav-link" href="#tech">技术栈</a></li>
                <li class="nav-item"><a class="nav-link" href="#quickstart">快速开始</a></li>
                <li class="nav-item"><a class="nav-link" href="#gallery">项目截图</a></li>
                <li class="nav-item"><a class="btn btn-sm btn-outline-primary" href="#cta">立即体验</a></li>
            </ul>
        </div>
    </div>
</nav>

<header id="top" class="hero py-5 py-lg-6">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="hero__badge mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-stars"></i> 完全由 AI 驱动构建
                </div>
                <h1 class="hero__title display-4 mb-3">基于 Hyperf 的全栈后台管理系统</h1>
                <p class="hero__sub fs-5 mb-4">遵循通用 CRUD 设计，通过配置即可生成数据管理界面。前后端不分离、纯 PHP 运行、无 Node 依赖，开箱即用，轻松适配多数据库与多站点业务场景。</p>
                <div class="d-flex flex-wrap hero__cta gap-3">
                    <a href="#quickstart" class="btn btn-primary btn-lg"><i class="bi bi-lightning-charge-fill me-1"></i> 快速上手</a>
                    <a href="https://github.com/M0Yi/moyi-admin" target="_blank" class="btn btn-outline-dark btn-lg"><i class="bi bi-github me-1"></i> 查看源码</a>
                </div>
                <div class="stats-grid mt-4">
                    <div class="stat-item">
                        <span>Hyperf 版本</span>
                        <strong>3.1</strong>
                    </div>
                    <div class="stat-item">
                        <span>核心模块</span>
                        <strong>8+</strong>
                    </div>
                    <div class="stat-item">
                        <span>数据库连接</span>
                        <strong>Multi</strong>
                    </div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="card-feature p-4">
                    <div class="mb-3">
                        <span class="pill"><i class="bi bi-cpu"></i> Swoole 协程</span>
                        <span class="pill ms-2"><i class="bi bi-gear"></i> 通用 CRUD</span>
                    </div>
                    <h5 class="fw-semibold mb-3">为什么选择 Moyi Admin？</h5>
                    <ul class="list-unstyled text-muted mb-4">
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i> 多数据库一键切换</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i> 全链路权限与日志审计</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i> Excel / CSV 导入导出</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i> 回收站与数据恢复</li>
                        <li class="mb-2"><i class="bi bi-check2-circle text-success me-2"></i> Docker 一键部署</li>
                    </ul>
                    <div class="alert alert-success mb-0" role="alert">
                        <i class="bi bi-info-circle me-2"></i> 支持 JWT + Session 双重认证，全程安全可控。
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<section id="features" class="py-5">
    <div class="container">
        <div class="mb-4 text-center">
            <p class="text-uppercase text-primary fw-semibold mb-1">核心能力</p>
            <h2 class="section-title mb-2">围绕 CRUD 的完整后台生态</h2>
            <p class="section-desc mx-auto">基于 README 中的功能说明，我们将系统拆分为四大能力域，从数据处理、权限控制到运维体验全面覆盖。</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card-feature p-4 h-100">
                    <div class="icon bg-primary bg-opacity-10 text-primary mb-3"><i class="bi bi-kanban"></i></div>
                    <h5 class="fw-semibold mb-2">通用 CRUD 引擎</h5>
                    <p class="text-muted mb-3">配置即生成列表、表单、搜索与导出，支持多数据库、多模型以及状态切换、批量操作等高级用法。</p>
                    <ul class="text-muted mb-0">
                        <li>分页、排序、条件搜索</li>
                        <li>软删除、回收站、恢复/清空</li>
                        <li>Excel / CSV 条件导出</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-feature p-4 h-100">
                    <div class="icon bg-success bg-opacity-10 text-success mb-3"><i class="bi bi-shield-lock"></i></div>
                    <h5 class="fw-semibold mb-2">权限与安全</h5>
                    <p class="text-muted mb-3">角色-权限-菜单一体化配置，配合操作日志、登录日志与多重防护策略，确保系统运行可追溯可审计。</p>
                    <ul class="text-muted mb-0">
                        <li>JWT + Session 组合认证</li>
                        <li>操作 / 登录日志留痕</li>
                        <li>中间件裁决 + CSRF 防护</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-feature p-4 h-100">
                    <div class="icon bg-warning bg-opacity-10 text-warning mb-3"><i class="bi bi-diagram-3"></i></div>
                    <h5 class="fw-semibold mb-2">AI 驱动迭代</h5>
                    <p class="text-muted mb-3">项目从架构、代码到文档全部借助 AI 自动化创建，天然具备快速迭代与统一编码规范能力。</p>
                    <ul class="text-muted mb-0">
                        <li>自然语言驱动代码生成</li>
                        <li>自动重构、测试、自检</li>
                        <li>跨语言、跨框架的知识迁移</li>
                    </ul>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card-feature p-4 h-100">
                    <div class="icon bg-info bg-opacity-10 text-info mb-3"><i class="bi bi-cloud-arrow-up"></i></div>
                    <h5 class="fw-semibold mb-2">部署与运维</h5>
                    <p class="text-muted mb-3">支持本地运行与 Docker Compose 一键启动，配套多环境配置、资源编译、日志轮转与备份策略。</p>
                    <ul class="text-muted mb-0">
                        <li>Docker 镜像复用 · 一键重建</li>
                        <li>storage/runtime 目录规范</li>
                        <li>静态资源版本化与优化</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="tech" class="py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-5">
                <p class="text-uppercase text-primary fw-semibold mb-1">技术栈</p>
                <h2 class="section-title mb-3">后端高性能 · 前端现代化</h2>
                <p class="section-desc">README 中列出的核心技术原样呈现，帮助你快速了解系统底层实现与适配场景。</p>
                <div class="timeline mt-4">
                    <div class="timeline-item">
                        <strong>后端</strong>
                        <span>Hyperf 3.1 · PHP 8.1+ · Swoole 5 · Redis · Hyperf ORM</span>
                    </div>
                    <div class="timeline-item">
                        <strong>前端</strong>
                        <span>Bootstrap 5.3 · Bootstrap Icons · Blade 模板 · 原生 ES6+</span>
                    </div>
                    <div class="timeline-item">
                        <strong>工程化</strong>
                        <span>Web/Vite 构建 · 静态资源优化 · 版本化发布</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="tech-card h-100">
                            <h6 class="fw-bold mb-2">后端特性</h6>
                            <ul class="mb-0">
                                <li>协程化请求处理</li>
                                <li>依赖注入 & 中间件</li>
                                <li>多数据库连接池</li>
                                <li>任务、队列、定时器</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="tech-card h-100">
                            <h6 class="fw-bold mb-2">前端体验</h6>
                            <ul class="mb-0">
                                <li>Bootstrap 响应式界面</li>
                                <li>可复用 Blade 组件</li>
                                <li>自定义 DataTable 组件</li>
                                <li>Flatpickr / Tom Select</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="tech-card">
                            <h6 class="fw-bold mb-2">安全与合规</h6>
                            <ul class="mb-0">
                                <li>严格遵循 PSR-1/2/4/12、PHPStan 校验</li>
                                <li>CSRF、XSS、SQL 注入多重防护</li>
                                <li>文件上传白名单 + 大小限制</li>
                                <li>日志审计 + 数据备份机制</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="quickstart" class="py-5">
    <div class="container">
        <div class="mb-4 text-center">
            <p class="text-uppercase text-primary fw-semibold mb-1">快速开始</p>
            <h2 class="section-title mb-2">5 分钟启动你的 Moyi Admin</h2>
            <p class="section-desc mx-auto">README 的安装指南被拆解为可操作的四步走流程，结合代码段帮助你直接复制执行。</p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="card-feature p-4 h-100">
                    <div class="pill mb-3"><span class="fw-bold">STEP 01</span> 克隆项目</div>
                    <div class="code-block mb-0"><code>git clone https://github.com/M0Yi/moyi-admin.git
cd moyi-admin</code></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card-feature p-4 h-100">
                    <div class="pill mb-3"><span class="fw-bold">STEP 02</span> 安装依赖</div>
                    <div class="code-block mb-0"><code>composer install</code></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card-feature p-4 h-100">
                    <div class="pill mb-3"><span class="fw-bold">STEP 03</span> 配置环境</div>
                    <div class="code-block mb-0"><code>cp .env.example .env
# 编辑数据库/Redis 配置</code></div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card-feature p-4 h-100">
                    <div class="pill mb-3"><span class="fw-bold">STEP 04</span> 启动服务</div>
                    <div class="code-block mb-0"><code>php bin/hyperf.php start
# 或 docker-compose up -d</code></div>
                </div>
            </div>
        </div>
        <div class="alert alert-warning mt-4" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i> 使用 Docker 重新编译时，避免随意执行 <code>docker-compose down -v</code>，该命令会删除所有卷和数据库数据，请先做好备份。
        </div>
    </div>
</section>

<section id="gallery" class="py-5">
    <div class="container">
        <div class="mb-4 text-center">
            <p class="text-uppercase text-primary fw-semibold mb-1">可视化成果</p>
            <h2 class="section-title mb-2">后台界面预览</h2>
            <p class="section-desc mx-auto">README 中的截图位展示被转化为一个简洁的网格布局，可根据需要替换成真实图片。</p>
        </div>
        <div class="gallery">
            <div class="gallery-card">
                <figure>登录页面示意</figure>
                <h6 class="fw-bold mb-1">登录 / 认证</h6>
                <p class="text-muted mb-0">支持验证码、登录日志记录与失败次数限制。</p>
            </div>
            <div class="gallery-card">
                <figure>仪表盘示意</figure>
                <h6 class="fw-bold mb-1">仪表盘</h6>
                <p class="text-muted mb-0">展示关键指标、快捷入口与系统动态。</p>
            </div>
            <div class="gallery-card">
                <figure>CRUD 列表示意</figure>
                <h6 class="fw-bold mb-1">CRUD 列表</h6>
                <p class="text-muted mb-0">分页、搜索、筛选、批量操作一应俱全。</p>
            </div>
            <div class="gallery-card">
                <figure>配置示意</figure>
                <h6 class="fw-bold mb-1">配置中心</h6>
                <p class="text-muted mb-0">可视化配置字段属性、验证规则与功能开关。</p>
            </div>
        </div>
    </div>
</section>

<section id="cta" class="py-5">
    <div class="container">
        <div class="card-feature p-4 p-lg-5">
            <div class="row align-items-center g-4">
                <div class="col-lg-8">
                    <h3 class="fw-bold mb-2">准备好体验 AI 驱动的后台开发方式了吗？</h3>
                    <p class="text-muted mb-0">立即 Fork 或下载 Moyi Admin，遵循 README 的最佳实践，将你的业务模型与数据管理需求一键落地。</p>
                </div>
                <div class="col-lg-4 text-lg-end">
                    <a href="http://localhost:6501/install" class="btn btn-primary btn-lg me-2"><i class="bi bi-rocket-takeoff me-1"></i> 开始安装</a>
                    <a href="https://github.com/M0Yi/moyi-admin" target="_blank" class="btn btn-outline-secondary btn-lg"><i class="bi bi-cloud-download me-1"></i> 下载源码</a>
                </div>
            </div>
        </div>
    </div>
</section>

<footer class="footer py-4">
    <div class="container">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
            <div>© {{ date('Y') }} {{ site()?->name ?? 'Moyi Admin' }} · 构建于 Hyperf</div>
            <div>备案号：<a href="#">{{ site()?->icp_number ?? site_config('compliance.icp', 'ICP备XXXXXXX号') }}</a></div>
        </div>
    </div>
</footer>

@include('components.plugin.bootstrap-js')
</body>
</html>
