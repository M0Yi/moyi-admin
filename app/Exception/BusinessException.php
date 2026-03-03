<?php

declare(strict_types=1);

/**
 * 业务异常类
 *
 * 用于抛出业务逻辑相关的异常，自动从 ErrorCode 获取错误消息
 *
 * 使用示例：
 * throw new BusinessException(ErrorCode::USER_NOT_FOUND, '指定的用户不存在');
 * throw new BusinessException(ErrorCode::AUTH_FAILED);
 *
 * @package App\Exception
 */

namespace App\Exception;

use App\Constants\ErrorCode;
use Hyperf\Server\Exception\ServerException;
use Throwable;

class BusinessException extends ServerException
{
    /**
     * 错误数据
     */
    private array $errorData = [];

    /**
     * 构造函数
     *
     * @param int $code 错误码（使用 ErrorCode 常量）
     * @param string|null $message 自定义错误消息（为空时自动从 ErrorCode 获取）
     * @param Throwable|null $previous 前一个异常
     * @param array $errorData 附加错误数据
     */
    public function __construct(
        int $code = 0,
        ?string $message = null,
        ?Throwable $previous = null,
        array $errorData = []
    ) {
        // 如果未提供消息，从 ErrorCode 自动获取
        if ($message === null || $message === '') {
            $message = ErrorCode::getMessage($code) ?? '未知错误';
        }

        parent::__construct($message, $code, $previous);

        $this->errorData = $errorData;
    }

    /**
     * 获取错误数据
     */
    public function getErrorData(): array
    {
        return $this->errorData;
    }

    /**
     * 获取 HTTP 状态码
     */
    public function getHttpStatusCode(): int
    {
        return ErrorCode::getHttpStatusCode($this->code);
    }

    /**
     * 判断是否为客户端错误 (4xx)
     */
    public function isClientError(): bool
    {
        return ErrorCode::isClientError($this->code);
    }

    /**
     * 判断是否为服务端错误 (5xx)
     */
    public function isServerError(): bool
    {
        return ErrorCode::isServerError($this->code);
    }

    /**
     * 判断是否为业务错误 (1000+)
     */
    public function isBusinessError(): bool
    {
        return ErrorCode::isBusinessError($this->code);
    }

    /**
     * 添加错误数据
     *
     * @param string $key 键名
     * @param mixed $value 值
     * @return self
     */
    public function withData(string $key, mixed $value): self
    {
        $this->errorData[$key] = $value;
        return $this;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'code' => $this->getCode(),
            'message' => $this->getMessage(),
            'data' => $this->errorData,
        ];
    }

    /**
     * 创建验证失败异常
     *
     * @param string $message 错误消息
     * @param array $errors 验证错误详情
     * @return self
     */
    public static function validationFailed(string $message, array $errors = []): self
    {
        return new self(
            ErrorCode::VALIDATION_ERROR,
            $message,
            null,
            ['validation_errors' => $errors]
        );
    }

    /**
     * 创建未授权异常
     *
     * @param string|null $message 错误消息
     * @return self
     */
    public static function unauthorized(?string $message = null): self
    {
        return new self(
            ErrorCode::UNAUTHORIZED,
            $message ?? '未登录或登录已过期'
        );
    }

    /**
     * 创建禁止访问异常
     *
     * @param string|null $message 错误消息
     * @return self
     */
    public static function forbidden(?string $message = null): self
    {
        return new self(
            ErrorCode::FORBIDDEN,
            $message ?? '权限不足，无法访问该资源'
        );
    }

    /**
     * 创建资源不存在异常
     *
     * @param string|null $message 错误消息
     * @return self
     */
    public static function notFound(?string $message = null): self
    {
        return new self(
            ErrorCode::NOT_FOUND,
            $message ?? '请求的资源不存在'
        );
    }

    /**
     * 创建认证失败异常
     *
     * @param string|null $message 错误消息
     * @return self
     */
    public static function authFailed(?string $message = null): self
    {
        return new self(
            ErrorCode::AUTH_FAILED,
            $message ?? '用户名或密码错误'
        );
    }

    /**
     * 创建用户已禁用异常
     *
     * @param string|null $message 错误消息
     * @return self
     */
    public static function userDisabled(?string $message = null): self
    {
        return new self(
            ErrorCode::USER_DISABLED,
            $message ?? '账号已被禁用，请联系管理员'
        );
    }
}
