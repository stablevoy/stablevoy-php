<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\TestCase;
use Stablevoy\Http\CurlHeaderLines;

final class CurlHeaderLinesTest extends TestCase
{
    /** curl reports the header lines of every answer it reads: a proxy's CONNECT answer and 1xx answers come first. */
    public function testKeepsOnlyTheHeadersOfTheFinalAnswer(): void
    {
        $lines = new CurlHeaderLines();
        foreach (["HTTP/1.1 200 Connection established\r\n", "Proxy-Agent: squid\r\n", "\r\n", "HTTP/1.1 100 Continue\r\n", "\r\n", "HTTP/2 429\r\n", "retry-after: 3\r\n", "Set-Cookie: a=1\r\n", "Set-Cookie: b=2\r\n", "\r\n"] as $line) {
            self::assertSame(strlen($line), $lines->add($line));
        }
        self::assertSame([['retry-after', '3'], ['Set-Cookie', 'a=1'], ['Set-Cookie', 'b=2']], $lines->headers());
    }

    public function testKeepsAValueWithAColonWhole(): void
    {
        $lines = new CurlHeaderLines();
        $lines->add("HTTP/1.1 200 OK\r\n");
        $lines->add("Location: https://api.stablevoy.com:443/v1\r\n");
        self::assertSame([['Location', 'https://api.stablevoy.com:443/v1']], $lines->headers());
    }
}
