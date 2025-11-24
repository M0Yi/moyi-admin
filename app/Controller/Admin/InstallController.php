<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Model\Admin\AdminUser;
use App\Model\Admin\AdminRole;
use App\Model\Admin\AdminPermission;
use App\Model\Admin\AdminMenu;
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
                'message' => '系统已经初始化，如需重新初始化，清空数据库重试）',
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

            logger()->info('开始创建默认菜单');
            $this->createDefaultMenus($site->id);
            logger()->info('默认菜单创建完成');

            // 4. 创建超级管理员账号
            logger()->info('开始创建超级管理员账号');
            $adminUser = $this->createAdminUser($data, $site->id);
            logger()->info('超级管理员账号创建完成，ID=' . $adminUser->id);

            // 5. 分配角色给管理员
            logger()->info('开始分配角色给管理员');
            $adminUser->roles()->attach($superAdminRole->id);
            logger()->info('角色分配完成');

            // 6. 插入测试数据
            logger()->info('开始插入测试数据');
            $this->insertTestData($site->id, $adminUser->id);
            logger()->info('测试数据插入完成');

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
            // iframe 模式体验
            [
                'site_id' => $siteId,
                'parent_id' => 0,
                'name' => 'Iframe 模式体验',
                'slug' => 'system.iframe-demo.view',
                'type' => 'menu',
                'icon' => 'columns-gap',
                'path' => '/system/iframe-demo',
                'status' => 1,
                'sort' => 902,
            ],
