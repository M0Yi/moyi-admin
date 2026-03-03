<?php

declare(strict_types=1);

/**
 * Mock 对象构建器
 * 
 * 用于创建各种 Mock 对象，简化测试代码
 */

namespace HyperfTest\Mock;

use Mockery;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * Mock 对象构建器
 */
class MockBuilder
{
    /**
     * 创建 Request Mock
     */
    public static function mockRequest(array $queryParams = [], array $postData = [], array $serverParams = []): RequestInterface
    {
        $mock = Mockery::mock(RequestInterface::class);

        // Query params
        $mock->shouldReceive('input')
            ->andReturnUsing(function ($key, $default = null) use ($queryParams) {
                return $queryParams[$key] ?? $default;
            });

        $mock->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) use ($queryParams) {
                return $queryParams[$key] ?? $default;
            });

        $mock->shouldReceive('all')
            ->andReturn($postData);

        // Post data
        $mock->shouldReceive('post')
            ->andReturnUsing(function ($key, $default = null) use ($postData) {
                return $postData[$key] ?? $default;
            });

        // Server params
        $mock->shouldReceive('getServerParams')
            ->andReturn($serverParams + [
                'REQUEST_URI' => '/test',
                'REQUEST_METHOD' => 'GET',
                'REMOTE_ADDR' => '127.0.0.1',
            ]);

        // Headers
        $mock->shouldReceive('getHeaderLine')
            ->andReturn('');

        // Uri
        $mockUri = Mockery::mock(\Psr\Http\Message\UriInterface::class);
        $mockUri->shouldReceive('getPath')->andReturn('/test');
        $mockUri->shouldReceive('getQuery')->andReturn('');
        $mock->shouldReceive('getUri')->andReturn($mockUri);

        return $mock;
    }

    /**
     * 创建 Response Mock
     */
    public static function mockResponse(): ResponseInterface
    {
        $mock = Mockery::mock(ResponseInterface::class);

        $mock->shouldReceive('json')
            ->andReturnUsing(function ($data) {
                return $data;
            });

        $mock->shouldReceive('redirect')
            ->andReturnUsing(function ($url) {
                return ['redirect' => $url];
            });

        $mock->shouldReceive('withAddedHeader')
            ->andReturnSelf();

        $mock->shouldReceive('withBody')
            ->andReturnSelf();

        $mockStream = Mockery::mock(\Psr\Http\Message\StreamInterface::class);
        $mockStream->shouldReceive('__toString')->andReturn('{}');
        $mock->shouldReceive('getBody')->andReturn($mockStream);

        return $mock;
    }

    /**
     * 创建 Session Mock
     */
    public static function mockSession(array $data = []): \Hyperf\Contract\SessionInterface
    {
        $mock = Mockery::mock(\Hyperf\Contract\SessionInterface::class);

        $mock->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) use ($data) {
                return $data[$key] ?? $default;
            });

        $mock->shouldReceive('set')
            ->andReturnUsing(function ($key, $value) use (&$data) {
                $data[$key] = $value;
            });

        $mock->shouldReceive('has')
            ->andReturnUsing(function ($key) use ($data) {
                return isset($data[$key]);
            });

        $mock->shouldReceive('forget')
            ->andReturnUsing(function ($key) use (&$data) {
                unset($data[$key]);
            });

        $mock->shouldReceive('flush')
            ->andReturnUsing(function () use (&$data) {
                $data = [];
            });

        return $mock;
    }

    /**
     * 创建 Validator Mock
     */
    public static function mockValidator(bool $isValid = true, array $errors = []): \Hyperf\Validation\Contract\ValidatorFactoryInterface
    {
        $mock = Mockery::mock(\Hyperf\Validation\Contract\ValidatorFactoryInterface::class);
        $validator = Mockery::mock(\Hyperf\Validation\Contract\ValidatorInterface::class);

        $validator->shouldReceive('validate')
            ->andReturnUsing(function ($data, $rules) use ($isValid, $errors) {
                if (!$isValid) {
                    throw new \Hyperf\Validation\ValidationException($validator, $errors);
                }
                return $data;
            });

        $validator->shouldReceive('fails')
            ->andReturn(!$isValid);

        $validator->shouldReceive('errors')
            ->andReturn(Mockery::mock(\Hyperf\Translation\Arrayable::class));

        $mock->shouldReceive('make')
            ->andReturn($validator);

        return $mock;
    }

    /**
     * 创建 Render Mock
     */
    public static function mockRender(): \Hyperf\View\RenderInterface
    {
        $mock = Mockery::mock(\Hyperf\View\RenderInterface::class);

        $mock->shouldReceive('render')
            ->andReturnUsing(function ($view, $data = []) {
                return [
                    'view' => $view,
                    'data' => $data,
                ];
            });

        return $mock;
    }

    /**
     * 创建 Config Mock
     */
    public static function mockConfig(array $config = []): \Hyperf\Contract\ConfigInterface
    {
        $mock = Mockery::mock(\Hyperf\Contract\ConfigInterface::class);

        $mock->shouldReceive('get')
            ->andReturnUsing(function ($key, $default = null) use ($config) {
                $keys = explode('.', $key);
                $value = $config;

                foreach ($keys as $k) {
                    if (!isset($value[$k])) {
                        return $default;
                    }
                    $value = $value[$k];
                }

                return $value ?? $default;
            });

        $mock->shouldReceive('has')
            ->andReturnUsing(function ($key) use ($config) {
                $keys = explode('.', $key);
                $value = $config;

                foreach ($keys as $k) {
                    if (!isset($value[$k])) {
                        return false;
                    }
                    $value = $value[$k];
                }

                return true;
            });

        return $mock;
    }

    /**
     * 创建 Logger Mock
     */
    public static function mockLogger(): \Psr\Log\LoggerInterface
    {
        $mock = Mockery::mock(\Psr\Log\LoggerInterface::class);

        $mock->shouldReceive('debug')->andReturn(null);
        $mock->shouldReceive('info')->andReturn(null);
        $mock->shouldReceive('warning')->andReturn(null);
        $mock->shouldReceive('error')->andReturn(null);
        $mock->shouldReceive('critical')->andReturn(null);

        return $mock;
    }

    /**
     * 创建 Database Mock
     */
    public static function mockDb(): \Hyperf\DbConnection\Db
    {
        $mock = Mockery::mock('alias:' . \Hyperf\DbConnection\Db::class);

        $mock->shouldReceive('table')
            ->andReturn(Mockery::mock(\Hyperf\Database\Query\Builder::class));

        $mock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        return $mock;
    }

    /**
     * 清理 Mockery
     */
    public static function close(): void
    {
        Mockery::close();
    }
}
