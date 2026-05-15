<?php

/**
 * 测试 JianhuiOrg 模型
 */

require_once __DIR__ . '/vendor/autoload.php';

use Addons\JianhuiOrg\Model\JianhuiProject;
use Addons\JianhuiOrg\Model\JianhuiDonation;
use Addons\JianhuiOrg\Model\JianhuiAnnualReport;
use Hyperf\DbConnection\Db;

echo "========================================\n";
echo "  JianhuiOrg 模型测试\n";
echo "========================================\n\n";

try {
    // 测试数据库连接
    echo "1. 测试数据库连接...\n";
    $pdo = Db::connection('pgsql')->getPdo();
    echo "   ✓ 数据库连接成功\n\n";

    // 测试 JianhuiProject 模型
    echo "2. 测试 JianhuiProject 模型...\n";
    $projects = JianhuiProject::all();

    echo "   查询到 " . $projects->count() . " 个项目:\n";
    foreach ($projects as $project) {
        echo "   - [{$project->id}] {$project->title}\n";
        echo "     类型: {$project->project_type_label}\n";
        echo "     状态: {$project->status_label}\n";
        echo "     筹款进度: {$project->progress_percentage}%\n";
        echo "     已筹/目标: " . number_format($project->raised_amount) . " / " . number_format($project->target_amount) . " 元\n";
        echo "\n";
    }

    // 测试单个项目查询
    echo "3. 测试单个项目详情...\n";
    $project = JianhuiProject::find(1);

    if ($project) {
        echo "   ✓ 项目加载成功\n";
        echo "   - 标题: {$project->title}\n";
        echo "   - 描述: {$project->description}\n";

        // 测试关联关系
        echo "   - 进展记录数: " . $project->progressRecords()->count() . "\n";
        echo "   - 捐赠记录数: " . $project->donations()->count() . "\n";

        // 测试辅助方法
        echo "   - 剩余天数: " . ($project->remaining_days ?? '未设置') . "\n";
        echo "   - 是否达标: " . ($project->isTargetReached() ? '是' : '否') . "\n";
    } else {
        echo "   ! 项目未找到\n";
    }

    echo "\n";

    // 测试 JianhuiDonation 模型
    echo "4. 测试 JianhuiDonation 模型...\n";
    $donations = JianhuiDonation::completed()->get();

    echo "   查询到 " . $donations->count() . " 条已完成捐赠:\n";
    foreach ($donations as $donation) {
        echo "   - {$donation->donor_display_name}: " . number_format($donation->amount) . " 元\n";
    }

    echo "\n";

    // 测试 JianhuiAnnualReport 模型
    echo "5. 测试 JianhuiAnnualReport 模型...\n";
    $reports = JianhuiAnnualReport::published()->orderByYear('desc')->get();

    echo "   查询到 " . $reports->count() . " 份已发布报告:\n";
    foreach ($reports as $report) {
        echo "   - [{$report->year}] {$report->title}\n";
        echo "     类型: {$report->report_type_label}\n";
        echo "     完整标题: {$report->full_title}\n";
    }

    echo "\n";

    // 测试查询作用域
    echo "6. 测试查询作用域...\n";

    $activeProjects = JianhuiProject::active()->get();
    echo "   - 进行中项目: " . $activeProjects->count() . " 个\n";

    $featuredProjects = JianhuiProject::featured()->get();
    echo "   - 精选项目: " . $featuredProjects->count() . " 个\n";

    $medicalProjects = JianhuiProject::projectType('medical')->get();
    echo "   - 医疗援助项目: " . $medicalProjects->count() . " 个\n";

    echo "\n";

    // 测试统计
    echo "7. 测试数据统计...\n";

    $totalRaised = JianhuiDonation::completed()->sum('amount');
    echo "   - 总筹款金额: " . number_format($totalRaised, 2) . " 元\n";

    $totalBeneficiaries = JianhuiProject::sum('beneficiary_count');
    echo "   - 总受益人数: " . number_format($totalBeneficiaries) . " 人\n";

    $avgProgress = JianhuiProject::active()->get()->avg(function ($p) {
        return $p->progress_percentage;
    });
    echo "   - 平均筹款进度: " . number_format($avgProgress, 2) . "%\n";

    echo "\n";

    echo "========================================\n";
    echo "  ✅ 所有测试通过！\n";
    echo "========================================\n";

} catch (\Exception $e) {
    echo "\n";
    echo "========================================\n";
    echo "  ❌ 测试失败！\n";
    echo "========================================\n";
    echo "错误: " . $e->getMessage() . "\n";
    echo "文件: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "堆栈:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
