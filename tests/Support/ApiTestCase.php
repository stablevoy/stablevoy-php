<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Stablevoy\Client;
use Stablevoy\ClientOptions;
use Stablevoy\Http\RetryPolicy;

abstract class ApiTestCase extends TestCase
{
    protected FakeHttpClient $http;
    protected RecordingSleeper $sleeper;

    protected function client(ResponseInterface|\Throwable ...$answers): Client
    {
        return $this->clientWith(new RetryPolicy(), ...$answers);
    }

    protected function clientWith(RetryPolicy $retries, ResponseInterface|\Throwable ...$answers): Client
    {
        $this->http = new FakeHttpClient(...$answers);
        $this->sleeper = new RecordingSleeper();
        $factory = new Psr17Factory();
        return Client::fromPsr('sk_test_key', $this->http, $factory, $factory, new ClientOptions(retries: $retries, sleeper: $this->sleeper));
    }

    /**
     * @param array<mixed>|string $body
     * @param array<string, string> $headers
     */
    protected static function json(int $status, array|string $body, array $headers = []): Response
    {
        $type = $status >= 400 ? 'application/problem+json' : 'application/json';
        return new Response($status, ['Content-Type' => $type] + $headers, is_string($body) ? $body : json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<mixed>
     */
    protected static function problem(int $status, string $code, string $detail): array
    {
        return ['type' => 'https://stablevoy.com/docs/api/errors/#error-' . $code, 'title' => $code, 'status' => $status, 'code' => $code, 'detail' => $detail];
    }
}
