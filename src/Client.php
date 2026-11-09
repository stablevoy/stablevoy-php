<?php

declare(strict_types=1);

namespace Stablevoy;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Http\Transport;
use Stablevoy\Http\TransportFactory;
use Stablevoy\Resource\Invoices;
use Stablevoy\Resource\Platform;
use Stablevoy\Resource\Stores;
use Stablevoy\Resource\UnmatchedTransfers;

/**
 * The Stablevoy API. Build it with Client::create('sk_live_...'), which talks over ext-curl with a timeout, or with
 * Client::fromPsr() to pass your own PSR-18 client (then its timeout is yours to set).
 */
final class Client
{
    public const VERSION = '1.8.0';

    public function __construct(
        private readonly Invoices $invoices,
        private readonly Stores $stores,
        private readonly Platform $platform,
        private readonly UnmatchedTransfers $unmatchedTransfers,
    ) {}

    /**
     * Talks over ext-curl; every attempt is limited to $timeoutSeconds (30 by default, at most 600) from connecting to
     * the last byte. Finds installed PSR-17 factories for the messages and fails loudly when there are none.
     */
    public static function create(string $apiKey, ClientOptions $options = new ClientOptions(), float $timeoutSeconds = 30.0): self
    {
        return self::fromTransport(TransportFactory::overCurl($apiKey, $options, $timeoutSeconds));
    }

    public static function fromPsr(string $apiKey, ClientInterface $http, RequestFactoryInterface $requests, StreamFactoryInterface $streams, ClientOptions $options = new ClientOptions()): self
    {
        return self::fromTransport(TransportFactory::overPsr($apiKey, $http, $requests, $streams, $options));
    }

    public static function fromTransport(Transport $transport): self
    {
        return new self(new Invoices($transport), new Stores($transport), new Platform($transport), new UnmatchedTransfers($transport));
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

    public function unmatchedTransfers(): UnmatchedTransfers
    {
        return $this->unmatchedTransfers;
    }
}
