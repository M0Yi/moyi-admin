<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Exception\ValidationException;
use App\Model\Admin\AdminSite;
use App\Service\Admin\SiteService;
use Hyperf\Database\Exception\QueryException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 站点设置控制器
 */
class SiteController extends AbstractController
{
    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    #[Inject]
    protected SiteService $siteService;

    /**
     * 编辑页面
     */
    public function edit(): ResponseInterface
    {
        // 获取当前站点
        $site = site();
        
        if (!$site) {
            throw new BusinessException(500, '站点不存在');
        }

        // 获取表单字段配置
        $fields = $this->siteService->getFormFields('edit', $site);
        
        // 构建表单 Schema
        $formSchema = [
            'title' => '站点设置',
            'fields' => $fields,
            'submitUrl' => admin_route('system/sites'),
            'method' => 'PUT',
            'redirectUrl' => admin_route('system/sites'),
            'endpoints' => [
                'uploadToken' => admin_route('api/admin/upload/token'),
            ],
        ];

        // 将 Schema 转换为 JSON（用于前端渲染）
        $formSchemaJson = json_encode($formSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->renderAdmin('admin.system.site.edit', [
            'site' => $site,
            'formSchemaJson' => $formSchemaJson,
        ]);
    }

    /**
     * 更新站点设置
     */
    public function update(RequestInterface $request): ResponseInterface
    {
        $site = site();
        
        if (!$site) {
            throw new BusinessException(500, '站点不存在');
        }

        $data = $request->all();

        // 处理主题配置：将 theme_ 前缀的字段转换为 config.theme JSON 结构
        $themeConfig = [];
        $themeFields = [
            'primary_color',
            'secondary_color',
            'primary_gradient',
            'primary_hover',
            'success_color',
            'warning_color',
            'danger_color',
            'info_color',
            'light_color',
            'dark_color',
            'border_color',
        ];

        foreach ($themeFields as $field) {
            $themeKey = 'theme_' . $field;
            if (isset($data[$themeKey])) {
                $themeConfig[$field] = $data[$themeKey];
                unset($data[$themeKey]); // 移除原始字段
            }
        }

        // 处理上传格式配置：直接保存到独立字段
        // upload_allowed_mime_types 和 upload_allowed_extensions 字段直接保存，不需要转换
        // S3 配置的处理将在验证后统一进行（见第 244-290 行）

        // 处理 AI 配置：将 ai_ 前缀的字段转换为 config.ai JSON 结构
        $aiConfig = [];
        $aiFields = [
            'token',
            'text_model',
            'image_model',
            'video_model',
            'provider',
        ];

        foreach ($aiFields as $field) {
            $aiKey = 'ai_' . $field;
            if (isset($data[$aiKey])) {
                $value = $data[$aiKey];
                $aiConfig[$field] = $value;
                unset($data[$aiKey]); // 移除原始字段
            }
        }

        // 根据提供商自动设置 base_url（次要非必填）
        // 如果没有设置提供商，使用默认值（智谱AI）
        if (empty($aiConfig['provider'])) {
            $aiConfig['provider'] = 'zhipu';
        }
        
        // 根据提供商自动设置 base_url（如果用户没有自定义 base_url）
        $baseUrlMap = [
            'zhipu' => 'https://open.bigmodel.cn/api/paas/v4',
        ];
        
        // 如果已有 base_url 且不为空，保留原值；否则根据提供商自动设置
        $existingConfig = $site->config['ai'] ?? [];
        if (!empty($existingConfig['base_url'])) {
            $aiConfig['base_url'] = $existingConfig['base_url'];
        } elseif (isset($baseUrlMap[$aiConfig['provider']])) {
            $aiConfig['base_url'] = $baseUrlMap[$aiConfig['provider']];
        }

        // 合并配置到 config JSON 字段
        $config = $site->config ?? [];
        if (!empty($themeConfig)) {
            $config['theme'] = $themeConfig;
        }
        if (!empty($aiConfig)) {
            $config['ai'] = $aiConfig;
        }
        if (!empty($config)) {
            $data['config'] = $config;
        }

        // 验证规则
        $rules = [
            'name' => 'required|string|max:100',
            'title' => 'nullable|string|max:200',
            'slogan' => 'nullable|string|max:200',
            'logo' => 'nullable|string|max:255',
            'favicon' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'keywords' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:100',
            'contact_phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'icp_number' => 'nullable|string|max:100',
            'analytics_code' => 'nullable|string',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'resource_cdn' => 'nullable|string|max:255',
            'upload_driver' => 'nullable|string|in:local,s3',
            'upload_config' => 'nullable|array',
            's3_key' => 'nullable|string|max:255',
            's3_secret' => 'nullable|string|max:255',
            's3_bucket' => 'nullable|string|max:255',
            's3_region' => 'nullable|string|max:100',
            's3_endpoint' => 'nullable|string|max:255',
            's3_cdn' => 'nullable|string|max:255',
            's3_path_style' => 'nullable|integer|in:0,1',
            'upload_allowed_mime_types' => 'nullable|string',
            'upload_allowed_extensions' => 'nullable|string',
            'config' => 'nullable|array',
            'use_custom_upload' => 'nullable|string',
        ];

        // 验证数据
        $validator = $this->validatorFactory->make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        // 处理上传配置
        // 判断是否使用自定义上传配置：检查 upload_driver 或独立字段
        $useCustomUpload = isset($data['upload_driver']) && $data['upload_driver'] === 's3';
        $hasS3Fields = !empty($data['s3_key']) || !empty($data['s3_bucket']) || !empty($data['s3_cdn']);
        
        if ($useCustomUpload || $hasS3Fields) {
            // 使用自定义上传配置，验证必填字段
            $s3Cdn = $data['s3_cdn'] ?? $data['upload_config']['s3']['cdn'] ?? '';
            if (empty($s3Cdn)) {
                throw new BusinessException(400, '使用 S3 上传时，CDN 域名是必填项');
            }
            
            // 确保 upload_driver 设置为 s3
            $data['upload_driver'] = 's3';
            
            // 处理独立字段：如果为空，尝试从 upload_config 读取（向后兼容）
            if (empty($data['s3_key']) && !empty($data['upload_config']['s3']['key'])) {
                $data['s3_key'] = $data['upload_config']['s3']['key'];
            }
            if (empty($data['s3_secret']) && !empty($data['upload_config']['s3']['secret'])) {
                $data['s3_secret'] = $data['upload_config']['s3']['secret'];
            }
            if (empty($data['s3_bucket']) && !empty($data['upload_config']['s3']['bucket'])) {
                $data['s3_bucket'] = $data['upload_config']['s3']['bucket'];
            }
            if (empty($data['s3_region']) && !empty($data['upload_config']['s3']['region'])) {
                $data['s3_region'] = $data['upload_config']['s3']['region'];
            }
            if (empty($data['s3_endpoint']) && !empty($data['upload_config']['s3']['endpoint'])) {
                $data['s3_endpoint'] = $data['upload_config']['s3']['endpoint'];
            }
            if (empty($data['s3_cdn']) && !empty($data['upload_config']['s3']['cdn'])) {
                $data['s3_cdn'] = $data['upload_config']['s3']['cdn'];
            }
            if (!isset($data['s3_path_style']) || $data['s3_path_style'] === '') {
                if (isset($data['upload_config']['s3']['use_path_style_endpoint'])) {
                    $data['s3_path_style'] = $data['upload_config']['s3']['use_path_style_endpoint'] ? 1 : 0;
                } else {
                    $data['s3_path_style'] = 0;
                }
            }
            
            // 构建 upload_config（向后兼容）
            $s3Config = [
                'key' => $data['s3_key'] ?? '',
                'secret' => $data['s3_secret'] ?? '',
                'bucket' => $data['s3_bucket'] ?? '',
                'bucket_name' => $data['s3_bucket'] ?? '',
                'region' => $data['s3_region'] ?? '',
                'endpoint' => $data['s3_endpoint'] ?? null,
                'cdn' => $data['s3_cdn'] ?? '',
                'use_path_style_endpoint' => (bool)($data['s3_path_style'] ?? 0),
                'credentials' => [
                    'key' => $data['s3_key'] ?? '',
                    'secret' => $data['s3_secret'] ?? '',
                ]
            ];
            $uploadConfig = $site->upload_config ?? [];
            $uploadConfig['s3'] = $s3Config;
            $data['upload_config'] = $uploadConfig;
        } else {
            // 不使用自定义上传配置，清空所有上传相关字段
            $data['upload_driver'] = null;
            $data['upload_config'] = null;
            $data['s3_key'] = null;
            $data['s3_secret'] = null;
            $data['s3_bucket'] = null;
            $data['s3_region'] = null;
            $data['s3_endpoint'] = null;
            $data['s3_cdn'] = null;
            $data['s3_path_style'] = 0;
        }

        // 移除不需要的字段
        unset($data['existing_ai_token']);

        // 更新站点
        $site->fill($data);
        $site->save();

        return $this->success(null, '保存成功');
    }

    /**
     * 获取站点选择组件的选项列表
     */
    public function options(RequestInterface $request): ResponseInterface
    {
        if (! is_super_admin()) {
            throw new BusinessException(403, '仅超级管理员可操作');
        }

        $keyword = trim((string) $request->query('keyword', ''));
        $options = $this->siteService->getSiteSelectorOptions($keyword ?: null);

        return $this->success([
            'options' => $options,
            'current_site_id' => site_id(),
        ]);
    }
}

