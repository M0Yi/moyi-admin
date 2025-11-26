<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Model\Admin\AdminSite;

/**
 * 站点管理服务
 */
class SiteService
{
    /**
     * 获取表单字段配置
     *
     * @param string $scene 场景：create|edit
     * @param AdminSite|null $site 站点对象（编辑时传入）
     * @return array
     */
    public function getFormFields(string $scene = 'edit', ?AdminSite $site = null): array
    {
        $uploadConfig = $site?->upload_config ?? [];
        $s3Config = $uploadConfig['s3'] ?? [];

        $fields = [
            // 基本信息分组
            [
                'name' => 'domain',
                'label' => '域名',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：example.com',
                'disabled' => true,
                'help' => '域名创建后不可修改，如需修改请联系管理员',
                'default' => $site?->domain ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],
            [
                'name' => 'admin_entry_path',
                'label' => '后台入口路径',
                'type' => 'text',
                'required' => true,
                'placeholder' => '例如：admin',
                'disabled' => true,
                'help' => '后台入口路径创建后不可修改，如需修改请联系管理员',
                'default' => $site?->admin_entry_path ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],
            [
                'name' => 'name',
                'label' => '站点名称',
                'type' => 'text',
                'required' => true,
                'placeholder' => '请输入站点名称',
                'default' => $site?->name ?? '',
                'col' => 'col-12',
                'group' => '基本信息',
            ],
            [
                'name' => 'title',
                'label' => '站点标题',
                'type' => 'text',
                'required' => false,
                'placeholder' => '站点标题',
                'default' => $site?->title ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],
            [
                'name' => 'slogan',
                'label' => '站点口号',
                'type' => 'text',
                'required' => false,
                'placeholder' => '站点口号',
                'default' => $site?->slogan ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '基本信息',
            ],

            // 外观设置分组
            [
                'name' => 'logo',
                'label' => 'Logo',
                'type' => 'image',
                'required' => false,
                'placeholder' => '上传Logo图片',
                'default' => $site?->logo ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '外观设置',
            ],
            [
                'name' => 'favicon',
                'label' => 'Favicon',
                'type' => 'image',
                'required' => false,
                'placeholder' => '上传Favicon图标',
                'help' => '推荐使用 PNG 格式',
                'default' => $site?->favicon ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '外观设置',
            ],
            [
                'name' => 'primary_color',
                'label' => '主题色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#667eea',
                'help' => '十六进制颜色值',
                'default' => $site?->primary_color ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '外观设置',
            ],
            [
                'name' => 'secondary_color',
                'label' => '辅助色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#764ba2',
                'help' => '十六进制颜色值',
                'default' => $site?->secondary_color ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '外观设置',
            ],

            // SEO设置分组
            [
                'name' => 'description',
                'label' => '站点描述',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '站点描述（用于SEO）',
                'rows' => 4,
                'default' => $site?->description ?? '',
                'col' => 'col-12',
                'group' => 'SEO设置',
            ],
            [
                'name' => 'keywords',
                'label' => 'SEO关键词',
                'type' => 'text',
                'required' => false,
                'placeholder' => '关键词，多个关键词用逗号分隔',
                'help' => '多个关键词用逗号分隔',
                'default' => $site?->keywords ?? '',
                'col' => 'col-12',
                'group' => 'SEO设置',
            ],

            // 联系信息分组
            [
                'name' => 'contact_email',
                'label' => '联系邮箱',
                'type' => 'email',
                'required' => false,
                'placeholder' => '联系邮箱',
                'default' => $site?->contact_email ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '联系信息',
            ],
            [
                'name' => 'contact_phone',
                'label' => '联系电话',
                'type' => 'text',
                'required' => false,
                'placeholder' => '联系电话',
                'default' => $site?->contact_phone ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '联系信息',
            ],
            [
                'name' => 'address',
                'label' => '地址',
                'type' => 'text',
                'required' => false,
                'placeholder' => '地址',
                'default' => $site?->address ?? '',
                'col' => 'col-12',
                'group' => '联系信息',
            ],
            [
                'name' => 'icp_number',
                'label' => 'ICP备案号',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'ICP备案号',
                'default' => $site?->icp_number ?? '',
                'col' => 'col-12',
                'group' => '联系信息',
            ],

            // 自定义代码分组
            [
                'name' => 'analytics_code',
                'label' => '统计代码',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => 'Google Analytics、百度统计等代码',
                'help' => '支持HTML代码，如Google Analytics、百度统计等',
                'rows' => 6,
                'default' => $site?->analytics_code ?? '',
                'col' => 'col-12',
                'group' => '自定义代码',
            ],
            [
                'name' => 'custom_css',
                'label' => '自定义CSS',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '自定义CSS样式代码',
                'rows' => 6,
                'default' => $site?->custom_css ?? '',
                'col' => 'col-12',
                'group' => '自定义代码',
            ],
            [
                'name' => 'custom_js',
                'label' => '自定义JavaScript',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '自定义JavaScript代码',
                'rows' => 6,
                'default' => $site?->custom_js ?? '',
                'col' => 'col-12',
                'group' => '自定义代码',
            ],

            // 上传配置分组（这些字段需要特殊处理，在 JS 中处理）
            [
                'name' => 'use_custom_upload',
                'label' => '使用自定义上传配置',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => ($site?->upload_driver !== null || $site?->upload_config !== null) ? '1' : '0',
                'col' => 'col-12',
                'group' => '上传配置',
                'help' => '开启后将使用您配置的对象存储，而非系统默认配置',
            ],
            [
                'name' => 's3_key',
                'label' => 'Access Key ID',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入 Access Key ID',
                'help' => '在对应云服务商的控制台中创建 AccessKey',
                'default' => $s3Config['key'] ?? $s3Config['credentials']['key'] ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_secret',
                'label' => 'Secret Access Key',
                'type' => 'password',
                'required' => false,
                'placeholder' => '请输入 Secret Access Key',
                'help' => '与 Access Key ID 对应的密钥（留空则保留原值）',
                'default' => '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_bucket',
                'label' => 'Bucket 名称',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入存储桶名称',
                'help' => '在云服务商控制台创建的存储桶名称',
                'default' => $s3Config['bucket'] ?? $s3Config['bucket_name'] ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_region',
                'label' => 'Region（区域）',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入区域标识',
                'help' => '存储桶所在的区域',
                'default' => $s3Config['region'] ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_endpoint',
                'label' => 'Endpoint（可选）',
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入访问端点地址',
                'help' => 'API 访问端点，通常根据 Region 自动生成',
                'default' => $s3Config['endpoint'] ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_cdn',
                'label' => 'CDN 域名',
                'type' => 'text',
                'required' => false,
                'placeholder' => '例如：https://cdn.example.com',
                'help' => '必填：用于访问图片的 CDN 域名或对象存储的访问域名（如 OSS 的 Bucket 域名）',
                'default' => $s3Config['cdn'] ?? '',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
            ],
            [
                'name' => 's3_path_style',
                'label' => '使用路径样式端点（Path Style）',
                'type' => 'switch',
                'required' => false,
                'onValue' => '1',
                'offValue' => '0',
                'default' => ($s3Config['use_path_style_endpoint'] ?? false) ? '1' : '0',
                'col' => 'col-12 col-md-6',
                'group' => '上传配置',
                'conditional' => 'use_custom_upload',
                'help' => 'AWS：通常关闭；阿里云 OSS：建议开启；R2：建议开启；腾讯云 COS：建议开启；MinIO：必须开启',
            ],
        ];

        // 保存原始 Secret 值（用于当用户清空字段时保留原值）
        if (isset($s3Config['secret']) || isset($s3Config['credentials']['secret'])) {
            $fields[] = [
                'name' => 'existing_s3_secret',
                'label' => '',
                'type' => 'hidden',
                'required' => false,
                'default' => $s3Config['secret'] ?? $s3Config['credentials']['secret'] ?? '',
                'col' => 'col-12',
                'group' => '上传配置',
            ];
        }

        return $fields;
    }
}


