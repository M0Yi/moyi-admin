<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Cookie\Cookie;
use Hyperf\Contract\SessionInterface;
use HyperfExtension\Auth\Contracts\AuthManagerInterface;

class CookieTestController extends AbstractController
{
    #[Inject]
    protected ConfigInterface $config;
    #[Inject]
    protected SessionInterface $session;
    #[Inject]
    protected AuthManagerInterface $auth;

    /**
     * Guard 检查（用于调试）
     */
    public function guardCheck()
    {
        try {
            $guard = $this->auth->guard('admin');
            $check = false;
            $user = null;
            try {
                $check = $guard->check();
            } catch (\Throwable $e) {
                logger()->warning('CookieTestController guard->check() threw', ['error' => $e->getMessage()]);
            }
            try {
                $u = $guard->user();
                if ($u) {
                    $user = is_object($u) && method_exists($u, 'toArray') ? $u->toArray() : $u;
                }
            } catch (\Throwable $e) {
                logger()->warning('CookieTestController guard->user() threw', ['error' => $e->getMessage()]);
            }

            // 记录调试日志：guard 状态 + request headers + session keys
            try {
                $sessionInfo = [];
                if (method_exists($this->session, 'getId')) {
                    try {
                        $sessionInfo['id'] = $this->session->getId();
                    } catch (\Throwable $_) {}
                }
                try {
                    $sessionInfo['admin_user_id'] = $this->session->get('admin_user_id');
                    $sessionInfo['admin_user'] = $this->session->get('admin_user');
                } catch (\Throwable $_) {}
                logger()->info('CookieTestController guardCheck', [
                    'guard_check' => $check,
                    'auth_user' => $user,
                    'request_headers' => $this->request->getHeaders(),
                    'session_info' => $sessionInfo,
                ]);
            } catch (\Throwable $_) {}

            return $this->success([
                'guard_check' => $check,
                'auth_user' => $user,
            ], 'guard status');
        } catch (\Throwable $e) {
            logger()->error('CookieTestController guardCheck failed', ['error' => $e->getMessage()]);
            return $this->error('guard check failed: ' . $e->getMessage(), null, 500);
        }
    }
    /**
     * Cookie 测试页面
     */
    public function index()
    {
        // 获取所有请求中的 cookies
        $requestCookies = $this->request->getCookieParams();
        
        // 处理 cookies 信息
        $cookiesInfo = [];
        foreach ($requestCookies as $name => $value) {
            $cookiesInfo[] = [
                'name' => $name,
                'value' => $value,
                'value_length' => strlen($value),
                'value_preview' => mb_strlen($value) > 50 ? mb_substr($value, 0, 50) . '...' : $value,
            ];
        }

        // 获取 Session Cookie 信息（如果存在）
        $sessionName = $this->config->get('session.options.session_name', 'HYPERF_SESSION_ID');
        $sessionCookieLifetime = $this->config->get('session.options.cookie_lifetime', 0);
        $sessionCookiePath = $this->config->get('session.options.path', '/');
        $sessionCookieDomain = $this->config->get('session.options.domain');
        $sessionCookieSameSite = $this->config->get('session.options.cookie_same_site', 'lax');

        // 计算 Session Cookie 过期时间
        $sessionCookieExpires = null;
        if ($sessionCookieLifetime > 0) {
            $sessionCookieExpires = time() + $sessionCookieLifetime;
        }

        // 获取当前请求的域名和协议
        $host = $this->request->getHeaderLine('Host');
        $scheme = $this->request->getUri()->getScheme();
        $isSecure = ($scheme === 'https');
        $serverParams = $this->request->getServerParams();

        // 尝试获取更多 Session 信息（若实现提供）
        $sessionDump = [];
        try {
            if (method_exists($this->session, 'getId')) {
                $sessionDump['id'] = $this->session->getId();
            }
            if (method_exists($this->session, 'all')) {
                $sessionDump['all'] = $this->session->all();
            }
        } catch (\Throwable $_) {
            // 忽略读取 session 全量信息失败
        }

        // 常见 Session 键
        $sessionDump['admin_user_id'] = $this->session->get('admin_user_id');
        $sessionDump['admin_site_id'] = $this->session->get('admin_site_id');
        $sessionDump['admin_user'] = $this->session->get('admin_user');

        return $this->renderAdmin('admin.cookie-test.index', [
            'cookies' => $cookiesInfo,
            'cookies_count' => count($cookiesInfo),
            'session_config' => [
                'name' => $sessionName,
                'lifetime' => $sessionCookieLifetime,
                'lifetime_formatted' => $this->formatSeconds($sessionCookieLifetime),
                'expires_at' => $sessionCookieExpires,
                'expires_at_formatted' => $sessionCookieExpires ? date('Y-m-d H:i:s', $sessionCookieExpires) : '浏览器关闭时过期',
                'path' => $sessionCookiePath,
                'domain' => $sessionCookieDomain ?: '当前域名',
                'same_site' => $sessionCookieSameSite,
                'secure' => $isSecure,
                'http_only' => true,
            ],
            'request_info' => [
                'host' => $host,
                'scheme' => $scheme,
                'is_secure' => $isSecure,
                'current_time' => time(),
                'current_time_formatted' => date('Y-m-d H:i:s'),
            ],
            'admin_user' => $this->session->get('admin_user'),
            'admin_user_id' => $this->session->get('admin_user_id'),
            'server_params' => $serverParams,
            'request_headers' => $this->request->getHeaders(),
            'session_dump' => $sessionDump,
        ]);
    }

