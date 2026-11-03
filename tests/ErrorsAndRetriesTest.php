<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Stablevoy\Exception\ApiException;
use Stablevoy\Exception\AuthenticationException;
use Stablevoy\Exception\MaintenanceException;
use Stablevoy\Exception\NotFoundException;
use Stablevoy\Exception\RateLimitedException;
use Stablevoy\Exception\TransportException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Exception\ValidationException;
use Stablevoy\Http\RetryPolicy;
use Stablevoy\Model\CreateInvoice;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\FakeClientException;
use Stablevoy\Tests\Support\FakeNetworkException;
use Stablevoy\Tests\Support\OpenApi;

final class ErrorsAndRetriesTest extends ApiTestCase
{
    /**
     * @return iterable<string, array{int, string, class-string<ApiException>}>
     */
    public static function errors(): iterable
    {
        yield 'bad key' => [401, 'authentication_failed', AuthenticationException::class];
        yield 'not found' => [404, 'not_found', NotFoundException::class];
        yield 'validation' => [400, 'validation_failed', ValidationException::class];
        yield 'business rule' => [422, 'amount_below_minimum', ValidationException::class];
        yield 'conflict' => [409, 'conflict', ApiException::class];
        yield 'other 503' => [503, 'networks_unavailable', ApiException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('errors')]
    public function testMapsEachProblemToItsException(int $status, string $code, string $class): void
    {
        $client = $this->clientWith(RetryPolicy::none(), self::json($status, self::problem($status, $code, 'Something went wrong')));
        try {
            $client->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e');
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($status, $e->status);
            self::assertSame($code, $e->errorCode);
            self::assertSame('Something went wrong', $e->detail);
            self::assertSame('https://stablevoy.com/docs/api/errors/#error-' . $code, $e->type);
            self::assertStringContainsString("HTTP $status", $e->getMessage());
        }
    }

    public function testKeepsTheIssuesOfAValidationError(): void
    {
        $problem = self::problem(400, 'validation_failed', 'Invalid body') + ['issues' => [['path' => 'amountUsdCents', 'message' => 'Expected integer'], 'junk']];
        try {
            $this->client(self::json(400, $problem))->invoices()->create(new CreateInvoice(1));
            self::fail('no exception');
        } catch (ValidationException $e) {
            self::assertSame([['path' => 'amountUsdCents', 'message' => 'Expected integer']], $e->issues);
        }
    }

    public function testGivesTheLimitAnAmountWasRefusedFor(): void
    {
        $refused = self::problem(422, 'amount_above_store_limit', 'The amount is above the store limit.') + ['limitUsdCents' => 50000];
        try {
            $this->client(self::json(422, $refused))->invoices()->create(new CreateInvoice(99999));
            self::fail('no exception');
        } catch (ValidationException $e) {
            self::assertSame(50000, $e->limitUsdCents);
        }
        try {
            $this->client(self::json(404, self::problem(404, 'not_found', 'Invoice not found')))->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e');
            self::fail('no exception');
        } catch (NotFoundException $e) {
            self::assertNull($e->limitUsdCents);
        }
    }

    public function testNamesAnErrorWithoutAProblemBodyByItsStatus(): void
    {
        try {
            $this->clientWith(RetryPolicy::none(), self::json(502, 'Bad Gateway'))->stores()->current();
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame('http_502', $e->errorCode);
            self::assertSame('The API answered HTTP 502', $e->detail);
            self::assertNull($e->type);
        }
    }

    public function testRetriesAReadOnRateLimitAsLongAsRetryAfterSays(): void
    {
        $client = $this->client(
            self::json(429, self::problem(429, 'rate_limited', 'Slow down'), ['Retry-After' => '3']),
            self::json(200, OpenApi::example('GET', '/v1/limits', 200)),
        );
        $client->platform()->limits();
        self::assertSame([3], $this->sleeper->slept);
        self::assertCount(2, $this->http->requests);
    }

    public function testBacksOffWithoutRetryAfterAndGivesUpAfterTheLastRetry(): void
    {
        $limited = self::json(429, self::problem(429, 'rate_limited', 'Slow down'));
        $client = $this->client($limited, $limited, $limited);
        try {
            $client->platform()->limits();
            self::fail('no exception');
        } catch (RateLimitedException $e) {
            self::assertNull($e->retryAfterSeconds);
        }
        self::assertSame([1, 2], $this->sleeper->slept);
    }

    public function testDoesNotSleepThroughMaintenanceButReportsWhenToComeBack(): void
    {
        $client = $this->client(self::json(503, self::problem(503, 'maintenance', 'Stablevoy is under maintenance.'), ['Retry-After' => '300']));
        try {
            $client->invoices()->get('7c3360b5-e8cc-4775-8a13-7324d4f1296e');
            self::fail('no exception');
        } catch (MaintenanceException $e) {
            self::assertSame(300, $e->retryAfterSeconds);
        }
        self::assertSame([], $this->sleeper->slept);
    }

    public function testRetriesACreateOnlyWithAnIdempotencyKey(): void
    {
        $busy = self::json(503, self::problem(503, 'maintenance', 'Back soon'), ['Retry-After' => '2']);
        $created = self::json(201, OpenApi::example('POST', '/v1/invoices', 201));

        $client = $this->client($busy, $created);
        $client->invoices()->create(new CreateInvoice(1999), 'order-1');
        self::assertSame([2], $this->sleeper->slept);

        $client = $this->client($busy);
        try {
            $client->invoices()->create(new CreateInvoice(1999));
            self::fail('no exception');
        } catch (MaintenanceException) {
            self::assertSame([], $this->sleeper->slept);
            self::assertCount(1, $this->http->requests);
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function gatewayErrors(): iterable
    {
        foreach ([502, 504, 520, 521, 522, 523, 524] as $status) {
            yield "HTTP $status" => [$status];
        }
    }

    /** While the API restarts, nginx and Cloudflare answer with a gateway error and an HTML page, not a problem document. */
    #[DataProvider('gatewayErrors')]
    public function testRetriesAGatewayErrorOnReadsAndIdempotentCreates(int $status): void
    {
        $gateway = new Response($status, ['Content-Type' => 'text/html'], '<html>Bad gateway</html>');
        $client = $this->client($gateway, self::json(200, OpenApi::example('GET', '/v1/limits', 200)));
        $client->platform()->limits();
        self::assertSame([1], $this->sleeper->slept);

        $client = $this->client($gateway, self::json(201, OpenApi::example('POST', '/v1/invoices', 201)));
        $client->invoices()->create(new CreateInvoice(1999), 'order-1');
        self::assertSame([1], $this->sleeper->slept);

        $client = $this->client($gateway);
        try {
            $client->invoices()->create(new CreateInvoice(1999));
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame('http_' . $status, $e->errorCode);
            self::assertSame([], $this->sleeper->slept);
        }
    }

    public function testDoesNotRetryAnInternalError(): void
    {
        $client = $this->client(self::json(500, self::problem(500, 'internal_error', 'Something went wrong')));
        try {
            $client->platform()->limits();
            self::fail('no exception');
        } catch (ApiException $e) {
            self::assertSame('internal_error', $e->errorCode);
        }
        self::assertSame([], $this->sleeper->slept);
        self::assertCount(1, $this->http->requests);
    }

    public function testRetriesAFailedConnectionAndThenReportsIt(): void
    {
        $request = new Request('GET', 'https://api.stablevoy.com/v1/limits');
        $client = $this->client(new FakeNetworkException($request), self::json(200, OpenApi::example('GET', '/v1/limits', 200)));
        $client->platform()->limits();
        self::assertSame([1], $this->sleeper->slept);

        $client = $this->clientWith(RetryPolicy::none(), new FakeNetworkException($request));
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot reach the Stablevoy API: Connection refused');
        $client->platform()->limits();
    }

    public function testReportsAClientThatCannotSendAtAllWithoutRetrying(): void
    {
        $client = $this->client(new FakeClientException('bad request object'));
        try {
            $client->platform()->limits();
            self::fail('no exception');
        } catch (TransportException $e) {
            self::assertStringContainsString('bad request object', $e->getMessage());
        }
        self::assertSame([], $this->sleeper->slept);
    }

    public function testRefusesAnAnswerThatIsNotJson(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, '<html>'))->platform()->limits();
    }

    public function testRefusesJsonThatIsNotAnObjectOrList(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->client(self::json(200, '"text"'))->platform()->limits();
    }

    public function testNamesTheFieldOfAnAnswerItCannotRead(): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('limits.maxInvoiceUsdCents is not an integer');
        $this->client(self::json(200, ['minInvoiceUsdCents' => 500, 'maxInvoiceUsdCents' => '1000']))->platform()->limits();
    }
}
