<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\Store;
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
        self::assertSame([], $store->networksWithoutPayout);
        self::assertTrue($store->canAcceptPayments());
        self::assertSame($example['connectedPlatform'], $store->connectedPlatform);
        $connected = $this->client(self::json(200, ['connectedPlatform' => 'woocommerce'] + $example))->stores()->current();
        self::assertSame('woocommerce', $connected->connectedPlatform);
    }

    public function testTellsWhetherAnyEnabledNetworkHasAPayoutWallet(): void
    {
        $example = OpenApi::example('GET', '/v1/store', 200);
        $read = fn(array $missing): Store => $this->client(self::json(200, ['enabledNetworks' => ['base', 'tron'], 'networksWithoutPayout' => $missing] + $example))->stores()->current();
        self::assertSame(['tron'], $read(['tron'])->networksWithoutPayout);
        self::assertTrue($read(['tron'])->canAcceptPayments());
        self::assertFalse($read(['base', 'tron'])->canAcceptPayments());
    }

    public function testRefusesAStoreWithoutThePayoutField(): void
    {
        $example = OpenApi::example('GET', '/v1/store', 200);
        unset($example['networksWithoutPayout']);
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $example))->stores()->current();
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
        $example = OpenApi::example('GET', '/v1/networks', 200)[0];
        self::assertIsArray($example);
        self::assertSame([$example['forwarderImplementation'], $example['explorerAddressUrl'], $example['rpcUrl']], [$networks[0]->forwarderImplementation, $networks[0]->explorerAddressUrl, $networks[0]->rpcUrl]);
    }

    public function testRefusesNetworksThatAreNotAList(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, ['key' => 'base']))->platform()->networks();
    }
}
