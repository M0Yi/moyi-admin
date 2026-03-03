<?php

declare(strict_types=1);

namespace App\Controller\Admin\Strategy;

use App\Exception\ValidationException;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * CRUD 验证策略
 *
 * 负责处理数据验证、错误翻译等逻辑
 *
 * 使用示例：
 * ```php
 * class UserValidationStrategy extends CrudValidationStrategy
 * {
 *     protected function getValidationRules(string $scene, ?int $id = null): array
 *     {
 *         return [
 *             'create' => [
 *                 'username' => 'required|string|max:50|unique:admin_users',
 *                 'email' => 'required|email|unique:admin_users',
 *             ],
 *             'update' => [
 *                 'username' => 'required|string|max:50|unique:admin_users,username,' . $id,
 *                 'email' => 'required|email|unique:admin_users,email,' . $id,
 *             ],
 *         ][$scene] ?? [];
 *     }
 * }
 * ```
 */
abstract class CrudValidationStrategy
{
    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    /**
     * 获取验证规则
     *
     * @param string $scene 场景：create 或 update
     * @param int|null $id 记录ID（用于 update 场景的唯一性验证）
     * @return array 验证规则数组
     */
    abstract protected function getValidationRules(string $scene, ?int $id = null): array;

    /**
     * 获取字段标签映射
     *
     * @return array 字段标签映射
     */
    public function getFieldLabels(): array
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

    /**
     * 验证数据
     *
     * @param array $data 数据数组
     * @param string $scene 场景：create 或 update
     * @param int|null $id 记录ID（用于 update 场景）
     * @throws ValidationException
     */
    public function validate(array $data, string $scene, ?int $id = null): void
    {
        $rules = $this->getValidationRules($scene, $id);
        if (empty($rules)) {
            return;
        }

        $validator = $this->validatorFactory->make($data, $rules);
        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            $fieldLabels = $this->getFieldLabels();
            $translatedErrors = $this->translateValidationErrors($errors, $fieldLabels);
            throw new ValidationException($translatedErrors, '数据验证失败', $fieldLabels);
        }
    }

    /**
     * 转换验证错误消息为中文友好的格式
     *
     * @param array $errors 原始错误信息
     * @param array $fieldLabels 字段标签映射
     * @return array 转换后的错误信息
     */
    protected function translateValidationErrors(array $errors, array $fieldLabels): array
    {
        $translated = [];
        foreach ($errors as $field => $fieldErrors) {
            $fieldLabel = $fieldLabels[$field] ?? $field;
            $translated[$field] = [];
            foreach ($fieldErrors as $error) {
                $translatedError = $this->translateErrorMessage($error, $fieldLabel);
                $translated[$field][] = $translatedError;
            }
        }
        return $translated;
    }

    /**
     * 转换单个错误消息
     *
     * @param string $error 原始错误消息
     * @param string $fieldLabel 字段标签
     * @return string 转换后的错误消息
     */
    protected function translateErrorMessage(string $error, string $fieldLabel): string
    {
        // 如果已经是中文消息，直接返回
        if (preg_match('/[\x{4e00}-\x{9fa5}]/u', $error)) {
            return $error;
        }

        // 处理常见的验证错误消息
        if (preg_match('/^The (.+?) (?:field )?is required$/i', $error)) {
            return "{$fieldLabel}不能为空";
        }
        if (preg_match('/^The (.+?) must be (?:a )?valid email address$/i', $error)) {
            return "{$fieldLabel}必须是有效的邮箱地址";
        }
        if (preg_match('/^The (.+?) has already been taken$/i', $error)) {
            return "{$fieldLabel}已存在，请使用其他值";
        }
        if (preg_match('/^The (.+?) may not be greater than (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}不能超过{$matches[2]}个字符";
        }
        if (preg_match('/^The (.+?) must be at least (\d+)/i', $error, $matches)) {
            return "{$fieldLabel}至少需要{$matches[2]}个字符";
        }

        // 默认：返回字段标签 + 原始错误消息
        $cleaned = preg_replace('/^The (.+?) /i', '', $error);
        return "{$fieldLabel}：{$cleaned}";
    }
}
