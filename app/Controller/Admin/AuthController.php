<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminUser;
use App\Service\LoginAttemptService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Context\Context;

/**
 * 管理后台认证控制器
 */
class AuthController extends AbstractController
{
    #[Inject]
    protected LoginAttemptService $loginAttemptService;

    /**
     * 登录页面
     */
    public function login(): \Psr\Http\Message\ResponseInterface
    {
        // 如果已登录，跳转到仪表盘
        $adminUser = Context::get('admin_user');
        if ($adminUser) {
            $adminPath = Context::get('admin_entry_path', '/admin');
            return $this->response->redirect($adminPath . '/dashboard');
        }
        
        // 验证码接口 URL（通用接口，不在管理后台路径下）
        $captchaUrl = '/captcha';

        // 不需要传递 $site 到视图，视图中直接使用 site() 全局函数
        return $this->render->render('admin.auth.login', [
            'captchaUrl' => $captchaUrl,
        ]);
    }

    /**
     * 处理登录请求
     */
    public function doLogin(): \Psr\Http\Message\ResponseInterface
    {
        $data = $this->request->all();

        // 1. 验证输入数据
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return $this->error('请输入用户名和密码', null, 400);
        }

        // 2. 获取当前站点信息（使用 site() 全局函数）
        $site = site();
        if (!$site) {
            return $this->error('站点信息异常', null, 400);
        }

        try {
            // 3. 查询用户（根据用户名和站点ID）
            $user = \App\Model\Admin\AdminUser::query()
                ->where('username', $username)
                ->where('site_id', $site->id)
                ->first();

            // 4. 验证用户是否存在
            if (!$user instanceof AdminUser) {
                // 登录失败，增加失败次数
                $this->loginAttemptService->increment();
                return $this->error('用户名或密码错误', null, 400);
            }

            // 5. 验证密码（使用 Model 中的 verifyPassword 方法）
            if (!$user->verifyPassword($password)) {
                // 登录失败，增加失败次数
                $this->loginAttemptService->increment();
                return $this->error('用户名或密码错误', null, 400);
            }

            // 6. 检查用户状态
            if ($user->status != 1) {
                // 登录失败，增加失败次数
                $this->loginAttemptService->increment();
                return $this->error('账号已被禁用', null, 400);
            }

            // 7. 登录成功，清除失败次数
            $this->loginAttemptService->clear();

            // 8. 创建 Session
            $this->session->set('admin_user_id', $user->id);
            $this->session->set('admin_site_id', $site->id);

            // 9. 更新最后登录信息
            $user->update([
                'last_login_ip' => $this->getClientIp(),
                'last_login_at' => date('Y-m-d H:i:s'),
            ]);

            // 10. TODO: 记录登录日志
            // $this->logService->recordLogin($user, true);

            auth('admin')->login($user);

            // 11. 处理"记住我" - 设置长期 Cookie
            $remember = (int)($data['remember'] ?? 0) === 1;
            if ($remember) {
                $expires = time() + 60 * 60 * 24 * 30; // 30 天
                $payload = $user->id . '.' . $site->id . '.' . $expires;
                $signature = hash_hmac('sha256', $payload, (string)$user->password);
                $value = base64_encode($payload . '.' . $signature);

                $secure = ($this->request->getUri()->getScheme() === 'https');
                $cookie = new \Hyperf\HttpMessage\Cookie\Cookie('admin_remember', $value, $expires, '/', '', $secure, true);
                $this->response->withCookie($cookie);
            }


            return $this->success([
                    'redirect' => 'dashboard',
            ], '登录成功');

        } catch (\Throwable $e) {
            return $this->error('登录失败：' . $e->getMessage(), null, 500);
        }
    }

    /**
     * 获取客户端 IP
     */
    private function getClientIp(): string
    {
        $serverParams = $this->request->getServerParams();

        // 优先获取代理转发的真实 IP
        if (isset($serverParams['http_x_forwarded_for'])) {
            $ips = explode(',', $serverParams['http_x_forwarded_for']);
            return trim($ips[0]);
        }

        if (isset($serverParams['http_x_real_ip'])) {
            return $serverParams['http_x_real_ip'];
        }

        return $serverParams['remote_addr'] ?? '0.0.0.0';
    }


    /**
     * 退出登录
     */
    public function logout(): \Psr\Http\Message\ResponseInterface
    {
        // 1. 清除 Session
        $this->session->clear();

        // 2. 使 Session 无效
        $this->session->invalidate();

        // 3. 清除“记住我” Cookie
        try {
            $expired = time() - 3600;
            $cookie = new \Hyperf\HttpMessage\Cookie\Cookie('admin_remember', '', $expired, '/', '', false, true);
            $this->response->withCookie($cookie);
        } catch (\Throwable $e) {}

        // 4. 重定向到登录页
        $adminPath = Context::get('admin_entry_path', '/admin');
        return $this->response->redirect($adminPath . '/login');
    }
}
