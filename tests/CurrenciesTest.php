<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\CreateInvoice;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\OpenApi;

final class CurrenciesTest extends ApiTestCase
{
    private const STORE = '7c3360b5-e8cc-4775-8a13-7324d4f1296e';
    private const PRICING = ['currency' => 'EUR', 'amount' => 4999, 'exponent' => 2, 'usdPerUnit' => '1.0992', 'rateSource' => 'ecb', 'rateAt' => '2026-11-19T15:00:00.000Z'];

    public function testCreatesAnInvoicePricedInEurAndReadsThePriceAndTheRate(): void
    {
        $example = ['amountUsdCents' => 5495, 'pricing' => self::PRICING] + OpenApi::example('POST', '/v1/invoices', 201);
        $client = $this->client(self::json(201, $example));
        $created = $client->invoices()->create(CreateInvoice::inCurrency(4999, 'EUR', 'order-1'));
        self::assertSame(['amount' => 4999, 'currency' => 'EUR', 'externalReference' => 'order-1'], json_decode((string) $this->http->last()->getBody(), true));
        self::assertSame(5495, $created->invoice->amountUsdCents);
        $p = $created->invoice->pricing;
        self::assertNotNull($p);
        self::assertSame(['EUR', 4999, 2, '1.0992', 'ecb'], [$p->currency, $p->amount, $p->exponent, $p->usdPerUnit, $p->rateSource]);
        self::assertSame('2026-11-19T15:00:00+00:00', $p->rateAt->format(DATE_ATOM));
    }

    public function testReadsAUsdInvoiceAndAnOldPayloadWithoutPricing(): void
    {
        $usd = OpenApi::example('GET', '/v1/invoices/{invoiceId}', 200);
        $old = $usd;
        unset($old['pricing']);
        $client = $this->client(self::json(200, $usd), self::json(200, $old));
        self::assertNull($client->invoices()->get(self::STORE)->pricing);
        self::assertNull($client->invoices()->get(self::STORE)->pricing);
    }

    /** @return iterable<string, array{0: callable(): CreateInvoice}> */
    public static function badAmounts(): iterable
    {
        yield 'both forms' => [static fn() => new CreateInvoice(100, amount: 100, currency: 'EUR')];
        yield 'amountUsdCents with a currency' => [static fn() => new CreateInvoice(100, currency: 'EUR')];
        yield 'neither form' => [static fn() => new CreateInvoice(null)];
        yield 'amount without currency' => [static fn() => new CreateInvoice(null, amount: 100)];
        yield 'a zero amount' => [static fn() => CreateInvoice::inCurrency(0, 'EUR')];
        yield 'a lowercase code' => [static fn() => CreateInvoice::inCurrency(100, 'eur')];
    }

    /** @dataProvider badAmounts */
    #[\PHPUnit\Framework\Attributes\DataProvider('badAmounts')]
    public function testRefusesAnAmountOfTheWrongShape(callable $make): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $make();
    }

    public function testListsTheCurrenciesAndThoseOfAStoreWithItsOwnRates(): void
    {
        $list = OpenApi::example('GET', '/v1/currencies', 200);
        $mine = OpenApi::example('GET', '/v1/stores/{storeId}/currencies', 200);
        $client = $this->client(self::json(200, $list), self::json(200, $mine));
        $all = $client->currencies()->list();
        self::assertSame('https://api.stablevoy.com/v1/currencies', (string) $this->http->last()->getUri());
        self::assertCount(count($list), $all);
        $usd = $all[0];
        self::assertSame(['USD', null, null, '1', 'usd'], [$usd->code, $usd->market, $usd->store, $usd->effective?->usdPerUnit, $usd->effective?->source]);
        $eur = array_values(array_filter($all, static fn($c) => $c->code === 'EUR'))[0];
        self::assertTrue($eur->market?->usable);
        $own = array_values(array_filter($client->currencies()->forStore(self::STORE), static fn($c) => $c->code === 'EUR'))[0];
        self::assertSame('https://api.stablevoy.com/v1/stores/' . self::STORE . '/currencies', (string) $this->http->last()->getUri());
        self::assertSame('merchant', $own->effective?->source);
        self::assertFalse($own->store?->divergesFromMarket);
        self::assertSame('2026-11-19T16:20:00+00:00', $own->store->setAt->format(DATE_ATOM));
    }

    public function testRefusesAStoreIdThatIsNotAUuidAndAnAnswerThatIsNotAList(): void
    {
        $client = $this->client(self::json(200, ['not' => 'a list']));
        try {
            $client->currencies()->forStore('../me');
            self::fail('no exception');
        } catch (\InvalidArgumentException) {
        }
        self::assertCount(0, $this->http->requests);
        $this->expectException(UnexpectedResponseException::class);
        $client->currencies()->list();
    }
}