//            // 用户管理
//            [
//                'site_id' => $siteId,
//                'parent_id' => 0,
//                'name' => '用户管理',
//                'slug' => 'users',
//                'type' => 'menu',
//                'icon' => 'users',
//                'path' => '/users',
//                'status' => 1,
//                'sort' => 2,
//            ],
//            // 角色管理
//            [
//                'site_id' => $siteId,
//                'parent_id' => 0,
//                'name' => '角色管理',
//                'slug' => 'roles',
//                'type' => 'menu',
//                'icon' => 'shield',
//                'path' => '/roles',
//                'status' => 1,
//                'sort' => 3,
//            ],
//            // 权限管理
//            [
//                'site_id' => $siteId,
//                'parent_id' => 0,
//                'name' => '权限管理',
//                'slug' => 'permissions',
//                'type' => 'menu',
//                'icon' => 'key',
//                'path' => '/permissions',
//                'status' => 1,
//                'sort' => 4,
//            ],
//            // 站点管理
//            [
//                'site_id' => $siteId,
//                'parent_id' => 0,
//                'name' => '站点管理',
//                'slug' => 'sites',
//                'type' => 'menu',
//                'icon' => 'globe',
//                'path' => '/sites',
//                'status' => 1,
//                'sort' => 5,
//            ],
//            // 系统配置
//            [
//                'site_id' => $siteId,
//                'parent_id' => 0,
//                'name' => '系统配置',
//                'slug' => 'settings',
//                'type' => 'menu',
//                'icon' => 'settings',
//                'path' => '/settings',
//                'status' => 1,
//                'sort' => 6,
//            ],
        ];

        foreach ($permissions as $permission) {
            AdminPermission::create($permission);
        }
    }

    private function createDefaultMenus(int $siteId): void
    {
        $dashboard = AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'path' => '/dashboard'],
            [
                'parent_id' => 0,
                'name' => 'dashboard',
                'title' => '仪表盘',
                'icon' => 'bi bi-speedometer2',
                'component' => null,
                'redirect' => null,
                'type' => AdminMenu::TYPE_MENU,
                'target' => AdminMenu::TARGET_SELF,
                'badge' => null,
                'badge_type' => null,
                'permission' => null,
                'visible' => 1,
                'status' => 1,
                'sort' => 1,
                'cache' => 1,
                'config' => null,
                'remark' => null,
            ]
        );

        $system = AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'path' => '/system'],
            [
                'parent_id' => 0,
                'name' => 'system',
                'title' => '系统管理',
                'icon' => 'bi bi-gear',
                'component' => null,
                'redirect' => null,
                'type' => AdminMenu::TYPE_GROUP,
                'target' => AdminMenu::TARGET_SELF,
                'badge' => null,
                'badge_type' => null,
                'permission' => null,
                'visible' => 1,
                'status' => 1,
                'sort' => 100,
                'cache' => 1,
                'config' => null,
                'remark' => null,
            ]
        );

       AdminMenu::query()->firstOrCreate(
           ['site_id' => $siteId, 'path' => '/system/users'],
           [
               'parent_id' => $system->id,
               'name' => 'system.users',
               'title' => '用户管理',
               'icon' => 'bi bi-people',
               'component' => null,
               'redirect' => null,
               'type' => AdminMenu::TYPE_MENU,
               'target' => AdminMenu::TARGET_SELF,
               'badge' => null,
               'badge_type' => null,
               'permission' => 'system.users.view',
               'visible' => 1,
               'status' => 1,
               'sort' => 1,
               'cache' => 1,
               'config' => null,
               'remark' => null,
           ]
       );

       AdminMenu::query()->firstOrCreate(
           ['site_id' => $siteId, 'path' => '/system/roles'],
           [
               'parent_id' => $system->id,
               'name' => 'system.roles',
               'title' => '角色管理',
               'icon' => 'bi bi-person-badge',
               'component' => null,
               'redirect' => null,
               'type' => AdminMenu::TYPE_MENU,
               'target' => AdminMenu::TARGET_SELF,
               'badge' => null,
               'badge_type' => null,
               'permission' => 'system.roles.view',
               'visible' => 1,
               'status' => 1,
               'sort' => 2,
               'cache' => 1,
               'config' => null,
               'remark' => null,
           ]
       );

       AdminMenu::query()->firstOrCreate(
           ['site_id' => $siteId, 'path' => '/system/permissions'],
           [
               'parent_id' => $system->id,
               'name' => 'system.permissions',
               'title' => '权限管理',
               'icon' => 'bi bi-shield-check',
               'component' => null,
               'redirect' => null,
               'type' => AdminMenu::TYPE_MENU,
               'target' => AdminMenu::TARGET_SELF,
               'badge' => null,
               'badge_type' => null,
               'permission' => 'system.permissions.view',
               'visible' => 1,
               'status' => 1,
               'sort' => 3,
               'cache' => 1,
               'config' => null,
               'remark' => null,
           ]
       );

       AdminMenu::query()->firstOrCreate(
           ['site_id' => $siteId, 'path' => '/system/menus'],
           [
               'parent_id' => $system->id,
               'name' => 'system.menus',
               'title' => '菜单管理',
               'icon' => 'bi bi-menu-button-wide',
               'component' => null,
               'redirect' => null,
               'type' => AdminMenu::TYPE_MENU,
               'target' => AdminMenu::TARGET_SELF,
               'badge' => null,
               'badge_type' => null,
               'permission' => 'system.menus.view',
               'visible' => 1,
               'status' => 1,
               'sort' => 4,
               'cache' => 1,
               'config' => null,
               'remark' => null,
           ]
       );

       AdminMenu::query()->firstOrCreate(
           ['site_id' => $siteId, 'path' => '/system/sites'],
           [
               'parent_id' => $system->id,
               'name' => 'system.sites',
               'title' => '站点设置',
               'icon' => 'bi bi-sliders',
               'component' => null,
               'redirect' => null,
               'type' => AdminMenu::TYPE_MENU,
               'target' => AdminMenu::TARGET_SELF,
               'badge' => null,
               'badge_type' => null,
               'permission' => 'system.sites.edit',
               'visible' => 1,
               'status' => 1,
               'sort' => 5,
               'cache' => 1,
               'config' => null,
               'remark' => null,
           ]
       );

        AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'name' => 'system.divider1'],
            [
                'parent_id' => $system->id,
                'title' => '-',
                'icon' => null,
                'path' => null,
                'component' => null,
                'redirect' => null,
                'type' => AdminMenu::TYPE_DIVIDER,
                'target' => AdminMenu::TARGET_SELF,
                'badge' => null,
                'badge_type' => null,
                'permission' => null,
                'visible' => 1,
                'status' => 1,
                'sort' => 1090,
                'cache' => 1,
                'config' => null,
                'remark' => null,
            ]
        );

        AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'path' => '/system/crud-generator'],
            [
                'parent_id' => $system->id,
                'name' => 'system.crud-generator',
                'title' => 'CRUD生成器',
                'icon' => 'bi bi-code-slash',
                'component' => 'admin/system/crud-generator/index',
                'redirect' => null,
                'type' => AdminMenu::TYPE_MENU,
                'target' => AdminMenu::TARGET_SELF,
                'badge' => null,
                'badge_type' => null,
                'permission' => 'system.crud-generator.view',
                'visible' => 1,
                'status' => 1,
                'sort' => 1900,
                'cache' => 1,
                'config' => null,
                'remark' => '系统菜单',
            ]
        );

        AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'path' => '/system/iframe-demo'],
            [
                'parent_id' => $system->id,
                'name' => 'system.iframe-demo',
                'title' => 'Iframe 模式体验',
                'icon' => 'bi bi-columns-gap',
                'component' => 'admin/system/iframe-demo/index',
                'redirect' => null,
                'type' => AdminMenu::TYPE_MENU,
                'target' => AdminMenu::TARGET_SELF,
                'badge' => null,
                'badge_type' => null,
                'permission' => 'system.iframe-demo.view',
                'visible' => 1,
                'status' => 1,
                'sort' => 1910,
                'cache' => 1,
                'config' => null,
                'remark' => '系统菜单',
            ]
        );

        // 开源地址（外部链接）
        AdminMenu::query()->firstOrCreate(
            ['site_id' => $siteId, 'name' => 'moyi.github.link'],
            [
                'parent_id' => 0,
                'title' => '开源地址',
                'icon' => 'bi bi-code-square',
                'path' => 'https://github.com/M0Yi/moyi-admin',
                'component' => null,
                'redirect' => null,
                'type' => AdminMenu::TYPE_LINK,
                'target' => AdminMenu::TARGET_BLANK,
                'badge' => 'github',
                'badge_type' => AdminMenu::BADGE_PRIMARY,
                'permission' => null,
                'visible' => 1,
                'status' => 1,
                'sort' => 10000,
                'cache' => 1,
                'config' => null,
                'remark' => '项目来源 Hyperf交流 6 群',
            ]
        );
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
                $table->string('resource_cdn', 255)->nullable()->comment('资源CDN地址');
                $table->longText('config')->nullable()->comment('扩展配置(JSON)');
                $table->unsignedBigInteger('default_brand_id')->nullable()->comment('默认品牌ID');
                $table->unsignedBigInteger('default_wechat_provider_id')->nullable()->comment('默认微信服务商ID');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->string('upload_driver', 20)->nullable()->comment('上传驱动');
                $table->longText('upload_config')->nullable()->comment('上传配置(JSON)');
                $table->integer('sort')->default(0)->comment('排序');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->softDeletes()->comment('删除时间');
                $table->unique('domain', 'admin_sites_domain_unique');
                $table->index('status', 'admin_sites_status_index');
                $table->index('default_brand_id', 'admin_sites_default_brand_id_index');
                $table->index('default_wechat_provider_id', 'admin_sites_default_wechat_provider_id_index');
            });
            Db::statement("ALTER TABLE `admin_sites` COMMENT = '站点表'");
        }

        if (! Schema::hasTable('admin_users')) {
            Schema::create('admin_users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('用户ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->string('username', 50)->comment('用户名');
                $table->string('password', 255)->comment('密码');
                $table->string('email', 100)->nullable()->comment('邮箱');
                $table->string('mobile', 20)->nullable()->comment('手机号');
                $table->string('avatar', 255)->nullable()->comment('头像');
                $table->string('real_name', 50)->nullable()->comment('真实姓名');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->tinyInteger('is_admin')->default(0)->comment('是否超级管理员：0=否，1=是');
                $table->string('last_login_ip', 50)->nullable()->comment('最后登录IP');
                $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->softDeletes()->comment('删除时间');
                $table->unique('username', 'admin_users_username_unique');
                $table->unique('email', 'admin_users_email_unique');
                $table->index('status', 'admin_users_status_index');
                $table->index('created_at', 'admin_users_created_at_index');
                $table->index('site_id', 'idx_sites_id');
            });
            Db::statement("ALTER TABLE `admin_users` COMMENT = '管理员用户表'");
        }

        if (! Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('角色ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->string('name', 50)->comment('角色名称');
                $table->string('slug', 50)->comment('角色标识');
                $table->string('description', 255)->nullable()->comment('角色描述');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique('slug', 'admin_roles_slug_unique');
                $table->index('status', 'admin_roles_status_index');
                $table->index('sort', 'admin_roles_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
            Db::statement("ALTER TABLE `admin_roles` COMMENT = '角色表'");
        }

        if (! Schema::hasTable('admin_permissions')) {
            Schema::create('admin_permissions', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('权限ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
                $table->string('name', 50)->comment('权限名称');
                $table->string('slug', 100)->comment('权限标识');
                $table->string('type', 20)->default('menu')->comment('类型：menu=菜单，button=按钮');
                $table->string('icon', 50)->nullable()->comment('图标');
                $table->string('path', 255)->nullable()->comment('路径');
                $table->string('component', 255)->nullable()->comment('组件');
                $table->string('description', 255)->nullable()->comment('描述');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique('slug', 'admin_permissions_slug_unique');
                $table->index('parent_id', 'admin_permissions_parent_id_index');
                $table->index('status', 'admin_permissions_status_index');
                $table->index('sort', 'admin_permissions_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
            Db::statement("ALTER TABLE `admin_permissions` COMMENT = '权限表'");
        }

        if (! Schema::hasTable('admin_permission_role')) {
            Schema::create('admin_permission_role', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('ID');
                $table->unsignedBigInteger('permission_id')->comment('权限ID');
                $table->unsignedBigInteger('role_id')->comment('角色ID');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique(['permission_id', 'role_id'], 'uk_permission_role');
                $table->index('role_id', 'admin_permission_role_role_id_index');
            });
            Db::statement("ALTER TABLE `admin_permission_role` COMMENT = '权限角色关联表'");
        }

        if (! Schema::hasTable('admin_role_user')) {
            Schema::create('admin_role_user', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('ID');
                $table->unsignedBigInteger('role_id')->comment('角色ID');
                $table->unsignedBigInteger('user_id')->comment('用户ID');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique(['role_id', 'user_id'], 'uk_role_user');
                $table->index('user_id', 'admin_role_user_user_id_index');
            });
            Db::statement("ALTER TABLE `admin_role_user` COMMENT = '角色用户关联表'");
        }

        if (! Schema::hasTable('admin_menus')) {
            Schema::create('admin_menus', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('菜单ID');
                $table->unsignedBigInteger('site_id')->default(0)->comment('站点ID');
                $table->unsignedBigInteger('parent_id')->default(0)->comment('父级ID');
                $table->string('name', 100)->comment('菜单名称');
                $table->string('title', 100)->comment('菜单标题');
                $table->string('icon', 50)->nullable()->comment('图标');
                $table->string('path', 255)->nullable()->comment('路径');
                $table->string('component', 255)->nullable()->comment('组件');
                $table->string('redirect', 255)->nullable()->comment('重定向');
                $table->string('type', 20)->default('menu')->comment('类型：menu=菜单，group=分组，divider=分割线');
                $table->string('target', 20)->default('_self')->comment('打开方式：_self=当前窗口，_blank=新窗口');
                $table->string('badge', 50)->nullable()->comment('徽章');
                $table->string('badge_type', 20)->nullable()->comment('徽章类型');
                $table->string('permission', 100)->nullable()->comment('权限标识');
                $table->tinyInteger('visible')->default(1)->comment('是否可见：0=隐藏，1=显示');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->tinyInteger('cache')->default(1)->comment('是否缓存：0=否，1=是');
                $table->longText('config')->nullable()->comment('配置(JSON)');
                $table->text('remark')->nullable()->comment('备注');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->index('site_id', 'idx_sites_id');
                $table->index('parent_id', 'idx_parent_id');
                $table->index(['site_id', 'status'], 'idx_sites_status');
                $table->index(['site_id', 'parent_id', 'sort'], 'idx_sites_parent_sort');
                $table->index('name', 'idx_name');
                $table->index('permission', 'idx_permission');
            });
            Db::statement("ALTER TABLE `admin_menus` COMMENT = '菜单表'");
        }

        if (! Schema::hasTable('admin_configs')) {
            Schema::create('admin_configs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('配置ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->string('group', 50)->default('system')->comment('配置分组');
                $table->string('key', 100)->comment('配置键');
                $table->text('value')->nullable()->comment('配置值');
                $table->string('type', 20)->default('string')->comment('类型：string=字符串，number=数字，boolean=布尔值，json=JSON');
                $table->string('description', 255)->nullable()->comment('描述');
                $table->integer('sort')->default(0)->comment('排序');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique('key', 'admin_configs_key_unique');
                $table->index('group', 'admin_configs_group_index');
                $table->index('sort', 'admin_configs_sort_index');
                $table->index('site_id', 'idx_sites_id');
            });
            Db::statement("ALTER TABLE `admin_configs` COMMENT = '配置表'");
        }

        if (! Schema::hasTable('admin_attachments')) {
            Schema::create('admin_attachments', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('附件ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->string('name', 255)->comment('附件名称');
                $table->text('description')->nullable()->comment('描述');
                $table->string('category', 50)->nullable()->comment('分类');
                $table->string('tags', 255)->nullable()->comment('标签');
                $table->string('original_filename', 255)->comment('原始文件名');
                $table->string('filename', 255)->comment('文件名');
                $table->string('file_path', 255)->comment('文件路径');
                $table->string('file_url', 255)->nullable()->comment('文件URL');
                $table->string('content_type', 100)->comment('文件类型');
                $table->unsignedBigInteger('file_size')->comment('文件大小（字节）');
                $table->string('storage_driver', 20)->default('local')->comment('存储驱动');
                $table->string('file_hash', 64)->nullable()->comment('文件哈希值');
                $table->string('related_type', 50)->nullable()->comment('关联类型');
                $table->unsignedBigInteger('related_id')->nullable()->comment('关联ID');
                $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
                $table->string('username', 50)->nullable()->comment('用户名');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->integer('sort')->default(0)->comment('排序');
                $table->string('ip_address', 50)->nullable()->comment('IP地址');
                $table->string('user_agent', 255)->nullable()->comment('用户代理');
                $table->integer('download_count')->default(0)->comment('下载次数');
                $table->timestamp('last_downloaded_at')->nullable()->comment('最后下载时间');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->softDeletes()->comment('删除时间');
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
            Db::statement("ALTER TABLE `admin_attachments` COMMENT = '附件表'");
        }

        if (! Schema::hasTable('admin_upload_files')) {
            Schema::create('admin_upload_files', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('文件ID');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->string('upload_token', 64)->comment('上传令牌');
                $table->unsignedBigInteger('user_id')->nullable()->comment('用户ID');
                $table->string('username', 50)->nullable()->comment('用户名');
                $table->string('original_filename', 255)->comment('原始文件名');
                $table->string('filename', 255)->comment('文件名');
                $table->string('file_path', 255)->comment('文件路径');
                $table->string('file_url', 255)->nullable()->comment('文件URL');
                $table->string('content_type', 100)->comment('文件类型');
                $table->unsignedBigInteger('file_size')->comment('文件大小（字节）');
                $table->string('storage_driver', 20)->default('local')->comment('存储驱动');
                $table->tinyInteger('status')->default(0)->comment('状态：0=待上传，1=已上传，2=已删除');
                $table->text('violation_reason')->nullable()->comment('违规原因');
                $table->timestamp('token_expire_at')->comment('令牌过期时间');
                $table->timestamp('uploaded_at')->nullable()->comment('上传时间');
                $table->timestamp('checked_at')->nullable()->comment('审核时间');
                $table->tinyInteger('check_status')->default(0)->comment('审核状态：0=待审核，1=通过，2=拒绝');
                $table->text('check_result')->nullable()->comment('审核结果');
                $table->string('ip_address', 50)->nullable()->comment('IP地址');
                $table->string('user_agent', 255)->nullable()->comment('用户代理');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->softDeletes()->comment('删除时间');
                $table->unique('upload_token', 'admin_upload_files_upload_token_unique');
                $table->index('site_id', 'idx_site_id');
                $table->index('user_id', 'idx_user_id');
                $table->index('upload_token', 'idx_upload_token');
                $table->index('status', 'idx_status');
                $table->index('check_status', 'idx_check_status');
                $table->index('token_expire_at', 'idx_token_expire_at');
                $table->index('created_at', 'idx_created_at');
            });
            Db::statement("ALTER TABLE `admin_upload_files` COMMENT = '上传文件表'");
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('用户ID');
                $table->unsignedBigInteger('site_id')->comment('所属站点ID');
                $table->string('username', 50)->comment('登录名');
                $table->string('nickname', 50)->nullable()->comment('显示昵称');
                $table->string('password', 255)->comment('密码');
                $table->string('email', 100)->nullable()->comment('邮箱');
                $table->string('mobile', 20)->nullable()->comment('手机号');
                $table->string('avatar', 255)->nullable()->comment('头像');
                $table->tinyInteger('gender')->nullable()->comment('性别：0=未知，1=男，2=女');
                $table->tinyInteger('status')->default(1)->comment('状态：0=禁用，1=启用');
                $table->integer('points')->default(0)->comment('积分余额');
                $table->decimal('balance', 12, 2)->default(0)->comment('账户余额');
                $table->string('last_login_ip', 50)->nullable()->comment('最后登录IP');
                $table->dateTime('last_login_at')->nullable()->comment('最后登录时间');
                $table->timestamp('created_at')->useCurrent()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate()->comment('更新时间');
                $table->timestamp('deleted_at')->nullable()->comment('软删除时间');
                $table->unique(['site_id', 'username'], 'users_site_username_unique');
                $table->unique(['site_id', 'email'], 'users_site_email_unique');
                $table->unique(['site_id', 'mobile'], 'users_site_mobile_unique');
                $table->index('site_id', 'users_site_id_index');
                $table->index('status', 'users_status_index');
            });
            Db::statement("ALTER TABLE `users` COMMENT = '普通用户表'");

            Db::table('users')->insert([
                'site_id' => 1,
                'username' => '用户名',
                'nickname' => '用户昵称',
                'password' => '',
                'email' => null,
                'mobile' => null,
                'avatar' => null,
                'gender' => 1,
                'status' => 1,
                'points' => 0,
                'balance' => 0,
                'last_login_ip' => null,
                'last_login_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'deleted_at' => null,
            ]);
        }

        if (! Schema::hasTable('test')) {
            Schema::create('test', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_general_ci';
                $table->bigIncrements('id')->comment('id');
                $table->unsignedBigInteger('site_id')->nullable()->comment('站点ID');
                $table->unsignedBigInteger('user_id')->comment('用户');
                $table->longText('user_ids')->nullable()->comment('用户合集');
                $table->string('image', 255)->nullable()->comment('图片');
                $table->longText('images')->nullable()->comment('图片组');
                $table->boolean('is_show')->comment('显示状态:0=隐藏,1=显示');
                $table->string('status', 20)->default('active')->comment('状态:active=启用, inactive=禁用');
                $table->string('title', 200)->comment('标题');
                $table->longText('content')->comment('内容');
                $table->integer('view_count')->default(0)->comment('浏览次数');
                $table->dateTime('published_at')->nullable()->comment('发布时间');
                $table->dateTime('created_at')->nullable()->comment('创建时间');
                $table->dateTime('updated_at')->nullable()->comment('更新时间');
                $table->dateTime('deleted_at')->nullable()->comment('删除时间');
            });
            Db::statement("ALTER TABLE `test` COMMENT = '测试表'");
        }

        if (! Schema::hasTable('admin_crud_configs')) {
            Schema::create('admin_crud_configs', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_unicode_ci';
                $table->bigIncrements('id')->comment('配置ID');
                $table->unsignedBigInteger('site_id')->comment('站点ID');
                $table->string('table_name', 100)->comment('数据表名');
                $table->string('db_connection', 50)->default('default')->comment('数据库连接名称，对应 config/databases.php 中的连接键名');
                $table->string('model_name', 100)->comment('模型名称');
                $table->string('controller_name', 100)->comment('控制器名称');
                $table->string('module_name', 100)->comment('模块名称（中文）');
                $table->string('route_prefix', 150)->comment('路由前缀（后台访问路径，例如 system/articles）');
                $table->string('route_slug', 100)->comment('路由标识（用于菜单/组件）');
                $table->string('icon', 50)->default('bi bi-table')->comment('模块图标（CSS类名或图标路径）');
                $table->longText('fields_config')->nullable()->comment('字段配置（JSON格式）');
                $table->longText('options')->nullable()->comment('其他选项（分页、软删除等）');
                $table->integer('page_size')->default(15)->comment('分页大小');
                $table->tinyInteger('soft_delete')->default(0)->comment('是否启用软删除：0=否，1=是');
                $table->tinyInteger('feature_search')->default(1)->comment('是否启用搜索功能');
                $table->tinyInteger('feature_add')->default(1)->comment('是否启用新增功能');
                $table->tinyInteger('feature_edit')->default(1)->comment('是否启用编辑功能');
                $table->tinyInteger('feature_delete')->default(1)->comment('是否启用删除功能');
                $table->tinyInteger('feature_export')->default(1)->comment('是否启用导出功能');
                $table->tinyInteger('sync_to_menu')->default(1)->comment('是否同步到菜单：0=否，1=是');
                $table->tinyInteger('status')->default(0)->comment('状态：0=配置中，1=已生成');
                $table->timestamp('generated_at')->nullable()->comment('生成时间');
                $table->timestamp('created_at')->nullable()->comment('创建时间');
                $table->timestamp('updated_at')->nullable()->comment('更新时间');
                $table->unique('model_name', 'admin_crud_configs_model_name_index');
                $table->index('site_id', 'admin_crud_configs_sites_id_index');
                $table->index('table_name', 'admin_crud_configs_table_name_index');
                $table->index('status', 'admin_crud_configs_status_index');
                $table->index('created_at', 'admin_crud_configs_created_at_index');
            });

            // 修改 fields_config 和 options 字段的排序规则为 utf8mb4_bin，并添加 JSON 验证约束
            Db::statement("ALTER TABLE `admin_crud_configs` MODIFY `fields_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '字段配置（JSON格式）' CHECK (json_valid(`fields_config`))");
            Db::statement("ALTER TABLE `admin_crud_configs` MODIFY `options` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT '其他选项（分页、软删除等）' CHECK (json_valid(`options`))");
            Db::statement("ALTER TABLE `admin_crud_configs` COMMENT = 'CRUD配置表'");
        }
    }

    /**
     * 插入测试数据
     */
    private function insertTestData(int $siteId, int $userId): void
    {
        $testData = [
            [
                'id' => 1,
                'site_id' => $siteId,
                'user_id' => $userId,
                'user_ids' => null,
                'image' => 'https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287317-b6a262c586998312.jpg',
                'images' => '["https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287317-f02bf714faed7258.jpg","https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287318-4d1ca3f323f43571.jpg"]',
                'is_show' => 0,
                'status' => 'active',
                'title' => '厉害',
                'content' => '',
                'view_count' => 222,
                'published_at' => '2025-11-06 18:01:38',
                'created_at' => '2025-11-05 10:03:34',
                'updated_at' => '2025-11-16 10:02:55',
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'site_id' => $siteId,
                'user_id' => $userId,
                'user_ids' => null,
                'image' => 'https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287317-229f8e96dc80ae75.jpg',
                'images' => '["https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287318-aa76a192cc466b58.jpg","https://inkakofenghui.oss-cn-shenzhen.aliyuncs.com/inkako/meeting/images/user-1/1763287318-b6d5cb42c70c57e4.jpg"]',
                'is_show' => 1,
                'status' => 'active',
                'title' => '哈哈',
                'content' => '',
                'view_count' => 555,
                'published_at' => '2025-11-06 18:01:38',
                'created_at' => '2025-11-05 10:03:34',
                'updated_at' => '2025-11-16 10:03:11',
                'deleted_at' => null,
            ],
        ];

        foreach ($testData as $data) {
            Db::table('test')->insert($data);
        }
    }
}
