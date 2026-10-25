<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Exception\ConfigurationException;

/**
 * The PSR-18 client Client::create() uses: ext-curl with a limit on the whole exchange, so a server that never answers,
 * or answers one byte at a time, cannot hang the caller. Redirects are never followed (they would carry the API key
 * wherever they point), certificates are always verified, and only http(s) is spoken.
 */
final class CurlHttpClient implements ClientInterface
{
    public const MAX_TIMEOUT_SECONDS = 600;
    private const CONNECT_TIMEOUT_SECONDS = 10;

    public function __construct(
        private readonly ResponseFactoryInterface $responses,
        private readonly StreamFactoryInterface $streams,
        private readonly float $timeoutSeconds = 30.0,
    ) {
        if (!($timeoutSeconds > 0 && $timeoutSeconds <= self::MAX_TIMEOUT_SECONDS)) {
            throw new ConfigurationException('The timeout must be more than 0 and at most ' . self::MAX_TIMEOUT_SECONDS . ' seconds; got ' . $timeoutSeconds);
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $url = (string) $request->getUri();
        $method = $request->getMethod();
        if ($url === '' || $method === '') {
            throw new CurlRequestException('A request needs a URL and a method', $request);
        }
        $headers = ['Expect:'];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }
        /** @var list<array{string, string}> $received */
        $received = [];
        $handle = curl_init();
        curl_setopt_array($handle, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TIMEOUT_MS => (int) ceil($this->timeoutSeconds * 1000),
            CURLOPT_CONNECTTIMEOUT_MS => (int) ceil(min($this->timeoutSeconds, self::CONNECT_TIMEOUT_SECONDS) * 1000),
            CURLOPT_HEADERFUNCTION => static function (\CurlHandle $handle, string $line) use (&$received): int {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $received[] = [trim($parts[0]), trim($parts[1])];
                }
                return strlen($line);
            },
        ]);
        $body = (string) $request->getBody();
        if ($body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        // The handle is freed when it goes out of scope; curl_close() has done nothing since PHP 8.0.
        $answer = curl_exec($handle);
        if (!is_string($answer)) {
            throw new CurlNetworkException(curl_error($handle), curl_errno($handle), $request);
        }
        $response = $this->responses->createResponse((int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE));
        foreach ($received as [$name, $value]) {
            $response = $response->withAddedHeader($name, $value);
        }
        return $response->withBody($this->streams->createStream($answer));
    }
}
