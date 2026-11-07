<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use Stablevoy\Exception\ApiException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Model\SummaryPeriod;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\OpenApi;

final class SummaryAndUnmatchedTest extends ApiTestCase
{
    public function testAsksForAPeriodAndReadsTheTotals(): void
    {
        $example = OpenApi::example('GET', '/v1/invoices/summary', 200);
        $summary = $this->client(self::json(200, $example))->invoices()->summary(period: SummaryPeriod::LAST_7_DAYS);
        self::assertSame('https://api.stablevoy.com/v1/invoices/summary?period=last_7_days', (string) $this->http->last()->getUri());
        self::assertSame([$example['createdFrom'], $example['createdTo']], [$summary->createdFrom, $summary->createdTo]);
        $invoices = $example['invoices'];
        self::assertIsArray($invoices);
        self::assertSame($invoices['count'], $summary->invoiceCount);
        self::assertSame($invoices['byStatus'], $summary->invoicesByStatus);
        self::assertSame($example['invoicedUsdCents'], $summary->invoicedUsdCents);
        self::assertSame($example['receivedUsdMicros'], $summary->receivedUsdMicros);
        $byNetwork = $example['byNetwork'];
        self::assertIsArray($byNetwork);
        self::assertCount(count($byNetwork), $summary->byNetwork);
        $first = $byNetwork[0];
        self::assertIsArray($first);
        self::assertSame([$first['network'], $first['token'], $first['payments'], $first['receivedUsdMicros']], [$summary->byNetwork[0]->network, $summary->byNetwork[0]->token, $summary->byNetwork[0]->payments, $summary->byNetwork[0]->receivedUsdMicros]);
    }

    public function testAsksForOwnDays(): void
    {
        $this->client(self::json(200, OpenApi::example('GET', '/v1/invoices/summary', 200)))->invoices()->summary(createdFrom: '2026-10-01', createdTo: '2026-10-31');
        self::assertSame('https://api.stablevoy.com/v1/invoices/summary?createdFrom=2026-10-01&createdTo=2026-10-31', (string) $this->http->last()->getUri());
    }

    /**
     * @return array<string, array{?string, ?string, ?string}>
     */
    public static function badRanges(): array
    {
        return [
            'nothing' => [null, null, null],
            'period and days' => ['last_7_days', '2026-10-01', '2026-10-31'],
            'unknown period' => ['last_year', null, null],
            'only the first day' => [null, '2026-10-01', null],
            'only the last day' => [null, null, '2026-10-31'],
            'not a day' => [null, '1.10.2026', '2026-10-31'],
        ];
    }

    #[DataProvider('badRanges')]
    public function testRefusesABadRangeBeforeSendingAnything(?string $period, ?string $from, ?string $to): void
    {
        $client = $this->client();
        try {
            $client->invoices()->summary($period, $from, $to);
            self::fail('accepted');
        } catch (\InvalidArgumentException) {
            self::assertSame([], $this->http->requests);
        }
    }

    public function testPassesOnTheRefusalOfARangeOver92Days(): void
    {
        $client = $this->client(self::json(400, self::problem(400, 'invalid_invoice_filter', 'createdFrom and createdTo are at most 92 days apart')));
        try {
            $client->invoices()->summary(createdFrom: '2026-01-01', createdTo: '2026-12-31');
            self::fail('accepted');
        } catch (ApiException $e) {
            self::assertSame(ApiException::INVALID_INVOICE_FILTER, $e->errorCode);
        }
    }

    public function testRefusesASummaryWithoutTheCounts(): void
    {
        $example = OpenApi::example('GET', '/v1/invoices/summary', 200);
        unset($example['invoices']);
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $example))->invoices()->summary(period: SummaryPeriod::THIS_MONTH);
    }

    public function testListsTheUnmatchedTransfersOfTheStore(): void
    {
        $example = OpenApi::example('GET', '/v1/unmatched-transfers', 200);
        $transfers = $this->client(self::json(200, $example))->unmatchedTransfers()->list();
        self::assertSame('https://api.stablevoy.com/v1/unmatched-transfers', (string) $this->http->last()->getUri());
        self::assertCount(count($example), $transfers);
        $first = $example[0];
        self::assertIsArray($first);
        self::assertIsString($first['detectedAt']);
        $transfer = $transfers[0];
        self::assertSame(
            [$first['id'], $first['network'], $first['networkName'], $first['payoutAddress'], $first['fromAddress'], $first['txHash'], $first['tokenSymbol'], $first['amountUsdMicros'], $first['explorerTxUrl'], $first['sharedWithStores']],
            [$transfer->id, $transfer->network, $transfer->networkName, $transfer->payoutAddress, $transfer->fromAddress, $transfer->txHash, $transfer->tokenSymbol, $transfer->amountUsdMicros, $transfer->explorerTxUrl, $transfer->sharedWithStores],
        );
        self::assertEquals(new \DateTimeImmutable($first['detectedAt']), $transfer->detectedAt);
    }

    public function testRefusesAnUnmatchedAnswerThatIsNotAList(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, ['id' => 'x']))->unmatchedTransfers()->list();
    }

    public function testRefusesATransferWithoutSharedWithStores(): void
    {
        $example = OpenApi::example('GET', '/v1/unmatched-transfers', 200);
        $first = $example[0];
        self::assertIsArray($first);
        unset($first['sharedWithStores']);
        $example[0] = $first;
        $this->expectExceptionMessage('unmatchedTransfers[0].sharedWithStores is missing');
        $this->client(self::json(200, $example))->unmatchedTransfers()->list();
    }

    public function testReadsThePerStoreLimits(): void
    {
        $example = OpenApi::example('GET', '/v1/limits', 200);
        $limits = $this->client(self::json(200, $example))->platform()->limits();
        self::assertSame($example['apiKeysPerStore'], ['plugin' => $limits->apiKeysPerStore->plugin, 'mcp' => $limits->apiKeysPerStore->mcp, 'api' => $limits->apiKeysPerStore->api]);
        self::assertSame([$example['openInvoicesPerStore'], $example['invoicesPerMinutePerStore']], [$limits->openInvoicesPerStore, $limits->invoicesPerMinutePerStore]);
        unset($example['openInvoicesPerStore']);
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, $example))->platform()->limits();
    }
}
