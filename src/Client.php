<?php

declare(strict_types=1);

namespace Stablevoy;

use Http\Discovery\Exception\NotFoundException as DiscoveryNotFound;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Exception\ConfigurationException;
use Stablevoy\Http\ErrorFactory;
use Stablevoy\Http\HttpTransport;
use Stablevoy\Http\Transport;
use Stablevoy\Resource\Invoices;
use Stablevoy\Resource\Platform;
use Stablevoy\Resource\Stores;

/**
 * The Stablevoy API. Build it with Client::create('sk_live_...') when a PSR-18 HTTP client is installed (Guzzle,
 * Symfony HttpClient and others), or with Client::fromPsr() to pass your own.
 */
final class Client
{
    public const VERSION = '1.0.0';

    public function __construct(
        private readonly Invoices $invoices,
        private readonly Stores $stores,
        private readonly Platform $platform,
    ) {}

    /** Finds an installed PSR-18 client and PSR-17 factories; fails loudly when there is none. */
    public static function create(string $apiKey, ClientOptions $options = new ClientOptions()): self
    {
        try {
            return self::fromPsr($apiKey, Psr18ClientDiscovery::find(), Psr17FactoryDiscovery::findRequestFactory(), Psr17FactoryDiscovery::findStreamFactory(), $options);
        } catch (DiscoveryNotFound $e) {
            throw new ConfigurationException('No PSR-18 HTTP client is installed. Run "composer require guzzlehttp/guzzle" (or any PSR-18 client), or pass yours to Client::fromPsr().', 0, $e);
        }
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
