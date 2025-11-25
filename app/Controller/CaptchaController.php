<?php

declare(strict_types=1);

namespace App\Controller;

use App\Controller\AbstractController;
use App\Service\CaptchaService;
use App\Service\LoginAttemptService;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

/**
 * 验证码控制器
 */
class CaptchaController extends AbstractController
{
    #[Inject]
    protected CaptchaService $captchaService;

    #[Inject]
    protected LoginAttemptService $loginAttemptService;

    /**
     * 获取验证码
     * 
     * 请求参数：
     * - type: 验证码类型，'char' 字符验证码，'math' 数学验证码，不传则随机选择
     * 
     * 返回内容：
     * - type: 验证码类型（'char' 或 'math'）
     * - image: 验证码图片（base64，统一格式，无论是字符验证码还是数学验证码都返回图片）
     * - free_token: 免验证码令牌（如果不需要验证码时返回，为 null 时表示需要验证码）
     * 
     * 逻辑说明：
     * - 验证码类型随机选择（字符验证码或数学验证码）
     * - 所有验证码都统一以图片形式返回
     * - 如果 free_token 存在（不为 null），表示不需要验证码
     * - 如果 free_token 为 null，表示需要验证码
     * 
     * @return ResponseInterface
     */
    public function getCaptcha(): ResponseInterface
    {
        // 获取验证码类型参数（不传则随机选择）
        $type = $this->request->getQueryParams()['type'] ?? null;

        // 生成验证码（随机选择类型或使用指定类型）
        $result = $this->captchaService->generate($type);

        // 尝试获取免验证码令牌（用于登录场景）
        // 如果返回 null，表示需要验证码；如果返回 token，表示不需要验证码
        $freeToken = $this->loginAttemptService->tryGetFreeToken();

        // 统一返回图片格式
        $responseData = [
            'type' => $result['type'],
            'image' => $result['image'], // 统一返回图片
            'free_token' => $freeToken,
        ];

        return $this->success($responseData, '获取成功');
    }
}