    /**
     * 设置测试 Cookie
     */
    public function setTestCookie()
    {
        // 获取请求参数（支持 JSON 和表单数据）
        $params = $this->request->getParsedBody();
        if (is_array($params)) {
            $name = $params['name'] ?? 'test_cookie';
            $value = $params['value'] ?? 'test_value';
            $expire = (int)($params['expire'] ?? 3600); // 默认1小时
            $path = $params['path'] ?? '/';
            $domain = $params['domain'] ?? '';
            $secure = (bool)($params['secure'] ?? false);
            $httpOnly = (bool)($params['http_only'] ?? false);
        } else {
            // 如果解析失败，使用默认值
            $name = 'test_cookie';
            $value = 'test_value';
            $expire = 3600;
            $path = '/';
            $domain = '';
            $secure = false;
            $httpOnly = false;
        }

        // 计算过期时间戳
        $expiresAt = time() + $expire;

        // 创建 Cookie
        $cookie = new Cookie($name, $value, $expiresAt, $path, $domain, $secure, $httpOnly);

        // 设置 Cookie 到响应
        $response = $this->success([
            'message' => 'Cookie 设置成功',
            'cookie_info' => [
                'name' => $name,
                'value' => $value,
                'expires_at' => $expiresAt,
                'expires_at_formatted' => date('Y-m-d H:i:s', $expiresAt),
                'expire_seconds' => $expire,
                'expire_formatted' => $this->formatSeconds($expire),
                'path' => $path,
                'domain' => $domain ?: '当前域名',
                'secure' => $secure,
                'http_only' => $httpOnly,
            ],
        ], 'Cookie 设置成功');

        return $response->withCookie($cookie);
    }

    /**
     * 删除测试 Cookie
     */
    public function deleteTestCookie()
    {
        // 获取请求参数（支持 JSON 和表单数据）
        $params = $this->request->getParsedBody();
        if (is_array($params)) {
            $name = $params['name'] ?? 'test_cookie';
            $path = $params['path'] ?? '/';
            $domain = $params['domain'] ?? '';
        } else {
            $name = 'test_cookie';
            $path = '/';
            $domain = '';
        }

        // 设置过期时间为过去的时间来删除 cookie
        $cookie = new Cookie($name, '', time() - 3600, $path, $domain, false, false);

        $response = $this->success([
            'message' => 'Cookie 删除成功',
            'cookie_name' => $name,
        ], 'Cookie 删除成功');

        return $response->withCookie($cookie);
    }

    /**
     * 格式化秒数为可读格式
     */
    private function formatSeconds(int $seconds): string
    {
        if ($seconds <= 0) {
            return '浏览器关闭时过期';
        }

        $units = [
            '年' => 365 * 24 * 60 * 60,
            '月' => 30 * 24 * 60 * 60,
            '天' => 24 * 60 * 60,
            '小时' => 60 * 60,
            '分钟' => 60,
            '秒' => 1,
        ];

        $result = [];
        foreach ($units as $unit => $unitSeconds) {
            if ($seconds >= $unitSeconds) {
                $count = floor($seconds / $unitSeconds);
                $result[] = $count . $unit;
                $seconds %= $unitSeconds;
            }
        }

        return !empty($result) ? implode(' ', $result) : '0秒';
    }
}

