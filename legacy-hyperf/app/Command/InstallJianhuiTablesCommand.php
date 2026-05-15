<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Admin\Addons\AddonsPgsqlService;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputOption;

class InstallJianhuiTablesCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('install:jianhui-tables');
    }

    public function configure()
    {
        $this->setDescription('创建/更新 JianhuiOrg 插件数据库表')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制重新创建所有表');
    }

    public function handle()
    {
        $this->info('开始创建 JianhuiOrg 插件数据库表...');
        $this->line('');

        try {
            $pgsqlFile = BASE_PATH . '/addons/JianhuiOrg/Manager/pgsql.json';

            if (!file_exists($pgsqlFile)) {
                $this->error("错误：配置文件不存在: {$pgsqlFile}");
                return 1;
            }

            $this->info("1. 检查配置文件: {$pgsqlFile}");
            $configContent = file_get_contents($pgsqlFile);
            $config = json_decode($configContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("错误：JSON格式错误: " . json_last_error_msg());
                return 1;
            }

            $this->info("   ✓ 配置文件读取成功");
            $this->line('');

            // 创建服务实例
            $this->info("2. 初始化 PostgreSQL 服务...");
            $service = new AddonsPgsqlService();
            $this->info("   ✓ 服务初始化成功");
            $this->line('');

            // 执行数据库管理
            $this->info("3. 创建数据库表...");
            $result = $service->managePgsqlDatabase('JianhuiOrg', $pgsqlFile);

            if ($result) {
                $this->info("   ✓ 数据库表创建成功");
                $this->line('');

                // 显示创建的表
                $this->info("4. 验证表创建情况:");
                $this->info("   已创建/更新的表:");

                if (isset($config['tables']) && is_array($config['tables'])) {
                    foreach (array_keys($config['tables']) as $tableName) {
                        $this->info("   - {$tableName}");
                    }
                }

                $this->line('');

                // 验证表是否真的创建了
                $this->info("5. 验证数据库中的表:");
                try {
                    $pdo = Db::connection('pgsql')->getPdo();
                    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'jianhui_org%' ORDER BY table_name");
                    $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                    $this->info("   ✓ 数据库连接成功");
                    $this->info("   当前 JianhuiOrg 相关表 (" . count($tables) . " 个):");
                    foreach ($tables as $table) {
                        // 检查新表
                        if (in_array($table, [
                            'jianhui_org_projects',
                            'jianhui_org_project_progress',
                            'jianhui_org_donations',
                            'jianhui_org_donation_disclosures',
                            'jianhui_org_annual_reports',
                        ])) {
                            $this->line("   ✓ <info>{$table}</info> (新表)", '', 'info');
                        } else {
                            $this->line("   - {$table}");
                        }
                    }

                } catch (\Exception $e) {
                    $this->warn("   ! 验证时出错: " . $e->getMessage());
                }

                $this->line('');
                $this->info('✅ 安装完成！');

                return 0;

            } else {
                $this->error("   ✗ 数据库表创建失败");
                $this->error("   请查看日志获取详细信息");
                return 1;
            }

        } catch (\Exception $e) {
            $this->line('');
            $this->error('错误: ' . $e->getMessage());
            $this->error('文件: ' . $e->getFile() . ':' . $e->getLine());
            return 1;
        }
    }
}
