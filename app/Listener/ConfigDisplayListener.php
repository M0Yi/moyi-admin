<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Contract\ConfigInterface;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Framework\Event\MainWorkerStart;
use Psr\Container\ContainerInterface;

#[Listener]
class ConfigDisplayListener implements ListenerInterface
{
    private StdoutLoggerInterface $logger;
    private ConfigInterface $config;

    public function __construct(ContainerInterface $container)
    {
        $this->logger = $container->get(StdoutLoggerInterface::class);
        $this->config = $container->get(ConfigInterface::class);
    }

    public function listen(): array
    {
        return [
            MainWorkerStart::class,
        ];
    }

    public function process(object $event): void
    {
        if ($event instanceof MainWorkerStart) {
            $this->displayConfig();
        }
    }

    private function displayConfig(): void
    {
        $this->logger->info('');
        $this->logger->info('═══════════════════════════════════════════════════════════════');
        $this->logger->info('                   应用配置信息');
        $this->logger->info('═══════════════════════════════════════════════════════════════');
        $this->logger->info('');

        // 应用信息
//        $this->displayAppInfo();

        // 服务器配置
        $this->displayServerConfig();

        // 数据库配置
        $this->displayDatabaseConfig();

        // Redis 配置
        $this->displayRedisConfig();

        // 其他配置
        $this->displayOtherConfig();

        $this->logger->info('');
        $this->logger->info('═══════════════════════════════════════════════════════════════');
        $this->logger->info('');
    }

