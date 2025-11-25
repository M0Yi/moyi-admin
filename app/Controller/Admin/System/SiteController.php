<?php

declare(strict_types=1);

namespace App\Controller\Admin\System;

use App\Controller\AbstractController;
use App\Exception\BusinessException;
use App\Exception\ValidationException;
use App\Model\Admin\AdminSite;
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

        return $this->renderAdmin('admin.system.site.edit', [
            'site' => $site,
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

        // 验证规则
        $rules = [
            'name' => 'required|string|max:100',
            'title' => 'nullable|string|max:200',
            'slogan' => 'nullable|string|max:200',
            'logo' => 'nullable|string|max:255',
            'favicon' => 'nullable|string|max:255',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
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
        ];

        // 验证数据
        $validator = $this->validatorFactory->make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator->errors()->first());
        }

        // 处理上传配置
        if (isset($data['upload_driver']) && $data['upload_driver'] === 's3') {
            // 验证 S3 配置
            if (empty($data['upload_config']['s3']['cdn'])) {
                throw new BusinessException(400, '使用 S3 上传时，CDN 域名是必填项');
            }

            // 如果 Secret 为空，保留原有值
            if (empty($data['upload_config']['s3']['secret']) && isset($data['existing_s3_secret'])) {
                $data['upload_config']['s3']['secret'] = $data['existing_s3_secret'];
            }
        } else {
            // 不使用自定义上传配置
            $data['upload_driver'] = null;
            $data['upload_config'] = null;
        }

        // 移除不需要的字段
        unset($data['existing_s3_secret']);

        // 更新站点
        $site->fill($data);
        $site->save();

        return $this->success(null, '保存成功');
    }
}

