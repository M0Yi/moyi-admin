<?php

declare(strict_types=1);

namespace App\Command;

use Addons\JianhuiOrg\Model\JianhuiProject;
use Addons\JianhuiOrg\Model\JianhuiDonation;
use Addons\JianhuiOrg\Model\JianhuiAnnualReport;
use Hyperf\Command\Command as HyperfCommand;
use Hyperf\DbConnection\Db;
use Psr\Container\ContainerInterface;

class TestJianhuiModelsCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('test:jianhui');
    }

    public function configure()
    {
        $this->setDescription('测试 JianhuiOrg 模型功能');
    }

    public function handle()
    {
        $this->info("========================================");
        $this->info("  JianhuiOrg 模型测试");
        $this->info("========================================");
        $this->line('');

        try {
            // 测试数据库连接
            $this->info("1. 测试数据库连接...");
            $pdo = Db::connection('pgsql')->getPdo();
            $this->info("   ✓ 数据库连接成功");
            $this->line('');

            // 测试 JianhuiProject 模型
            $this->info("2. 测试 JianhuiProject 模型...");
            $projects = JianhuiProject::all();

            $this->info("   查询到 " . $projects->count() . " 个项目:");
            foreach ($projects as $project) {
                $this->line("   - [<info>{$project->id}</info>] <comment>{$project->title}</comment>");
                $this->line("     类型: {$project->project_type_label}");
                $this->line("     状态: <fg=green>{$project->status_label}</fg=green>");
                $this->line("     筹款进度: <fg=yellow>{$project->progress_percentage}%</fg=yellow>");
                $this->line("     已筹/目标: " . number_format($project->raised_amount) . " / " . number_format($project->target_amount) . " 元");
                $this->line('');
            }

            // 测试单个项目查询
            $this->info("3. 测试单个项目详情...");
            $project = JianhuiProject::find(1);

            if ($project) {
                $this->info("   ✓ 项目加载成功");
                $this->line("   - 标题: <comment>{$project->title}</comment>");
                $this->line("   - 描述: {$project->description}");

                // 测试关联关系
                $this->line("   - 进展记录数: <info>" . $project->progressRecords()->count() . "</info>");
                $this->line("   - 捐赠记录数: <info>" . $project->donations()->count() . "</info>");

                // 测试辅助方法
                $this->line("   - 剩余天数: " . ($project->remaining_days ?? '未设置'));
                $this->line("   - 是否达标: " . ($project->isTargetReached() ? '<fg=green>是</fg=green>' : '<fg=red>否</fg=red>'));
            } else {
                $this->warn("   ! 项目未找到");
            }

            $this->line('');

            // 测试 JianhuiDonation 模型
            $this->info("4. 测试 JianhuiDonation 模型...");
            $donations = JianhuiDonation::completed()->get();

            $this->info("   查询到 " . $donations->count() . " 条已完成捐赠:");
            foreach ($donations as $donation) {
                $this->line("   - <info>{$donation->donor_display_name}</info>: " . number_format($donation->amount) . " 元");
            }

            $this->line('');

            // 测试 JianhuiAnnualReport 模型
            $this->info("5. 测试 JianhuiAnnualReport 模型...");
            $reports = JianhuiAnnualReport::published()->orderByYear('desc')->get();

            $this->info("   查询到 " . $reports->count() . " 份已发布报告:");
            foreach ($reports as $report) {
                $this->line("   - [<info>{$report->year}</info>] {$report->title}");
                $this->line("     类型: {$report->report_type_label}");
                $this->line("     完整标题: {$report->full_title}");
            }

            $this->line('');

            // 测试查询作用域
            $this->info("6. 测试查询作用域...");

            $activeProjects = JianhuiProject::active()->get();
            $this->line("   - 进行中项目: <info>{$activeProjects->count()}</info> 个");

            $featuredProjects = JianhuiProject::featured()->get();
            $this->line("   - 精选项目: <info>{$featuredProjects->count()}</info> 个");

            $medicalProjects = JianhuiProject::projectType('medical')->get();
            $this->line("   - 医疗援助项目: <info>{$medicalProjects->count()}</info> 个");

            $this->line('');

            // 测试统计
            $this->info("7. 测试数据统计...");

            $totalRaised = JianhuiDonation::completed()->sum('amount');
            $this->line("   - 总筹款金额: <fg=green>" . number_format($totalRaised, 2) . "</fg=green> 元");

            $totalBeneficiaries = JianhuiProject::sum('beneficiary_count');
            $this->line("   - 总受益人数: <info>" . number_format($totalBeneficiaries) . "</info> 人");

            $avgProgress = JianhuiProject::active()->get()->avg(function ($p) {
                return $p->progress_percentage;
            });
            $this->line("   - 平均筹款进度: <fg=yellow>" . number_format($avgProgress, 2) . "%</fg=yellow>");

            $this->line('');
            $this->info("========================================");
            $this->info("  ✅ 所有测试通过！");
            $this->info("========================================");

            return 0;

        } catch (\Exception $e) {
            $this->line('');
            $this->error("========================================");
            $this->error("  ❌ 测试失败！");
            $this->error("========================================");
            $this->error("错误: " . $e->getMessage());
            $this->error("文件: " . $e->getFile() . ":" . $e->getLine());
            return 1;
        }
    }
}
