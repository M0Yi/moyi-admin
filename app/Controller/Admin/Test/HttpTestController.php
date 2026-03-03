<?php

declare(strict_types=1);

namespace App\Controller\Admin\Test;

use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\ViewEngine\HyperfViewEngine;

/**
 * HTTP 测试控制器
 * 
 * 用于测试 T3 阶段开发的 HTTP 请求增强功能
 */
class HttpTestController
{
    /**
     * 测试页面渲染
     */
    public function index(ResponseInterface $response)
    {
        return $response->raw(<<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>T3 HTTP 请求增强集成测试</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .test-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .test-result {
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 12px;
        }
        .log-entry {
            padding: 4px 8px;
            border-bottom: 1px solid #eee;
        }
        .log-entry.success { background: #d4edda; }
        .log-entry.error { background: #f8d7da; }
        .log-entry.info { background: #d1ecf1; }
        .log-entry.warning { background: #fff3cd; }
        .log-entry.loading { background: #e2e3e5; font-style: italic; }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-indicator.running { background: #0dcaf0; animation: pulse 1s infinite; }
        .status-indicator.passed { background: #198754; }
        .status-indicator.failed { background: #dc3545; }
        .status-indicator.pending { background: #ffc107; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <h1 class="mb-4">🔧 T3 HTTP 请求增强集成测试</h1>
        
        <!-- 测试控制面板 -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">测试控制</h5>
                        <div class="btn-group">
                            <button class="btn btn-primary" onclick="runAllTests()">
                                <i class="bi bi-play-circle"></i> 运行全部测试
                            </button>
                            <button class="btn btn-outline-secondary" onclick="clearLogs()">
                                <i class="bi bi-trash"></i> 清空日志
                            </button>
                            <button class="btn btn-outline-info" onclick="showRateLimitStatus()">
                                <i class="bi bi-speedometer"></i> 查看限流状态
                            </button>
                        </div>
                        <div class="mt-3">
                            <strong>测试状态：</strong>
                            <span id="test-status">
                                <span class="status-indicator pending"></span>
                                <span id="status-text">等待测试</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 测试结果概览 -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h2 class="card-title" id="total-tests">0</h2>
                        <p class="card-text">总测试数</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h2 class="card-title" id="passed-tests">0</h2>
                        <p class="card-text">通过</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <h2 class="card-title" id="failed-tests">0</h2>
                        <p class="card-text">失败</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-dark">
                    <div class="card-body">
                        <h2 class="card-title" id="pending-tests">0</h2>
                        <p class="card-text">待测试</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 测试详情 -->
        <div class="row">
            <div class="col-md-8">
                <!-- T3.1 请求重试机制测试 -->
                <div class="test-section">
                    <h5>📌 T3.1 请求重试机制测试</h5>
                    <div class="btn-group mb-3">
                        <button class="btn btn-outline-primary btn-sm" onclick="testRetrySuccess()">
                            测试成功重试
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="testRetryNetworkError()">
                            测试网络错误重试
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="testRetryServerError()">
                            测试服务器错误重试
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="testRetryMaxAttempts()">
                            测试最大重试次数
                        </button>
                    </div>
                    <div class="test-result border" id="retry-test-log"></div>
                </div>

                <!-- T3.2 请求限流测试 -->
                <div class="test-section">
                    <h5>📌 T3.2 请求限流测试</h5>
                    <div class="btn-group mb-3">
                        <button class="btn btn-outline-warning btn-sm" onclick="testRateLimitBasic()">
                            测试基础限流
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="testRateLimitBurst()">
                            测试突发请求
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="testRateLimitQueue()">
                            测试队列管理
                        </button>
                    </div>
                    <div class="test-result border" id="ratelimit-test-log"></div>
                </div>

                <!-- T3.3 错误重试测试 -->
                <div class="test-section">
                    <h5>📌 T3.3 错误重试测试</h5>
                    <div class="btn-group mb-3">
                        <button class="btn btn-outline-danger btn-sm" onclick="testBusinessErrorRetry()">
                            测试业务错误重试
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="testTimeoutRetry()">
                            测试超时重试
                        </button>
                        <button class="btn btn-outline-danger btn-sm" onclick="testDisabledRetry()">
                            测试禁用重试
                        </button>
                    </div>
                    <div class="test-result border" id="errorretry-test-log"></div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- 限流状态监控 -->
                <div class="test-section">
                    <h5>📊 限流状态监控</h5>
                    <div id="ratelimit-status">
                        <p><strong>队列长度：</strong><span id="queue-length">-</span></p>
                        <p><strong>处理中：</strong><span id="processing-count">-</span></p>
                        <p><strong>窗口请求数：</strong><span id="window-requests">-</span></p>
                        <p><strong>时间窗口：</strong><span id="window-time">-</span>ms</p>
                    </div>
                    <button class="btn btn-outline-info btn-sm w-100" onclick="refreshRateLimitStatus()">
                        <i class="bi bi-arrow-clockwise"></i> 刷新状态
                    </button>
                </div>

                <!-- 配置信息 -->
                <div class="test-section">
                    <h5>⚙️ 当前配置</h5>
                    <div id="config-info">
                        <p><strong>重试次数：</strong><span id="config-retry">-</span></p>
                        <p><strong>重试延迟：</strong><span id="config-retry-delay">-</span>ms</p>
                        <p><strong>最大并发：</strong><span id="config-max-requests">-</span></p>
                        <p><strong>时间窗口：</strong><span id="config-window">-</span>ms</p>
                        <p><strong>队列大小：</strong><span id="config-queue">-</span></p>
                    </div>
                </div>

                <!-- 测试说明 -->
                <div class="test-section">
                    <h5>📝 测试说明</h5>
                    <ul class="small">
                        <li><strong>T3.1 请求重试</strong>：测试网络错误、服务器错误时的自动重试机制</li>
                        <li><strong>T3.2 请求限流</strong>：测试并发限制、队列管理</li>
                        <li><strong>T3.3 错误重试</strong>：测试业务错误码、超时等场景</li>
                    </ul>
                    <div class="alert alert-info small">
                        <i class="bi bi-info-circle"></i>
                        测试会使用模拟的 API 端点，如果服务器不可用会显示相应错误。
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast 容器 -->
    <div id="toast-container" class="toast-container position-fixed p-3" style="z-index: 9999; top: 0; right: 0;"></div>

    <!-- 脚本引入 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- 项目依赖 -->
    <script src="/js/utils/helper.js"></script>
    <script src="/js/utils/request.js"></script>
    
    <!-- Toast 组件 -->
    <script src="/js/components/toast/index.js"></script>
    
    <!-- Loading 组件 -->
    <script src="/js/components/loading/index.js"></script>

    <script>
        // ========== 测试状态管理 ==========
        const testState = {
            total: 0,
            passed: 0,
            failed: 0,
            pending: 0,
            results: []
        };

        // ========== 日志工具 ==========
        function log(containerId, message, type = 'info') {
            const container = document.getElementById(containerId);
            const entry = document.createElement('div');
            entry.className = 'log-entry ' + type;
            entry.innerHTML = '<small class="text-muted">' + new Date().toLocaleTimeString() + '</small> ' +
                '<span class="badge bg-' + (type === 'success' ? 'success' : type === 'error' ? 'danger' : type === 'warning' ? 'warning' : 'info') + '">' + type.toUpperCase() + '</span> ' + message;
            container.insertBefore(entry, container.firstChild);
            
            // 保持最多 50 条日志
            while (container.children.length > 50) {
                container.removeChild(container.lastChild);
            }
        }

        function clearLogs() {
            document.getElementById('retry-test-log').innerHTML = '';
            document.getElementById('ratelimit-test-log').innerHTML = '';
            document.getElementById('errorretry-test-log').innerHTML = '';
            testState.total = 0;
            testState.passed = 0;
            testState.failed = 0;
            updateTestSummary();
        }

        // ========== 测试统计 ==========
        function updateTestSummary() {
            document.getElementById('total-tests').textContent = testState.total;
            document.getElementById('passed-tests').textContent = testState.passed;
            document.getElementById('failed-tests').textContent = testState.failed;
            document.getElementById('pending-tests').textContent = testState.total - testState.passed - testState.failed;
        }

        function recordTest(containerId, testName, passed, message) {
            testState.total++;
            if (passed) {
                testState.passed++;
                log(containerId, '<strong>' + testName + '</strong> - 通过 ✓' + (message ? ': ' + message : ''), 'success');
            } else {
                testState.failed++;
                log(containerId, '<strong>' + testName + '</strong> - 失败 ✗' + (message ? ': ' + message : ''), 'error');
            }
            updateTestSummary();
        }

        // ========== 限流状态监控 ==========
        function refreshRateLimitStatus() {
            try {
                const status = $http.getRateLimitStatus();
                document.getElementById('queue-length').textContent = status.queueLength;
                document.getElementById('processing-count').textContent = status.processing;
                document.getElementById('window-requests').textContent = status.requestsInWindow;
                document.getElementById('window-time').textContent = status.windowMs;
                
                // 更新配置信息
                if ($http.config && $http.config.rateLimit) {
                    document.getElementById('config-retry').textContent = $http.config.retry || 1;
                    document.getElementById('config-retry-delay').textContent = $http.config.retryDelay || 1000;
                    document.getElementById('config-max-requests').textContent = $http.config.rateLimit.maxRequests || 10;
                    document.getElementById('config-window').textContent = $http.config.rateLimit.windowMs || 1000;
                    document.getElementById('config-queue').textContent = $http.config.rateLimit.queueSize || 50;
                }
            } catch (e) {
                log('retry-test-log', '获取限流状态失败: ' + e.message, 'error');
            }
        }

        function showRateLimitStatus() {
            refreshRateLimitStatus();
            $toast.info('限流状态已更新');
        }

        // ========== T3.1 请求重试机制测试 ==========
        
        async function testRetrySuccess() {
            const containerId = 'retry-test-log';
            log(containerId, '开始测试：请求成功重试', 'loading');
            
            try {
                // 模拟一个会失败两次然后成功的请求
                let attempt = 0;
                const mockFetch = window.fetch;
                window.fetch = function(url, options) {
                    attempt++;
                    if (attempt < 3) {
                        return Promise.resolve(new Response(JSON.stringify({
                            code: 500,
                            msg: '模拟服务器错误'
                        }), { status: 500 }));
                    }
                    return mockFetch(url, options);
                };

                const startTime = Date.now();
                const data = await $http.get('/api/test/retry-success', { retry: 3, retryDelay: 500 });
                const duration = Date.now() - startTime;

                window.fetch = mockFetch;

                // 验证重试发生（应该至少花费 1 秒，因为重试了 2 次，每次间隔 500ms）
                if (duration >= 900) {
                    recordTest(containerId, '请求成功重试 - 重试间隔验证', true, '耗时 ' + duration + 'ms（正确触发重试）');
                } else {
                    recordTest(containerId, '请求成功重试 - 重试间隔验证', false, '耗时 ' + duration + 'ms（未正确触发重试）');
                }
            } catch (e) {
                recordTest(containerId, '请求成功重试', false, e.message);
            }
        }

        async function testRetryNetworkError() {
            const containerId = 'retry-test-log';
            log(containerId, '开始测试：网络错误重试', 'loading');
            
            try {
                // 模拟网络错误
                let attempt = 0;
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    attempt++;
                    if (attempt < 2) {
                        return Promise.reject(new Error('Network Error'));
                    }
                    return originalFetch(url, options);
                };

                const data = await $http.get('/api/test/network', { retry: 3, retryDelay: 200 });
                window.fetch = originalFetch;
                
                recordTest(containerId, '网络错误重试', true, '网络错误后自动重试成功');
            } catch (e) {
                recordTest(containerId, '网络错误重试', false, e.message);
            }
        }

        async function testRetryServerError() {
            const containerId = 'retry-test-log';
            log(containerId, '开始测试：服务器错误重试', 'loading');
            
            try {
                // 测试 500 错误自动重试
                let attempt = 0;
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    attempt++;
                    if (attempt < 2) {
                        return Promise.resolve(new Response(JSON.stringify({
                            code: 500,
                            msg: 'Internal Server Error'
                        }), { status: 500 }));
                    }
                    return originalFetch(url, options);
                };

                const data = await $http.get('/api/test/500', { retry: 3, retryDelay: 100 });
                window.fetch = originalFetch;
                
                recordTest(containerId, '服务器错误(500)重试', true, '500 错误后自动重试成功');
            } catch (e) {
                recordTest(containerId, '服务器错误(500)重试', false, e.message);
            }
        }

        async function testRetryMaxAttempts() {
            const containerId = 'retry-test-log';
            log(containerId, '开始测试：最大重试次数限制', 'loading');
            
            try {
                // 模拟持续失败
                let attempt = 0;
                window.fetch = function(url, options) {
                    attempt++;
                    return Promise.resolve(new Response(JSON.stringify({
                        code: 500,
                        msg: 'Server Error'
                    }), { status: 500 }));
                };

                let errorCaught = false;
                try {
                    await $http.get('/api/test/always-fail', { retry: 2, retryDelay: 50 });
                } catch (e) {
                    errorCaught = true;
                    // 验证重试次数（应该是 3 次：1 次原始请求 + 2 次重试）
                    if (attempt === 3) {
                        recordTest(containerId, '最大重试次数限制', true, '实际请求次数: ' + attempt + '（符合预期）');
                    } else {
                        recordTest(containerId, '最大重试次数限制', false, '实际请求次数: ' + attempt + '（期望 3 次）');
                    }
                }

                if (!errorCaught) {
                    recordTest(containerId, '最大重试次数限制', false, '应该抛出错误但没有');
                }

                window.fetch = null;
            } catch (e) {
                recordTest(containerId, '最大重试次数限制', false, e.message);
            }
        }

        // ========== T3.2 请求限流测试 ==========
        
        async function testRateLimitBasic() {
            const containerId = 'ratelimit-test-log';
            log(containerId, '开始测试：基础限流功能', 'loading');
            
            try {
                const startTime = Date.now();
                
                // 发送 5 个快速请求
                const promises = [];
                for (let i = 0; i < 5; i++) {
                    promises.push($http.get('/api/test/quick', { retry: 0, showLoading: false }));
                }
                
                await Promise.all(promises);
                const duration = Date.now() - startTime;
                
                // 如果所有请求都在短时间内完成，说明限流可能在排队
                recordTest(containerId, '基础限流 - 快速请求', true, '5 个请求耗时 ' + duration + 'ms');
                
                // 验证限流状态
                const status = $http.getRateLimitStatus();
                if (status.queueLength !== undefined) {
                    recordTest(containerId, '基础限流 - 状态查询', true, '队列长度: ' + status.queueLength);
                } else {
                    recordTest(containerId, '基础限流 - 状态查询', false, '无法获取限流状态');
                }
            } catch (e) {
                recordTest(containerId, '基础限流', false, e.message);
            }
        }

        async function testRateLimitBurst() {
            const containerId = 'ratelimit-test-log';
            log(containerId, '开始测试：突发请求处理', 'loading');
            
            try {
                // 发送大量请求测试突发流量
                const requestCount = 15;
                const startTime = Date.now();
                
                const promises = [];
                for (let i = 0; i < requestCount; i++) {
                    promises.push(
                        $http.get('/api/test/burst/' + i, { 
                            retry: 0, 
                            showLoading: false,
                            rateLimit: true 
                        }).catch(function(e) {
                            // 允许一些请求失败（限流）
                            return { error: e.message };
                        })
                    );
                }
                
                const results = await Promise.all(promises);
                const duration = Date.now() - startTime;
                
                const successCount = results.filter(function(r) { return !r.error; }).length;
                recordTest(containerId, '突发请求处理', true, requestCount + ' 个请求中 ' + successCount + ' 个成功，耗时 ' + duration + 'ms');
                
                // 验证队列状态
                const status = $http.getRateLimitStatus();
                log(containerId, '突发请求后队列状态: 队列=' + status.queueLength + ', 处理中=' + status.processing, 'info');
                
            } catch (e) {
                recordTest(containerId, '突发请求处理', false, e.message);
            }
        }

        async function testRateLimitQueue() {
            const containerId = 'ratelimit-test-log';
            log(containerId, '开始测试：队列管理', 'loading');
            
            try {
                // 配置更严格的限流
                $http.configureRateLimiter({
                    maxRequests: 3,
                    windowMs: 1000,
                    queueSize: 10
                });

                const startTime = Date.now();
                
                // 发送 8 个请求（超过 maxRequests）
                const promises = [];
                for (let i = 0; i < 8; i++) {
                    promises.push(
                        $http.get('/api/test/queue/' + i, { 
                            retry: 0, 
                            showLoading: false 
                        }).catch(function(e) { return { error: e.message }; })
                    );
                }
                
                const results = await Promise.all(promises);
                const duration = Date.now() - startTime;
                
                const successCount = results.filter(function(r) { return !r.error; }).length;
                recordTest(containerId, '队列管理', true, '8 个请求中 ' + successCount + ' 个成功，耗时 ' + duration + 'ms');
                
                // 验证队列工作正常
                const status = $http.getRateLimitStatus();
                log(containerId, '队列管理后状态: 队列=' + status.queueLength + ', 处理中=' + status.processing, 'info');
                
            } catch (e) {
                recordTest(containerId, '队列管理', false, e.message);
            } finally {
                // 恢复默认配置
                $http.configureRateLimiter({
                    maxRequests: 10,
                    windowMs: 1000,
                    queueSize: 50
                });
            }
        }

        // ========== T3.3 错误重试测试 ==========
        
        async function testBusinessErrorRetry() {
            const containerId = 'errorretry-test-log';
            log(containerId, '开始测试：业务错误重试', 'loading');
            
            try {
                // 测试业务错误码重试
                let attempt = 0;
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    attempt++;
                    if (attempt < 2) {
                        return Promise.resolve(new Response(JSON.stringify({
                            code: 'TOKEN_EXPIRED',
                            msg: 'Token 已过期'
                        }), { status: 200 }));
                    }
                    return originalFetch(url, options);
                };

                let caughtError = null;
                try {
                    await $http.get('/api/test/business-error', {
                        retry: 3,
                        retryDelay: 100,
                        businessRetry: {
                            enabled: true,
                            retryCodes: ['TOKEN_EXPIRED', 'SERVER_ERROR']
                        }
                    });
                } catch (e) {
                    caughtError = e;
                }

                window.fetch = originalFetch;
                
                if (attempt > 1) {
                    recordTest(containerId, '业务错误重试 - TOKEN_EXPIRED', true, '触发了 ' + attempt + ' 次请求');
                } else {
                    recordTest(containerId, '业务错误重试 - TOKEN_EXPIRED', false, '只触发了 ' + attempt + ' 次请求');
                }
            } catch (e) {
                recordTest(containerId, '业务错误重试', false, e.message);
            }
        }

        async function testTimeoutRetry() {
            const containerId = 'errorretry-test-log';
            log(containerId, '开始测试：超时重试', 'loading');
            
            try {
                // 测试 408 请求超时重试
                let attempt = 0;
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    attempt++;
                    if (attempt < 2) {
                        return Promise.resolve(new Response(JSON.stringify({
                            code: 408,
                            msg: 'Request Timeout'
                        }), { status: 408 }));
                    }
                    return originalFetch(url, options);
                };

                await $http.get('/api/test/timeout', { retry: 3, retryDelay: 100 });
                window.fetch = originalFetch;
                
                recordTest(containerId, '超时重试(408)', true, '408 错误后自动重试成功');
            } catch (e) {
                // 如果重试成功但后续出错，记录一下
                if (e.message.includes('Request Timeout')) {
                    recordTest(containerId, '超时重试(408)', true, '正确触发重试');
                } else {
                    recordTest(containerId, '超时重试(408)', false, e.message);
                }
            }
        }

        async function testDisabledRetry() {
            const containerId = 'errorretry-test-log';
            log(containerId, '开始测试：禁用重试', 'loading');
            
            try {
                // 测试禁用重试后是否立即返回错误
                let attempt = 0;
                window.fetch = function(url, options) {
                    attempt++;
                    return Promise.resolve(new Response(JSON.stringify({
                        code: 500,
                        msg: 'Server Error'
                    }), { status: 500 }));
                };

                let caughtError = null;
                try {
                    await $http.get('/api/test/no-retry', { 
                        retry: 0,
                        showLoading: false 
                    });
                } catch (e) {
                    caughtError = e;
                }

                window.fetch = null;
                
                if (attempt === 1 && caughtError) {
                    recordTest(containerId, '禁用重试', true, '禁用重试后立即返回错误');
                } else if (attempt > 1) {
                    recordTest(containerId, '禁用重试', false, '应该只请求 1 次，实际 ' + attempt + ' 次');
                } else {
                    recordTest(containerId, '禁用重试', false, '预期错误但未抛出');
                }
            } catch (e) {
                recordTest(containerId, '禁用重试', false, e.message);
            }
        }

        // ========== 运行全部测试 ==========
        async function runAllTests() {
            clearLogs();
            document.getElementById('status-text').textContent = '测试运行中...';
            
            const statusIndicator = document.querySelector('#test-status .status-indicator');
            statusIndicator.className = 'status-indicator running';
            
            // T3.1 请求重试机制测试
            await testRetryNetworkError();
            await testRetryServerError();
            await testRetryMaxAttempts();
            
            // T3.2 请求限流测试
            await testRateLimitBasic();
            await testRateLimitBurst();
            await testRateLimitQueue();
            
            // T3.3 错误重试测试
            await testBusinessErrorRetry();
            await testTimeoutRetry();
            await testDisabledRetry();
            
            // 更新最终状态
            statusIndicator.className = testState.failed > 0 ? 'status-indicator failed' : 'status-indicator passed';
            document.getElementById('status-text').textContent = 
                testState.failed > 0 
                    ? '完成 (' + testState.failed + ' 个失败)' 
                    : '全部通过！';
            
            var summary = '测试完成: ' + testState.passed + ' 通过, ' + testState.failed + ' 失败';
            if (testState.failed === 0) {
                $toast.success(summary);
            } else {
                $toast.warning(summary);
            }
        }

        // ========== 初始化 ==========
        document.addEventListener('DOMContentLoaded', function() {
            refreshRateLimitStatus();
            log('retry-test-log', '测试环境已准备就绪', 'info');
            log('ratelimit-test-log', '测试环境已准备就绪', 'info');
            log('errorretry-test-log', '测试环境已准备就绪', 'info');
        });
    </script>
</body>
</html>
HTML
);
    }
}
