<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Stablevoy\Client;
use Stablevoy\ClientOptions;
use Stablevoy\Http\RetryPolicy;
use Stablevoy\Tests\Support\FakeHttpClient;

final class ClientTest extends TestCase
{
    public function testFindsAnInstalledHttpClient(): void
    {
        $client = Client::create('sk_test_key');
        self::assertSame($client->invoices(), $client->invoices());
    }

    public function testTalksToTheConfiguredHostAndNamesTheIntegration(): void
    {
        $http = new FakeHttpClient(new \Nyholm\Psr7\Response(200, [], '{"minInvoiceUsdCents":500,"maxInvoiceUsdCents":100000}'));
        $factory = new Psr17Factory();
        $client = Client::fromPsr('sk_test_key', $http, $factory, $factory, new ClientOptions(baseUrl: 'http://localhost:8080/', userAgentSuffix: 'my-shop/2.1'));
        $client->platform()->limits();
        self::assertSame('http://localhost:8080/v1/limits', (string) $http->last()->getUri());
        self::assertStringEndsWith(' my-shop/2.1', $http->last()->getHeaderLine('User-Agent'));
    }

    public function testRefusesAnEmptyKey(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The API key is empty');
        Client::fromPsr(' ', new FakeHttpClient(), $factory, $factory);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeBaseUrls(): iterable
    {
        yield 'plain http to a real host would send the key unencrypted' => ['http://api.stablevoy.com'];
        yield 'credentials in the URL' => ['https://user:secret@api.stablevoy.com'];
        yield 'a path' => ['https://api.stablevoy.com/v1'];
        yield 'a query' => ['https://api.stablevoy.com?x=1'];
        yield 'no scheme' => ['api.stablevoy.com'];
        yield 'another scheme' => ['ftp://api.stablevoy.com'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unsafeBaseUrls')]
    public function testRefusesABaseUrlThatCouldLeakTheKeyOrMisroute(string $baseUrl): void
    {
        $factory = new Psr17Factory();
        $this->expectException(\InvalidArgumentException::class);
        Client::fromPsr('sk_test_key', new FakeHttpClient(), $factory, $factory, new ClientOptions(baseUrl: $baseUrl));
    }

    public function testAllowsHttpsWithAPortAndPlainHttpOnlyForLocalDevelopment(): void
    {
        $factory = new Psr17Factory();
        foreach (['https://api.stablevoy.com:8443', 'http://localhost:8080', 'http://127.0.0.1:18700', 'http://[::1]:8080'] as $url) {
            $http = new FakeHttpClient(new \Nyholm\Psr7\Response(200, [], '{"minInvoiceUsdCents":500,"maxInvoiceUsdCents":100000}'));
            Client::fromPsr('sk_test_key', $http, $factory, $factory, new ClientOptions(baseUrl: $url))->platform()->limits();
            self::assertSame($url . '/v1/limits', (string) $http->last()->getUri());
        }
    }

    public function testRefusesNegativeRetrySettings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(-1);
    }
}
