<?php

declare(strict_types=1);

namespace App\Support;

class SiteVerificationToken
{
    public static function generate(string $domain, string $source, string $challenge): string
    {
        $payload = sprintf(
            '%s|%s|%s',
            strtolower($domain),
            strtolower($source),
            $challenge
        );

        return hash('sha256', $payload);
    }

    public static function validate(string $token, string $domain, string $source, string $challenge): bool
    {
        return hash_equals($token, self::generate($domain, $source, $challenge));
    }
}























