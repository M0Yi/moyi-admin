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
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\View\RenderInterface;
use Psr\Http\Message\ResponseInterface;
use function Hyperf\ViewEngine\view;
use Hyperf\DbConnection\Db;
use Hyperf\Redis\RedisFactory;
use Hyperf\Contract\ConfigInterface;

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;
#[AutoController]
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

    public function test()
    {
        //
        if (! Schema::hasTable('admin_sites')) {
            Schema::create('admin_sites', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('站点ID');
                $table->string('domain', 255)->comment('域名');
                $table->string('name', 100)->comment('站点名称');
                $table->string('title', 200)->nullable()->comment('站点标题');
                $table->string('admin_entry_path', 64)->default('admin')->comment('后台入口');
                $table->string('slogan', 200)->nullable()->comment('站点口号');
                $table->string('logo', 255)->nullable()->comment('Logo路径');
                $table->string('favicon', 255)->nullable()->comment('Favicon路径');
                $table->string('primary_color', 20)->default('#FAD709')->comment('主题色');
                $table->string('secondary_color', 20)->nullable()->comment('辅助色');
                $table->text('description')->nullable()->comment('站点描述');
                $table->string('keywords', 255)->nullable()->comment('SEO关键词');
                $table->string('contact_email', 100)->nullable()->comment('联系邮箱');
                $table->string('contact_phone', 50)->nullable()->comment('联系电话');
                $table->string('address', 255)->nullable()->comment('地址');
                $table->string('icp_number', 100)->nullable()->comment('ICP备案号');
                $table->text('analytics_code')->nullable()->comment('统计代码');
                $table->text('custom_css')->nullable()->comment('自定义CSS');
                $table->text('custom_js')->nullable()->comment('自定义JS');
                $table->longText('config')->nullable()->comment('扩展配置(JSON)');
                $table->unsignedBigInteger('default_brand_id')->nullable()->comment('默认品牌ID');
                $table->unsignedBigInteger('default_wechat_provider_id')->nullable()->comment('默认微信服务商ID');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->string('upload_driver', 20)->nullable();
                $table->longText('upload_config')->nullable();
                $table->integer('sort')->default(0)->comment('排序');
                $table->timestamps();
                $table->softDeletes();
                $table->unique('domain', 'admin_sites_domain_unique');
                $table->index('status', 'admin_sites_status_index');
                $table->index('default_brand_id', 'admin_sites_default_brand_id_index');
                $table->index('default_wechat_provider_id', 'admin_sites_default_wechat_provider_id_index');
            });
        }

        return $this->response->json(['error']);
    }
}
