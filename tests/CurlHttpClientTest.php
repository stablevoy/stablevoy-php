<?php

declare(strict_types=1);

namespace Stablevoy\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stablevoy\Client;
use Stablevoy\ClientOptions;
use Stablevoy\Exception\ConfigurationException;
use Stablevoy\Exception\TransportException;
use Stablevoy\Http\CurlHttpClient;
use Stablevoy\Http\CurlNetworkException;
use Stablevoy\Http\CurlRequestException;
use Stablevoy\Http\RetryPolicy;

final class CurlHttpClientTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static string $url = '';

    public static function setUpBeforeClass(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($probe);
        $address = (string) stream_socket_get_name($probe, false);
        fclose($probe);
        self::$url = 'http://' . $address;
        $server = proc_open([PHP_BINARY, '-S', $address, __DIR__ . '/Support/local-server.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['PHP_CLI_SERVER_WORKERS' => '4']);
        self::assertIsResource($server);
        self::$server = $server;
        for ($i = 0; $i < 100; $i++) {
            $socket = @fsockopen('127.0.0.1', (int) parse_url(self::$url, PHP_URL_PORT), $errno, $error, 0.1);
            if ($socket !== false) {
                fclose($socket);
                return;
            }
            usleep(50_000);
        }
        self::fail('The local test server did not start');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public function testTalksOverCurlWhenCreated(): void
    {
        $client = Client::create('sk_test_key', new ClientOptions(baseUrl: self::$url));
        self::assertSame(500, $client->platform()->limits()->minInvoiceUsdCents);
    }

    public function testSendsMethodHeadersAndBodyAndReadsEveryHeader(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('POST', self::$url . '/echo')->withHeader('X-Probe', 'yes')->withBody($factory->createStream('hello'));
        $response = (new CurlHttpClient($factory, $factory))->sendRequest($request);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['method' => 'POST', 'probe' => 'yes', 'body' => 'hello'], json_decode((string) $response->getBody(), true));
        self::assertSame(['a', 'b'], $response->getHeader('X-Twice'));
    }

    public function testNeverFollowsARedirect(): void
    {
        $factory = new Psr17Factory();
        $response = (new CurlHttpClient($factory, $factory))->sendRequest($factory->createRequest('GET', self::$url . '/redirect'));
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('https://evil.example/steal', $response->getHeaderLine('Location'));
    }

    public function testCutsOffAServerThatAnswersOneByteAtATime(): void
    {
        $factory = new Psr17Factory();
        $started = microtime(true);
        try {
            (new CurlHttpClient($factory, $factory, 0.3))->sendRequest($factory->createRequest('GET', self::$url . '/drip'));
            self::fail('The drip was not cut off');
        } catch (CurlNetworkException $e) {
            self::assertSame(CURLE_OPERATION_TIMEDOUT, $e->getCode());
            self::assertSame(self::$url . '/drip', (string) $e->getRequest()->getUri());
        }
        self::assertLessThan(2, microtime(true) - $started);
    }

    public function testATimedOutRequestIsATransportError(): void
    {
        $client = Client::create('sk_test_key', new ClientOptions(baseUrl: self::$url . '/', retries: RetryPolicy::none()), 0.3);
        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot reach the Stablevoy API');
        $client->platform()->networks();
    }

    public function testSpeaksOnlyHttp(): void
    {
        $factory = new Psr17Factory();
        $this->expectException(CurlNetworkException::class);
        (new CurlHttpClient($factory, $factory))->sendRequest($factory->createRequest('GET', 'file:///etc/passwd'));
    }

    public function testRefusesARequestWithoutAUrl(): void
    {
        $factory = new Psr17Factory();
        $request = $factory->createRequest('GET', '');
        try {
            (new CurlHttpClient($factory, $factory))->sendRequest($request);
            self::fail('A request without a URL was sent');
        } catch (CurlRequestException $e) {
            self::assertSame($request, $e->getRequest());
        }
    }

    /** @return iterable<string, array{float}> */
    public static function badTimeouts(): iterable
    {
        yield 'zero' => [0.0];
        yield 'negative' => [-1.0];
        yield 'not a number' => [NAN];
        yield 'too long' => [601.0];
    }

    #[DataProvider('badTimeouts')]
    public function testRefusesATimeoutOutOfRange(float $timeout): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('The timeout must be more than 0 and at most 600 seconds');
        Client::create('sk_test_key', timeoutSeconds: $timeout);
    }
}
