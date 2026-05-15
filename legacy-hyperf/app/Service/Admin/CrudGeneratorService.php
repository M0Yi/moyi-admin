<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminCrudConfig;
use Hyperf\DbConnection\Db;

/**
 * CRUD 生成器服务
 * 用于生成 Model、Controller、Views、Routes 等代码
 */
class CrudGeneratorService
{
    public function __construct(
        protected DatabaseService $databaseService
    ) {
    }

    /**
     * CRUD 生成器功能已移除
     * 原有的自动代码生成功能已停用，建议使用 UniversalCrudService
     *
     * @param AdminCrudConfig $config
     * @return array 返回空数组
     * @deprecated 此方法已废弃，请使用 UniversalCrudService
     */
    public function generate(AdminCrudConfig $config): array
    {
        return [];
    }
}

