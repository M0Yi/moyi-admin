<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminUser;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminPermission;
use App\Model\Admin\AdminSite;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;
use Hyperf\DbConnection\Db;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Hyperf\Di\Annotation\Inject;
use HyperfExtension\Auth\Events\Logout;

/**
 * 系统初始化控制器
 */
class InstallController extends AbstractController
{
    /**
     * 初始化页面
     */
    public function index(): \Psr\Http\Message\ResponseInterface
    {
        // 检查是否已经初始化
        if ($this->isInstalled()) {
            return $this->render->render('admin.install.installed', [
                'message' => '系统已经初始化，如需重新初始化，请删除默认站点（ID=1）',
            ]);
        }

        return $this->render->render('admin.install.index');
    }

    /**
     * 处理初始化请求
     */
    public function install(RequestInterface $request, HttpResponse $response): \Psr\Http\Message\ResponseInterface
    {
        // 检查是否已经初始化
        if ($this->isInstalled()) {
            logger()->info('检测到系统已初始化，终止安装');
            return $response->json([
                'code' => 1,
                'message' => '系统已经初始化，无法重复初始化',
            ]);
        }
        logger()->info('开始初始化系统');

        // 获取表单数据
        $data = $request->all();
        logger()->info('完成安装参数接收');

        // 验证数据
        $errors = $this->validateInstallData($data);
        if (!empty($errors)) {
            logger()->info('安装参数校验失败');
            return $response->json([
                'code' => 1,
                'message' => '数据验证失败',
                'errors' => $errors,
            ]);
        }

        try {
            // 0.初始化数据表（DDL 操作不参与事务）
            logger()->info('开始初始化数据表');
            $this->initDatabase();
            logger()->info('数据表初始化完成');

            // 开始事务（仅包含后续 DML 操作）
            logger()->info('开始数据库事务');
            Db::beginTransaction();

            // 1. 创建站点
            logger()->info('开始创建站点');
            $site = $this->createSite($data);
            logger()->info('站点创建完成，ID=' . $site->id);

            // 2. 创建超级管理员角色
            logger()->info('开始创建超级管理员角色');
            $superAdminRole = $this->createSuperAdminRole($site->id);
            logger()->info('超级管理员角色创建完成，ID=' . $superAdminRole->id);

            // 3. 创建默认权限
            logger()->info('开始创建默认权限');
            $this->createDefaultPermissions($site->id);
            logger()->info('默认权限创建完成');

            // 4. 创建超级管理员账号
            logger()->info('开始创建超级管理员账号');
            $adminUser = $this->createAdminUser($data, $site->id);
            logger()->info('超级管理员账号创建完成，ID=' . $adminUser->id);

            // 5. 分配角色给管理员
            logger()->info('开始分配角色给管理员');
            $adminUser->roles()->attach($superAdminRole->id);
            logger()->info('角色分配完成');

            // 提交事务
            logger()->info('准备提交事务');
            Db::commit();
            logger()->info('系统初始化成功');

            return $response->json([
                'code' => 200,
                'message' => '系统初始化成功',
                'data' => [
                    'admin_path' => $site->admin_entry_path,
                    'username' => $data['username'],
                    'site_name' => $site->name,
                ],
            ]);

        } catch (\Throwable $e) {
            logger()->error('初始化失败：' . $e->getMessage());
            // 回滚事务（仅在事务活动时回滚）
            try {
                Db::rollBack();
            } catch (\Throwable $ignored) {
                logger()->error('回滚失败');
            }

            return $response->json([
                'code' => 1,
                'message' => '初始化失败：' . $e->getMessage(),
            ]);
        }
    }

    /**
     * 检查是否已安装
     *
     * 判断标准：默认站点（ID=1）是否存在
     */
    private function isInstalled(): bool
    {
        try {
            // 只检查默认站点（ID=1）是否存在
            return AdminSite::query()
                ->where('id', 1)
                ->exists();
        } catch (\Throwable $e) {
            // 数据库连接失败或表不存在，视为未安装
            return false;
        }
    }

