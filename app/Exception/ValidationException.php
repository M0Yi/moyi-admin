<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

/**
 * 验证异常
 * 用于传递验证错误信息
 */
class ValidationException extends RuntimeException
{
    /**
     * 验证错误信息（字段 => 错误消息数组）
     * 
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * 格式化后的错误消息
     */
    protected string $formattedMessage = '';

    /**
     * 字段标签映射（字段名 => 中文标签）
     * 
     * @var array<string, string>
     */
    protected array $fieldLabels = [];

    /**
     * @param array<string, array<string>> $errors 验证错误信息
     * @param string $message 默认错误消息
     * @param array<string, string> $fieldLabels 字段标签映射（可选）
     */
    public function __construct(array $errors, string $message = '数据验证失败', array $fieldLabels = [])
    {
        $this->errors = $errors;
        $this->fieldLabels = $fieldLabels;
        $this->formattedMessage = $this->formatErrors($errors);
        
        parent::__construct($this->formattedMessage);
    }

    /**
     * 获取所有验证错误
     * 
     * @return array<string, array<string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * 获取第一个错误消息（向后兼容）
     */
    public function getFirstError(): string
    {
        if (empty($this->errors)) {
            return $this->getMessage();
        }

        $firstField = array_key_first($this->errors);
        $firstErrors = $this->errors[$firstField];
        
        return $firstErrors[0] ?? $this->getMessage();
    }

    /**
     * 格式化错误信息为友好的字符串
     * 
     * @param array<string, array<string>> $errors
     * @return string
     */
    protected function formatErrors(array $errors): string
    {
        if (empty($errors)) {
            return '数据验证失败';
        }

        $messages = [];
        foreach ($errors as $field => $fieldErrors) {
            // 获取字段的中文名称（如果有配置）
            $fieldLabel = $this->getFieldLabel($field);
            
            // 组合字段名和错误信息
            foreach ($fieldErrors as $error) {
                $messages[] = $fieldLabel . '：' . $error;
            }
        }

        return implode('；', $messages);
    }

    /**
     * 获取字段的中文标签
     * 
     * @param string $field 字段名
     * @return string
     */
    protected function getFieldLabel(string $field): string
    {
        // 优先使用传入的字段标签映射
        if (!empty($this->fieldLabels[$field])) {
            return $this->fieldLabels[$field];
        }
        
        // 如果没有标签映射，直接返回字段名
        return $field;
    }
}

