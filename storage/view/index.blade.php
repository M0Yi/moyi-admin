<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MoYi</title>
    <style>
        :root {
            --bg: #0f172a; /* slate-900 */
            --panel: #111827; /* gray-900 */
            --text: #e5e7eb; /* gray-200 */
            --muted: #9ca3af; /* gray-400 */
            --primary: #6366f1; /* indigo-500 */
            --success: #22c55e; /* green-500 */
            --danger: #ef4444; /* red-500 */
            --warning: #f59e0b; /* amber-500 */
            --card: #0b1220; /* custom deep */
            --border: #1f2937; /* gray-800 */
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: radial-gradient(1200px 800px at 10% 0%, #0b1220 10%, var(--bg) 60%),
                        radial-gradient(1000px 600px at 90% 0%, #111827 10%, var(--bg) 60%);
            color: var(--text);
        }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 40px 24px; }
        .hero {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            padding: 24px 28px; border: 1px solid var(--border); border-radius: 16px; background: var(--panel);
        }
        .hero h1 { margin: 0; font-size: 24px; font-weight: 700; letter-spacing: 0.3px; }
        .hero .meta { display: flex; gap: 14px; flex-wrap: wrap; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; background: #0b1220; border: 1px solid var(--border); color: var(--muted); font-size: 12px; }
        .grid { display: grid; grid-template-columns: repeat(12, 1fr); gap: 16px; margin-top: 20px; }
        .card { grid-column: span 6; border: 1px solid var(--border); border-radius: 12px; background: var(--card); padding: 18px; }
        .card h3 { margin: 0 0 10px; font-size: 16px; letter-spacing: 0.2px; }
        .rows { display: grid; gap: 10px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px dashed var(--border); }
        .row:last-child { border-bottom: none; }
        .k { color: var(--muted); font-size: 13px; }
        .v { font-size: 13px; }
        .status { display: inline-flex; align-items: center; gap: 8px; padding: 6px 10px; border-radius: 999px; border: 1px solid var(--border); font-size: 12px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .status-connected { color: var(--success); }
        .status-connected .dot { background: var(--success); box-shadow: 0 0 10px rgba(34,197,94,0.6); }
        .status-disconnected { color: var(--danger); }
        .status-disconnected .dot { background: var(--danger); box-shadow: 0 0 10px rgba(239,68,68,0.6); }
        .footer { margin-top: 24px; color: var(--muted); font-size: 12px; text-align: center; }
        @media (max-width: 900px) { .card { grid-column: span 12; } }
    </style>
</head>
<body>
<div class="wrap">
    <div class="hero">
        <h1>{{ $app['name'] ?? 'MoYi' }} · 环境自检面板</h1>
        <div class="meta">
            <span class="badge">Env: {{ $app['env'] ?? 'dev' }}</span>
            <span class="badge">PHP: {{ $app['php'] ?? '' }}</span>
            <span class="badge">Swoole: {{ ($app['swooleEnabled'] ?? false) ? ($app['swooleVersion'] ?? 'enabled') : 'disabled' }}</span>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Redis</h3>
            <div class="rows">
                <div class="row">
                    <span class="k">连接状态</span>
                    <span class="status {{ ($redisStatus ?? null) === 'connected' ? 'status-connected' : 'status-disconnected' }}">
                        <span class="dot"></span>
                        {{ $redisStatus ?? 'unknown' }}
                    </span>
                </div>
                <div class="row"><span class="k">地址</span><span class="v">{{ $redis['host'] ?? '—' }}:{{ $redis['port'] ?? '—' }}</span></div>

            </div>
        </div>

        <div class="card">
            <h3>MySQL</h3>
            <div class="rows">
                <div class="row">
                    <span class="k">连接状态</span>
                    <span class="status {{ ($mysqlStatus ?? null) === 'connected' ? 'status-connected' : 'status-disconnected' }}">
                        <span class="dot"></span>
                        {{ $mysqlStatus ?? 'unknown' }}
                    </span>
                </div>
                <div class="row"><span class="k">地址</span><span class="v">{{ $db['host'] ?? '—' }}:{{ $db['port'] ?? '—' }}</span></div>

            </div>
        </div>
    </div>

    <p class="footer">Hello, {{ $name }} · 本页面用于快速检查运行环境与连接状态。</p>
</div>
</body>
</html>
