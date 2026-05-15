@once
    <style>
        .iframe-shell-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(4px);
            z-index: 1090;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            transition: opacity 0.25s ease;
        }

        .iframe-shell-overlay[hidden] {
            opacity: 0;
            pointer-events: none;
        }

        .iframe-shell-container {
            width: 100%;
            max-width: 1280px;
            height: 90vh;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.35);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .iframe-shell-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            background: #f8fafc;
        }

        .iframe-shell-title {
            font-weight: 600;
            font-size: 1rem;
            color: #0f172a;
        }

        .iframe-shell-actions {
            display: flex;
            gap: 0.5rem;
        }

        .iframe-shell-action-btn {
            border: none;
            background: #e2e8f0;
            color: #0f172a;
            border-radius: 8px;
            padding: 0.4rem 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.9rem;
            transition: background 0.2s ease;
        }

        .iframe-shell-action-btn:hover {
            background: #cbd5f5;
        }

        .iframe-shell-body {
            flex: 1;
            position: relative;
            background: #0f172a;
        }

        .iframe-shell-body iframe {
            width: 100%;
            height: 100%;
            border: none;
            background: #fff;
        }

        .iframe-shell-loading {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.4);
            color: #fff;
            font-size: 0.95rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        @media (max-width: 992px) {
            .iframe-shell-container {
                height: 100vh;
                max-width: 100%;
                border-radius: 0;
            }

            .iframe-shell-overlay {
                padding: 0;
            }
        }
    </style>
@endonce

<div class="iframe-shell-overlay" data-iframe-shell hidden>
    <div class="iframe-shell-container" role="dialog" aria-modal="true">
        <div class="iframe-shell-header">
            <div class="iframe-shell-title" data-iframe-shell-title>加载中...</div>
            <div class="iframe-shell-actions">
                <button type="button"
                        class="iframe-shell-action-btn"
                        data-iframe-shell-open-tab
                        title="在新标签打开">
                    <i class="bi bi-window-stack"></i>
                    <span>新标签</span>
                </button>
                <button type="button"
                        class="iframe-shell-action-btn"
                        data-iframe-shell-open-new
                        title="在新窗口打开">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <span>新窗口</span>
                </button>
                <button type="button"
                        class="iframe-shell-action-btn"
                        data-iframe-shell-close
                        title="关闭">
                    <i class="bi bi-x-lg"></i>
                    <span>关闭</span>
                </button>
            </div>
        </div>
        <div class="iframe-shell-body">
            <div class="iframe-shell-loading" data-iframe-shell-loading>
                <span class="spinner-border spinner-border-sm me-2"></span>
                正在加载...
            </div>
            <iframe src="about:blank"
                    data-iframe-shell-frame
                    allowfullscreen
                    referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</div>

