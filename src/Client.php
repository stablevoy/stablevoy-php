<?php

declare(strict_types=1);

namespace Stablevoy;

use Http\Discovery\Exception\NotFoundException as DiscoveryNotFound;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Exception\ConfigurationException;
use Stablevoy\Http\CurlHttpClient;
use Stablevoy\Http\ErrorFactory;
use Stablevoy\Http\HttpTransport;
use Stablevoy\Http\Transport;
use Stablevoy\Resource\Invoices;
use Stablevoy\Resource\Platform;
use Stablevoy\Resource\Stores;

/**
 * The Stablevoy API. Build it with Client::create('sk_live_...'), which talks over ext-curl with a timeout, or with
 * Client::fromPsr() to pass your own PSR-18 client (then its timeout is yours to set).
 */
final class Client
{
    public const VERSION = '1.2.0';

    public function __construct(
        private readonly Invoices $invoices,
        private readonly Stores $stores,
        private readonly Platform $platform,
    ) {}

    /**
     * Talks over ext-curl; every attempt is limited to $timeoutSeconds (30 by default, at most 600) from connecting to
     * the last byte. Finds installed PSR-17 factories for the messages and fails loudly when there are none.
     */
    public static function create(string $apiKey, ClientOptions $options = new ClientOptions(), float $timeoutSeconds = 30.0): self
    {
        try {
            $requests = Psr17FactoryDiscovery::findRequestFactory();
            $responses = Psr17FactoryDiscovery::findResponseFactory();
            $streams = Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryNotFound $e) {
            throw new ConfigurationException('No PSR-17 HTTP message factories are installed. Run "composer require nyholm/psr7" (Guzzle and Symfony projects already have them).', 0, $e);
        }
        return self::fromPsr($apiKey, new CurlHttpClient($responses, $streams, $timeoutSeconds), $requests, $streams, $options);
    }

    public static function fromPsr(string $apiKey, ClientInterface $http, RequestFactoryInterface $requests, StreamFactoryInterface $streams, ClientOptions $options = new ClientOptions()): self
    {
        $userAgent = 'stablevoy-php/' . self::VERSION . ' PHP/' . PHP_VERSION . ($options->userAgentSuffix === null ? '' : ' ' . $options->userAgentSuffix);
        return self::fromTransport(new HttpTransport($apiKey, $http, $requests, $streams, $options->baseUrl, $userAgent, $options->retries, $options->sleeper, new ErrorFactory()));
    }

    public static function fromTransport(Transport $transport): self
    {
        return new self(new Invoices($transport), new Stores($transport), new Platform($transport));
    }

    public function invoices(): Invoices
    {
        return $this->invoices;
    }

    public function stores(): Stores
    {
        return $this->stores;
    }

    public function platform(): Platform
    {
        return $this->platform;
    }
}
