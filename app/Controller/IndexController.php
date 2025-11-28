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
use App\Model\Admin\AdminSite;
use Hyperf\Database\Connection;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\ViewEngine\view;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\RedisFactory;
use Hyperf\Contract\ConfigInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
use function Hyperf\Config\config;

class IndexController extends AbstractController
{
    protected string $connection = 'admin';

    public function index(RenderInterface $render, HttpResponse $response): ResponseInterface
    {
        $currentSite = site();
        if (! $currentSite) {
            // 检查系统是否已安装（是否有任何站点）
            $isInstalled = $this->isSystemInstalled();
            
            if (! $isInstalled) {
                // 系统未安装，重定向到安装页面
                return $response->redirect('/install');
            }
            
            // 系统已安装但当前域名没有匹配的站点，显示"站点未配置"错误页面
            $uri = $this->request->getUri();
            $allowPublicCreation = (bool) config('site.public_creation_enabled', false);
            return $render->render('errors.site_not_found', [
                'requestHost' => $uri->getHost(),
                'requestPath' => $uri->getPath(),
                'allowPublicSiteCreation' => $allowPublicCreation,
                'siteCreationUrl' => $allowPublicCreation
                    ? '/site/register?domain=' . rawurlencode($uri->getHost())
                    : null,
            ])->withStatus(503);
        }

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

    /**
     * 检查系统是否已安装
     * 
     * 判断标准：是否存在任何站点
     * 
     * @return bool
     */
    private function isSystemInstalled(): bool
    {
        try {
            // 检查是否存在任何站点
            return AdminSite::query()->exists();
        } catch (\Throwable $e) {
            // 数据库连接失败或表不存在，视为未安装
            return false;
        }
    }

    public function child(RenderInterface $render)
    {
        return $render->render('child',['name' => 'moyi']);
    }
}
