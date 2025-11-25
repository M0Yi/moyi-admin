<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Exception\Handler;

use App\Exception\BusinessException;
use App\Exception\ValidationException;
use Hyperf\Context\Context;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Database\Exception\QueryException;
use Hyperf\ExceptionHandler\ExceptionHandler;
use Hyperf\HttpMessage\Exception\MethodNotAllowedHttpException;
use Hyperf\HttpMessage\Exception\NotFoundHttpException;
use Hyperf\HttpMessage\Exception\ServerErrorHttpException;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

class AppExceptionHandler extends ExceptionHandler
{
    public function __construct(
        protected StdoutLoggerInterface $logger,
        protected RenderInterface $render
    ) {
    }

    public function handle(Throwable $throwable, ResponseInterface $response): ResponseInterface
    {

        $request = Context::get(ServerRequestInterface::class);
        $isApiRequest = $this->isApiRequest($request);

        if ($throwable instanceof BusinessException) {
            $payload = [
                'code' => $throwable->getCode() > 0 ? $throwable->getCode() : 500,
                'msg' => $throwable->getMessage(),
                'data' => null,
            ];

            return $this->jsonResponse($response, $payload, 200);
        }

        if ($throwable instanceof ValidationException) {
            $payload = [
                'code' => 422,
                'msg' => $throwable->getMessage(),
                'data' => null,
                'errors' => $throwable->getErrors(),
            ];

            return $this->jsonResponse($response, $payload, 200);
        }

        if ($throwable instanceof QueryException) {
            print_r($throwable);
            $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
            $message = str_contains($throwable->getMessage(), '1142')
                ? '数据库权限不足，请联系管理员'
                : '系统繁忙，请稍后再试';

            $payload = [
                'code' => 500,
                'msg' => $message,
                'data' => null,
            ];

            return $this->jsonResponse($response, $payload, 500);
        }

        if (! $isApiRequest) {
            if ($throwable instanceof NotFoundHttpException) {
                $content = $this->render->render('errors.404', [
                    'requestPath' => $request?->getUri()->getPath(),
                    'requestMethod' => $request?->getMethod(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(404)
                    ->withBody(new SwooleStream((string) $content));
            }

            if ($throwable instanceof MethodNotAllowedHttpException) {
                $content = $this->render->render('errors.405', [
                    'requestPath' => $request?->getUri()->getPath(),
                    'requestMethod' => $request?->getMethod(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(405)
                    ->withBody(new SwooleStream((string) $content));
            }

            if ($throwable instanceof ServerErrorHttpException) {
                $content = $this->render->render('errors.500', [
                    'errorMessage' => $throwable->getMessage(),
                    'errorFile' => $throwable->getFile(),
                    'errorLine' => $throwable->getLine(),
                ]);

                return $response
                    ->withHeader('Server', 'Hyperf')
                    ->withStatus(500)
                    ->withBody(new SwooleStream((string) $content));
            }
        }
        $this->logger->error(sprintf('%s[%s] in %s', $throwable->getMessage(), $throwable->getLine(), $throwable->getFile()));
        $this->logger->error($throwable->getTraceAsString());
        return $response->withHeader('Server', 'Hyperf')->withStatus(500)->withBody(new SwooleStream('Internal Server Error.'));
    }

    public function isValid(Throwable $throwable): bool
    {
        return true;
    }

    protected function jsonResponse(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        return $response
            ->withHeader('Server', 'Hyperf')
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status)
            ->withBody(new SwooleStream((string) json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * 判断是否是 API 请求
     */
    protected function isApiRequest(?ServerRequestInterface $request): bool
    {
        if (! $request) {
            return false;
        }

        if ($request->getAttribute('expects_json')) {
            return true;
        }

        $path = $request->getUri()->getPath();
        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            return true;
        }

        return false;
    }
}