    /**
     * 输出对齐的 Markdown 表格
     *
     * @param string $title 表格标题
     * @param array $rows 表格数据，格式：[['类型', '名称', '值'], ...]
     */
    private function displayTable(string $title, array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $this->logger->info(sprintf('### %s', $title));
        $this->logger->info('');

        // 计算每列的最大宽度（使用中文字符宽度计算）
        $typeWidth = 8; // 类型列最小宽度
        $nameWidth = 12; // 名称列最小宽度
        $valueWidth = 20; // 值列最小宽度

        foreach ($rows as $row) {
            $type = $row[0] ?? '';
            $name = $row[1] ?? '';
            $value = $row[2] ?? '';

            $typeLen = $this->getStringWidth($type);
            $nameLen = $this->getStringWidth($name);
            $valueLen = $this->getStringWidth($value);

            $typeWidth = max($typeWidth, $typeLen);
            $nameWidth = max($nameWidth, $nameLen);
            $valueWidth = max($valueWidth, $valueLen);
        }

        // 输出表头
        $header = sprintf(
            '| %s | %s | %s |',
            $this->padString('类型', $typeWidth),
            $this->padString('名称', $nameWidth),
            $this->padString('值', $valueWidth)
        );
        $this->logger->info($header);

        // 输出分隔线
        $separator = sprintf(
            '|%s|%s|%s|',
            str_repeat('-', $typeWidth + 2),
            str_repeat('-', $nameWidth + 2),
            str_repeat('-', $valueWidth + 2)
        );
        $this->logger->info($separator);

        // 输出数据行
        foreach ($rows as $row) {
            $type = $row[0] ?? '';
            $name = $row[1] ?? '';
            $value = $row[2] ?? '';

            // 转义表格中的管道符和换行符
            $type = str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) $type);
            $name = str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) $name);
            $value = str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) $value);

            $row = sprintf(
                '| %s | %s | %s |',
                $this->padString($type, $typeWidth),
                $this->padString($name, $nameWidth),
                $this->padString($value, $valueWidth)
            );
            $this->logger->info($row);
        }

        $this->logger->info('');
    }

    /**
     * 获取字符串显示宽度（中文字符按2个宽度计算）
     */
    private function getStringWidth(string $str): int
    {
        $width = 0;
        $len = mb_strlen($str, 'UTF-8');
        for ($i = 0; $i < $len; $i++) {
            $char = mb_substr($str, $i, 1, 'UTF-8');
            // 中文字符、全角字符按2个宽度计算
            if (preg_match('/[\x{4e00}-\x{9fa5}\x{3000}-\x{303F}\x{FF00}-\x{FFEF}]/u', $char)) {
                $width += 2;
            } else {
                $width += 1;
            }
        }
        return $width;
    }

    /**
     * 填充字符串到指定宽度（左对齐，右侧填充空格）
     */
    private function padString(string $str, int $width): string
    {
        $strWidth = $this->getStringWidth($str);
        if ($strWidth >= $width) {
            return $str;
        }

        $padding = $width - $strWidth;
        return $str . str_repeat(' ', $padding);
    }

    private function displayAppInfo(): void
    {
        $appName = $this->config->get('config.app_name', 'unknown');
        $appEnv = $this->config->get('config.app_env', 'unknown');
        $scanCacheable = $this->config->get('config.scan_cacheable', false) ? 'true' : 'false';

        $rows = [
            ['应用', '应用名称', $appName],
            ['应用', '运行环境', $appEnv],
            ['应用', '扫描缓存', $scanCacheable],
        ];

        $this->displayTable('应用信息', $rows);
    }

    private function displayServerConfig(): void
    {
        $serverConfig = $this->config->get('server', []);
        $servers = $serverConfig['servers'] ?? [];

        if (empty($servers)) {
            return;
        }

        $rows = [];
        foreach ($servers as $server) {
            $name = $server['name'] ?? 'unknown';
            $host = $server['host'] ?? 'unknown';
            $port = $server['port'] ?? 'unknown';
            $address = sprintf('%s:%s', $host, $port);
            $rows[] = ['服务器', $name, $address];
        }

        if (! empty($rows)) {
            $this->displayTable('服务器配置', $rows);
        }
    }

    private function displayDatabaseConfig(): void
    {
        $dbConfig = $this->config->get('databases', []);

        if (empty($dbConfig)) {
            return;
        }

        $rows = [];
        foreach ($dbConfig as $name => $config) {
            if (is_array($config) && isset($config['driver'])) {
                $host = $config['host'] ?? 'unknown';
                $port = $config['port'] ?? 'unknown';
                $address = sprintf('%s:%s', $host, $port);
                $rows[] = ['数据库', $name, $address];
            }
        }

        if (! empty($rows)) {
            $this->displayTable('数据库配置', $rows);
        }
    }

    private function displayRedisConfig(): void
    {
        $redisConfig = $this->config->get('redis', []);

        if (empty($redisConfig)) {
            return;
        }

        $rows = [];
        foreach ($redisConfig as $name => $config) {
            if (is_array($config) && isset($config['host'])) {
                $host = $config['host'] ?? 'unknown';
                $port = $config['port'] ?? 'unknown';
                $address = sprintf('%s:%s', $host, $port);
                $rows[] = ['Redis', $name, $address];
            }
        }

        if (! empty($rows)) {
            $this->displayTable('Redis 配置', $rows);
        }
    }

    private function displayOtherConfig(): void
    {
        $rows = [];

        // 时区
        $timezone = date_default_timezone_get();
        $rows[] = ['系统', '时区', $timezone];

        // PHP 版本
        $phpVersion = PHP_VERSION;
        $rows[] = ['系统', 'PHP 版本', $phpVersion];

        // Swoole 版本
        if (extension_loaded('swoole')) {
            $swooleVersion = swoole_version();
            $rows[] = ['系统', 'Swoole 版本', $swooleVersion];
        }

        // 内存限制
        $memoryLimit = ini_get('memory_limit');
        $rows[] = ['系统', '内存限制', $memoryLimit ?: 'unknown'];

        // 基础路径
        $basePath = defined('BASE_PATH') ? BASE_PATH : 'unknown';
        $rows[] = ['系统', '项目路径', $basePath];

        $this->displayTable('其他配置', $rows);
    }
}

