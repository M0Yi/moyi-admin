<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\Admin\InstallController;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminSite;
use App\Model\Admin\AdminUser;
use App\Support\SiteVerificationToken;
use Hyperf\DbConnection\Db;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\Config\config;
use function Hyperf\Support\env;

/**
 * 公共站点创建向导
 */
class SiteCreationController extends InstallController
{
    /**
     * 展示站点创建向导
     */
    public function create(): ResponseInterface
    {
        if (! $this->isPublicCreationEnabled()) {
            return $this->render->render('errors.404');
        }

        $currentSite = site();
        if ($currentSite) {
            return $this->render->render('errors.site_already_bound', [
                'siteName' => $currentSite->name,
                'siteDomain' => $currentSite->domain,
            ])->withStatus(403);
        }

        return $this->render->render('site.create', [
            'requestedDomain' => $this->getHostWithPort(),
            'requestedPath' => $this->request->getUri()->getPath(),
        ]);
    }

    /**
     * 处理站点创建请求
     */
    public function store(): ResponseInterface
    {
        if (! $this->isPublicCreationEnabled()) {
            return $this->error('站点创建功能未开启', null, 403);
        }

        if (site()) {
            return $this->error('当前域名已绑定站点，无法重复注册', null, 403);
        }

        $payload = $this->request->all();

        $rawDomain = trim((string) ($payload['site_domain'] ?? ''));

        try {
            $payload['site_domain'] = $this->normalizeDomain($rawDomain);
        } catch (\InvalidArgumentException $exception) {
            return $this->error('数据验证失败', [
                'site_domain' => $exception->getMessage(),
            ], 422);
        }
        $token = $payload['domain_token'] ?? '';
        if (! $this->verifyDomainToken($payload['site_domain'], (string) $token)) {
            $this->logDomainEvent('域名令牌校验失败', [
                'domain' => $payload['site_domain'],
                'token_present' => $token !== '',
                'token' => (string) $token,
            ]);
            return $this->error('域名尚未通过验证，请先验证后再提交', null, 422);
        }

        $errors = $this->validatePublicSiteData($payload);
        if (! empty($errors)) {
            return $this->error('数据验证失败', $errors, 422);
        }

        try {
            Db::beginTransaction();

            $site = $this->createSite($payload);
            $adminUser = $this->createAdminUser($payload, $site->id);

            $role = $this->getDefaultRole();
            $adminUser->roles()->attach($role->id);

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollBack();
            return $this->error('站点创建失败：' . $e->getMessage(), null, 500);
        }

        return $this->success([
            'site_id' => $site->id,
            'site_domain' => $site->domain,
            'site_name' => $site->name,
            'admin_entry_path' => $site->admin_entry_path,
            'login_url' => '/admin/' . $site->admin_entry_path . '/login',
            'username' => $adminUser->username,
            'password' => $payload['password'],
            'role_name' => $role->name,
        ], '站点创建成功');
    }

