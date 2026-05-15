<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

class ExecSqlCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('db:exec');
    }

    public function configure()
    {
        $this->setDescription('执行SQL文件')
            ->addArgument('file', InputArgument::OPTIONAL, 'SQL文件路径', 'create_jianhui_tables.sql');
    }

    public function handle()
    {
        $sqlFile = $this->argument('file');

        if (!file_exists($sqlFile)) {
            $this->error("SQL文件不存在: {$sqlFile}");
            return 1;
        }

        $this->info("开始执行SQL文件: {$sqlFile}");
        $this->line('');

        try {
            $sql = file_get_contents($sqlFile);

            // 分割SQL语句（简单实现，按分号分割）
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            $this->info("共 " . count($statements) . " 条SQL语句");
            $this->line('');

            $pdo = Db::connection('pgsql')->getPdo();
            $successCount = 0;
            $errorCount = 0;

            foreach ($statements as $index => $statement) {
                if (empty($statement)) {
                    continue;
                }

                try {
                    $pdo->exec($statement);
                    $successCount++;
                    $this->line("<fg=green>✓</> 语句 " . ($index + 1) . " 执行成功");
                } catch (\Exception $e) {
                    $errorCount++;
                    // 某些错误是可以忽略的（如对象已存在）
                    if (strpos($e->getMessage(), 'already exists') !== false) {
                        $this->line("<comment>!</comment> 语句 " . ($index + 1) . " 已存在（跳过）");
                    } else {
                        $this->line("<fg=red>✗</fg=red> 语句 " . ($index + 1) . " 失败: " . $e->getMessage());
                    }
                }
            }

            $this->line('');
            $this->info("执行完成：");
            $this->line("  成功: <fg=green>{$successCount}</fg=green>");
            $this->line("  失败/跳过: <comment>{$errorCount}</comment>");

            // 验证表是否创建成功
            $this->line('');
            $this->info("验证数据库表...");
            $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_name LIKE 'jianhui_org%' ORDER BY table_name");
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $this->line("  JianhuiOrg 相关表 (" . count($tables) . " 个):");
            foreach ($tables as $table) {
                if (in_array($table, [
                    'jianhui_org_projects',
                    'jianhui_org_project_progress',
                    'jianhui_org_donations',
                    'jianhui_org_donation_disclosures',
                    'jianhui_org_annual_reports',
                ])) {
                    $this->line("  ✓ <info>{$table}</info> (新表)");
                } else {
                    $this->line("  - {$table}");
                }
            }

            $this->line('');
            $this->info('✅ 完成！');

            return 0;

        } catch (\Exception $e) {
            $this->error('错误: ' . $e->getMessage());
            return 1;
        }
    }
}
