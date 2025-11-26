/**
 * 站点设置表单组件
 * 处理站点设置页面的交互逻辑（上传配置、快速配置等）
 */
(function() {
    'use strict';

    // 记录当前选择的存储类型
    let currentStorageType = null;

    /**
     * 初始化站点表单
     */
    function initSiteForm() {
        // 自定义上传配置开关
        const useCustomUpload = document.getElementById('use_custom_upload');
        const s3ConfigArea = document.getElementById('s3ConfigArea');
        
        if (useCustomUpload && s3ConfigArea) {
            // 处理 required 属性的辅助函数
            const toggleS3FieldsRequired = (required) => {
                const s3Fields = ['s3_cdn']; // 只有 s3_cdn 是必填的
                s3Fields.forEach(fieldName => {
                    const field = document.querySelector(`[name="${fieldName}"]`);
                    if (field) {
                        if (required) {
                            field.setAttribute('required', 'required');
                        } else {
                            field.removeAttribute('required');
                        }
                    }
                });
            };
            
            // 初始化：根据开关状态设置 required 属性和显示状态
            const isChecked = useCustomUpload.checked || useCustomUpload.value === '1';
            s3ConfigArea.style.display = isChecked ? 'block' : 'none';
            toggleS3FieldsRequired(isChecked);
            
            // 监听开关变化
            useCustomUpload.addEventListener('change', function() {
                const checked = this.checked || this.value === '1';
                if (checked) {
                    s3ConfigArea.style.display = 'block';
                    toggleS3FieldsRequired(true);
                } else {
                    s3ConfigArea.style.display = 'none';
                    toggleS3FieldsRequired(false);
                }
            });
        }

        // 检测当前配置类型（页面加载时）
        const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
        if (s3EndpointInput && s3EndpointInput.value) {
            const endpoint = s3EndpointInput.value.trim();
            if (endpoint.includes('oss-') && endpoint.includes('.aliyuncs.com')) {
                currentStorageType = 'aliyun';
                showHelp('aliyunOssHelp');
            } else if (endpoint.includes('.r2.cloudflarestorage.com')) {
                currentStorageType = 'r2';
                showHelp('r2Help');
            } else if (endpoint.includes('.myqcloud.com') || endpoint.includes('qcloud.com')) {
                currentStorageType = 'qcloud';
                showHelp('qcloudCosHelp');
            } else if (endpoint.includes('.qiniucs.com') || endpoint.includes('qiniu')) {
                currentStorageType = 'qiniu';
                showHelp('qiniuHelp');
            } else if (endpoint.includes('localhost') || endpoint.includes('minio')) {
                currentStorageType = 'minio';
            } else if (!endpoint || endpoint.includes('amazonaws.com')) {
                currentStorageType = 'aws';
            }
        }

        // 监听 Region 字段变化，自动更新 Endpoint
        const s3RegionInput = document.querySelector('[name="s3_region"]');
        const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
        
        if (s3RegionInput && s3EndpointInput) {
            s3RegionInput.addEventListener('input', function() {
                const region = this.value.trim();
                
                // 阿里云 OSS：自动更新 Endpoint
                if (currentStorageType === 'aliyun' && region && /^cn-[a-z]+$/.test(region)) {
                    s3EndpointInput.value = `https://oss-${region}.aliyuncs.com`;
                }
                // 腾讯云 COS：自动更新 Endpoint
                else if (currentStorageType === 'qcloud' && region && /^ap-[a-z]+$/.test(region)) {
                    s3EndpointInput.value = `https://cos.${region}.myqcloud.com`;
                }
                // 七牛云：自动更新 Endpoint
                else if (currentStorageType === 'qiniu' && region && /^(cn|us|eu|ap|na|sa)-[a-z]+-\d+$/.test(region)) {
                    s3EndpointInput.value = `https://s3-${region}.qiniucs.com`;
                }
            });
        }
    }

    /**
     * 显示帮助信息
     */
    function showHelp(helpId) {
        const help = document.getElementById(helpId);
        if (help) {
            help.style.display = 'block';
        }
    }

    /**
     * 隐藏所有帮助信息
     */
    function hideAllHelp() {
        ['aliyunOssHelp', 'r2Help', 'qcloudCosHelp', 'qiniuHelp'].forEach(id => {
            const help = document.getElementById(id);
            if (help) {
                help.style.display = 'none';
            }
        });
    }

    /**
     * 更新帮助文本
     */
    function updateHelpText(inputName, helpText) {
        const input = document.querySelector(`[name="${inputName}"]`);
        if (input) {
            const formGroup = input.closest('.mb-3') || input.closest('.form-group');
            if (formGroup) {
                const helpElement = formGroup.querySelector('.form-text');
                if (helpElement) {
                    helpElement.textContent = helpText;
                }
            }
        }
    }

    /**
     * 快速配置 S3
     */
    function fillS3Config(type) {
        const s3KeyInput = document.querySelector('[name="s3_key"]');
        const s3SecretInput = document.querySelector('[name="s3_secret"]');
        const s3BucketInput = document.querySelector('[name="s3_bucket"]');
        const s3RegionInput = document.querySelector('[name="s3_region"]');
        const s3EndpointInput = document.querySelector('[name="s3_endpoint"]');
        const s3CdnInput = document.querySelector('[name="s3_cdn"]');
        const s3PathStyleCheckbox = document.getElementById('s3_path_style') || document.querySelector('[name="s3_path_style"]');
        const useCustomUpload = document.getElementById('use_custom_upload') || document.querySelector('[name="use_custom_upload"]');

        // 确保开关已开启
        if (useCustomUpload) {
            if (useCustomUpload.type === 'checkbox') {
                useCustomUpload.checked = true;
            } else {
                useCustomUpload.value = '1';
            }
            const s3ConfigArea = document.getElementById('s3ConfigArea');
            if (s3ConfigArea) {
                s3ConfigArea.style.display = 'block';
            }
            // 触发 change 事件
            useCustomUpload.dispatchEvent(new Event('change'));
        }

        // 隐藏所有帮助提示
        hideAllHelp();

        switch(type) {
            case 'aws':
                currentStorageType = 'aws';
                if (s3RegionInput) s3RegionInput.value = 'us-east-1';
                if (s3EndpointInput) s3EndpointInput.value = '';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = false;
                    } else {
                        s3PathStyleCheckbox.value = '0';
                    }
                }
                updateHelpText('s3_key', '在 AWS IAM 中创建 Access Key');
                updateHelpText('s3_secret', '与 Access Key ID 对应的 Secret Key');
                updateHelpText('s3_bucket', '在 AWS S3 控制台创建的存储桶名称');
                updateHelpText('s3_region', '存储桶区域，如 us-east-1、ap-southeast-1 等');
                updateHelpText('s3_endpoint', '通常留空，AWS 会自动识别');
                updateHelpText('s3_cdn', '必填：AWS S3 Bucket 的访问域名，格式：https://{bucket}.s3.{region}.amazonaws.com');
                break;

            case 'aliyun':
                currentStorageType = 'aliyun';
                if (s3RegionInput) s3RegionInput.value = 'cn-beijing';
                if (s3EndpointInput) s3EndpointInput.value = 'https://oss-cn-beijing.aliyuncs.com';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = true;
                    } else {
                        s3PathStyleCheckbox.value = '1';
                    }
                }
                updateHelpText('s3_key', '在 AccessKey 管理中创建，需开启 S3 兼容性');
                updateHelpText('s3_secret', '与 AccessKey ID 对应的 Secret');
                updateHelpText('s3_bucket', 'OSS 存储桶名称');
                updateHelpText('s3_region', '如 cn-beijing、cn-hangzhou、cn-shanghai 等');
                updateHelpText('s3_endpoint', '格式：https://oss-{region}.aliyuncs.com');
                updateHelpText('s3_cdn', '必填：OSS Bucket 的访问域名，格式：https://{bucket}.oss-{region}.aliyuncs.com');
                showHelp('aliyunOssHelp');
                break;

            case 'r2':
                currentStorageType = 'r2';
                if (s3RegionInput) s3RegionInput.value = 'auto';
                if (s3EndpointInput) s3EndpointInput.value = '';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = true;
                    } else {
                        s3PathStyleCheckbox.value = '1';
                    }
                }
                updateHelpText('s3_key', '在 R2 → Manage R2 API Tokens 创建');
                updateHelpText('s3_secret', 'R2 API Token Secret');
                updateHelpText('s3_bucket', 'R2 存储桶名称');
                updateHelpText('s3_region', '设置为 auto 或留空');
                updateHelpText('s3_endpoint', '格式：https://{account-id}.r2.cloudflarestorage.com');
                updateHelpText('s3_cdn', '必填：R2 Bucket 的公共访问域名，可在 R2 控制台查看或使用自定义域名');
                showHelp('r2Help');
                break;

            case 'qcloud':
                currentStorageType = 'qcloud';
                if (s3RegionInput) s3RegionInput.value = 'ap-beijing';
                if (s3EndpointInput) s3EndpointInput.value = 'https://cos.ap-beijing.myqcloud.com';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = true;
                    } else {
                        s3PathStyleCheckbox.value = '1';
                    }
                }
                updateHelpText('s3_key', '在访问管理 → API 密钥管理中创建');
                updateHelpText('s3_secret', '与 SecretId 对应的 SecretKey');
                updateHelpText('s3_bucket', 'COS 存储桶名称');
                updateHelpText('s3_region', '如 ap-beijing、ap-shanghai、ap-guangzhou 等');
                updateHelpText('s3_endpoint', '格式：https://cos.{region}.myqcloud.com');
                updateHelpText('s3_cdn', '必填：COS Bucket 的访问域名，格式：https://{bucket}.cos.{region}.myqcloud.com');
                showHelp('qcloudCosHelp');
                break;

            case 'qiniu':
                currentStorageType = 'qiniu';
                if (s3RegionInput) s3RegionInput.value = 'cn-east-1';
                if (s3EndpointInput) s3EndpointInput.value = 'https://s3-cn-east-1.qiniucs.com';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = true;
                    } else {
                        s3PathStyleCheckbox.value = '1';
                    }
                }
                updateHelpText('s3_key', '在密钥管理 → AccessKey 中创建');
                updateHelpText('s3_secret', '与 AccessKey 对应的 SecretKey');
                updateHelpText('s3_bucket', '七牛云存储空间名称');
                updateHelpText('s3_region', '如 cn-east-1、cn-north-1、cn-south-1 等');
                updateHelpText('s3_endpoint', '格式：https://s3-{region}.qiniucs.com');
                updateHelpText('s3_cdn', '必填：七牛云存储空间的访问域名（测试域名或自定义域名）');
                showHelp('qiniuHelp');
                break;

            case 'minio':
                currentStorageType = 'minio';
                if (s3RegionInput) s3RegionInput.value = 'us-east-1';
                if (s3EndpointInput) s3EndpointInput.value = 'http://localhost:9000';
                if (s3PathStyleCheckbox) {
                    if (s3PathStyleCheckbox.type === 'checkbox') {
                        s3PathStyleCheckbox.checked = true;
                    } else {
                        s3PathStyleCheckbox.value = '1';
                    }
                }
                updateHelpText('s3_key', 'MinIO 访问密钥');
                updateHelpText('s3_secret', 'MinIO 密钥');
                updateHelpText('s3_bucket', 'MinIO 存储桶名称');
                updateHelpText('s3_region', '通常使用 us-east-1');
                updateHelpText('s3_endpoint', 'MinIO 服务地址，如 http://localhost:9000');
                updateHelpText('s3_cdn', '必填：MinIO 的公网访问地址（如果使用域名，填写域名，否则填写 Endpoint）');
                break;
        }

        // 聚焦到第一个输入框
        if (s3KeyInput) {
            s3KeyInput.focus();
        }
    }

    /**
     * 处理表单提交前的数据转换
     */
    function prepareSubmitData(formData) {
        const jsonData = {};
        for (const [key, value] of formData.entries()) {
            // 跳过 _method 和 _token
            if (key === '_method' || key === '_token') {
                continue;
            }

            // 跳过保护字段：domain 和 admin_entry_path（这些字段是 disabled，不应提交）
            if (key === 'domain' || key === 'admin_entry_path') {
                continue;
            }

            jsonData[key] = value;
        }

        // 处理上传配置
        const useCustomUpload = document.getElementById('use_custom_upload') || document.querySelector('[name="use_custom_upload"]');
        const isUseCustomUpload = useCustomUpload && (
            (useCustomUpload.type === 'checkbox' && useCustomUpload.checked) ||
            (useCustomUpload.value === '1')
        );
        
        if (isUseCustomUpload) {
            // 使用自定义上传配置，收集 S3 配置
            const s3Key = document.querySelector('[name="s3_key"]')?.value || '';
            const s3Secret = document.querySelector('[name="s3_secret"]')?.value || '';
            const s3Bucket = document.querySelector('[name="s3_bucket"]')?.value || '';
            const s3Region = document.querySelector('[name="s3_region"]')?.value || '';
            const s3Endpoint = document.querySelector('[name="s3_endpoint"]')?.value || '';
            const s3Cdn = document.querySelector('[name="s3_cdn"]')?.value?.trim() || '';
            const s3PathStyleCheckbox = document.getElementById('s3_path_style') || document.querySelector('[name="s3_path_style"]');
            const s3PathStyle = s3PathStyleCheckbox && (
                (s3PathStyleCheckbox.type === 'checkbox' && s3PathStyleCheckbox.checked) ||
                (s3PathStyleCheckbox.value === '1')
            );

            // 验证必填字段
            if (!s3Cdn) {
                throw new Error('请填写 CDN 域名，这是必填项，用于访问图片');
            }

            // 如果 Secret 为空，则从现有配置中获取（不更新密码）
            let finalSecret = s3Secret;
            if (!finalSecret) {
                const existingSecretInput = document.querySelector('[name="existing_s3_secret"]');
                if (existingSecretInput) {
                    finalSecret = existingSecretInput.value;
                }
            }

            // 构建 upload_config
            const uploadConfig = {
                s3: {
                    key: s3Key,
                    secret: finalSecret,
                    bucket: s3Bucket,
                    region: s3Region,
                    endpoint: s3Endpoint || null,
                    cdn: s3Cdn,
                    use_path_style_endpoint: s3PathStyle,
                }
            };

            jsonData['upload_driver'] = 's3';
            jsonData['upload_config'] = uploadConfig;
        } else {
            // 不使用自定义配置，设为 null（使用系统默认）
            jsonData['upload_driver'] = null;
            jsonData['upload_config'] = null;
        }

        // 移除临时字段（不在表单中提交）
        delete jsonData['s3_key'];
        delete jsonData['s3_secret'];
        delete jsonData['s3_bucket'];
        delete jsonData['s3_region'];
        delete jsonData['s3_endpoint'];
        delete jsonData['s3_cdn'];
        delete jsonData['existing_s3_secret'];
        delete jsonData['use_custom_upload'];
        delete jsonData['s3_path_style'];

        return jsonData;
    }

    // 导出到全局
    window.SiteForm = {
        init: initSiteForm,
        fillS3Config: fillS3Config,
        prepareSubmitData: prepareSubmitData
    };
})();


