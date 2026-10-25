<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Stablevoy\Model\CreateInvoice;
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
        self::assertStringStartsWith('stablevoy-php/1.1.0 PHP/', $request->getHeaderLine('User-Agent'));
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
}
