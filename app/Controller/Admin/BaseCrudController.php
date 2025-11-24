<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\AbstractController;
use App\Exception\BusinessException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * 基础 CRUD 控制器
 * 
 * 提供通用的 CRUD 操作方法，供自定义控制器继承
 * 子类可以重写这些方法以实现自定义逻辑
 */
abstract class BaseCrudController extends AbstractController
{
    /**
     * 获取 Service 实例
     * 子类必须实现此方法，返回对应的 Service
     * 
     * @return object Service 实例
     */
    abstract protected function getService(): object;

    /**
     * 切换字段值（通用方法）
     * 
     * 通过 field 参数指定要切换的字段，默认切换 status 字段
     * 支持切换任意布尔类型字段（如 status、visible 等）
     * 
     * @param RequestInterface $request
     * @param int $id 记录ID
     * @return ResponseInterface
     */
    public function toggleStatus(RequestInterface $request, int $id): ResponseInterface
    {
        try {
            // 获取要切换的字段名，默认为 status
            $field = $request->input('field', 'status');
            
            // 验证字段名
            if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $field)) {
                return $this->error('无效的字段名', [], 400);
            }
            
            // 调用 Service 的 toggleField 方法
            $service = $this->getService();
            
            // 检查 Service 是否有 toggleField 方法
            if (!method_exists($service, 'toggleField')) {
                return $this->error('Service 不支持字段切换功能', [], 500);
            }
            
            $result = $service->toggleField($id, $field);
            
            // 构建返回消息
            $fieldLabels = $this->getFieldLabels();
            $fieldLabel = $fieldLabels[$field] ?? $field;
            
            $newValue = is_object($result) ? $result->{$field} : $result[$field] ?? null;
            $message = $newValue == 1 
                ? ($fieldLabels[$field . '_enabled'] ?? "{$fieldLabel}已启用")
                : ($fieldLabels[$field . '_disabled'] ?? "{$fieldLabel}已禁用");
            
            // 返回更新后的字段值
            $data = is_object($result) ? [$field => $result->{$field}] : [$field => $newValue];
            
            return $this->success($data, $message);
        } catch (BusinessException $e) {
            return $this->error($e->getMessage(), [], $e->getCode());
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取字段标签映射
     * 子类可以重写此方法以自定义字段标签
     * 
     * @return array 字段标签映射，例如 ['status' => '状态', 'visible' => '可见性']
     */
    protected function getFieldLabels(): array
    {
        return [
            'status' => '状态',
            'visible' => '可见性',
            'status_enabled' => '已启用',
            'status_disabled' => '已禁用',
            'visible_enabled' => '已显示',
            'visible_disabled' => '已隐藏',
        ];
    }
}

