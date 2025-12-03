<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Model\Admin\AdminUser;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\View\RenderInterface;
use HyperfExtension\Auth\Contracts\AuthManagerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function Hyperf\Support\env;

/**
 * 管理后台认证中间件
 *
 * 功能：
 * - 验证用户是否已登录
 * - API 请求返回 401 JSON
 * - 页面请求重定向到登录页
 * - 将用户信息存入上下文
 */
class AdminAuthMiddleware implements MiddlewareInterface
{


    #[Inject]
    protected AuthManagerInterface $auth;

    public function __construct(
        protected HttpResponse $response,
        protected \Hyperf\Contract\SessionInterface $session,
        protected RenderInterface $render
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $guard = $this->auth->guard('admin');

        // 检查用户是否已登录
        if (!$guard->check()) {
            // 尝试通过“记住我”自动登录
            if ($this->attemptRememberLogin($request)) {
                return $handler->handle($request);
            }
            // 用户未登录，判断是否为 API 请求
            if ($this->isApiRequest($request)) {
                return $this->response->json([
                    'code' => 401,
                    'message' => '未登录',
                ])->withStatus(401);
            }
            
            // 页面请求：检查是否为 iframe 请求
            if ($this->isEmbeddedRequest($request)) {
                // iframe 请求未登录时，返回特殊页面通知主页面刷新
                return $this->handleUnauthorizedInIframe($request);
            }
            
            // 普通页面请求重定向到登录页
            $adminPath = Context::get('admin_entry_path', '/admin');
            $loginUrl = $adminPath . '/login';
            return $this->response->redirect($loginUrl);
        }

        // 获取当前用户
        $user = $guard->user();
        
        if (!$user) {
            // 用户不存在，处理未授权
            return $this->handleUnauthorized($request);
        }

        // 检查用户状态（如果是对象，检查 status 属性；如果是数组，检查 status 键）
        $status = is_object($user) ? $user->status : ($user['status'] ?? null);
        if ($status != 1) {
            // 检查是否为 iframe 请求
            if ($this->isEmbeddedRequest($request)) {
                return $this->handleDisabledInIframe($request);
            }
            return $this->handleDisabled($request);
        }

        // 将用户信息存入上下文
        // 支持对象和数组两种格式
        Context::set('admin_user', $user);
        
        // 获取用户ID（支持对象和数组）
        $userId = is_object($user) ? $user->id : ($user['id'] ?? null);
        Context::set('admin_user_id', $userId);

        return $handler->handle($request);
    }