    /**
     * 域名验证接口
     */
    public function verifyDomain(): ResponseInterface
    {
        if (! $this->isPublicCreationEnabled()) {
            return $this->error('站点创建功能未开启', null, 403);
        }

        if (site()) {
            return $this->error('当前域名已绑定站点，无法重复注册', null, 403);
        }

        $payload = $this->request->all();
        $rawDomain = trim((string) ($payload['site_domain'] ?? ''));

        if ($rawDomain === '') {
            return $this->error('域名不能为空', null, 422);
        }

        try {
            $domain = $this->normalizeDomain($rawDomain);
        } catch (\InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), null, 422);
        }

        $connectivityDetails = $this->validateDomainConnectivity($domain);
        if ($connectivityDetails['error']) {
            $this->logDomainEvent('域名验证失败', [
                'domain' => $domain,
                'errors' => $connectivityDetails['error'],
                'connectivity' => $connectivityDetails['details'],
            ]);
            return $this->error('域名验证失败', $connectivityDetails['error'], 422);
        }

        $token = $this->generateDomainToken($domain);
        $this->logDomainEvent('域名验证成功', [
            'domain' => $domain,
        ]);

        return $this->success([
            'token' => $token,
            'domain' => $domain,
            'expires_in' => (int) config('site.verification_token_ttl', 300),
        ], '域名验证成功');
    }

    /**
     * 校验并补充公共站点创建字段
     */
    private function validatePublicSiteData(array $data): array
    {
        $errors = $this->validateInstallData($data);

        if (empty($data['real_name'])) {
            $errors['real_name'] = '请输入真实姓名';
        }

        if (empty($data['mobile'])) {
            $errors['mobile'] = '请输入手机号';
        } elseif (! preg_match('/^1[3-9]\d{9}$/', $data['mobile'])) {
            $errors['mobile'] = '手机号格式不正确';
        }

        $domain = $data['site_domain'] ?? '';
        if ($domain !== '') {
            $connectivityDetails = $this->validateDomainConnectivity($domain);
            if ($connectivityDetails['error']) {
                $errors = array_merge($errors, $connectivityDetails['error']);
            }
        }

        return $errors;
    }

    private function isValidDomainFormat(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * 检查域名是否已存在
     */
    private function domainExists(string $domain): bool
    {
        return AdminSite::query()
            ->where('domain', $domain)
            ->exists();
    }

    /**
     * 标准化域名格式
     */
    private function normalizeDomain(string $domain): string
    {
        $value = trim($domain);
        $value = preg_replace('#^https?://#i', '', $value);
        if (str_contains($value, '/')) {
            $value = explode('/', $value)[0];
        }

        return strtolower($value);
    }

    private function getDefaultRole(): AdminRole
    {
        $roleId = (int) config('site.default_role_id', 2);
        $role = AdminRole::query()->find($roleId);

        if (! $role) {
            throw new \RuntimeException('默认角色不存在，请联系管理员配置 SITE_PUBLIC_DEFAULT_ROLE_ID');
        }

        return $role;
    }

    private function validateDomainConnectivity(string $domain): array
    {
        if (config('site.validate_hostname', true)) {
            if (! $this->isValidDomainFormat($domain)) {
                return [
                    'error' => ['site_domain' => '请输入有效的域名'],
                    'details' => null,
                ];
            }
        }

        if ($this->domainExists($domain)) {
            return [
                'error' => ['site_domain' => '该域名已绑定其他站点，请更换域名'],
                'details' => null,
            ];
        }

        $sourceHost = $this->request->getUri()->getHost();
        $sourcePort = $this->request->getUri()->getPort();
        $source = $sourceHost;
        if ($sourcePort && ! in_array($sourcePort, [80, 443], true)) {
            $source .= ':' . $sourcePort;
        }
        $challenge = bin2hex(random_bytes(16));
        $verification = $this->performVerificationHandshake($domain, $source, $challenge);

        if (! $verification['ok']) {
            return [
                'error' => ['site_domain' => '无法验证该域名，请确保 /site/verification 接口可访问'],
                'details' => $verification,
            ];
        }

        return [
            'error' => null,
            'details' => $verification,
        ];
    }

    private function performVerificationHandshake(string $domain, string $source, string $challenge): array
    {
        $schemes = ['https', 'http'];
        $attempts = [];
        $expectedContext = $this->buildVerificationExpectation($domain, $source, $challenge);

        $this->logDomainEvent('域名验证握手开始', [
            'domain' => $domain,
            'source' => $source,
            'challenge' => $challenge,
            '期望参数' => $expectedContext,
        ]);

        foreach ($schemes as $scheme) {
            $url = sprintf(
                '%s://%s/site/verification?source=%s&challenge=%s',
                $scheme,
                $domain,
                rawurlencode($source),
                rawurlencode($challenge)
            );

            $response = $this->fetchVerificationResponse($url);

            $attempt = [
                'protocol' => $scheme,
                'url' => $url,
                'status' => $response['ok'] ? 'ok' : 'failed',
                'message' => $response['message'] ?? null,
            ];

            if ($response['ok']) {
                $expectedToken = $expectedContext['token'];

                $responseParams = $response['response'] ?? null;
                $attempt['response_params'] = $responseParams;
                $attempt['expected_params'] = $expectedContext;

                if (SiteVerificationToken::validate($response['token'], $domain, $source, $challenge)) {
                    $logContext = [
                        'domain' => $domain,
                        'source' => $source,
                        'challenge' => $challenge,
                        'protocol' => $scheme,
                        'url' => $url,
                        'status' => 'ok',
                        'message' => null,
                        '返回token' => (string) $response['token'],
                        '期望token' => $expectedToken,
                        '返回参数' => $responseParams,
                        '期望参数' => $expectedContext,
                    ];

                    $this->logDomainEvent('域名验证握手尝试', $logContext);
                    $this->logDomainEvent('域名验证握手成功', $logContext);

                    $attempts[] = $attempt;
                    return [
                        'ok' => true,
                        'scheme' => $scheme,
                        'attempts' => $attempts,
                        'expected' => $expectedContext,
                        'response_params' => $responseParams,
                    ];
                }

                $attempt['status'] = 'failed';
                $attempt['message'] = 'Token mismatch';

                $logContext = [
                    'domain' => $domain,
                    'source' => $source,
                    'challenge' => $challenge,
                    'protocol' => $scheme,
                    'url' => $url,
                    'status' => 'failed',
                    'message' => 'Token mismatch',
                    '返回token' => (string) $response['token'],
                    '期望token' => $expectedToken,
                    '返回参数' => $responseParams,
                    '期望参数' => $expectedContext,
                ];

                $this->logDomainEvent('域名验证握手尝试', $logContext);
                $this->logDomainEvent('域名验证令牌不一致', $logContext);
            } else {
                $logContext = [
                    'domain' => $domain,
                    'source' => $source,
                    'challenge' => $challenge,
                    'protocol' => $scheme,
                    'url' => $url,
                    'status' => 'failed',
                    'message' => $response['message'] ?? '请求失败',
                    '期望参数' => $expectedContext,
                ];

                $this->logDomainEvent('域名验证握手尝试', $logContext);
            }

            if (! isset($attempt['expected_params'])) {
                $attempt['expected_params'] = $expectedContext;
            }

            $attempts[] = $attempt;
        }

        $this->logDomainEvent('域名验证握手失败', [
            'domain' => $domain,
            'source' => $source,
            'challenge' => $challenge,
            'attempts' => $attempts,
            '期望参数' => $expectedContext,
        ]);

        return [
            'ok' => false,
            'attempts' => $attempts,
            'expected' => $expectedContext,
        ];
    }

    private function fetchVerificationResponse(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        try {
            $raw = @file_get_contents($url, false, $context);
            if ($raw === false) {
                $phpError = error_get_last();
                $this->logDomainEvent('域名验证请求失败', [
                    'url' => $url,
                    'message' => '请求失败',
                    'php_error' => $phpError['message'] ?? null,
                ]);
                return ['ok' => false, 'message' => '请求失败'];
            }

            $data = json_decode($raw, true);
            if (! is_array($data) || ! isset($data['token'])) {
                $this->logDomainEvent('域名验证响应格式错误', [
                    'url' => $url,
                    'raw' => $raw,
                ]);
                return ['ok' => false, 'message' => '响应格式无效'];
            }

            $this->logDomainEvent('域名验证请求成功', [
                'url' => $url,
                '返回token' => (string) $data['token'],
                '返回参数' => $data,
                'raw' => $raw,
            ]);

            return [
                'ok' => true,
                'token' => (string) $data['token'],
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            $this->logDomainEvent('域名验证请求异常', [
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateDomainToken(string $domain): string
    {
        $ttl = (int) config('site.verification_token_ttl', 300);
        $expiresAt = time() + $ttl;
        $secret = env('APP_KEY', 'moyi-secret');
        $payload = $domain . '|' . $expiresAt;
        $signature = hash_hmac('sha256', $payload, $secret);

        return base64_encode($payload . '|' . $signature);
    }

    private function verifyDomainToken(string $domain, string $token): bool
    {
        if ($token === '') {
            $this->logDomainEvent('缺少域名令牌', ['domain' => $domain]);
            return false;
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false || ! str_contains($decoded, '|')) {
            $this->logDomainEvent('域名令牌解码失败', [
                'domain' => $domain,
                'token' => $token,
            ]);
            return false;
        }

        $parts = explode('|', $decoded);
        if (count($parts) !== 3) {
            $this->logDomainEvent('域名令牌字段数量错误', [
                'domain' => $domain,
                'token' => $token,
            ]);
            return false;
        }

        [$tokenDomain, $expiresAt, $signature] = $parts;

        if ((int) $expiresAt < time()) {
            $this->logDomainEvent('域名令牌已过期', [
                'domain' => $domain,
                'expires_at' => $expiresAt,
                'token' => $token,
            ]);
            return false;
        }

        if ($tokenDomain !== $domain) {
            $this->logDomainEvent('域名令牌域名不匹配', [
                'domain' => $domain,
                '令牌中的域名' => $tokenDomain,
                'token' => $token,
            ]);
            return false;
        }

        $secret = env('APP_KEY', 'moyi-secret');
        $payload = $tokenDomain . '|' . $expiresAt;
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        $valid = hash_equals($expectedSignature, $signature);
        if (! $valid) {
            $this->logDomainEvent('域名令牌签名无效', [
                'domain' => $domain,
                'token' => $token,
            ]);
        } else {
            $this->logDomainEvent('域名令牌校验通过', [
                'domain' => $domain,
                'expires_at' => $expiresAt,
                'token' => $token,
            ]);
        }

        return $valid;
    }

    private function buildVerificationExpectation(string $domain, string $source, string $challenge): array
    {
        $normalizedDomain = strtolower($domain);
        $normalizedSource = strtolower($source);
        $payload = sprintf('%s|%s|%s', $normalizedDomain, $normalizedSource, $challenge);

        return [
            'domain' => $normalizedDomain,
            'source' => $normalizedSource,
            'challenge' => $challenge,
            'payload' => $payload,
            'token' => SiteVerificationToken::generate($domain, $source, $challenge),
            'algorithm' => 'sha256',
        ];
    }

    private function logDomainEvent(string $message, array $context = []): void
    {
        logger()->info('[SiteCreation] ' . $message, $context);
    }

    private function getHostWithPort(): string
    {
        $uri = $this->request->getUri();
        $host = $uri->getHost();
        $port = $uri->getPort();

        if ($port && ! in_array($port, [80, 443], true)) {
            return $host . ':' . $port;
        }

        return $host;
    }

    private function isPublicCreationEnabled(): bool
    {
        return (bool) config('site.public_creation_enabled', false);
    }

    /**
     * 公共站点注册产生的管理员不自动授予超级管理员
     */
    protected function createAdminUser(array $data, int $siteId): AdminUser
    {
        return AdminUser::create([
            'site_id' => $siteId,
            'username' => $data['username'],
            'password' => $data['password'],
            'email' => $data['email'] ?? '',
            'mobile' => $data['mobile'] ?? '',
            'real_name' => $data['real_name'] ?? '站点管理员',
            'status' => 1,
            'is_admin' => 0,
        ]);
    }
}


