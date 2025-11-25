<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

#[Constants]
class ErrorCode extends AbstractConstants
{
    /**
     * @Message("Server Error！")
     */
    public const SERVER_ERROR = 500;

    /**
     * @Message("Bad Request")
     */
    public const BAD_REQUEST = 400;

    /**
     * @Message("Validation Error")
     */
    public const VALIDATION_ERROR = 422;

    /**
     * @Message("请先完成滑动验证")
     */
    public const CAPTCHA_REQUIRED = 460;

    /**
     * @Message("滑动验证已失效")
     */
    public const CAPTCHA_EXPIRED = 461;

    /**
     * @Message("滑动验证失败")
     */
    public const CAPTCHA_FAILED = 462;
}
