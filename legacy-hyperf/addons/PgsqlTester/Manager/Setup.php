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

namespace Addons\PgsqlTester\Manager;

use Addons\PgsqlTester\Service\PgsqlTesterService;
use Hyperf\Di\Annotation\Inject;


/**
 * PgsqlTester 插件设置类.
 *
 * 处理插件的安装、启用、禁用、卸载等生命周期事件
 */
class Setup
{
    #[Inject]
    protected PgsqlTesterService $testerService;
    /**
     * 插件安装时执行.
     */
    public function install(): bool
    {
        logger()->info('[PgsqlTester] 开始执行插件安装...');

        try {
            logger()->info('[PgsqlTester] 插件安装成功');
            return true;
        } catch (\Throwable $e) {
            logger()->error('[PgsqlTester] 插件安装异常: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 插件启用时执行.
     */
    public function enable(): bool
    {
        logger()->info('[PgsqlTester] 开始执行插件启用...');

        try {
            // 检查 zhparser 扩展
            logger()->info('[PgsqlTester] 检查 zhparser 扩展是否已安装...');
            $extensionCheck = $this->testerService->checkZhparserExtension();

            if ($extensionCheck['status'] === 'error') {
                logger()->error('[PgsqlTester] 检查 zhparser 扩展失败: ' . $extensionCheck['error']);
                // 如果检查失败，尝试安装扩展
                logger()->info('[PgsqlTester] 尝试安装 zhparser 扩展...');
                $installResult = $this->testerService->installZhparserExtension();

                if ($installResult['status'] === 'error') {
                    logger()->error('[PgsqlTester] 安装 zhparser 扩展失败: ' . $installResult['error']);
                    return false;
                }

                logger()->info('[PgsqlTester] zhparser 扩展安装成功');
            } elseif (!$extensionCheck['extension_installed']) {
                logger()->info('[PgsqlTester] zhparser 扩展未安装，正在安装...');
                $installResult = $this->testerService->installZhparserExtension();

                if ($installResult['status'] === 'error') {
                    logger()->error('[PgsqlTester] 安装 zhparser 扩展失败: ' . $installResult['error']);
                    return false;
                }

                logger()->info('[PgsqlTester] zhparser 扩展安装成功');
            } else {
                logger()->info('[PgsqlTester] zhparser 扩展已安装');
            }

            // 检查 zhparser 配置
            logger()->info('[PgsqlTester] 检查 zhparser 文本搜索配置...');
            $configCheck = $this->testerService->checkZhparserConfiguration();

            if ($configCheck['status'] === 'error') {
                logger()->error('[PgsqlTester] 检查 zhparser 配置失败: ' . $configCheck['error']);
                // 如果检查失败，尝试创建配置
                logger()->info('[PgsqlTester] 尝试创建 zhparser 配置...');
                $createResult = $this->testerService->createZhparserConfiguration();

                if ($createResult['status'] === 'error') {
                    logger()->error('[PgsqlTester] 创建 zhparser 配置失败: ' . $createResult['error']);
                    return false;
                }

                logger()->info('[PgsqlTester] zhparser 配置创建成功');
            } elseif (!$configCheck['fully_configured']) {
                logger()->info('[PgsqlTester] zhparser 配置不完整，尝试创建...');

                if (!$configCheck['config_exists']) {
                    logger()->info('[PgsqlTester] 创建 zhparser 文本搜索配置...');
                    $createResult = $this->testerService->createZhparserConfiguration();

                    if ($createResult['status'] === 'error') {
                        logger()->error('[PgsqlTester] 创建 zhparser 配置失败: ' . $createResult['error']);
                        return false;
                    }
                } elseif (!$configCheck['mapping_exists']) {
                    logger()->info('[PgsqlTester] 添加 zhparser 映射策略...');
                    $addMappingSql = "
                        ALTER TEXT SEARCH CONFIGURATION zhparser
                        ADD MAPPING FOR n,v,a,i,e,l WITH simple
                    ";
                    $mappingResult = $this->testerService->executeDdl($addMappingSql);

                    if ($mappingResult['status'] === 'error') {
                        logger()->error('[PgsqlTester] 添加 zhparser 映射失败: ' . $mappingResult['error']);
                        return false;
                    }
                }

                logger()->info('[PgsqlTester] zhparser 配置补全成功');
            } else {
                logger()->info('[PgsqlTester] zhparser 配置已存在且完整');
            }

            // 检查主键约束
            logger()->info('[PgsqlTester] 检查主键约束...');
            $primaryKeyCheck = $this->testerService->checkPrimaryKeyConstraint();

            if ($primaryKeyCheck['status'] === 'error') {
                logger()->error('[PgsqlTester] 检查主键约束失败: ' . $primaryKeyCheck['error']);
                return false;
            } elseif (!$primaryKeyCheck['has_primary_key']) {
                logger()->info('[PgsqlTester] 主键约束不存在，正在创建...');
                $createPrimaryKeyResult = $this->testerService->createPrimaryKeyConstraint();

                if ($createPrimaryKeyResult['status'] === 'error') {
                    logger()->error('[PgsqlTester] 创建主键约束失败: ' . $createPrimaryKeyResult['error']);
                    return false;
                }

                logger()->info('[PgsqlTester] 主键约束创建成功');
            } else {
                logger()->info('[PgsqlTester] 主键约束已存在');
            }

            // 检查向量数据完整性
            logger()->info('[PgsqlTester] 检查中文搜索向量数据完整性...');
            $vectorCheck = $this->testerService->checkVectorDataIntegrity();

            if ($vectorCheck['status'] === 'error') {
                logger()->error('[PgsqlTester] 检查向量数据失败: ' . $vectorCheck['error']);
                return false;
            } elseif (!$vectorCheck['vectors_populated']) {
                logger()->info('[PgsqlTester] 向量数据不完整，正在更新...');
                $updateResult = $this->testerService->updateVectorData();

                if ($updateResult['status'] === 'error') {
                    logger()->error('[PgsqlTester] 更新向量数据失败: ' . $updateResult['error']);
                    return false;
                }

                logger()->info('[PgsqlTester] 向量数据更新成功，共处理 ' .
                    ($updateResult['integrity_check']['total_records'] ?? 0) . ' 条记录');
            } else {
                logger()->info('[PgsqlTester] 向量数据完整，共 ' .
                    $vectorCheck['total_records'] . ' 条记录，' .
                    $vectorCheck['vector_ratio'] . ' 的记录有向量数据');
            }

            // 最终完整性检查
            logger()->info('[PgsqlTester] 执行最终完整性检查...');
            $finalCheck = $this->testerService->checkZhparserSetupComplete();

            if (!$finalCheck['setup_complete']) {
                logger()->error('[PgsqlTester] zhparser 设置不完整，最终检查失败');
                return false;
            }

            logger()->info('[PgsqlTester] zhparser 中文搜索设置完整，所有组件正常');
            logger()->info('[PgsqlTester] 插件启用成功');
            return true;
        } catch (\Throwable $e) {
            logger()->error('[PgsqlTester] 插件启用异常: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 插件禁用时执行.
     */
    public function disable(): bool
    {
        logger()->info('[PgsqlTester] 插件禁用完成（无需处理数据库）');

        // 根据需求，插件关闭时不需要处理数据库
        // 这里可以添加一些清理逻辑，但不处理数据库表

        return true;
    }

    /**
     * 插件卸载时执行.
     */
    public function uninstall(): bool
    {
        logger()->info('[PgsqlTester] 开始执行插件卸载...');

        try {
            // 清理插件相关的数据库表（如果需要）
            // 注意：这里可能需要根据业务需求决定是否删除表

            logger()->info('[PgsqlTester] 插件卸载完成');
            return true;
        } catch (\Throwable $e) {
            logger()->error('[PgsqlTester] 插件卸载异常: ' . $e->getMessage());
            return false;
        }
    }

}
