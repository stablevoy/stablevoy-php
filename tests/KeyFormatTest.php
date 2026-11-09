<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\TestCase;
use Stablevoy\Key\KeyCheck;
use Stablevoy\Key\KeyFormat;

final class KeyFormatTest extends TestCase
{
    private const RANDOM = 'aB3dE5gH7jK9mN1pQ3sT5vW7yZ9bC1';

    /** Built independently of the SDK: CRC-32 written as 6 base62 characters, as the API does (spec SPEC-key-prefixes 4.1). */
    private static function withChecksum(string $prefix): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $n = (int) hexdec(hash('crc32b', $prefix . self::RANDOM));
        $out = '';
        for ($i = 0; $i < 6; $i++) {
            $out = $alphabet[$n % 62] . $out;
            $n = intdiv($n, 62);
        }
        return $prefix . self::RANDOM . $out;
    }

    public function testAcceptsChecksummedAndOlderFormats(): void
    {
        self::assertSame(KeyCheck::Ok, KeyFormat::checkApiKey(self::withChecksum('svk_live_')));
        self::assertSame(KeyCheck::Ok, KeyFormat::checkApiKey(self::withChecksum('svk_test_')));
        self::assertSame(KeyCheck::Ok, KeyFormat::checkWebhookSecret(self::withChecksum('svwhsec_')));
        self::assertSame(KeyCheck::Ok, KeyFormat::checkApiKey('sk_live_Xy3-Zq'));
        self::assertSame(KeyCheck::Ok, KeyFormat::checkApiKey('sk_test_Xy3-Zq'));
        self::assertSame(KeyCheck::Ok, KeyFormat::checkWebhookSecret('whsec_abc'));
    }

    public function testSaysMistypedForABrokenChecksummedKey(): void
    {
        $key = self::withChecksum('svk_live_');
        self::assertSame(KeyCheck::Mistyped, KeyFormat::checkApiKey(substr($key, 0, -1) . (str_ends_with($key, 'a') ? 'b' : 'a')));
        self::assertSame(KeyCheck::Mistyped, KeyFormat::checkApiKey(substr($key, 0, -1)));
        self::assertSame(KeyCheck::Mistyped, KeyFormat::checkApiKey($key . 'x'));
        self::assertSame(KeyCheck::Mistyped, KeyFormat::checkApiKey('svk_live_' . str_repeat('-', 36)));
        self::assertSame(KeyCheck::Mistyped, KeyFormat::checkWebhookSecret(substr(self::withChecksum('svwhsec_'), 0, -2)));
    }

    public function testSaysNotAKeyForAnythingElse(): void
    {
        foreach (['', 'pk_live_abc', 'svk_prod_abc', 'Bearer sk_live_abc'] as $value) {
            self::assertSame(KeyCheck::NotAKey, KeyFormat::checkApiKey($value));
        }
        self::assertSame(KeyCheck::NotAKey, KeyFormat::checkWebhookSecret('sk_live_abc'));
        self::assertSame(KeyCheck::NotAKey, KeyFormat::checkApiKey(self::withChecksum('svwhsec_')));
    }
}
