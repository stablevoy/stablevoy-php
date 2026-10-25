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

    public function testRefusesABaseUrlWithAPath(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(\InvalidArgumentException::class);
        Client::fromPsr('sk_test_key', new FakeHttpClient(), $factory, $factory, new ClientOptions(baseUrl: 'https://api.stablevoy.com/v1'));
    }

    public function testRefusesNegativeRetrySettings(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RetryPolicy(-1);
    }
}
