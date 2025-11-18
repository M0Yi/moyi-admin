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

namespace App\Controller;

use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\View\RenderInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\SessionInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;


abstract class AbstractController
{

    #[Inject]
    protected ValidatorFactoryInterface $validationFactory;
    #[Inject]

    protected SessionInterface $session;

    #[Inject]
    protected ContainerInterface $container;

    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    #[Inject]
    protected RenderInterface $render;

    protected function result(string $msg = '', array $data = null, int $code = 0,array $extra = []): \Psr\Http\Message\ResponseInterface
    {

        return $this->response->json(array_merge([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
        ],$extra));
    }


    protected function error(string $msg = 'error', array|null $data = null, int $code = 400): \Psr\Http\Message\ResponseInterface
    {
        return $this->result($msg, $data, $code);
    }

    protected function success(array|null $data = null, string $msg = '', int $code = 200): \Psr\Http\Message\ResponseInterface
    {
        return $this->result($msg, $data, $code);
    }


}
