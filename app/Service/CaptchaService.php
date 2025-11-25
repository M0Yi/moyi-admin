<?php

declare(strict_types=1);

namespace App\Service;

use Hyperf\Contract\SessionInterface;
use Hyperf\Di\Annotation\Inject;

/**
 * 验证码服务
 * 
 * 功能：
 * - 生成图形验证码（字符验证码）
 * - 生成数学验证码（加减法）
 * - 验证验证码
 * - 支持数字+字母组合
 */
class CaptchaService
{
    #[Inject]
    protected SessionInterface $session;

    /**
     * 验证码类型：字符验证码
     */
    public const TYPE_CHAR = 'char';

    /**
     * 验证码类型：数学验证码
     */
    public const TYPE_MATH = 'math';

    /**
     * 验证码 Session Key
     */
    private const SESSION_KEY = 'captcha_code';

    /**
     * 验证码过期时间（秒）
     */
    private const EXPIRE_TIME = 300; // 5分钟

    /**
     * 验证码长度（字符验证码）
     */
    private const CODE_LENGTH = 4;

    /**
     * 图片宽度
     */
    private const IMAGE_WIDTH = 120;

    /**
     * 图片高度
     */
    private const IMAGE_HEIGHT = 40;

    /**
     * 数学验证码：最小数字
     */
    private const MATH_MIN = 1;

    /**
     * 数学验证码：最大数字
     */
    private const MATH_MAX = 30;

    /**
     * 干扰线数量
     */
    private const NOISE_LINE_COUNT = 6;

    /**
     * 干扰点数量
     */
    private const NOISE_PIXEL_COUNT = 150;

    /**
     * 字符角度范围：最小角度（度）
     */
    private const CHAR_ANGLE_MIN = -35;

    /**
     * 字符角度范围：最大角度（度）
     */
    private const CHAR_ANGLE_MAX = 35;

    /**
     * 生成验证码
     * 
     * @param string|null $type 验证码类型：'char' 字符验证码，'math' 数学验证码，null 表示随机选择
     * @return array{image: string, code: string, type: string} 返回验证码数据
     *   - image: base64 图片（统一格式，无论是字符验证码还是数学验证码都返回图片）
     *   - code: 验证码值（字符验证码）或答案（数学验证码）
     *   - type: 验证码类型（'char' 或 'math'）
     */
    public function generate(?string $type = null): array
    {
        // 如果没有指定类型，随机选择
        if ($type === null) {
            $type = random_int(0, 1) === 0 ? self::TYPE_CHAR : self::TYPE_MATH;
        }

        // 验证类型参数
        if (!in_array($type, [self::TYPE_CHAR, self::TYPE_MATH], true)) {
            $type = self::TYPE_CHAR;
        }

        if ($type === self::TYPE_MATH) {
            return $this->generateMath();
        }

        return $this->generateChar();
    }

    /**
     * 生成字符验证码
     * 
     * @return array{image: string, code: string, type: string}
     */
    private function generateChar(): array
    {
        // 生成验证码字符串（数字+大写字母，排除易混淆字符：0, O, 1, I, L）
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // 创建图片
        $image = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);

        // 设置颜色
        $bgColor = imagecolorallocate($image, 255, 255, 255); // 白色背景
        $textColor = imagecolorallocate($image, 0, 0, 0); // 黑色文字
        $lineColor = imagecolorallocate($image, 200, 200, 200); // 灰色干扰线
        $pixelColor = imagecolorallocate($image, 150, 150, 150); // 灰色干扰点

        // 填充背景
        imagefilledrectangle($image, 0, 0, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $bgColor);

        // 添加干扰线
        for ($i = 0; $i < self::NOISE_LINE_COUNT; $i++) {
            $lineColorRandom = imagecolorallocate(
                $image,
                random_int(150, 200),
                random_int(150, 200),
                random_int(150, 200)
            );
            imageline(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $lineColorRandom
            );
        }

        // 添加干扰点
        for ($i = 0; $i < self::NOISE_PIXEL_COUNT; $i++) {
            imagesetpixel(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $pixelColor
            );
        }

        // 写入验证码文字
        // imagestring 的 Y 坐标是字符顶部位置
        // 字体大小 5 的高度约为 13px，需要留出足够的上边距和下边距
        $x = 20;
        $y = 15; // 从 28 调整为 15，确保字符完整显示在图片内

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            // 随机颜色（深色系）
            $textColorRandom = imagecolorallocate(
                $image,
                random_int(0, 100),
                random_int(0, 100),
                random_int(0, 100)
            );

            // 随机角度
            $angle = random_int(self::CHAR_ANGLE_MIN, self::CHAR_ANGLE_MAX);

            // 随机 Y 位置（上下浮动，减小范围避免超出边界）
            $yOffset = random_int(-3, 3);

