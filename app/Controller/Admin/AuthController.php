<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminLoginLog;
use App\Model\Admin\AdminUser;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Hyperf\Contract\SessionInterface;
use HyperfExtension\Auth\Contracts\AuthManagerInterface;

class AuthController extends AbstractController
{
    #[Inject]
    protected SessionInterface $session;
    #[Inject]
    protected AuthManagerInterface $auth;

    /**
     * 登录页面
     */
    public function login(): ResponseInterface
    {
        $captchaUrl = '/captcha';
        return $this->render->render('admin.auth.login', [
            'captchaUrl' => $captchaUrl,
        ]);
    }

    /**
     * 登录提交
     */
    public function doLogin(RequestInterface $request): ResponseInterface
    {
        $data = $request->all();
        $username = trim((string) ($data['username'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        // 获取一些请求信息
        $serverParams = $request->getServerParams();
        $userAgent = $request->getHeaderLine('User-Agent') ?: null;

        // 解析 ip 与 ip_list（简单实现，兼容常见头）
        $xForwardedFor = $request->getHeaderLine('X-Forwarded-For');
        $ipList = [];
        if (!empty($xForwardedFor)) {
            $parts = array_map('trim', explode(',', $xForwardedFor));
            foreach ($parts as $p) {
                if (filter_var($p, FILTER_VALIDATE_IP)) {
                    $ipList[] = $p;
                }
            }
        }
        $xRealIp = $request->getHeaderLine('X-Real-IP');
        if (!empty($xRealIp) && filter_var($xRealIp, FILTER_VALIDATE_IP)) {
            $ipList[] = trim($xRealIp);
        }
        $remoteAddr = $serverParams['REMOTE_ADDR'] ?? $serverParams['remote_addr'] ?? null;
        if ($remoteAddr && filter_var($remoteAddr, FILTER_VALIDATE_IP)) {
            $ipList[] = $remoteAddr;
        }
        $ipList = array_values(array_unique($ipList));
        $ip = $ipList[0] ?? ($remoteAddr ?? '0.0.0.0');

        // 记录基础登录日志数据
        $siteId = (int) (site_id() ?? 0);
        // 后台入口路径（站点配置优先，回退到 URL 解析）
        $siteObj = function_exists('site') ? site() : null;
        $adminEntryPath = $siteObj?->admin_entry_path ?? null;
        if (empty($adminEntryPath)) {
            $requestPath = $request->getUri()->getPath();
            if (preg_match('#^/admin/([^/]+)#', $requestPath, $m)) {
                $adminEntryPath = $m[1];
            }
        }

        try {
            // 查找用户
            $user = AdminUser::query()
                ->where('username', $username)
                ->first();

            if (! $user) {
                // 未找到用户 -> 记录失败日志
                AdminLoginLog::create([
                    'site_id' => $siteId,
                    'user_id' => null,
                    'username' => $username,
                    'ip' => $ip,
                    'ip_list' => $ipList,
                    'admin_entry_path' => $adminEntryPath,
                    'user_agent' => $userAgent,
                    'status' => AdminLoginLog::STATUS_FAILED,
                    'message' => '用户名或密码错误',
                ]);

                return $this->error('用户名或密码错误', null, 401);
            }

            // 检查用户状态
            if ($user->status != 1) {
                AdminLoginLog::create([
                    'site_id' => $siteId,
                    'user_id' => $user->id,
                    'username' => $username,
                    'ip' => $ip,
                    'ip_list' => $ipList,
                    'admin_entry_path' => $adminEntryPath,
                    'user_agent' => $userAgent,
                    'status' => AdminLoginLog::STATUS_FAILED,
                    'message' => '账号已被禁用',
                ]);

                return $this->error('账号已被禁用', null, 403);
            }

            // 验证密码
            if (! $user->verifyPassword($password)) {
                AdminLoginLog::create([
                    'site_id' => $siteId,
                    'user_id' => $user->id,
                    'username' => $username,
                    'ip' => $ip,
                    'ip_list' => $ipList,
                    'admin_entry_path' => $adminEntryPath,
                    'user_agent' => $userAgent,
                    'status' => AdminLoginLog::STATUS_FAILED,
                    'message' => '用户名或密码错误',
                ]);

                return $this->error('用户名或密码错误', null, 401);
            }

            // 登录成功：设置 Session（页面登录）
            $this->session->set('admin_user_id', $user->id);
            $this->session->set('admin_site_id', $user->site_id ?? $siteId);
            $this->session->set('admin_user', $user->toArray());

            // 使用 guard 的登录方法（更标准），若不可用则回退到 setUser
            try {
                if (method_exists($this->auth->guard('admin'), 'login')) {
                    $this->auth->guard('admin')->login($user);
                } elseif (method_exists($this->auth->guard('admin'), 'setUser')) {
                    $this->auth->guard('admin')->setUser($user);
                }
            } catch (\Throwable $e) {
                // 记录但不阻塞登录流程
                try {
                    logger()->warning('Auth guard login fallback failed', ['error' => $e->getMessage()]);
                } catch (\Throwable $_) {}
            }

            // 记录 guard 状态以便调试
            try {
                $guardCheck = false;
                try {
                    $guardCheck = $this->auth->guard('admin')->check();
                } catch (\Throwable $_) {}
                logger()->info('AuthController login guard check after login', [
                    'user_id' => $user->id,
                    'guard_check' => $guardCheck,
                ]);
            } catch (\Throwable $_) {}
            
            // 记录 Session 状态以便排查（session id / 常用键 / 全量）
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
                if (method_exists($this->session, 'all')) {
                    try {
                        $sessionInfo['all'] = $this->session->all();
                    } catch (\Throwable $_) {}
                }
                logger()->info('AuthController session after login', $sessionInfo);
            } catch (\Throwable $_) {}

            // 更新最后登录信息
            $user->update([
                'last_login_ip' => $ip,
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);

            // 记录成功日志
            AdminLoginLog::create([
                'site_id' => $siteId,
                'user_id' => $user->id,
                'username' => $username,
                'ip' => $ip,
                'ip_list' => $ipList,
                'admin_entry_path' => $adminEntryPath,
                'user_agent' => $userAgent,
                'status' => AdminLoginLog::STATUS_SUCCESS,
                'message' => '登录成功',
            ]);

            // 返回重定向到后台首页
            return $this->success(['redirect' => admin_route('dashboard')], '登录成功', 200);
        } catch (\Throwable $e) {
            // 记录失败日志（异常）
            try {
                AdminLoginLog::create([
                    'site_id' => $siteId,
                    'user_id' => $user->id ?? null,
                    'username' => $username,
                    'ip' => $ip,
                    'ip_list' => $ipList,
                    'admin_entry_path' => $adminEntryPath,
                    'user_agent' => $userAgent,
                    'status' => AdminLoginLog::STATUS_FAILED,
                    'message' => '登录异常: ' . $e->getMessage(),
                ]);
            } catch (\Throwable $_) {
                // 忽略二次失败
            }

            // 详细调试输出，包含 print_r 风格的 payload 信息
            try {
                $debug = [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'username' => $username,
                    'server_params' => $request->getServerParams(),
                    'request_headers' => $request->getHeaders(),
                    'session_info' => null,
                ];

                try {
                    if (method_exists($this->session, 'all')) {
                        $debug['session_info'] = $this->session->all();
                    } else {
                        $debug['session_info'] = [
                            'admin_user_id' => $this->session->get('admin_user_id'),
                            'admin_user' => $this->session->get('admin_user'),
                        ];
                    }
                } catch (\Throwable $_) {
                    $debug['session_info'] = 'session read failed';
                }

                // 打印到 stdout style（便于快速查看），同时写入日志 context
                print_r($debug);
                logger()->error('登录处理异常', [
                    'debug_print' => print_r($debug, true),
                ]);
            } catch (\Throwable $_) {
                // 忽略调试日志错误
            }

            return $this->error('登录失败，请稍后重试', null, 500);
        }
    }

    /**
     * 登出
     */
    public function logout(): ResponseInterface
    {
        // 记录将要登出的用户（如有）
        $userId = null;
        try {
            $userId = $this->session->get('admin_user_id');
        } catch (\Throwable $_) {}

        try {
            // 尝试通过 guard 注销
            try {
                $guard = $this->auth->guard('admin');
                if (method_exists($guard, 'logout')) {
                    $guard->logout();
                }
            } catch (\Throwable $_) {
                // 忽略 guard 注销失败
            }

            // 清除 session 中的用户信息
            try {
                if (method_exists($this->session, 'set')) {
                    $this->session->set('admin_user_id', null);
                    $this->session->set('admin_site_id', null);
                    $this->session->set('admin_user', null);
                }
                if (method_exists($this->session, 'destroy')) {
                    $this->session->destroy();
                }
            } catch (\Throwable $_) {
                // 忽略
            }

            try {
                logger()->info('AuthController logout', ['user_id' => $userId]);
            } catch (\Throwable $_) {}
        } catch (\Throwable $e) {
            try {
                logger()->warning('AuthController logout failed', ['error' => $e->getMessage()]);
            } catch (\Throwable $_) {}
        }

        // 重定向到登录页
        return $this->response->redirect(admin_route('login'));
    }
}