    /**
     * 验证安装数据
     */
    private function validateInstallData(array $data): array
    {
        $errors = [];

        // 站点名称
        if (empty($data['site_name'])) {
            $errors['site_name'] = '请输入站点名称';
        }

        // 站点域名
        if (empty($data['site_domain'])) {
            $errors['site_domain'] = '请输入站点域名';
        }

        // 管理员用户名
        if (empty($data['username'])) {
            $errors['username'] = '请输入管理员用户名';
        } elseif (strlen($data['username']) < 3) {
            $errors['username'] = '用户名长度至少3位';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors['username'] = '用户名只能包含字母、数字和下划线';
        }

        // 管理员密码
        if (empty($data['password'])) {
            $errors['password'] = '请输入管理员密码';
        } elseif (strlen($data['password']) < 6) {
            $errors['password'] = '密码长度至少6位';
        }

        // 确认密码
        if (empty($data['password_confirmation'])) {
            $errors['password_confirmation'] = '请确认密码';
        } elseif ($data['password'] !== $data['password_confirmation']) {
            $errors['password_confirmation'] = '两次密码输入不一致';
        }

        // 管理员邮箱
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = '邮箱格式不正确';
        }

        return $errors;
    }

    /**
     * 创建站点
     */
    private function createSite(array $data): AdminSite
    {
        // 生成随机的后台入口路径
        $adminPath = AdminSite::generateRandomAdminPath(16);
        return AdminSite::create([
            'domain' => $data['site_domain'],
            'admin_entry_path' => $adminPath,
            'name' => $data['site_name'],
            'title' => $data['site_title'] ?? $data['site_name'],
            'primary_color' => '#007bff',
            'status' => AdminSite::STATUS_ENABLED,
            'sort' => 0,
        ]);
    }

    /**
     * 创建超级管理员角色
     */
    private function createSuperAdminRole(int $siteId): AdminRole
    {
        return AdminRole::create([
            'site_id' => $siteId,
            'name' => '超级管理员',
            'slug' => 'super-admin',
            'description' => '拥有所有权限的超级管理员',
            'status' => 1,
            'sort' => 0,
        ]);
    }

    /**
     * 创建默认权限
     */
    private function createDefaultPermissions(int $siteId): void
    {
        $permissions = [
            // 仪表盘
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '仪表盘',
                'slug' => 'dashboard',
                'type' => 'menu',
                'icon' => 'grid',
                'path' => '/dashboard',
                'status' => 1,
                'sort' => 1,
            ],
            // 用户管理
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '用户管理',
                'slug' => 'users',
                'type' => 'menu',
                'icon' => 'users',
                'path' => '/users',
                'status' => 1,
                'sort' => 2,
            ],
            // 角色管理
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '角色管理',
                'slug' => 'roles',
                'type' => 'menu',
                'icon' => 'shield',
                'path' => '/roles',
                'status' => 1,
                'sort' => 3,
            ],
            // 权限管理
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '权限管理',
                'slug' => 'permissions',
                'type' => 'menu',
                'icon' => 'key',
                'path' => '/permissions',
                'status' => 1,
                'sort' => 4,
            ],
            // 站点管理
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '站点管理',
                'slug' => 'sites',
                'type' => 'menu',
                'icon' => 'globe',
                'path' => '/sites',
                'status' => 1,
                'sort' => 5,
            ],
            // 系统配置
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => '系统配置',
                'slug' => 'settings',
                'type' => 'menu',
                'icon' => 'settings',
                'path' => '/settings',
                'status' => 1,
                'sort' => 6,
            ],
        ];

        foreach ($permissions as $permission) {
            AdminPermission::create($permission);
        }
    }

    /**
     * 创建管理员用户
     */
    private function createAdminUser(array $data, int $siteId): AdminUser
    {
        return AdminUser::create([
            'site_id' => $siteId,
            'username' => $data['username'],
            'password' => $data['password'], // 会自动通过 setPasswordAttribute 加密
            'email' => $data['email'] ?? '',
            'mobile' => $data['mobile'] ?? '',
            'real_name' => $data['real_name'] ?? '超级管理员',
            'status' => 1,
            'is_admin' => 1,
        ]);
    }

    /**
     * 检查环境
     */
    public function checkEnvironment(HttpResponse $response): \Psr\Http\Message\ResponseInterface
    {
        $checks = [
            'php_version' => [
                'name' => 'PHP 版本',
                'required' => '>= 8.0',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, '8.0.0', '>='),
            ],
            'extensions' => [],
        ];

        // 检查必需的扩展
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'swoole', 'redis'];
        foreach ($requiredExtensions as $ext) {
            $checks['extensions'][$ext] = [
                'name' => $ext,
                'passed' => extension_loaded($ext),
            ];
        }

        // 检查目录权限
        $checks['directories'] = [
            'runtime' => [
                'name' => 'runtime 目录',
                'path' => BASE_PATH . '/runtime',
                'writable' => is_writable(BASE_PATH . '/runtime'),
            ],
            'storage' => [
                'name' => 'storage 目录',
                'path' => BASE_PATH . '/storage',
                'writable' => is_writable(BASE_PATH . '/storage'),
            ],
        ];

        try {
            $dbNameRow = Db::select('SELECT DATABASE() AS db');
            $database = isset($dbNameRow[0]) ? (array) $dbNameRow[0] : [];
            $dbName = $database['db'] ?? '';

            $tables = Db::select('SHOW TABLES');
            $tableCount = is_array($tables) ? count($tables) : 0;

            $checks['database'] = [
                'name' => '数据库状态',
                'database' => $dbName,
                'table_count' => $tableCount,
                'empty' => $tableCount === 0,
                'passed' => $tableCount === 0,
                'suggest' => $tableCount === 0
                    ? '当前数据库为空，适合进行安装'
                    : '检测到当前数据库已有数据，建议在空数据库上安装以避免冲突',
            ];
        } catch (\Throwable $e) {
            $checks['database'] = [
                'name' => '数据库状态',
                'database' => '',
                'table_count' => null,
                'empty' => null,
                'passed' => false,
                'error' => '无法连接到数据库：' . $e->getMessage(),
                'suggest' => '请检查数据库连接配置是否正确',
            ];
        }

        return $response->json([
            'code' => 200,
            'data' => $checks,
        ]);
    }


    /**
     * 创建站点
     */
    private function initDatabase():void
    {
        if (!Schema::hasTable('admin_sites')) {
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

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('username', 50);
                $table->string('password', 255);
                $table->string('email', 100)->nullable();
                $table->string('mobile', 20)->nullable();
                $table->string('avatar', 255)->nullable();
                $table->string('real_name', 50)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->tinyInteger('is_admin')->default(0);
                $table->string('last_login_ip', 50)->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique('username', 'admin_users_username_unique');
                $table->unique('email', 'admin_users_email_unique');
                $table->index('status', 'admin_users_status_index');
                $table->index('created_at', 'admin_users_created_at_index');
                $table->index('site_id', 'idx_sites_id');
            });
        }

        if (! Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 50);
                $table->string('slug', 50);
                $table->string('description', 255)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique('slug', 'admin_roles_slug_unique');
                $table->index('status', 'admin_roles_status_index');
                $table->index('sort', 'admin_roles_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
        }

        if (! Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('name', 50);
                $table->string('slug', 100);
                $table->string('type', 20)->default('menu');
                $table->string('icon', 50)->nullable();
                $table->string('path', 255)->nullable();
                $table->string('component', 255)->nullable();
                $table->string('description', 255)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique('slug', 'admin_permissions_slug_unique');
                $table->index('parent_id', 'admin_permissions_parent_id_index');
                $table->index('status', 'admin_permissions_status_index');
                $table->index('sort', 'admin_permissions_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
        }

        if (! Schema::hasTable('admin_permission_role')) {
            Schema::create('admin_permission_role', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('permission_id');
                $table->unsignedBigInteger('role_id');
                $table->timestamps();
                $table->unique(['permission_id', 'role_id'], 'uk_permission_role');
                $table->index('role_id', 'admin_permission_role_role_id_index');
            });
        }

        if (! Schema::hasTable('admin_role_user')) {
            Schema::create('admin_role_user', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('role_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();
                $table->unique(['role_id', 'user_id'], 'uk_role_user');
                $table->index('user_id', 'admin_role_user_user_id_index');
            });
        }

        if (! Schema::hasTable('admin_menus')) {
            Schema::create('admin_menus', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->default(0);
                $table->unsignedBigInteger('parent_id')->default(0);
                $table->string('name', 100);
                $table->string('title', 100);
                $table->string('icon', 50)->nullable();
                $table->string('path', 255)->nullable();
                $table->string('component', 255)->nullable();
                $table->string('redirect', 255)->nullable();
                $table->string('type', 20)->default('menu');
                $table->string('target', 20)->default('_self');
                $table->string('badge', 50)->nullable();
                $table->string('badge_type', 20)->nullable();
                $table->string('permission', 100)->nullable();
                $table->tinyInteger('visible')->default(1);
                $table->tinyInteger('status')->default(1);
                $table->integer('sort')->default(0);
                $table->tinyInteger('cache')->default(1);
                $table->longText('config')->nullable();
                $table->text('remark')->nullable();
                $table->timestamps();
                $table->index('site_id', 'idx_sites_id');
                $table->index('parent_id', 'idx_parent_id');
                $table->index(['site_id', 'status'], 'idx_sites_status');
                $table->index(['site_id', 'parent_id', 'sort'], 'idx_sites_parent_sort');
                $table->index('name', 'idx_name');
                $table->index('permission', 'idx_permission');
            });
        }

        if (! Schema::hasTable('admin_configs')) {
            Schema::create('admin_configs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('group', 50)->default('system');
                $table->string('key', 100);
                $table->text('value')->nullable();
                $table->string('type', 20)->default('string');
                $table->string('description', 255)->nullable();
                $table->integer('sort')->default(0);
                $table->timestamps();
                $table->unique('key', 'admin_configs_key_unique');
                $table->index('group', 'admin_configs_group_index');
                $table->index('sort', 'admin_configs_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
        }

        if (! Schema::hasTable('admin_attachments')) {
            Schema::create('admin_attachments', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('name', 255);
                $table->text('description')->nullable();
                $table->string('category', 50)->nullable();
                $table->string('tags', 255)->nullable();
                $table->string('original_filename', 255);
                $table->string('filename', 255);
                $table->string('file_path', 255);
                $table->string('file_url', 255)->nullable();
                $table->string('content_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->string('storage_driver', 20)->default('local');
                $table->string('file_hash', 64)->nullable();
                $table->string('related_type', 50)->nullable();
                $table->unsignedBigInteger('related_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('username', 50)->nullable();
                $table->tinyInteger('status')->default(1);
                $table->integer('sort')->default(0);
                $table->string('ip_address', 50)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->integer('download_count')->default(0);
                $table->timestamp('last_downloaded_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index('site_id', 'idx_site_id');
                $table->index('user_id', 'idx_user_id');
                $table->index('category', 'idx_category');
                $table->index('related_type', 'idx_related_type');
                $table->index(['related_type', 'related_id'], 'idx_related');
                $table->index('status', 'idx_status');
                $table->index('sort', 'idx_sort');
                $table->index('created_at', 'idx_created_at');
                $table->index('file_hash', 'idx_file_hash');
            });
        }

        if (! Schema::hasTable('admin_upload_files')) {
            Schema::create('admin_upload_files', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->nullable();
                $table->string('upload_token', 64);
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('username', 50)->nullable();
                $table->string('original_filename', 255);
                $table->string('filename', 255);
                $table->string('file_path', 255);
                $table->string('file_url', 255)->nullable();
                $table->string('content_type', 100);
                $table->unsignedBigInteger('file_size');
                $table->string('storage_driver', 20)->default('local');
                $table->tinyInteger('status')->default(0);
                $table->text('violation_reason')->nullable();
                $table->timestamp('token_expire_at');
                $table->timestamp('uploaded_at')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->tinyInteger('check_status')->default(0);
                $table->text('check_result')->nullable();
                $table->string('ip_address', 50)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique('upload_token', 'admin_upload_files_upload_token_unique');
                $table->index('site_id', 'idx_site_id');
                $table->index('user_id', 'idx_user_id');
                $table->index('upload_token', 'idx_upload_token');
                $table->index('status', 'idx_status');
                $table->index('check_status', 'idx_check_status');
                $table->index('token_expire_at', 'idx_token_expire_at');
                $table->index('created_at', 'idx_created_at');
            });
        }
    }
}
