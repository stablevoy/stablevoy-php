<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\OpenApi;

final class PlatformAndStoresTest extends ApiTestCase
{
    public function testReadsTheStoreOfTheKey(): void
    {
        $example = OpenApi::example('GET', '/v1/store', 200);
        $store = $this->client(self::json(200, $example))->stores()->current();
        self::assertSame('https://api.stablevoy.com/v1/store', (string) $this->http->last()->getUri());
        self::assertSame($example['name'], $store->name);
        self::assertSame($example['enabledNetworks'], $store->enabledNetworks);
    }

    public function testReadsLimitsPricingAndNetworks(): void
    {
        $client = $this->client(
            self::json(200, OpenApi::example('GET', '/v1/limits', 200)),
            self::json(200, OpenApi::example('GET', '/v1/pricing', 200)),
            self::json(200, OpenApi::example('GET', '/v1/networks', 200)),
        );
        self::assertGreaterThan(0, $client->platform()->limits()->maxInvoiceUsdCents);
        self::assertGreaterThanOrEqual(0, $client->platform()->pricing()->feeBps);
        $networks = $client->platform()->networks();
        self::assertNotEmpty($networks);
        self::assertNotEmpty($networks[0]->tokens);
    }

    public function testRefusesNetworksThatAreNotAList(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, ['key' => 'base']))->platform()->networks();
    }
}