    /**
     * 尝试从“记住我” Cookie 自动登录
     */
    protected function attemptRememberLogin(ServerRequestInterface $request): bool
    {
        try {
            $cookies = $request->getCookieParams();
            $raw = $cookies['admin_remember'] ?? null;
            if (!$raw) return false;

            $decoded = base64_decode($raw, true);
            if ($decoded === false) return false;

            $parts = explode('.', $decoded);
            if (count($parts) !== 4) return false;

            [$userId, $siteId, $expires, $signature] = $parts;
            if (!is_numeric($userId) || !is_numeric($siteId) || !is_numeric($expires)) return false;
            if ((int)$expires < time()) return false;

            $user = \App\Model\Admin\AdminUser::query()
                ->where('id', (int)$userId)
                ->where('site_id', (int)$siteId)
                ->first();
            if (!$user instanceof AdminUser) return false;

            $payload = $userId . '.' . $siteId . '.' . $expires;
            $expected = hash_hmac('sha256', $payload, (string)$user->password);
            if (!hash_equals($expected, $signature)) return false;

            // 账号状态检查
            if ((int)$user->status !== 1) return false;
            // 登录并写入会话
            auth('admin')->login($user);
            $this->session->set('admin_user_id', (int)$userId);
            $this->session->set('admin_site_id', (int)$siteId);
            Context::set('admin_user', $user);
            Context::set('admin_user_id', (int)$userId);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 获取认证用户
     */
    protected function getAuthUser(ServerRequestInterface $request): ?array
    {
        // 1. 优先从 JWT Token 获取（API 请求）
        $token = $this->getTokenFromRequest($request);
        if ($token) {
            // TODO: 验证 JWT Token 并返回用户信息
            // $user = $this->jwtService->validateToken($token);
            // if ($user) {
            //     return $user;
            // }
        }

        $this->auth->guard('admin')->check();
        // 2. 从 Session 获取用户信息（页面请求）
        $userId = $this->session->get('admin_user_id');
        $siteId = $this->session->get('admin_site_id');

        if (!$userId || !$siteId) {
            return null;
        }

        // 3. 从数据库获取用户完整信息
        return $this->getUserById((int)$userId, (int)$siteId);
    }

    /**
     * 从请求中提取 Token
     */
    protected function getTokenFromRequest(ServerRequestInterface $request): ?string
    {
        // 从 Authorization Header 获取
        $authorization = $request->getHeaderLine('Authorization');
        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        // 从 Query 参数获取
        $params = $request->getQueryParams();
        if (isset($params['token'])) {
            return $params['token'];
        }

        return null;
    }

    /**
     * 处理未登录
     */
    protected function handleUnauthorized(ServerRequestInterface $request): ResponseInterface
    {
        // 判断是否为 API 请求
        if ($this->isApiRequest($request)) {
            return $this->response->json([
                'code' => 401,
                'message' => '未登录',
            ])->withStatus(401);
        }

        // 检查是否为 iframe 请求
        if ($this->isEmbeddedRequest($request)) {
            return $this->handleUnauthorizedInIframe($request);
        }

        // 页面请求重定向到登录页
        $adminPath = Context::get('admin_entry_path', '/admin');
        $loginUrl = $adminPath . '/login';

        return $this->response->redirect($loginUrl);
    }

    /**
     * 处理 iframe 中的未登录情况：通知主页面刷新
     */
    protected function handleUnauthorizedInIframe(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render->render('errors.unauthorized_in_iframe');
    }

    /**
     * 处理账号已禁用
     */
    protected function handleDisabled(ServerRequestInterface $request): ResponseInterface
    {
        // 判断是否为 API 请求
        if ($this->isApiRequest($request)) {
            return $this->response->json([
                'code' => 403,
                'message' => '账号已被禁用',
            ])->withStatus(403);
        }

        // 检查是否为 iframe 请求
        if ($this->isEmbeddedRequest($request)) {
            return $this->handleDisabledInIframe($request);
        }

        // 页面请求重定向到登录页并显示错误信息
        $adminPath = Context::get('admin_entry_path', '/admin');
        $loginUrl = $adminPath . '/login?error=disabled';

        return $this->response->redirect($loginUrl);
    }

    /**
     * 处理 iframe 中的账号已禁用情况：通知主页面刷新
     */
    protected function handleDisabledInIframe(ServerRequestInterface $request): ResponseInterface
    {
        return $this->render->render('errors.unauthorized_in_iframe', [
            'message' => '账号已被禁用',
        ]);
    }

    /**
     * 判断是否为 API 请求
     */
    protected function isApiRequest(ServerRequestInterface $request): bool
    {
        $path = $request->getUri()->getPath();

        // 路径以 /api 开头
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        // 请求头包含 Accept: application/json
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        // 请求头包含 X-Requested-With: XMLHttpRequest
        $xRequestedWith = $request->getHeaderLine('X-Requested-With');
        if ($xRequestedWith === 'XMLHttpRequest') {
            return true;
        }

        return false;
    }

    /**
     * 判断是否为 iframe 嵌入请求
     */
    protected function isEmbeddedRequest(ServerRequestInterface $request): bool
    {
        // 方式1：检查 URL 参数 _embed
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['_embed']) && $queryParams['_embed'] === '1') {
            return true;
        }

        // 方式2：检查 HTTP_SEC_FETCH_DEST 请求头（现代浏览器支持）
        $serverParams = $request->getServerParams();
        if (isset($serverParams['HTTP_SEC_FETCH_DEST']) && $serverParams['HTTP_SEC_FETCH_DEST'] === 'iframe') {
            return true;
        }

        return false;
    }

    /**
     * 根据 ID 获取用户信息
     */
    protected function getUserById(int $userId, int $siteId): ?array
    {
        try {
            $user = \App\Model\Admin\AdminUser::query()
                ->where('id', $userId)
                ->where('site_id', $siteId)
                ->first();

            if (!$user) {
                return null;
            }

            return $user->toArray();
        } catch (\Throwable $e) {
            // 数据库查询失败，返回 null
            return null;
        }
    }
}
