<?php

declare(strict_types=1);

namespace App\Model;

use Hyperf\DbConnection\Model\Model;

/**
 * PostgreSQL 基础模型类
 *
 * 提供统一的 PostgreSQL 连接配置、自动时区处理、
 * PostgreSQL 数组类型转换、JSON/JSONB 类型支持
 */
abstract class PgsqlModel extends Model
{
    /**
     * 默认使用 pgsql 连接
     */
    protected ?string $connection = 'pgsql';

    /**
     * 默认表前缀
     */
    protected ?string $prefix = '';

    /**
     * 自动管理时间戳
     */
    public bool $timestamps = true;

    /**
     * 时间格式
     */
    protected ?string $dateFormat = 'Y-m-d H:i:s';

    /**
     * 创建时间字段
     */
    public const CREATED_AT = 'created_at';

    /**
     * 更新时间字段
     */
    public const UPDATED_AT = 'updated_at';

    /**
     * PostgreSQL 数组类型字段列表
     * 格式: ['tags', 'categories', ...]
     */
    protected array $pgsqlArrays = [];

    /**
     * JSON/JSONB 类型字段列表
     * 格式: ['metadata', 'settings', ...]
     */
    protected array $pgsqlJson = [];

    /**
     * 获取属性：自动处理 PostgreSQL 类型
     */
    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        // 处理 PostgreSQL 数组类型
        if (in_array($key, $this->pgsqlArrays) && $this->isPgsqlArrayString($value)) {
            return $this->parsePgsqlArray($value);
        }

        // 处理 JSON 类型
        if (in_array($key, $this->pgsqlJson) && is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        return $value;
    }

    /**
     * 设置属性：自动处理 PostgreSQL 类型
     */
    public function setAttribute($key, $value)
    {
        // 数组类型转换
        if (in_array($key, $this->pgsqlArrays) && is_array($value)) {
            $value = $this->encodePgsqlArray($value);
        }

        // JSON 类型转换
        if (in_array($key, $this->pgsqlJson) && is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * 判断是否为 PostgreSQL 数组字符串格式
     */
    protected function isPgsqlArrayString(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, '{');
    }

    /**
     * 解析 PostgreSQL 数组格式
     *
     * 格式: {elem1,elem2,"elem with space"} 或 {"tag1","tag2"}
     */
    protected function parsePgsqlArray(string $value): array
    {
        if (empty($value) || $value === '{}') {
            return [];
        }

        $result = [];
        $value = trim($value, '{}');
        $length = strlen($value);
        $i = 0;

        while ($i < $length) {
            // 跳过空白
            while ($i < $length && ctype_space($value[$i])) {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            // 检查是否是引号包裹的值
            if ($value[$i] === '"') {
                // 解析引号包裹的值
                $i++;
                $start = $i;
                $escaped = false;
                $buffer = '';

                while ($i < $length) {
                    if ($value[$i] === '\\' && $i + 1 < $length) {
                        $buffer .= $value[$i + 1];
                        $i += 2;
                        continue;
                    }

                    if ($value[$i] === '"') {
                        if ($escaped) {
                            $buffer .= '"';
                            $escaped = false;
                            $i++;
                            continue;
                        }
                        // 找到结束引号
                        $i++;
                        break;
                    }

                    $buffer .= $value[$i];
                    $i++;
                }

                $result[] = $buffer;
            } else {
                // 解析普通逗号分隔的值
                $start = $i;
                while ($i < $length && $value[$i] !== ',') {
                    $i++;
                }
                $result[] = trim(substr($value, $start, $i - $start));
            }

            // 跳过逗号
            if ($i < $length && $value[$i] === ',') {
                $i++;
            }
        }

        return $result;
    }

    /**
     * 编码为 PostgreSQL 数组格式
     *
     * @param array $value PHP 数组
     * @return string PostgreSQL 数组格式: {elem1,elem2}
     */
    protected function encodePgsqlArray(array $value): string
    {
        if (empty($value)) {
            return '{}';
        }

        $encoded = array_map(function ($item) {
            if (is_null($item)) {
                return 'NULL';
            }

            if (is_numeric($item)) {
                return (string)$item;
            }

            // 转义特殊字符并用引号包裹
            $escaped = str_replace(
                ['\\', '"', '{', '}', ','],
                ['\\\\', '\\"', '\\{', '\\}', '\\,'],
                (string)$item
            );

            return '"' . $escaped . '"';
        }, $value);

        return '{' . implode(',', $encoded) . '}';
    }

    /**
     * 批量设置数组字段（便捷方法）
     */
    public function setPgsqlArrayField(string $field, array $value): self
    {
        $this->{$field} = $value;
        return $this;
    }

    /**
     * 获取数组字段（返回空数组而非 null）
     */
    public function getPgsqlArrayField(string $field): array
    {
        $value = $this->{$field};
        return is_array($value) ? $value : [];
    }

    /**
     * 转换为数组时，确保 PostgreSQL 类型被正确转换
     * 解决 Hyperf Eloquent 序列化时 getAttribute 不被调用的问题
     */
    public function toArray(): array
    {
        $attributes = [];

        foreach ($this->getAttributes() as $key => $value) {
            // 使用 getAttribute 进行类型转换
            $attributes[$key] = $this->getAttribute($key);
        }

        return $attributes;
    }
}
