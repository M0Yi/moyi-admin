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
            // 主题颜色配置（存储在 config.theme JSON 中）
            // 注意：字段名使用 theme_ 前缀，保存时会转换为 config.theme 结构
            [
                'name' => 'theme_primary_color',
                'label' => '主题色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#667eea',
                'help' => '主色（品牌色）',
                'default' => $site?->getThemeColor('primary_color', '#6366f1'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_secondary_color',
                'label' => '辅助色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#764ba2',
                'help' => '辅助色',
                'default' => $site?->getThemeColor('secondary_color', '#8b5cf6'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_primary_gradient',
                'label' => '主渐变色',
                'type' => 'gradient',
                'required' => false,
                'placeholder' => '例如：linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'help' => '点击"选择渐变"按钮可视化配置渐变色',
                'default' => $site?->getThemeColor('primary_gradient', 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)'),
                'col' => 'col-12',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_primary_hover',
                'label' => '主色悬停',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#764ba2',
                'help' => '鼠标悬停时的主色',
                'default' => $site?->getThemeColor('primary_hover', '#764ba2'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_success_color',
                'label' => '成功色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#10b981',
                'help' => '成功状态的颜色',
                'default' => $site?->getThemeColor('success_color', '#10b981'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_warning_color',
                'label' => '警告色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#f59e0b',
                'help' => '警告状态的颜色',
                'default' => $site?->getThemeColor('warning_color', '#f59e0b'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_danger_color',
                'label' => '危险色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#ef4444',
                'help' => '危险/错误状态的颜色',
                'default' => $site?->getThemeColor('danger_color', '#ef4444'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_info_color',
                'label' => '信息色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#3b82f6',
                'help' => '信息提示的颜色',
                'default' => $site?->getThemeColor('info_color', '#3b82f6'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_light_color',
                'label' => '浅色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#f8f9fa',
                'help' => '浅色背景',
                'default' => $site?->getThemeColor('light_color', '#f8f9fa'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_dark_color',
                'label' => '深色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#1f2937',
                'help' => '深色文字/背景',
                'default' => $site?->getThemeColor('dark_color', '#1f2937'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
            ],
            [
                'name' => 'theme_border_color',
                'label' => '边框色',
                'type' => 'color',
                'required' => false,
                'placeholder' => '例如：#e5e7eb',
                'help' => '边框和分割线的颜色',
                'default' => $site?->getThemeColor('border_color', '#e5e7eb'),
                'col' => 'col-12 col-md-6',
                'group' => '主题配置',
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
                'type' => 'text',
                'required' => false,
                'placeholder' => '请输入 Secret Access Key',
                'help' => '与 Access Key ID 对应的密钥',
                'default' => $s3Config['secret'] ?? $s3Config['credentials']['secret'] ?? '',
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
            
            // 文件上传格式配置
            [
                'name' => 'upload_allowed_mime_types',
                'label' => '允许的 MIME 类型',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '例如：image/jpeg,image/png,video/mp4,application/pdf',
                'help' => '允许上传的文件 MIME 类型，多个用逗号分隔。留空则允许所有类型。',
                'rows' => 4,
                'default' => $site?->upload_allowed_mime_types ?? $this->getDefaultAllowedMimeTypes($site),
                'col' => 'col-12',
                'group' => '上传配置',
            ],
            [
                'name' => 'upload_allowed_extensions',
                'label' => '允许的文件扩展名',
                'type' => 'textarea',
                'required' => false,
                'placeholder' => '例如：jpg,jpeg,png,gif,mp4,pdf,doc,docx',
                'help' => '允许上传的文件扩展名（不含点号），多个用逗号分隔。留空则允许所有扩展名。',
                'rows' => 3,
                'default' => $site?->upload_allowed_extensions ?? $this->getDefaultAllowedExtensions($site),
                'col' => 'col-12',
                'group' => '上传配置',
            ],
        ];


        // AI 配置分组（放在最下面，可折叠且默认折叠）
        $aiConfig = $site?->getAiConfig() ?? [];
        $hasAiConfig = !empty(array_filter($aiConfig, fn($value) => !empty($value)));
        
        $fields[] = [
            'name' => 'ai_token',
            'label' => 'Token',
            'type' => 'text',
            'required' => false,
            'placeholder' => '请输入 AI API Token',
            'help' => 'AI 服务提供商的 API Token',
            'default' => $site?->getAiConfigValue('token', ''),
            'col' => 'col-12 col-md-6',
            'group' => 'AI 配置',
        ];
        $fields[] = [
            'name' => 'ai_provider',
            'label' => '模型提供商',
            'type' => 'select',
            'required' => true,
            'placeholder' => '请选择模型提供商',
            'help' => '选择使用的 AI 模型提供商',
            'options' => [
                ['value' => 'zhipu', 'label' => '智谱AI'],
            ],
            'default' => $site?->getAiConfigValue('provider', 'zhipu'),
            'col' => 'col-12 col-md-6',
            'group' => 'AI 配置',
        ];
        $fields[] = [
            'name' => 'ai_text_model',
            'label' => '文本模型',
            'type' => 'text',
            'required' => false,
            'placeholder' => '例如：glm-z1-flash, glm-4-flash',
            'help' => '用于文本生成的模型名称',
            'default' => $site?->getAiConfigValue('text_model', 'glm-z1-flash'),
            'col' => 'col-12 col-md-6',
            'group' => 'AI 配置',
        ];
        $fields[] = [
            'name' => 'ai_image_model',
            'label' => '图像模型',
            'type' => 'text',
            'required' => false,
            'placeholder' => '例如：cogview-3-flash',
            'help' => '用于图像生成的模型名称',
            'default' => $site?->getAiConfigValue('image_model', 'cogview-3-flash'),
            'col' => 'col-12 col-md-6',
            'group' => 'AI 配置',
        ];
        $fields[] = [
            'name' => 'ai_video_model',
            'label' => '视频模型',
            'type' => 'text',
            'required' => false,
            'placeholder' => '例如：cogvideox-flash',
            'help' => '用于视频生成的模型名称',
            'default' => $site?->getAiConfigValue('video_model', 'cogvideox-flash'),
            'col' => 'col-12 col-md-6',
            'group' => 'AI 配置',
        ];

        return $fields;
    }

    /**
     * 获取站点选择组件可用的选项
     */
    public function getSiteSelectorOptions(?string $keyword = null): array
    {
        $query = AdminSite::query()
            ->select(['id', 'name', 'title', 'domain', 'status', 'admin_entry_path', 'slogan', 'created_at'])
            ->orderBy('sort', 'asc')
            ->orderBy('id', 'asc');

        if ($keyword) {
            $query->where(function ($sub) use ($keyword) {
                $like = '%' . $keyword . '%';
                $sub->where('name', 'like', $like)
                    ->orWhere('domain', 'like', $like)
                    ->orWhere('title', 'like', $like);
            });
        }

        $currentSiteId = site_id();
        $statusMap = AdminSite::getStatuses();

        return $query->get()->map(static function (AdminSite $site) use ($currentSiteId, $statusMap) {
            $id = (int) $site->id;

            return [
                'value' => (string) $id,
                'label' => $site->name ?: ('站点 #' . $id),
                'name' => $site->name,
                'title' => $site->title,
                'domain' => $site->domain,
                'status' => (int) $site->status,
                'status_text' => $statusMap[$site->status] ?? '未知',
                'entry_path' => $site->admin_entry_path,
                'slogan' => $site->slogan,
                'created_at' => $site->created_at?->toDateTimeString(),
                'is_current' => $currentSiteId !== null && $id === $currentSiteId,
            ];
        })->toArray();
    }

    /**
     * 获取默认允许的 MIME 类型
     */
    private function getDefaultAllowedMimeTypes(?AdminSite $site): string
    {
        $uploadFormats = $site?->getUploadFormatsConfig() ?? [];
        if (!empty($uploadFormats['mime_types'])) {
            if (is_array($uploadFormats['mime_types'])) {
                return implode(',', $uploadFormats['mime_types']);
            }
            return $uploadFormats['mime_types'];
        }
        
        // 返回默认的常用格式
        return implode(',', $this->getDefaultMimeTypes());
    }

    /**
     * 获取默认允许的文件扩展名
     */
    private function getDefaultAllowedExtensions(?AdminSite $site): string
    {
        $uploadFormats = $site?->getUploadFormatsConfig() ?? [];
        if (!empty($uploadFormats['extensions'])) {
            if (is_array($uploadFormats['extensions'])) {
                return implode(',', $uploadFormats['extensions']);
            }
            return $uploadFormats['extensions'];
        }
        
        // 返回默认的常用扩展名
        return implode(',', $this->getDefaultExtensions());
    }

    /**
     * 获取默认的 MIME 类型列表
     */
    private function getDefaultMimeTypes(): array
    {
        return [
            // 图片格式
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
            'image/svg+xml',
            // 视频格式
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-ms-wmv',
            'video/webm',
            'video/x-flv',
            // 音频格式
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/x-wav',
            'audio/ogg',
            'audio/webm',
            // 办公文档
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // .docx
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation', // .pptx
            'application/vnd.oasis.opendocument.text', // .odt
            'application/vnd.oasis.opendocument.spreadsheet', // .ods
            'application/vnd.oasis.opendocument.presentation', // .odp
            // 文本格式
            'text/plain',
            'text/csv',
            'text/html',
            'text/css',
            'text/javascript',
            'application/json',
            'application/xml',
            'text/xml',
            // 压缩文件
            'application/zip',
            'application/x-zip-compressed',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
        ];
    }

    /**
     * 获取默认的文件扩展名列表
     */
    private function getDefaultExtensions(): array
    {
        return [
            // 图片
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
            // 视频
            'mp4', 'mpeg', 'mpg', 'mov', 'avi', 'wmv', 'webm', 'flv',
            // 音频
            'mp3', 'wav', 'ogg', 'm4a', 'aac',
            // 办公文档
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'odt', 'ods', 'odp',
            // 文本
            'txt', 'csv', 'html', 'css', 'js', 'json', 'xml',
            // 压缩文件
            'zip', 'rar', '7z', 'tar', 'gz',
        ];
    }
}


