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
    <title>{{ site()?->name ?? 'Moyi Admin' }} - 数据中心枢纽</title>
    @if(!empty($siteFavicon))
        <link rel="icon" href="{{ $siteFavicon }}" type="image/x-icon">
    @endif
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #00d4ff;
            --secondary: #7c3aed;
            --accent: #fbbf24;
            --success: #10b981;
            --danger: #ef4444;
            --bg-dark: #0a0e27;
            --bg-darker: #050815;
            --bg-panel: rgba(15, 23, 42, 0.8);
            --text-primary: #e2e8f0;
            --text-secondary: #94a3b8;
            --border-color: rgba(148, 163, 184, 0.1);
            --glow-primary: 0 0 20px rgba(0, 212, 255, 0.5);
            --glow-secondary: 0 0 20px rgba(124, 58, 237, 0.5);
        }

        body {
            font-family: 'Inter', 'PingFang SC', 'Microsoft YaHei', system-ui, -apple-system, sans-serif;
            background: var(--bg-darker);
            color: var(--text-primary);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* 粒子背景 */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: var(--primary);
            border-radius: 50%;
            opacity: 0.6;
            animation: float 15s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) translateX(0);
                opacity: 0.6;
            }
            50% {
                transform: translateY(-100px) translateX(50px);
                opacity: 1;
            }
        }

        /* 网格背景 */
        .grid-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background-image: 
                linear-gradient(rgba(0, 212, 255, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 212, 255, 0.03) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% {
                transform: translate(0, 0);
            }
            100% {
                transform: translate(50px, 50px);
            }
        }

        /* 渐变光效 */
        .gradient-orb {
            position: fixed;
            width: 600px;
            height: 600px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(0, 212, 255, 0.15) 0%, transparent 70%);
            filter: blur(80px);
            z-index: 0;
            animation: orbMove 20s ease-in-out infinite;
        }

        .gradient-orb:nth-child(1) {
            top: -300px;
            left: -300px;
        }

        .gradient-orb:nth-child(2) {
            bottom: -300px;
            right: -300px;
            background: radial-gradient(circle, rgba(124, 58, 237, 0.15) 0%, transparent 70%);
            animation-delay: -10s;
        }

        @keyframes orbMove {
            0%, 100% {
                transform: translate(0, 0) scale(1);
            }
            50% {
                transform: translate(100px, 100px) scale(1.2);
            }
        }

        /* 主容器 */
        .main-container {
            position: relative;
            z-index: 1;
            min-height: 100vh;
        }

        /* 顶部导航 */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            padding: 20px 40px;
            background: rgba(10, 14, 39, 0.8);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            list-style: none;
        }

        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s;
            position: relative;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        /* 英雄区域 */
        .hero {
            padding: 150px 40px 80px;
            text-align: center;
            position: relative;
        }

        .hero-badge {
            display: inline-block;
            padding: 8px 20px;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid var(--primary);
            border-radius: 50px;
            color: var(--primary);
            font-size: 14px;
            letter-spacing: 2px;
            margin-bottom: 30px;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                box-shadow: 0 0 0 0 rgba(0, 212, 255, 0.7);
            }
            50% {
                box-shadow: 0 0 0 10px rgba(0, 212, 255, 0);
            }
        }

        .hero h1 {
            font-size: clamp(36px, 6vw, 72px);
            font-weight: 800;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--primary), var(--secondary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: fadeInUp 1s ease-out;
        }

        .hero .subtitle {
            font-size: clamp(18px, 2.5vw, 24px);
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
            animation: fadeInUp 1s ease-out 0.2s both;
        }

        .hero-actions {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeInUp 1s ease-out 0.4s both;
        }

        .btn {
            padding: 14px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: var(--glow-primary);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(0, 212, 255, 0.6);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }

        .btn-outline:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* 轮播图区域 */
        .carousel-section {
            padding: 80px 40px;
            position: relative;
        }

        .section-title {
            text-align: center;
            font-size: clamp(28px, 4vw, 42px);
            font-weight: 700;
            margin-bottom: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .carousel-container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }

        .carousel-wrapper {
            overflow: hidden;
            border-radius: 20px;
            position: relative;
        }

        .carousel-track {
            display: flex;
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .carousel-slide {
            min-width: 100%;
            position: relative;
            height: 500px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            backdrop-filter: blur(20px);
        }

        .slide-content {
            text-align: center;
            padding: 60px;
            max-width: 800px;
        }

        .slide-icon {
            font-size: 80px;
            margin-bottom: 30px;
            display: inline-block;
            animation: floatIcon 3s ease-in-out infinite;
        }

        @keyframes floatIcon {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-20px);
            }
        }

        .slide-title {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary);
        }

        .slide-description {
            font-size: 18px;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .carousel-nav {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }

        .carousel-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--border-color);
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .carousel-dot.active {
            background: var(--primary);
            width: 30px;
            border-radius: 6px;
            box-shadow: var(--glow-primary);
        }

        .carousel-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid var(--primary);
            color: var(--primary);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 10;
        }

        .carousel-arrow:hover {
            background: var(--primary);
            color: white;
            box-shadow: var(--glow-primary);
        }

        .carousel-arrow.prev {
            left: -60px;
        }

        .carousel-arrow.next {
            right: -60px;
        }

        /* 功能特性 */
        .features-section {
            padding: 80px 40px;
            background: rgba(5, 8, 21, 0.5);
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 40px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(0, 212, 255, 0.1), rgba(124, 58, 237, 0.1));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary);
            box-shadow: var(--glow-primary);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 20px;
            display: inline-block;
        }

        .feature-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--primary);
        }

        .feature-description {
            color: var(--text-secondary);
            line-height: 1.8;
        }

        /* 统计数据 */
        .stats-section {
            padding: 80px 40px;
        }

        .stats-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
        }

        .stat-card {
            text-align: center;
            padding: 40px;
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--glow-primary);
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 16px;
        }

        /* 技术栈 */
        .tech-section {
            padding: 80px 40px;
            background: rgba(5, 8, 21, 0.5);
        }

        .tech-list {
            max-width: 1000px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .tech-badge {
            padding: 12px 24px;
            background: var(--bg-panel);
            border: 1px solid var(--border-color);
            border-radius: 50px;
            color: var(--text-primary);
            font-size: 14px;
            transition: all 0.3s;
        }

        .tech-badge:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: scale(1.05);
            box-shadow: var(--glow-primary);
        }

        /* 底部 */
        .footer {
            padding: 60px 40px 30px;
            text-align: center;
            border-top: 1px solid var(--border-color);
            background: rgba(5, 8, 21, 0.8);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-links {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .footer-links a {
            color: var(--text-secondary);
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-copyright {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 20px;
        }

        /* 响应式 */
        @media (max-width: 768px) {
            .header {
                padding: 15px 20px;
            }

            .nav-links {
                display: none;
            }

            .hero {
                padding: 120px 20px 60px;
            }

            .carousel-section,
            .features-section,
            .stats-section,
            .tech-section {
                padding: 60px 20px;
            }

            .carousel-arrow {
                display: none;
            }

            .slide-content {
                padding: 40px 20px;
            }

            .slide-title {
                font-size: 28px;
            }

            .slide-description {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- 背景效果 -->
    <div class="particles" id="particles"></div>
    <div class="grid-bg"></div>
    <div class="gradient-orb"></div>
    <div class="gradient-orb"></div>

    <!-- 主容器 -->
    <div class="main-container">
        <!-- 顶部导航 -->
        <header class="header">
            <div class="logo">MOYI ADMIN</div>
            <nav>
                <ul class="nav-links">
                    <li><a href="#features">功能特性</a></li>
                    <li><a href="#tech">技术栈</a></li>
                    <li><a href="https://github.com/M0Yi/moyi-admin" target="_blank">GitHub</a></li>
                    <li><a href="/admin/demo/login" class="btn btn-primary" style="padding: 8px 20px; font-size: 14px;">进入控制台</a></li>
                </ul>
            </nav>
        </header>

        <!-- 英雄区域 -->
        <section class="hero">
            <div class="hero-badge">🚀 数据中心枢纽</div>
            <h1>基于 Hyperf 的数据中心枢纽</h1>
            <p class="subtitle">
                高性能、通用 CRUD、多数据库管理、多站点支持<br>
                AI 驱动开发，零代码配置，极速部署
            </p>
            <div class="hero-actions">
                <a href="/admin/demo/login" class="btn btn-primary">立即体验</a>
                <a href="https://github.com/M0Yi/moyi-admin" target="_blank" class="btn btn-outline">查看源码</a>
            </div>
        </section>

        <!-- 轮播图 -->
        <section class="carousel-section" id="carousel">
            <h2 class="section-title">核心功能展示</h2>
            <div class="carousel-container">
                <div class="carousel-arrow prev" onclick="changeSlide(-1)">‹</div>
                <div class="carousel-wrapper">
                    <div class="carousel-track" id="carouselTrack">
                        <div class="carousel-slide">
                            <div class="slide-content">
                                <div class="slide-icon">🎯</div>
                                <h3 class="slide-title">通用 CRUD 设计</h3>
                                <p class="slide-description">
                                    通过配置即可完成数据管理功能，无需为每个模型重复编写代码。<br>
                                    一套代码管理所有数据模型，大幅减少重复开发工作。
                                </p>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="slide-content">
                                <div class="slide-icon">🗄️</div>
                                <h3 class="slide-title">多数据库管理</h3>
                                <p class="slide-description">
                                    支持添加和管理多个远程数据库连接，实现跨数据库的统一管理。<br>
                                    在 CRUD 操作时可选择不同的数据库连接，灵活应对复杂业务场景。
                                </p>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="slide-content">
                                <div class="slide-icon">🌐</div>
                                <h3 class="slide-title">多站点支持</h3>
                                <p class="slide-description">
                                    支持多站点独立管理，每个站点拥有独立的数据、会话和配置。<br>
                                    基于域名的会话隔离机制，确保多站点互不干扰。
                                </p>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="slide-content">
                                <div class="slide-icon">🚀</div>
                                <h3 class="slide-title">高性能架构</h3>
                                <p class="slide-description">
                                    基于 Swoole 协程，支持高并发处理。<br>
                                    数据库和 Redis 连接池，减少连接开销，提升系统性能。
                                </p>
                            </div>
                        </div>
                        <div class="carousel-slide">
                            <div class="slide-content">
                                <div class="slide-icon">🤖</div>
                                <h3 class="slide-title">AI 驱动开发</h3>
                                <p class="slide-description">
                                    完全基于最先进的 AI 技术构造，从架构设计到代码实现，<br>
                                    全程采用 AI 辅助开发，展现 AI 在软件开发领域的强大能力。
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="carousel-arrow next" onclick="changeSlide(1)">›</div>
                <div class="carousel-nav" id="carouselNav"></div>
            </div>
        </section>

        <!-- 功能特性 -->
        <section class="features-section" id="features">
            <h2 class="section-title">功能特性</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">📊</div>
                    <h3 class="feature-title">数据列表</h3>
                    <p class="feature-description">支持分页、搜索、排序，字段显示控制，灵活的数据展示方式</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">➕</div>
                    <h3 class="feature-title">数据创建</h3>
                    <p class="feature-description">表单验证、字段类型自动识别，支持选择不同数据库进行创建</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">✏️</div>
                    <h3 class="feature-title">数据编辑</h3>
                    <p class="feature-description">支持数据更新、数据回显，支持跨数据库操作</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🗑️</div>
                    <h3 class="feature-title">回收站</h3>
                    <p class="feature-description">支持软删除、恢复、永久删除，数据安全有保障</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">📥</div>
                    <h3 class="feature-title">数据导出</h3>
                    <p class="feature-description">支持 Excel/CSV 格式导出，支持按搜索条件导出数据</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">🖼️</div>
                    <h3 class="feature-title">iframe 模式</h3>
                    <p class="feature-description">支持在弹窗中以 iframe 方式打开页面，提升用户体验</p>
                </div>
            </div>
        </section>

        <!-- 统计数据 -->
        <section class="stats-section">
            <h2 class="section-title">系统数据</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number" data-target="100">0</div>
                    <div class="stat-label">+ 功能模块</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="99">0</div>
                    <div class="stat-label">% 代码复用率</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="1000">0</div>
                    <div class="stat-label">+ 并发支持</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" data-target="0">0</div>
                    <div class="stat-label">重复代码</div>
                </div>
            </div>
        </section>

        <!-- 技术栈 -->
        <section class="tech-section" id="tech">
            <h2 class="section-title">技术栈</h2>
            <div class="tech-list">
                <div class="tech-badge">Hyperf 3.1</div>
                <div class="tech-badge">Swoole 5</div>
                <div class="tech-badge">PHP 8.1+</div>
                <div class="tech-badge">MySQL</div>
                <div class="tech-badge">Redis</div>
                <div class="tech-badge">Bootstrap 5</div>
                <div class="tech-badge">Blade</div>
                <div class="tech-badge">原生 ES6+</div>
            </div>
        </section>

        <!-- 底部 -->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-links">
                    <a href="https://github.com/M0Yi/moyi-admin" target="_blank">GitHub</a>
                    <a href="/admin/demo/login">控制台</a>
                    <a href="#features">功能特性</a>
                </div>
                <div class="footer-copyright">
                    © {{ date('Y') }} {{ site()?->name ?? 'Moyi Admin' }} · 基于 Hyperf 的数据中心枢纽
                    @if (! empty(site()?->icp_number))
                        · <a href="https://beian.miit.gov.cn" target="_blank" rel="noreferrer" style="color: var(--text-secondary);">{{ site()->icp_number }}</a>
                    @endif
                </div>
            </div>
        </footer>
    </div>

    <script>
        // 粒子效果
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 15 + 's';
                particle.style.animationDuration = (10 + Math.random() * 10) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // 轮播图
        let currentSlide = 0;
        const slides = document.querySelectorAll('.carousel-slide');
        const totalSlides = slides.length;

        function initCarousel() {
            const nav = document.getElementById('carouselNav');
            for (let i = 0; i < totalSlides; i++) {
                const dot = document.createElement('button');
                dot.className = 'carousel-dot' + (i === 0 ? ' active' : '');
                dot.onclick = () => goToSlide(i);
                nav.appendChild(dot);
            }
            updateCarousel();
        }

        function changeSlide(direction) {
            currentSlide = (currentSlide + direction + totalSlides) % totalSlides;
            updateCarousel();
        }

        function goToSlide(index) {
            currentSlide = index;
            updateCarousel();
        }

        function updateCarousel() {
            const track = document.getElementById('carouselTrack');
            track.style.transform = `translateX(-${currentSlide * 100}%)`;

            const dots = document.querySelectorAll('.carousel-dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentSlide);
            });
        }

        // 自动轮播
        let autoSlideInterval = setInterval(() => {
            changeSlide(1);
        }, 5000);

        // 鼠标悬停时暂停自动轮播
        const carouselContainer = document.querySelector('.carousel-container');
        carouselContainer.addEventListener('mouseenter', () => {
            clearInterval(autoSlideInterval);
        });

        carouselContainer.addEventListener('mouseleave', () => {
            autoSlideInterval = setInterval(() => {
                changeSlide(1);
            }, 5000);
        });

        // 数字动画
        function animateNumber(element) {
            const target = parseInt(element.getAttribute('data-target'));
            const duration = 2000;
            const increment = target / (duration / 16);
            let current = 0;

            const updateNumber = () => {
                current += increment;
                if (current < target) {
                    element.textContent = Math.floor(current);
                    requestAnimationFrame(updateNumber);
                } else {
                    element.textContent = target;
                }
            };

            updateNumber();
        }

        // 滚动动画观察器
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumber = entry.target.querySelector('.stat-number');
                    if (statNumber && !statNumber.classList.contains('animated')) {
                        statNumber.classList.add('animated');
                        animateNumber(statNumber);
                    }
                }
            });
        }, observerOptions);

        // 观察统计卡片
        document.querySelectorAll('.stat-card').forEach(card => {
            observer.observe(card);
        });

        // 初始化
        createParticles();
        initCarousel();

        // 平滑滚动
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
