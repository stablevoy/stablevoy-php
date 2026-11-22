<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\CheckoutStep;
use Stablevoy\Model\CheckoutSurface;
use Stablevoy\Model\SummaryPeriod;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\OpenApi;

final class CheckoutTest extends ApiTestCase
{
    private const FUNNEL_PATH = '/v1/store/checkout-funnel';
    private const TRAIL_PATH = '/v1/invoices/{invoiceId}/checkout-trail';
    private const INVOICE = '7c3360b5-e8cc-4775-8a13-7324d4f1296e';

    public function testAsksForTheKeysStoreAndReadsEveryCount(): void
    {
        $example = OpenApi::example('GET', self::FUNNEL_PATH, 200);
        $funnel = $this->client(self::json(200, $example))->stores()->checkoutFunnel(period: SummaryPeriod::LAST_30_DAYS);
        self::assertSame('https://api.stablevoy.com/v1/store/checkout-funnel?period=last_30_days', (string) $this->http->last()->getUri());
        self::assertSame(
            [$example['createdFrom'], $example['createdTo'], $example['invoices'], $example['steps'], $example['openedOn'], $example['detected'], $example['paid']],
            [$funnel->createdFrom, $funnel->createdTo, $funnel->invoices, $funnel->steps, $funnel->openedOn, $funnel->detected, $funnel->paid],
        );
        $steps = $example['steps'];
        self::assertIsArray($steps);
        self::assertSame($steps['opened'], $funnel->steps[CheckoutStep::OPENED]);
        $openedOn = $example['openedOn'];
        self::assertIsArray($openedOn);
        self::assertSame($openedOn['widget'], $funnel->openedOn[CheckoutSurface::WIDGET]);
    }

    public function testAsksForOwnDays(): void
    {
        $this->client(self::json(200, OpenApi::example('GET', self::FUNNEL_PATH, 200)))->stores()->checkoutFunnel(createdFrom: '2026-11-01', createdTo: '2026-11-30');
        self::assertSame('https://api.stablevoy.com/v1/store/checkout-funnel?createdFrom=2026-11-01&createdTo=2026-11-30', (string) $this->http->last()->getUri());
    }

    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function badRanges(): array
    {
        return [
            'nothing' => [null, null, null],
            'unknown period' => ['last_year', null, null],
            'only the first day' => [null, '2026-11-01', null],
            'period and a day' => ['last_7_days', null, '2026-11-30'],
        ];
    }

    #[DataProvider('badRanges')]
    public function testRefusesABadRangeBeforeSendingAnything(?string $period, ?string $from, ?string $to): void
    {
        $client = $this->client();
        try {
            $client->stores()->checkoutFunnel($period, $from, $to);
            self::fail('accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->requests);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function funnelFields(): array
    {
        return ['steps' => ['steps'], 'paid' => ['paid']];
    }

    #[DataProvider('funnelFields')]
    public function testRefusesAFunnelWithoutAField(string $field): void
    {
        $answer = OpenApi::example('GET', self::FUNNEL_PATH, 200);
        unset($answer[$field]);
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $answer))->stores()->checkoutFunnel(period: SummaryPeriod::THIS_MONTH);
    }

    public function testRefusesACountThatIsNotAWholeNumber(): void
    {
        $answer = OpenApi::example('GET', self::FUNNEL_PATH, 200);
        self::assertIsArray($answer['steps']);
        $answer['steps']['opened'] = '31';
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $answer))->stores()->checkoutFunnel(period: SummaryPeriod::THIS_MONTH);
    }

    public function testReadsTheStepsOfOneInvoiceWithTheirTimes(): void
    {
        $example = OpenApi::example('GET', self::TRAIL_PATH, 200);
        $trail = $this->client(self::json(200, $example))->invoices()->checkoutTrail(self::INVOICE);
        self::assertSame('https://api.stablevoy.com/v1/invoices/' . self::INVOICE . '/checkout-trail', (string) $this->http->last()->getUri());
        self::assertSame($example['invoiceId'], $trail->invoiceId);
        $steps = $example['steps'];
        self::assertIsArray($steps);
        self::assertCount(count($steps), $trail->steps);
        foreach ($steps as $i => $step) {
            self::assertIsArray($step);
            self::assertIsString($step['at']);
            self::assertSame([$step['step'], (new \DateTimeImmutable($step['at']))->getTimestamp(), $step['surface']], [$trail->steps[$i]->step, $trail->steps[$i]->at->getTimestamp(), $trail->steps[$i]->surface]);
        }
    }

    public function testKeepsAStepThisLibraryDoesNotKnowYet(): void
    {
        $answer = ['invoiceId' => self::INVOICE, 'steps' => [['step' => 'refunded', 'at' => '2026-11-20T14:01:12.000Z', 'surface' => null]]];
        $trail = $this->client(self::json(200, $answer))->invoices()->checkoutTrail(self::INVOICE);
        self::assertSame('refunded', $trail->steps[0]->step);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badIds(): array
    {
        return ['path' => ['../store'], 'not a uuid' => ['not-a-uuid'], 'empty' => ['']];
    }

    #[DataProvider('badIds')]
    public function testRefusesABadInvoiceIdBeforeSendingAnything(string $id): void
    {
        $client = $this->client();
        try {
            $client->invoices()->checkoutTrail($id);
            self::fail('accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testRefusesAStepWithoutAValidTime(): void
    {
        $answer = ['invoiceId' => self::INVOICE, 'steps' => [['step' => 'created', 'at' => '###', 'surface' => null]]];
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $answer))->invoices()->checkoutTrail(self::INVOICE);
    }
}
