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
use Hyperf\Database\Connection;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\ViewEngine\view;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\RedisFactory;
use Hyperf\Contract\ConfigInterface;

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class IndexController extends AbstractController
{
    protected string $connection = 'admin';

    public function index(RenderInterface $render): ResponseInterface
    {
        $redisStatus = 'disconnected';
        $mysqlStatus = 'disconnected';

        /** @var ConfigInterface $config */
        $config = $this->container->get(ConfigInterface::class);
        $dbConf = (array) $config->get('databases.default', []);
        $redisConf = (array) $config->get('redis.default', []);
        $appName = (string) ($config->get('app_name') ?? 'MoYi');
        $appEnv = (string) ($config->get('app_env') ?? 'dev');
        $phpVersion = PHP_VERSION;
        $swooleEnabled = \extension_loaded('swoole');
        $swooleVersion = \defined('SWOOLE_VERSION') ? SWOOLE_VERSION : null;

        // Check Redis connection status
        try {
            $redis = $this->container->get(RedisFactory::class)->get('default');
            $redis->ping();
            $redisStatus = 'connected';
        } catch (\Throwable $e) {
            $redisStatus = 'disconnected';
        }

        // Check MySQL connection status
        try {
            Db::select('select 1');
            $mysqlStatus = 'connected';
        } catch (\Throwable $e) {
            $mysqlStatus = 'disconnected';
        }

        return $render->render('index', [
            'name' => 'moyi',
            'redisStatus' => $redisStatus,
            'mysqlStatus' => $mysqlStatus,
            'db' => [
                'host' => $dbConf['host'] ?? null,
                'port' => $dbConf['port'] ?? null,
                'driver' => $dbConf['driver'] ?? null,
            ],
            'redis' => [
                'host' => $redisConf['host'] ?? null,
                'port' => $redisConf['port'] ?? null,
            ],
            'app' => [
                'name' => $appName,
                'env' => $appEnv,
                'php' => $phpVersion,
                'swooleEnabled' => $swooleEnabled,
                'swooleVersion' => $swooleVersion,
            ],
        ]);
    }
    public function child(RenderInterface $render)
    {
        return $render->render('child',['name' => 'moyi']);
    }
}
