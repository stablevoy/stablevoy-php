<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Exception\ApiException;
use Stablevoy\Exception\TransportException;
use Stablevoy\Exception\UnexpectedResponseException;

/**
 * The API over any PSR-18 client: Bearer API key, JSON in and out, errors as exceptions, and retries only where a
 * repeat cannot do harm (reads, and creates that carry an Idempotency-Key).
 */
final class HttpTransport implements Transport
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly string $apiKey,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        string $baseUrl,
        private readonly string $userAgent,
        private readonly RetryPolicy $retries,
        private readonly Sleeper $sleeper,
        private readonly ErrorFactory $errors,
    ) {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('The API key is empty. Create one in the Stablevoy dashboard.');
        }
        $this->baseUrl = self::checkedBaseUrl($baseUrl);
    }

    public function get(string $path, array $query = []): array
    {
        $url = $this->baseUrl . $path . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
        return $this->send($this->requests->createRequest('GET', $url), true);
    }

    public function post(string $path, array $body, ?string $idempotencyKey = null): array
    {
        $request = $this->requests->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streams->createStream(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }
        return $this->send($request, $idempotencyKey !== null);
    }

    /**
     * @return array<mixed>
     */
    private function send(RequestInterface $request, bool $retryable): array
    {
        $request = $request
            ->withHeader('Authorization', 'Bearer ' . $this->apiKey)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->userAgent);
        for ($attempt = 1; ; $attempt++) {
            try {
                $response = $this->http->sendRequest($request);
            } catch (NetworkExceptionInterface $e) {
                $wait = $retryable ? $this->retries->waitBefore($attempt, null) : null;
                if ($wait === null) {
                    throw new TransportException('Cannot reach the Stablevoy API: ' . $e->getMessage(), 0, $e);
                }
                $this->sleeper->sleep($wait);
                continue;
            } catch (ClientExceptionInterface $e) {
                throw new TransportException('The HTTP client could not send the request: ' . $e->getMessage(), 0, $e);
            }
            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return $this->decode($response);
            }
            $error = $this->errors->fromProblem($status, (string) $response->getBody(), self::retryAfter($response));
            $wait = $retryable && self::isRetryable($error) ? $this->retries->waitBefore($attempt, $error->retryAfterSeconds) : null;
            if ($wait === null) {
                throw $error;
            }
            $this->sleeper->sleep($wait);
        }
    }

    /**
     * Only a scheme, a host and an optional port. The API key travels in every request, so plain http is refused except
     * for a local development server, and so are credentials, paths, queries and fragments in the URL.
     */
    private static function checkedBaseUrl(string $baseUrl): string
    {
        $url = rtrim($baseUrl, '/');
        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? ($parts['scheme'] ?? '') : '';
        $extra = is_array($parts) && (isset($parts['user']) || isset($parts['pass']) || isset($parts['path']) || isset($parts['query']) || isset($parts['fragment']));
        $local = in_array(strtolower($host), ['localhost', '127.0.0.1', '[::1]'], true);
        if ($host === '' || $extra || !($scheme === 'https' || ($scheme === 'http' && $local))) {
            throw new \InvalidArgumentException(sprintf('The base URL must be https and only a host, like https://api.stablevoy.com (http only for localhost); got %s', $baseUrl));
        }
        return $url;
    }

    private static function isRetryable(ApiException $error): bool
    {
        return $error->status === 429 || $error->status === 503;
    }

    /**
     * @return array<mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        try {
            $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new UnexpectedResponseException('The API answered with a body that is not JSON', 0, $e);
        }
        if (!is_array($data)) {
            throw new UnexpectedResponseException('The API answered with JSON that is not an object or a list');
        }
        return $data;
    }

    private static function retryAfter(ResponseInterface $response): ?int
    {
        $value = trim($response->getHeaderLine('Retry-After'));
        return ctype_digit($value) ? (int) $value : null;
    }
}
