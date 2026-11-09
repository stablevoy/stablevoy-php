<?php

declare(strict_types=1);

namespace Stablevoy\Key;

/**
 * Stablevoy store API keys are svk_live_ / svk_test_ followed by 30 random and 6 checksum characters, webhook signing
 * secrets svwhsec_ in the same shape; keys and secrets made before (sk_live_, sk_test_, whsec_) keep working. The checksum
 * is CRC-32 of prefix and random part written as 6 base62 characters, so a typo or a cut copy shows without any request.
 */
final class KeyFormat
{
    private const BASE62 = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    private const API_KEY_PREFIXES = ['svk_live_', 'svk_test_'];
    private const WEBHOOK_SECRET_PREFIX = 'svwhsec_';

    public static function checkApiKey(string $value): KeyCheck
    {
        foreach (self::API_KEY_PREFIXES as $prefix) {
            if (str_starts_with($value, $prefix)) {
                return self::hasValidChecksum($prefix, $value) ? KeyCheck::Ok : KeyCheck::Mistyped;
            }
        }
        // The older format is recognised by prefix only, as before the checksummed one existed.
        return preg_match('/^sk_(live|test)_/', $value) === 1 ? KeyCheck::Ok : KeyCheck::NotAKey;
    }

    public static function checkWebhookSecret(string $value): KeyCheck
    {
        if (str_starts_with($value, self::WEBHOOK_SECRET_PREFIX)) {
            return self::hasValidChecksum(self::WEBHOOK_SECRET_PREFIX, $value) ? KeyCheck::Ok : KeyCheck::Mistyped;
        }
        return str_starts_with($value, 'whsec_') ? KeyCheck::Ok : KeyCheck::NotAKey;
    }

    private static function hasValidChecksum(string $prefix, string $value): bool
    {
        $body = substr($value, strlen($prefix));
        if (preg_match('/^[0-9A-Za-z]{36}$/', $body) !== 1) {
            return false;
        }
        $n = crc32($prefix . substr($body, 0, 30));
        $checksum = '';
        for ($i = 0; $i < 6; $i++) {
            $checksum = self::BASE62[$n % 62] . $checksum;
            $n = intdiv($n, 62);
        }
        return substr($body, 30) === $checksum;
    }
}
