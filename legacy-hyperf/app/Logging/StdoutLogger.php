<?php

declare(strict_types=1);

namespace App\Logging;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Psr\Log\LogLevel;
use Hyperf\Stringable\Stringable;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function json_encode;
use function sprintf;
use function str_replace;

/**
 * 扩展 Hyperf 的 StdoutLogger，使 context 中的数组或非 Stringable 对象也能安全输出。
 * 核心做法是在写日志前把这些值统一转成字符串：对象会被标记为 `<OBJECT> ClassName`，
 * 数组被编码为 JSON（保留中文与斜杠），失败时给出 `<ARRAY> (json_encode failed)`。
 * 其他行为与框架默认实现保持一致。
 */
class StdoutLogger implements StdoutLoggerInterface
{
    private OutputInterface $output;

    private array $tags = [
        'component',
    ];

    public function __construct(private ConfigInterface $config, ?OutputInterface $output = null)
    {
        $this->output = $output ?? new ConsoleOutput();
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {

        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {

        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {

        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        // 匹配日志需要显示context数组的标签配置，从 config/log_context_tags 获取
        $contextTags = $this->config->get('log_context_tags', []);
        foreach ($contextTags as $tag) {
            if (str_starts_with($message, $tag)) {
                print_r($context);
                break; // 匹配到任意一个标签就打印，之后跳出循环
            }
        }

        $config = $this->config->get(StdoutLoggerInterface::class, ['log_level' => []]);

        // Check if the log level is allowed
        if (! in_array($level, $config['log_level'], true)) {
            return;
        }
        $tags = array_intersect_key($context, array_flip($this->tags));
        $context = array_diff_key($context, $tags);

        /**
         * 将 context 中的值标准化为字符串，避免 str_replace() 遇到数组或不可直接输出的对象。
         * - 非 Stringable 对象：标记为 `<OBJECT> ClassName`
         * - 数组：编码为 JSON（保持中文、斜杠不转义；失败时提示）
         */
        foreach ($context as $key => $value) {
            if (is_object($value)) {
                if (! $value instanceof Stringable) {
                    $context[$key] = '<OBJECT> ' . $value::class;
                }
            } elseif (is_array($value)) {
                // 将嵌套数组转换为 JSON 字符串，解决 str_replace() 无法处理数组的问题
                $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($json !== false) {
                    $context[$key] = $json;
                } else {
                    // JSON 编码失败的情况（理论上不应该发生，但为了健壮性保留）
                    $context[$key] = '<ARRAY> (json_encode failed)';
                }
            }
        }

        $search = array_map(fn ($key) => sprintf('{%s}', $key), array_keys($context));
        $message = str_replace($search, $context, $this->getMessage((string) $message, $level, $tags));

        $this->output->writeln($message);
    }

    protected function getMessage(string $message, string $level = LogLevel::INFO, array $tags = []): string
    {
        $tag = match ($level) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL => 'error',
            LogLevel::ERROR => 'fg=red',
            LogLevel::WARNING, LogLevel::NOTICE => 'comment',
            default => 'info',
        };

        $template = sprintf('<%s>[%s]</>', $tag, strtoupper($level));
        $implodedTags = '';
        foreach ($tags as $value) {
            $implodedTags .= (' [' . $value . ']');
        }

        return sprintf($template . $implodedTags . ' %s', $message);
    }
}

