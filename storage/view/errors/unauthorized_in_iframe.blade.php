<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>未登录 - 正在刷新主页面</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        
        .container {
            text-align: center;
            padding: 2rem;
        }
        
        .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.05);
            }
        }
        
        h1 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        p {
            font-size: 1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }
        
        .spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔒</div>
        <h1>{{ $message ?? '检测到未登录' }}</h1>
        <p>正在刷新主页面...</p>
        <div class="spinner"></div>
    </div>

    <script>
        (function() {
            'use strict';
            
            // 尝试通知主页面刷新
            function notifyMainFrameRefresh() {
                try {
                    // 方式1：使用 AdminIframeClient（如果存在）
                    if (window.AdminIframeClient && typeof window.AdminIframeClient.refreshMainFrame === 'function') {
                        console.log('[UnauthorizedInIframe] 使用 AdminIframeClient.refreshMainFrame 通知主页面刷新');
                        window.AdminIframeClient.refreshMainFrame({
                            message: '{{ $message ?? "检测到未登录，正在刷新主页面" }}',
                            delay: 0,
                            showToast: false
                        });
                        return;
                    }
                    
                    // 方式2：使用 postMessage 通知父窗口
                    if (window.parent && window.parent !== window) {
                        console.log('[UnauthorizedInIframe] 使用 postMessage 通知主页面刷新');
                        window.parent.postMessage({
                            channel: 'admin-iframe-shell',
                            action: 'refresh-main',
                            payload: {
                                message: '{{ $message ?? "检测到未登录，正在刷新主页面" }}',
                                delay: 0,
                                showToast: false
                            },
                            source: window.location.href
                        }, window.location.origin);
                        return;
                    }
                    
                    // 方式3：直接刷新父窗口（降级方案）
                    if (window.top && window.top !== window) {
                        console.log('[UnauthorizedInIframe] 直接刷新主窗口（降级方案）');
                        try {
                            window.top.location.reload();
                            return;
                        } catch (e) {
                            console.warn('[UnauthorizedInIframe] 无法刷新主窗口（可能是跨域限制）:', e);
                        }
                    }
                    
                    // 方式4：如果无法访问父窗口，刷新当前窗口
                    console.log('[UnauthorizedInIframe] 无法访问父窗口，刷新当前窗口');
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                    
                } catch (error) {
                    console.error('[UnauthorizedInIframe] 通知主页面刷新失败:', error);
                    // 降级方案：刷新当前窗口
                    setTimeout(function() {
                        window.location.reload();
                    }, 1000);
                }
            }
            
            // 页面加载后立即执行
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', notifyMainFrameRefresh);
            } else {
                notifyMainFrameRefresh();
            }
            
            // 延迟执行一次（防止第一次执行失败）
            setTimeout(notifyMainFrameRefresh, 500);
        })();
    </script>
</body>
</html>

















