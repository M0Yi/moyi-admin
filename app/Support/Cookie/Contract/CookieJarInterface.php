<?php

declare(strict_types=1);

namespace HyperfExtension\Cookie\Contract;

/**
 * Cookie Jar Interface - 兼容性接口
 */
interface CookieJarInterface
{
    /**
     * 设置一个 cookie
     *
     * @param string $name cookie 名称
     * @param string $value cookie 值
     * @param int $expire 过期时间戳，0 表示浏览器关闭时过期
     * @param string $path cookie 路径
     * @param string $domain cookie 域名
     * @param bool $secure 是否只在 HTTPS 下传输
     * @param bool $httpOnly 是否只能通过 HTTP 访问
     */
    public function set(
        string $name,
        string $value,
        int $expire = 0,
        string $path = '/',
        string $domain = '',
        bool $secure = false,
        bool $httpOnly = false
    ): void;

    /**
     * 获取一个 cookie
     *
     * @param string $name cookie 名称
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get(string $name, $default = null);

    /**
     * 检查 cookie 是否存在
     *
     * @param string $name cookie 名称
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * 删除一个 cookie
     *
     * @param string $name cookie 名称
     * @param string $path cookie 路径
     * @param string $domain cookie 域名
     */
    public function forget(string $name, string $path = '/', string $domain = ''): void;

    /**
     * 获取所有 cookies
     *
     * @return array
     */
    public function all(): array;
}
