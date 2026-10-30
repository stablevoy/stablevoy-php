<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Stablevoy\Model\CreateInvoice;
use Stablevoy\Model\InvoiceFilter;
use Stablevoy\Model\InvoiceSource;
use Stablevoy\Model\InvoiceStatus;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\OpenApi;

final class InvoicesTest extends ApiTestCase
{
    public function testCreatesAnInvoiceWithTheKeyTheIdempotencyKeyAndJson(): void
    {
        $example = OpenApi::example('POST', '/v1/invoices', 201);
        $client = $this->client(self::json(201, $example));

        $created = $client->invoices()->create(new CreateInvoice(1999, 'order-1042', 'https://shop.example/thanks'), 'order-1042');

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.stablevoy.com/v1/invoices', (string) $request->getUri());
        self::assertSame('Bearer sk_test_key', $request->getHeaderLine('Authorization'));
        self::assertSame('order-1042', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertStringStartsWith('stablevoy-php/1.2.0 PHP/', $request->getHeaderLine('User-Agent'));
        self::assertSame(['amountUsdCents' => 1999, 'externalReference' => 'order-1042', 'returnUrl' => 'https://shop.example/thanks'], json_decode((string) $request->getBody(), true));
        self::assertSame($example['checkoutUrl'], $created->checkoutUrl);
        self::assertSame($example['id'], $created->invoice->id);
        self::assertSame($example['replayed'], $created->replayed);
    }

    public function testSendsOnlyTheFieldsThatAreSet(): void
    {
        $client = $this->client(self::json(201, OpenApi::example('POST', '/v1/invoices', 201)));
        $client->invoices()->create(new CreateInvoice(500));
        self::assertSame(['amountUsdCents' => 500], json_decode((string) $this->http->last()->getBody(), true));
        self::assertFalse($this->http->last()->hasHeader('Idempotency-Key'));
    }

    public function testRefusesAnAmountThatIsNotPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CreateInvoice(0);
    }

