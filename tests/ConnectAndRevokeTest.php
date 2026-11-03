<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use Stablevoy\ClientOptions;
use Stablevoy\Connect\ConnectClient;
use Stablevoy\Exception\AuthenticationException;
use Stablevoy\Exception\MaintenanceException;
use Stablevoy\Exception\TransportException;
use Stablevoy\Exception\UnexpectedResponseException;
use Stablevoy\Exception\ValidationException;
use Stablevoy\Tests\Support\ApiTestCase;
use Stablevoy\Tests\Support\FakeHttpClient;
use Stablevoy\Tests\Support\FakeNetworkException;
use Stablevoy\Tests\Support\OpenApi;
use Stablevoy\Tests\Support\RecordingSleeper;

final class ConnectAndRevokeTest extends ApiTestCase
{
    private function connect(Response|\Throwable ...$answers): ConnectClient
    {
        $this->http = new FakeHttpClient(...$answers);
        $this->sleeper = new RecordingSleeper();
        $factory = new Psr17Factory();
        return ConnectClient::fromPsr($this->http, $factory, $factory, new ClientOptions(sleeper: $this->sleeper));
    }

    public function testRedeemsAConnectCodeWithoutAnApiKey(): void
    {
        $example = OpenApi::example('POST', '/v1/connect/exchange', 200);
        $store = $this->connect(self::json(200, $example))->exchange('code-1', 'verifier-1', 'https://shop.example.com');

        $request = $this->http->last();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://api.stablevoy.com/v1/connect/exchange', (string) $request->getUri());
        self::assertFalse($request->hasHeader('Authorization'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame(['code' => 'code-1', 'codeVerifier' => 'verifier-1', 'site' => 'https://shop.example.com'], json_decode((string) $request->getBody(), true));
        self::assertSame($example['apiKey'], $store->apiKey);
        self::assertSame($example['webhookSecret'], $store->webhookSecret);
        $expected = $example['store'];
        self::assertIsArray($expected);
        self::assertSame($expected['id'], $store->storeId);
        self::assertSame($expected['name'], $store->storeName);
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function brokenAnswers(): iterable
    {
        $example = OpenApi::example('POST', '/v1/connect/exchange', 200);
        yield 'not a store key' => [['apiKey' => 'pk_live_1'] + $example, 'connected.apiKey is not an API key (sk_live_ or sk_test_)'];
        yield 'not a webhook secret' => [['webhookSecret' => 'secret'] + $example, 'connected.webhookSecret is not a webhook secret (whsec_)'];
        yield 'no store' => [array_diff_key($example, ['store' => true]), 'connected.store is missing'];
        yield 'no store name' => [['store' => ['id' => 'x']] + $example, 'connected.store.name is missing'];
    }

    /**
     * @param array<mixed> $answer
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('brokenAnswers')]
    public function testRefusesAnAnswerWithoutAUsableKeySecretOrStore(array $answer, string $message): void
    {
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage($message);
        $this->connect(self::json(200, $answer))->exchange('c', 'v', 'https://shop.example.com');
    }

    public function testRaisesTheRefusalOfTheApi(): void
    {
        try {
            $this->connect(self::json(400, self::problem(400, 'invalid_connect_code', 'This connection code cannot be used.')))->exchange('c', 'v', 'https://shop.example.com');
            self::fail('no exception');
        } catch (ValidationException $e) {
            self::assertSame('invalid_connect_code', $e->errorCode);
        }
    }

    public function testNeverRepeatsAnExchangeTheCodeIsSpentByTheFirstOne(): void
    {
        $busy = self::json(503, self::problem(503, 'maintenance', 'Back soon'), ['Retry-After' => '1']);
        try {
            $this->connect($busy)->exchange('c', 'v', 'https://shop.example.com');
            self::fail('no exception');
        } catch (MaintenanceException) {
            self::assertCount(1, $this->http->requests);
            self::assertSame([], $this->sleeper->slept);
        }

        $this->expectException(TransportException::class);
        $this->connect(new FakeNetworkException(new Request('POST', 'https://api.stablevoy.com/v1/connect/exchange')))->exchange('c', 'v', 'https://shop.example.com');
    }

    public function testRefusesAnUnsafeBaseUrlToo(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(\InvalidArgumentException::class);
        ConnectClient::fromPsr(new FakeHttpClient(), $factory, $factory, new ClientOptions(baseUrl: 'http://api.stablevoy.com'));
    }

    public function testBuildsOverCurlWithTheSameBaseUrlCheck(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ConnectClient::create(new ClientOptions(baseUrl: 'http://api.stablevoy.com'));
    }

    public function testRevokesTheKeyOfTheClient(): void
    {
        $client = $this->client(new Response(204));
        $client->stores()->revokeCurrentKey();
        $request = $this->http->last();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('https://api.stablevoy.com/v1/store/api-keys/current', (string) $request->getUri());
        self::assertSame('Bearer sk_test_key', $request->getHeaderLine('Authorization'));
    }

    public function testRepeatsARevokeLikeARead(): void
    {
        $client = $this->client(new Response(502, [], '<html>Bad gateway</html>'), self::json(503, self::problem(503, 'maintenance', 'Back soon'), ['Retry-After' => '2']), new Response(204));
        $client->stores()->revokeCurrentKey();
        self::assertSame([1, 2], $this->sleeper->slept);
    }

    public function testRaisesAKeyThatIsAlreadyRevoked(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->client(self::json(401, self::problem(401, 'authentication_failed', 'Invalid API key')))->stores()->revokeCurrentKey();
    }
}
