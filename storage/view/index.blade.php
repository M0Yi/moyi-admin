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
    <style>
        :root {
            --bg: #030414;
            --ink: #f5f7ff;
            --muted: rgba(245,247,255,.72);
            --accent: #7ef7ff;
            --highlight: #ffd54f;
            --outline: rgba(255,255,255,.14);
            --panel: rgba(5,7,18,.62);
            --glass: rgba(3,4,12,.78);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Space Grotesk", "Inter", "Eurostile", system-ui, -apple-system, sans-serif;
            background: radial-gradient(circle at 15% 15%, rgba(126,247,255,.12), transparent 40%),
                        radial-gradient(circle at 78% 0, rgba(255,213,79,.18), transparent 45%),
                        linear-gradient(135deg, #01020b, #030414 55%, #050617 100%);
            color: var(--ink);
            overflow: hidden;
        }

        .cosmos {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        .stars,
        .grid,
        .grains {
            position: absolute;
            inset: 0;
            pointer-events: none;
        }

        .stars::before,
        .stars::after {
            content: "";
            position: absolute;
            inset: -40vh;
            background-image: radial-gradient(rgba(255,255,255,.4) 1px, transparent 1px);
            background-size: 140px 140px;
            animation: drift 60s linear infinite;
        }

        .stars::after {
            background-size: 80px 80px;
            opacity: .5;
            animation-duration: 120s;
        }

        .grid {
            background-image:
                linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 220px 220px;
            transform: perspective(1200px) rotateX(65deg);
            transform-origin: top;
            opacity: .4;
            animation: scan 22s linear infinite;
        }

        .grains {
            background: url('data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120"%3E%3Cfilter id="n"%3E%3CfeTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="4" stitchTiles="stitch"/%3E%3C/filter%3E%3Crect width="120" height="120" filter="url(%23n)" opacity="0.2"/%3E%3C/svg%3E');
            mix-blend-mode: soft-light;
            animation: pulse 14s ease-in-out infinite;
        }

        canvas#matrix {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            mix-blend-mode: screen;
            filter: drop-shadow(0 0 12px rgba(127,247,255,.2));
        }

        main.shell {
            position: relative;
            z-index: 2;
            min-height: 100vh;
            padding: clamp(24px, 4vw, 60px);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .holo {
            width: min(1200px, 100%);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: clamp(24px, 3vw, 40px);
            background: var(--glass);
            border: 1px solid var(--outline);
            border-radius: 32px;
            padding: clamp(30px, 4vw, 60px);
            box-shadow:
                0 0 60px rgba(10,12,28,.75),
                inset 0 0 60px rgba(127,247,255,.08);
            backdrop-filter: blur(28px);
        }

        .hero {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .eyebrow {
            letter-spacing: .6em;
            font-size: .75rem;
            color: var(--muted);
            text-transform: uppercase;
        }

        .glyph {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 8px 22px;
            border-radius: 999px;
            border: 1px solid rgba(255,213,79,.45);
            color: var(--highlight);
            letter-spacing: .4em;
            font-size: .85rem;
        }

        .glyph::before {
            content: "";
            width: 28px;
            height: 28px;
            border-radius: 50%;
            border: 1px solid rgba(255,213,79,.6);
            box-shadow: 0 0 14px rgba(255,213,79,.55);
            animation: pulse 4s ease-in-out infinite;
        }

        h1 {
            margin: 0;
            font-size: clamp(2.8rem, 5vw, 4.4rem);
            letter-spacing: .1em;
            line-height: 1.1;
        }

        h1 span {
            color: var(--accent);
            text-shadow: 0 0 35px rgba(126,247,255,.55);
        }

        .subline {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--muted);
        }

        .badges {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .badges span {
            padding: 6px 16px;
            border-radius: 999px;
            border: 1px solid var(--outline);
            letter-spacing: .2em;
            font-size: .7rem;
            color: var(--muted);
            background: rgba(255,255,255,.05);
        }

        .cta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 8px;
        }

        .btn {
            border: none;
            border-radius: 14px;
            padding: 14px 28px;
            font-size: .9rem;
            letter-spacing: .3em;
            text-transform: uppercase;
            cursor: pointer;
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .btn.primary {
            background: linear-gradient(120deg, var(--highlight), #ffb347, var(--highlight));
            color: #1b1400;
            text-decoration: none;
            box-shadow: 0 20px 35px rgba(255,179,71,.35);
        }

        .btn.ghost {
            border: 1px solid rgba(126,247,255,.55);
            color: var(--accent);
            background: transparent;
        }

        .btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 30px 45px rgba(0,0,0,.3);
        }

        .panel {
            border: 1px solid var(--outline);
            border-radius: 24px;
            padding: 26px;
            background: var(--panel);
            backdrop-filter: blur(18px);
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .panel-title {
            letter-spacing: .4em;
            font-size: .75rem;
            color: var(--muted);
            text-transform: uppercase;
        }

        .telemetry {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .telemetry li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,.08);
        }

        .telemetry span {
            letter-spacing: .2em;
            font-size: .8rem;
            color: var(--muted);
        }

        .telemetry strong {
            font-size: 1.2rem;
            color: var(--accent);
            text-shadow: 0 0 18px rgba(126,247,255,.45);
        }

        .log {
            font-family: "IBM Plex Mono", "Space Mono", Consolas, monospace;
            font-size: .85rem;
            color: var(--muted);
            background: rgba(255,255,255,.03);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 16px;
            padding: 18px;
            min-height: 160px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .log-line {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            animation: fadeIn .6s ease forwards;
        }

        .log-line .time {
            color: var(--accent);
        }

        .log-line .status {
            color: var(--highlight);
            letter-spacing: .2em;
        }

        footer {
            position: fixed;
            bottom: 18px;
            width: 100%;
            text-align: center;
            letter-spacing: .35em;
            font-size: .7rem;
            color: var(--muted);
            z-index: 2;
        }

        footer a {
            color: var(--accent);
        }

        @keyframes drift {
            to { transform: translate3d(60px, -80px, 0); }
        }

        @keyframes scan {
            to { background-position: 0 220px, 220px 0; }
        }

        @keyframes pulse {
            0%, 100% { opacity: .4; transform: scale(1); }
            50% { opacity: .8; transform: scale(1.08); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 768px) {
            .glyph, .eyebrow, footer {
                letter-spacing: .2em;
            }

            .holo {
                padding: 26px;
            }

            .cta {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="cosmos">
    <div class="stars"></div>
    <div class="grid"></div>
    <div class="grains"></div>
    <canvas id="matrix"></canvas>
</div>

<main class="shell">
    <section class="holo">
        <div class="hero">
            <span class="glyph">MOYI CORE</span>
            <p class="eyebrow">HYPERF 3.1 · CRUD KERNEL · AI DRIVEN</p>
            <h1>神秘控制中枢<br><span>MOYI ADMIN</span></h1>
            <p class="subline">
                Hyperf 3.1 + Swoole 5，天生高并发。<br>
                一键 CRUD，模型/表格/表单一体生成。<br>
                JWT + Session 双态守护，日志可追溯。<br>
                Excel/CSV 导入导出、回收站实时可用。
            </p>
            <div class="badges">
                <span>Hyperf 3.1</span>
                <span>Swoole 5</span>
                <span>MySQL · Redis</span>
                <span>Blade · Bootstrap</span>
            </div>
            <div class="cta">
                <a class="btn primary" href="/admin/demo/login">进入控制台</a>
                <a class="btn ghost" href="https://github.com/M0Yi/moyi-admin" target="_blank" rel="noreferrer">开源地址</a>
            </div>
        </div>

        <aside class="panel">
            <div class="panel-title">系统脉冲</div>
            <ul class="telemetry">
                <li>
                    <span>STACK</span>
                    <strong id="telemetry-runtime">Hyperf 3.1 + Swoole 5</strong>
                </li>
                <li>
                    <span>CRUD CORE</span>
                    <strong id="telemetry-latency">模型/表格/表单一体生成</strong>
                </li>
                <li>
                    <span>GUARD</span>
                    <strong id="telemetry-security">JWT + Session 双态守护</strong>
                </li>
            </ul>
            <div class="panel-title">操作回声</div>
            <div class="log" id="log-feed"></div>
        </aside>
    </section>
</main>

<footer>
    © {{ date('Y') }} {{ site()?->name ?? 'Moyi Admin' }} · MoYi
    @if (! empty(site()?->icp_number))
        · <a href="https://beian.miit.gov.cn" target="_blank" rel="noreferrer">{{ site()->icp_number }}</a>
    @endif
    <div class="gemini-note">
        页面创作来自
        <a href="https://gemini.google.com/" target="_blank" rel="noreferrer">Gemini 3 Pro</a>
    </div>
</footer>

<script>
const matrix = document.getElementById('matrix');
const mtxCtx = matrix.getContext('2d');
const glyphs = '01MOYI∆#@Ξ';
let columns;

function resizeMatrix() {
    const ratio = window.devicePixelRatio || 1;
    matrix.width = window.innerWidth * ratio;
    matrix.height = window.innerHeight * ratio;
    mtxCtx.setTransform(ratio, 0, 0, ratio, 0, 0);
    columns = Math.floor(window.innerWidth / 16);
    drops = Array(columns).fill(1);
}

let drops = [];
function drawMatrix() {
    mtxCtx.fillStyle = 'rgba(2,3,11,0.08)';
    mtxCtx.fillRect(0, 0, matrix.width, matrix.height);
    mtxCtx.fillStyle = 'rgba(126,247,255,0.8)';
    mtxCtx.font = '16px "Space Mono", monospace';

    drops.forEach((y, index) => {
        const text = glyphs[Math.floor(Math.random() * glyphs.length)];
        const x = index * 16;
        mtxCtx.fillText(text, x, y * 18);
        if (y * 18 > matrix.height && Math.random() > 0.975) {
            drops[index] = 0;
        }
        drops[index]++;
    });
    requestAnimationFrame(drawMatrix);
}

resizeMatrix();
drawMatrix();
window.addEventListener('resize', resizeMatrix);

const telemetryTargets = {
    runtime: document.getElementById('telemetry-runtime'),
    latency: document.getElementById('telemetry-latency'),
    security: document.getElementById('telemetry-security'),
};

const telemetryContent = {
    runtime: 'Hyperf 3.1 / Swoole 5',
    latency: '一键 CRUD · 极速部署',
    security: 'JWT + Session · 日志可追溯',
};

function applyTelemetry() {
    Object.entries(telemetryTargets).forEach(([key, el]) => {
        if (el && telemetryContent[key]) {
            el.textContent = telemetryContent[key];
        }
    });
}

applyTelemetry();

const logFeed = document.getElementById('log-feed');
const logPool = [
    'Hyperf 3.1 + Swoole 5 · 天生高并发',
    'Hyperf 控制中枢 · CRUD 核心 · 极速部署',
    '一键 CRUD · 模型/表格/表单一体生成',
    'Blade + 原生 JS + Bootstrap',
    'JWT + Session 双态守护 · 日志可追溯',
    'Excel/CSV 导入导出 · 回收站实时可用',
    'MySQL / Redis / Hyperf ORM 驱动',
    '安装一步到位 · docker-compose 即刻上线'
];

function pushLog() {
    const node = document.createElement('div');
    node.className = 'log-line';
    const time = new Date().toLocaleTimeString('en-GB', { hour12: false });
    node.innerHTML = `<span class="time">${time}</span><span>${logPool[Math.floor(Math.random() * logPool.length)]}</span><span class="status">OK</span>`;
    logFeed.prepend(node);
    if (logFeed.children.length > 6) {
        logFeed.removeChild(logFeed.lastChild);
    }
}

pushLog();
setInterval(pushLog, 2200);

</script>
</body>
</html>