            // 使用内置字体（如果系统有 TTF 字体，可以改用 imagettftext）
            imagestring(
                $image,
                5, // 内置字体大小（高度约 13px）
                $x + ($i * 25),
                $y + $yOffset,
                $code[$i],
                $textColorRandom
            );
        }

        // 输出图片为 base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

        $base64Image = 'data:image/png;base64,' . base64_encode($imageData);

        // 将验证码存入 Session（不区分大小写，统一转为大写存储）
        $this->session->set(self::SESSION_KEY, [
            'code' => strtoupper($code),
            'type' => self::TYPE_CHAR,
            'expire' => time() + self::EXPIRE_TIME,
        ]);

        return [
            'image' => $base64Image,
            'code' => $code, // 返回原始验证码（用于调试，生产环境可移除）
            'type' => self::TYPE_CHAR,
        ];
    }

    /**
     * 生成数学验证码（加减法），渲染为图片
     * 
     * @return array{image: string, code: string, type: string}
     */
    private function generateMath(): array
    {
        // 随机生成两个数字
        $num1 = random_int(self::MATH_MIN, self::MATH_MAX);
        $num2 = random_int(self::MATH_MIN, self::MATH_MAX);

        // 随机选择加法或减法
        $isAddition = random_int(0, 1) === 1;

        if ($isAddition) {
            // 加法
            $answer = $num1 + $num2;
            $text = "{$num1} + {$num2} = ?";
        } else {
            // 减法（确保结果不为负数）
            if ($num1 < $num2) {
                // 交换数字，确保结果为正数
                [$num1, $num2] = [$num2, $num1];
            }
            $answer = $num1 - $num2;
            $text = "{$num1} - {$num2} = ?";
        }

        // 创建图片
        $image = imagecreatetruecolor(self::IMAGE_WIDTH, self::IMAGE_HEIGHT);

        // 设置颜色
        $bgColor = imagecolorallocate($image, 255, 255, 255); // 白色背景
        $textColor = imagecolorallocate($image, 0, 0, 0); // 黑色文字
        $lineColor = imagecolorallocate($image, 200, 200, 200); // 灰色干扰线
        $pixelColor = imagecolorallocate($image, 150, 150, 150); // 灰色干扰点

        // 填充背景
        imagefilledrectangle($image, 0, 0, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, $bgColor);

        // 添加干扰线
        for ($i = 0; $i < self::NOISE_LINE_COUNT; $i++) {
            $lineColorRandom = imagecolorallocate(
                $image,
                random_int(150, 200),
                random_int(150, 200),
                random_int(150, 200)
            );
            imageline(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $lineColorRandom
            );
        }

        // 添加干扰点
        for ($i = 0; $i < self::NOISE_PIXEL_COUNT; $i++) {
            imagesetpixel(
                $image,
                random_int(0, self::IMAGE_WIDTH),
                random_int(0, self::IMAGE_HEIGHT),
                $pixelColor
            );
        }

        // 写入数学题文字
        // 使用内置字体，居中显示
        $fontSize = 5; // 内置字体大小
        $textX = 10; // 左对齐，留出边距
        $textY = 15; // 垂直居中（图片高度40，字体高度约13，所以Y=15左右居中）

        // 随机颜色（深色系）
        $textColorRandom = imagecolorallocate(
            $image,
            random_int(0, 100),
            random_int(0, 100),
            random_int(0, 100)
        );

        // 使用 imagestring 绘制文本（内置字体）
        imagestring($image, $fontSize, $textX, $textY, $text, $textColorRandom);

        // 输出图片为 base64
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

        $base64Image = 'data:image/png;base64,' . base64_encode($imageData);

        // 将答案存入 Session
        $this->session->set(self::SESSION_KEY, [
            'code' => (string) $answer,
            'type' => self::TYPE_MATH,
            'expire' => time() + self::EXPIRE_TIME,
        ]);

        return [
            'image' => $base64Image,
            'code' => (string) $answer,
            'type' => self::TYPE_MATH,
        ];
    }

    /**
     * 验证验证码
     * 
     * @param string $inputCode 用户输入的验证码
     * @return bool 验证是否通过
     */
    public function verify(string $inputCode): bool
    {
        $sessionData = $this->session->get(self::SESSION_KEY);

        if (!$sessionData) {
            return false;
        }

        // 检查是否过期
        if (isset($sessionData['expire']) && $sessionData['expire'] < time()) {
            // 清除过期的验证码
            $this->session->remove(self::SESSION_KEY);
            return false;
        }

        $type = $sessionData['type'] ?? self::TYPE_CHAR;
        $storedCode = $sessionData['code'] ?? '';
        $inputCode = trim($inputCode);

        if ($storedCode === '' || $inputCode === '') {
            return false;
        }

        // 根据验证码类型进行验证
        if ($type === self::TYPE_MATH) {
            // 数学验证码：直接比较数字
            $storedAnswer = (int) $storedCode;
            $inputAnswer = (int) $inputCode;
            
            if ($storedAnswer === $inputAnswer) {
                // 验证成功后清除验证码（防止重复使用）
                $this->session->remove(self::SESSION_KEY);
                return true;
            }
        } else {
            // 字符验证码：不区分大小写
            $storedCode = strtoupper($storedCode);
            $inputCode = strtoupper($inputCode);
            
            if ($storedCode === $inputCode) {
                // 验证成功后清除验证码（防止重复使用）
                $this->session->remove(self::SESSION_KEY);
                return true;
            }
        }

        return false;
    }

    /**
     * 清除验证码
     */
    public function clear(): void
    {
        $this->session->remove(self::SESSION_KEY);
    }
}

