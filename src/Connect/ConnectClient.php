<?php

declare(strict_types=1);

namespace Stablevoy\Connect;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\ClientOptions;
use Stablevoy\Http\Transport;
use Stablevoy\Http\TransportFactory;
use Stablevoy\Model\ConnectedStore;
use Stablevoy\Model\Fields;

/**
 * One-click connect: the shop sends the merchant to the Stablevoy dashboard with a PKCE challenge, gets a code back on
 * its callback and redeems it here for its API key and webhook secret. Needs no API key, since the shop has none yet.
 */
final class ConnectClient
{
    public function __construct(private readonly Transport $transport) {}

    /** Over ext-curl, like Client::create(). */
    public static function create(ClientOptions $options = new ClientOptions(), float $timeoutSeconds = 30.0): self
    {
        return new self(TransportFactory::overCurl(null, $options, $timeoutSeconds));
    }

    /** Over your own PSR-18 client, like Client::fromPsr(). */
    public static function fromPsr(ClientInterface $http, RequestFactoryInterface $requests, StreamFactoryInterface $streams, ClientOptions $options = new ClientOptions()): self
    {
        return new self(TransportFactory::overPsr(null, $http, $requests, $streams, $options));
    }

    /**
     * Redeems the code. It works once, so this call is never retried: after a lost answer the merchant clicks Connect
     * again. A refusal (invalid_connect_code, store_linked_to_other_site, store_connected_other_platform) is an
     * ApiException with that code.
     *
     * @param string $site the shop's own URL, the same one sent when the connection was started
     */
    public function exchange(string $code, string $codeVerifier, string $site): ConnectedStore
    {
        return ConnectedStore::fromFields(Fields::of($this->transport->post('/v1/connect/exchange', ['code' => $code, 'codeVerifier' => $codeVerifier, 'site' => $site]), 'connected'));
    }
}
