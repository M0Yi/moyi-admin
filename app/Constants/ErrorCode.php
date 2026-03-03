<?php

declare(strict_types=1);

/**
 * 系统错误码常量定义
 *
 * 错误码规范：
 * - 400-499: 客户端错误
 * - 500-599: 服务端错误
 * - 460-469: 验证码相关错误
 *
 * 使用方式：
 * - 抛出异常：throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
 * - 获取消息：ErrorCode::getMessage(ErrorCode::NOT_FOUND);
 *
 * @package App\Constants
 */

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ErrorCode extends AbstractConstants
{
    // ============================================
    // 成功状态码 (200)
    // ============================================

    /**
     * @Message("操作成功")
     * @MessageEn("Success")
     */
    public const SUCCESS = 200;

    // ============================================
    // 客户端错误 (400-499)
    // ============================================

    /**
     * @Message("请求参数错误")
     * @MessageEn("Bad Request")
     */
    public const BAD_REQUEST = 400;

    /**
     * @Message("未登录或登录已过期")
     * @MessageEn("Unauthorized")
     */
    public const UNAUTHORIZED = 401;

    /**
     * @Message("权限不足，无法访问该资源")
     * @MessageEn("Forbidden - Access denied")
     */
    public const FORBIDDEN = 403;

    /**
     * @Message("请求的资源不存在")
     * @MessageEn("Not Found")
     */
    public const NOT_FOUND = 404;

    /**
     * @Message("请求方法不允许")
     * @MessageEn("Method Not Allowed")
     */
    public const METHOD_NOT_ALLOWED = 405;

    /**
     * @Message("请求过于频繁，请稍后再试")
     * @MessageEn("Too Many Requests")
     */
    public const TOO_MANY_REQUESTS = 429;

    // ============================================
    // 验证码相关错误 (460-469)
    // ============================================

    /**
     * @Message("请先完成滑动验证")
     * @MessageEn("Captcha verification required")
     */
    public const CAPTCHA_REQUIRED = 460;

    /**
     * @Message("滑动验证已失效，请重新验证")
     * @MessageEn("Captcha expired, please retry")
     */
    public const CAPTCHA_EXPIRED = 461;

    /**
     * @Message("滑动验证失败，请重试")
     * @MessageEn("Captcha verification failed")
     */
    public const CAPTCHA_FAILED = 462;

    // ============================================
    // 服务器错误 (500-599)
    // ============================================

    /**
     * @Message("服务器内部错误，请稍后重试")
     * @MessageEn("Internal Server Error")
     */
    public const SERVER_ERROR = 500;

    /**
     * @Message("系统错误，请联系管理员")
     * @MessageEn("System Error")
     */
    public const SYSTEM_ERROR = 500;

    /**
     * @Message("数据库查询错误")
     * @MessageEn("Database Query Error")
     */
    public const DB_QUERY_ERROR = 500;

    /**
     * @Message("数据库连接失败")
     * @MessageEn("Database Connection Error")
     */
    public const DB_CONNECTION_ERROR = 500;

    // ============================================
    // 业务错误码 (1000+)
    // ============================================

    /**
     * @Message("数据验证失败")
     * @MessageEn("Validation Error")
     */
    public const VALIDATION_ERROR = 1001;

    /**
     * @Message("用户名或密码错误")
     * @MessageEn("Invalid username or password")
     */
    public const AUTH_FAILED = 1002;

    /**
     * @Message("账号已被禁用，请联系管理员")
     * @MessageEn("Account has been disabled")
     */
    public const USER_DISABLED = 1003;

    /**
     * @Message("用户不存在")
     * @MessageEn("User not found")
     */
    public const USER_NOT_FOUND = 1004;

    /**
     * @Message("角色不存在")
     * @MessageEn("Role not found")
     */
    public const ROLE_NOT_FOUND = 1005;

    /**
     * @Message("权限不足")
     * @MessageEn("Insufficient permissions")
     */
    public const PERMISSION_DENIED = 1006;

    /**
     * @Message("文件上传失败")
     * @MessageEn("File upload failed")
     */
    public const FILE_UPLOAD_ERROR = 1007;

    /**
     * @Message("文件大小超出限制")
     * @MessageEn("File size exceeds limit")
     */
    public const FILE_TOO_LARGE = 1008;

    /**
     * @Message("不支持的文件类型")
     * @MessageEn("Unsupported file type")
     */
    public const FILE_TYPE_ERROR = 1009;

    /**
     * @Message("数据不存在或已被删除")
     * @MessageEn("Data not found or has been deleted")
     */
    public const DATA_NOT_FOUND = 1010;

    /**
     * @Message("操作失败，请重试")
     * @MessageEn("Operation failed, please retry")
     */
    public const OPERATION_FAILED = 1011;

    /**
     * @Message("参数错误")
     * @MessageEn("Invalid parameters")
     */
    public const INVALID_PARAMS = 1012;

    /**
     * @Message("站点不存在或已被禁用")
     * @MessageEn("Site not found or disabled")
     */
    public const SITE_NOT_FOUND = 1013;

    /**
     * @Message("数据库连接不存在")
     * @MessageEn("Database connection not found")
     */
    public const DB_CONNECTION_NOT_FOUND = 1014;

    /**
     * @Message("数据库连接测试失败")
     * @MessageEn("Database connection test failed")
     */
    public const DB_CONNECTION_TEST_FAILED = 1015;

    /**
     * @Message("插件不存在")
     * @MessageEn("Addon not found")
     */
    public const ADDON_NOT_FOUND = 1016;

    /**
     * @Message("插件安装失败")
     * @MessageEn("Addon installation failed")
     */
    public const ADDON_INSTALL_FAILED = 1017;

    /**
     * @Message("插件启用失败")
     * @MessageEn("Addon enable failed")
     */
    public const ADDON_ENABLE_FAILED = 1018;

    /**
     * @Message("插件禁用失败")
     * @MessageEn("Addon disable failed")
     */
    public const ADDON_DISABLE_FAILED = 1019;

    /**
     * @Message("配置更新失败")
     * @MessageEn("Configuration update failed")
     */
    public const CONFIG_UPDATE_FAILED = 1020;

    /**
     * @Message("域名格式不正确")
     * @MessageEn("Invalid domain format")
     */
    public const INVALID_DOMAIN = 1021;

    /**
     * @Message("域名验证失败")
     * @MessageEn("Domain verification failed")
     */
    public const DOMAIN_VERIFICATION_FAILED = 1022;

    /**
     * @Message("站点创建已达上限")
     * @MessageEn("Site creation limit reached")
     */
    public const SITE_LIMIT_REACHED = 1023;

    /**
     * @Message("公共站点创建已禁用")
     * @MessageEn("Public site creation is disabled")
     */
    public const PUBLIC_SITE_CREATION_DISABLED = 1024;

    // ============================================
    // CRUD 错误码 (2000+)
    // ============================================

    /**
     * @Message("记录创建失败")
     * @MessageEn("Failed to create record")
     */
    public const CREATE_FAILED = 2001;

    /**
     * @Message("记录更新失败")
     * @MessageEn("Failed to update record")
     */
    public const UPDATE_FAILED = 2002;

    /**
     * @Message("记录删除失败")
     * @MessageEn("Failed to delete record")
     */
    public const DELETE_FAILED = 2003;

    /**
     * @Message("记录不存在")
     * @MessageEn("Record not found")
     */
    public const RECORD_NOT_FOUND = 2004;

    /**
     * @Message("记录已存在")
     * @MessageEn("Record already exists")
     */
    public const RECORD_EXISTS = 2005;

    /**
     * @Message("批量操作部分失败")
     * @MessageEn("Batch operation partially failed")
     */
    public const BATCH_OPERATION_PARTIAL_FAILED = 2006;

    /**
     * @Message("批量操作全部失败")
     * @MessageEn("Batch operation all failed")
     */
    public const BATCH_OPERATION_ALL_FAILED = 2007;

    // ============================================
    // 获取错误码对应的 HTTP 状态码
    // ============================================

    /**
     * 获取错误码对应的 HTTP 状态码
     *
     * @param int $code 错误码
     * @return int HTTP 状态码
     */
    public static function getHttpStatusCode(int $code): int
    {
        return match ($code) {
            self::SUCCESS => 200,
            self::BAD_REQUEST => 400,
            self::UNAUTHORIZED => 401,
            self::FORBIDDEN => 403,
            self::NOT_FOUND => 404,
            self::METHOD_NOT_ALLOWED => 405,
            self::TOO_MANY_REQUESTS => 429,
            self::CAPTCHA_REQUIRED,
            self::CAPTCHA_EXPIRED,
            self::CAPTCHA_FAILED,
            self::VALIDATION_ERROR => 422,
            default => 500,
        };
    }

    /**
     * 判断是否为客户端错误
     *
     * @param int $code 错误码
     * @return bool
     */
    public static function isClientError(int $code): bool
    {
        return $code >= 400 && $code < 500;
    }

    /**
     * 判断是否为服务端错误
     *
     * @param int $code 错误码
     * @return bool
     */
    public static function isServerError(int $code): bool
    {
        return $code >= 500 || ($code >= 1000 && $code < 2000);
    }

    /**
     * 判断是否为业务错误
     *
     * @param int $code 错误码
     * @return bool
     */
    public static function isBusinessError(int $code): bool
    {
        return $code >= 1000;
    }
}
