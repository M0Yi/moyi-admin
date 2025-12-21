@extends('admin.layouts.admin')

@section('title', 'Cookie 测试')

@push('admin_sidebar')
    @include('admin.components.sidebar')
@endpush

@push('admin_navbar')
    @include('admin.components.navbar')
@endpush

@push('admin_styles')
<style>
    .cookie-table {
        font-size: 0.9rem;
    }
    .cookie-table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    .cookie-value {
        max-width: 300px;
        word-break: break-all;
        font-family: monospace;
        font-size: 0.85rem;
    }
    .badge-expired {
        background-color: #dc3545;
    }
    .badge-valid {
        background-color: #28a745;
    }
    .test-form {
        background-color: #f8f9fa;
        padding: 1.5rem;
        border-radius: 0.5rem;
        margin-top: 2rem;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-cookie"></i> Cookie 测试页面
                    </h5>
                </div>
                <div class="card-body">
                    {{-- 请求信息 --}}
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">请求信息</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Host:</strong> {{ $request_info['host'] ?? 'N/A' }}</p>
                                <p><strong>协议:</strong> {{ $request_info['scheme'] ?? 'N/A' }} 
                                    @if($request_info['is_secure'] ?? false)
                                        <span class="badge bg-success">HTTPS</span>
                                    @else
                                        <span class="badge bg-warning">HTTP</span>
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>当前时间:</strong> {{ $request_info['current_time_formatted'] ?? 'N/A' }}</p>
                                <p><strong>时间戳:</strong> {{ $request_info['current_time'] ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>
                    
                    {{-- 当前登录用户信息 --}}
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">当前登录用户</h6>
                        @php
                            $adminUser = $admin_user ?? null;
                            $adminUserId = $admin_user_id ?? null;
                        @endphp
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>用户ID:</strong> {{ $adminUserId ?? '-' }}</p>
                                <p><strong>用户名:</strong> {{ $adminUser['username'] ?? ($adminUser->username ?? '-') }}</p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Guard 状态:</strong>
                                    <button id="checkGuardBtn" class="btn btn-sm btn-outline-secondary">检查 guard</button>
                                </p>
                                <div id="guardCheckResult" class="mt-2"></div>
                            </div>
                        </div>
                    </div>

                    {{-- Session 与请求详细信息（调试） --}}
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">Session 与请求详细信息（调试）</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered cookie-table">
                                <tbody>
                                    <tr>
                                        <th width="200">Server Params</th>
                                        <td><pre style="white-space:pre-wrap;">@php echo json_encode($server_params ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); @endphp</pre></td>
                                    </tr>
                                    <tr>
                                        <th>Request Headers</th>
                                        <td><pre style="white-space:pre-wrap;">@php echo json_encode($request_headers ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); @endphp</pre></td>
                                    </tr>
                                    <tr>
                                        <th>Session Dump</th>
                                        <td><pre style="white-space:pre-wrap;">@php echo json_encode($session_dump ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); @endphp</pre></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <hr>

                    {{-- Session Cookie 配置信息 --}}
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">Session Cookie 配置</h6>
                        <div class="table-responsive">
                            <table class="table table-bordered cookie-table">
                                <tbody>
                                    <tr>
                                        <th width="200">Cookie 名称</th>
                                        <td><code>{{ $session_config['name'] ?? 'N/A' }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>过期时间（秒）</th>
                                        <td>
                                            {{ $session_config['lifetime'] ?? 0 }} 秒
                                            <span class="badge bg-info ms-2">{{ $session_config['lifetime_formatted'] ?? 'N/A' }}</span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>过期时间（日期）</th>
                                        <td>
                                            {{ $session_config['expires_at_formatted'] ?? 'N/A' }}
                                            @if($session_config['expires_at'] ?? null)
                                                @php
                                                    $expiresAt = $session_config['expires_at'];
                                                    $currentTime = $request_info['current_time'] ?? time();
                                                    $isExpired = $expiresAt < $currentTime;
                                                @endphp
                                                @if($isExpired)
                                                    <span class="badge badge-expired ms-2">已过期</span>
                                                @else
                                                    <span class="badge badge-valid ms-2">有效</span>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Path</th>
                                        <td><code>{{ $session_config['path'] ?? '/' }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Domain</th>
                                        <td><code>{{ $session_config['domain'] ?? '当前域名' }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>SameSite</th>
                                        <td><code>{{ $session_config['same_site'] ?? 'lax' }}</code></td>
                                    </tr>
                                    <tr>
                                        <th>Secure</th>
                                        <td>
                                            @if($session_config['secure'] ?? false)
                                                <span class="badge bg-success">是</span>
                                            @else
                                                <span class="badge bg-secondary">否</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>HttpOnly</th>
                                        <td>
                                            @if($session_config['http_only'] ?? true)
                                                <span class="badge bg-success">是</span>
                                            @else
                                                <span class="badge bg-secondary">否</span>
                                            @endif
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <hr>

                    {{-- 当前所有 Cookies --}}
                    <div class="mb-4">
                        <h6 class="text-muted mb-3">
                            当前所有 Cookies 
                            <span class="badge bg-primary">{{ $cookies_count ?? 0 }}</span>
                        </h6>
                        @if(empty($cookies))
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> 当前没有 Cookie
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover cookie-table">
                                    <thead>
                                        <tr>
                                            <th width="200">Cookie 名称</th>
                                            <th>值</th>
                                            <th width="100">长度</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($cookies as $cookie)
                                        <tr>
                                            <td><code>{{ $cookie['name'] ?? 'N/A' }}</code></td>
                                            <td>
                                                <div class="cookie-value" title="{{ $cookie['value'] ?? '' }}">
                                                    {{ $cookie['value_preview'] ?? 'N/A' }}
                                                </div>
                                            </td>
                                            <td>{{ $cookie['value_length'] ?? 0 }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <hr>

                    {{-- Cookie 测试工具 --}}
                    <div class="test-form">
                        <h6 class="mb-3">
                            <i class="bi bi-tools"></i> Cookie 测试工具
                        </h6>
                        <form id="setCookieForm" class="mb-3">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Cookie 名称</label>
                                    <input type="text" class="form-control" name="name" value="test_cookie" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Cookie 值</label>
                                    <input type="text" class="form-control" name="value" value="test_value" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">过期时间（秒）</label>
                                    <input type="number" class="form-control" name="expire" value="3600" min="0" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Path</label>
                                    <input type="text" class="form-control" name="path" value="/">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">操作</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> 设置 Cookie
                                    </button>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-3">
                                    <label class="form-label">Domain</label>
                                    <input type="text" class="form-control" name="domain" placeholder="留空使用当前域名">
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="secure" id="secureCheck">
                                        <label class="form-check-label" for="secureCheck">
                                            Secure (HTTPS only)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" name="http_only" id="httpOnlyCheck" checked>
                                        <label class="form-check-label" for="httpOnlyCheck">
                                            HttpOnly
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">操作</label>
                                    <button type="button" class="btn btn-danger w-100" onclick="deleteTestCookie()">
                                        <i class="bi bi-trash"></i> 删除 Cookie
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div id="cookieResult" class="mt-3"></div>
                    </div>

                    {{-- JavaScript Cookie 信息（客户端） --}}
                    <div class="mt-4">
                        <h6 class="text-muted mb-3">
                            <i class="bi bi-code-square"></i> JavaScript 读取的 Cookies
                            <button class="btn btn-sm btn-outline-primary ms-2" onclick="refreshJsCookies()">
                                <i class="bi bi-arrow-clockwise"></i> 刷新
                            </button>
                        </h6>
                        <div id="jsCookiesInfo" class="alert alert-info">
                            <i class="bi bi-hourglass-split"></i> 正在加载...
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('admin_scripts')
<script>
    // 页面加载时显示 JavaScript Cookies
    document.addEventListener('DOMContentLoaded', function() {
        refreshJsCookies();
    });

    // 刷新 JavaScript Cookies
    function refreshJsCookies() {
        const cookies = document.cookie.split(';');
        const cookiesInfo = [];
        
        cookies.forEach(function(cookie) {
            const parts = cookie.trim().split('=');
            if (parts.length >= 2) {
                const name = parts[0];
                const value = parts.slice(1).join('=');
                cookiesInfo.push({
                    name: name,
                    value: value,
                    length: value.length
                });
            }
        });

        const container = document.getElementById('jsCookiesInfo');
        if (cookiesInfo.length === 0) {
            container.innerHTML = '<i class="bi bi-info-circle"></i> JavaScript 无法读取到任何 Cookie（可能是 HttpOnly Cookie）';
            container.className = 'alert alert-warning';
        } else {
            let html = '<table class="table table-sm table-bordered mb-0">';
            html += '<thead><tr><th>Cookie 名称</th><th>值</th><th>长度</th></tr></thead>';
            html += '<tbody>';
            cookiesInfo.forEach(function(cookie) {
                const valuePreview = cookie.value.length > 50 ? cookie.value.substring(0, 50) + '...' : cookie.value;
                html += '<tr>';
                html += '<td><code>' + escapeHtml(cookie.name) + '</code></td>';
                html += '<td><span class="cookie-value" title="' + escapeHtml(cookie.value) + '">' + escapeHtml(valuePreview) + '</span></td>';
                html += '<td>' + cookie.length + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            container.innerHTML = html;
            container.className = 'alert alert-info';
        }
    }

    // HTML 转义
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // 设置 Cookie 表单提交
    document.getElementById('setCookieForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            if (key === 'secure' || key === 'http_only') {
                data[key] = document.getElementById(key === 'secure' ? 'secureCheck' : 'httpOnlyCheck').checked;
            } else {
                data[key] = value;
            }
        });

        fetch('{{ admin_route("cookie-test/set") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify(data)
        })
        .then(response => response.json())
        .then(result => {
            const resultDiv = document.getElementById('cookieResult');
            if (result.code === 200) {
                resultDiv.innerHTML = '<div class="alert alert-success">' +
                    '<i class="bi bi-check-circle"></i> ' + result.msg + '<br>' +
                    '<small>过期时间: ' + result.data.cookie_info.expires_at_formatted + '</small>' +
                    '</div>';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="bi bi-exclamation-circle"></i> ' + (result.msg || '设置失败') +
                    '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('cookieResult').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> 请求失败</div>';
        });
    });

    // 删除 Cookie
    function deleteTestCookie() {
        const name = document.querySelector('input[name="name"]').value;
        const path = document.querySelector('input[name="path"]').value || '/';
        const domain = document.querySelector('input[name="domain"]').value || '';

        fetch('{{ admin_route("cookie-test/delete") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                name: name,
                path: path,
                domain: domain
            })
        })
        .then(response => response.json())
        .then(result => {
            const resultDiv = document.getElementById('cookieResult');
            if (result.code === 200) {
                resultDiv.innerHTML = '<div class="alert alert-success">' +
                    '<i class="bi bi-check-circle"></i> ' + result.msg +
                    '</div>';
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="bi bi-exclamation-circle"></i> ' + (result.msg || '删除失败') +
                    '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('cookieResult').innerHTML = 
                '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> 请求失败</div>';
        });
    }

    // 检查 guard 状态
    document.getElementById('checkGuardBtn')?.addEventListener('click', function() {
        fetch('{{ admin_route("cookie-test/guard-check") }}', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
        })
        .then(response => response.json())
        .then(result => {
            const container = document.getElementById('guardCheckResult');
            if (result.code === 200) {
                container.innerHTML = '<pre style="white-space:pre-wrap;">' + JSON.stringify(result.data, null, 2) + '</pre>';
                container.className = 'alert alert-info';
            } else {
                container.innerHTML = '<div class="alert alert-warning">检查失败: ' + (result.msg || '') + '</div>';
            }
        })
        .catch(err => {
            const container = document.getElementById('guardCheckResult');
            container.innerHTML = '<div class="alert alert-danger">请求失败</div>';
        });
    });
</script>
@endpush