    public function testReadsAnInvoice(): void
    {
        $example = OpenApi::example('GET', '/v1/invoices/{invoiceId}', 200);
        $client = $this->client(self::json(200, $example));

        $invoice = $client->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e');

        self::assertSame('https://api.stablevoy.com/v1/invoices/7c3360b5-e8cc-4775-8a13-7324d4f1296e', (string) $this->http->last()->getUri());
        self::assertSame($example['amountUsdCents'], $invoice->amountUsdCents);
        self::assertSame($example['status'], $invoice->status);
        self::assertSame(InvoiceStatus::isPaid($example['status']), $invoice->isPaid());
        self::assertIsArray($example['depositAddresses']);
        self::assertCount(count($example['depositAddresses']), $invoice->depositAddresses);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notInvoiceIds(): iterable
    {
        yield 'dot segment' => ['..'];
        yield 'path' => ['../stores'];
        yield 'query' => ['x?status=paid'];
        yield 'empty' => [''];
        yield 'almost a uuid' => ['7c3360b5-e8cc-4775-8a13-7324d4f1296'];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('notInvoiceIds')]
    public function testRefusesAnInvoiceIdThatIsNoUuidBeforeSendingAnything(string $id): void
    {
        $client = $this->client();
        try {
            $client->invoices()->get($id);
            self::fail('no exception');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testListsAPageWithItsFilters(): void
    {
        $client = $this->client(self::json(200, OpenApi::example('GET', '/v1/invoices', 200)));
        $page = $client->invoices()->list(20, 'cursor-1', InvoiceStatus::PAID);
        self::assertSame('https://api.stablevoy.com/v1/invoices?limit=20&cursor=cursor-1&status=paid', (string) $this->http->last()->getUri());
        self::assertNotEmpty($page->items);
    }

    public function testRefusesAPageSizeOutsideOneToAHundred(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->client()->invoices()->list(101);
    }

    public function testWalksEveryPage(): void
    {
        $invoice = OpenApi::example('GET', '/v1/invoices/{invoiceId}', 200);
        $client = $this->client(
            self::json(200, ['items' => [$invoice, $invoice], 'nextCursor' => 'next']),
            self::json(200, ['items' => [$invoice], 'nextCursor' => null]),
        );

        $all = iterator_to_array($client->invoices()->all(InvoiceStatus::EXPIRED, 2), false);

        self::assertCount(3, $all);
        self::assertSame('https://api.stablevoy.com/v1/invoices?limit=2&status=expired', (string) $this->http->requests[0]->getUri());
        self::assertSame('https://api.stablevoy.com/v1/invoices?limit=2&cursor=next&status=expired', (string) $this->http->requests[1]->getUri());
    }

    public function testSendsTheFilterAndReadsTheSourceOfEachListedInvoice(): void
    {
        $example = OpenApi::example('GET', '/v1/invoices/{invoiceId}', 200);
        $client = $this->client(self::json(200, ['items' => [
            array_merge($example, ['source' => ['kind' => 'plugin', 'platform' => 'woocommerce', 'apiKeyId' => 'k1']]),
            array_merge($example, ['source' => ['kind' => 'api_key', 'apiKeyId' => 'k2', 'apiKeyName' => 'own code']]),
            array_merge($example, ['source' => ['kind' => 'payment_link']]),
            array_merge($example, ['source' => null]),
        ], 'nextCursor' => null]));

        $filter = new InvoiceFilter(status: InvoiceStatus::PAID, source: InvoiceSource::WOOCOMMERCE, createdFrom: '2026-10-01', createdTo: '2026-10-31', search: 'order 1/2');
        $page = $client->invoices()->list(10, null, null, $filter);

        self::assertSame('https://api.stablevoy.com/v1/invoices?limit=10&status=paid&source=woocommerce&createdFrom=2026-10-01&createdTo=2026-10-31&q=order%201%2F2', (string) $this->http->last()->getUri());
        self::assertEquals(
            [
                new InvoiceSource(InvoiceSource::PLUGIN, 'woocommerce', 'k1', null),
                new InvoiceSource(InvoiceSource::API_KEY, null, 'k2', 'own code'),
                new InvoiceSource(InvoiceSource::PAYMENT_LINK, null, null, null),
                new InvoiceSource(InvoiceSource::NOT_RECORDED, null, null, null),
            ],
            array_map(static fn($i) => $i->source, $page->items),
        );
    }

    public function testReadsTheSourceOfASingleInvoiceAndNoneFromAnOlderApi(): void
    {
        $example = OpenApi::example('GET', '/v1/invoices/{invoiceId}', 200);
        $older = $example;
        unset($older['source']);
        $client = $this->client(self::json(200, $example), self::json(200, $older));
        self::assertSame(InvoiceSource::PLUGIN, $client->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e')->source?->kind);
        self::assertNull($client->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e')->source);
    }

    public function testRefusesABadFilterBeforeSendingAnything(): void
    {
        $client = $this->client();
        foreach ([
            static fn() => new InvoiceFilter(createdFrom: '1.10.2026'),
            static fn() => new InvoiceFilter(createdTo: '2026-10-1'),
            static fn() => new InvoiceFilter(search: ''),
            static fn() => new InvoiceFilter(search: str_repeat('x', 201)),
            static fn() => $client->invoices()->list(10, null, InvoiceStatus::PAID, new InvoiceFilter(status: InvoiceStatus::EXPIRED)),
        ] as $i => $make) {
            try {
                $make();
                self::fail("case $i: no exception");
            } catch (\InvalidArgumentException) {
                self::assertSame([], $this->http->requests);
            }
        }
    }

    public function testWalksEveryPageOfAFilter(): void
    {
        $client = $this->client(self::json(200, ['items' => [], 'nextCursor' => null]));
        iterator_to_array($client->invoices()->all(null, 50, new InvoiceFilter(source: InvoiceSource::PAYMENT_LINK)), false);
        self::assertSame('https://api.stablevoy.com/v1/invoices?limit=50&source=payment_link', (string) $this->http->last()->getUri());
    }
}
