<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Http\Discovery\Exception\NotFoundException as DiscoveryNotFound;
use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Stablevoy\Client;
use Stablevoy\ClientOptions;
use Stablevoy\Exception\ConfigurationException;

/**
 * Builds the transport Client and ConnectClient talk through, so both build it the same way.
 *
 * @internal
 */
final class TransportFactory
{
    /**
     * Over ext-curl with a limit on each attempt, with the installed PSR-17 factories; fails loudly when there are none.
     */
    public static function overCurl(?string $apiKey, ClientOptions $options, float $timeoutSeconds): Transport
    {
        try {
            $requests = Psr17FactoryDiscovery::findRequestFactory();
            $responses = Psr17FactoryDiscovery::findResponseFactory();
            $streams = Psr17FactoryDiscovery::findStreamFactory();
        } catch (DiscoveryNotFound $e) {
            throw new ConfigurationException('No PSR-17 HTTP message factories are installed. Run "composer require nyholm/psr7" (Guzzle and Symfony projects already have them).', 0, $e);
        }
        return self::overPsr($apiKey, new CurlHttpClient($responses, $streams, $timeoutSeconds), $requests, $streams, $options);
    }

    public static function overPsr(?string $apiKey, ClientInterface $http, RequestFactoryInterface $requests, StreamFactoryInterface $streams, ClientOptions $options): Transport
    {
        $userAgent = 'stablevoy-php/' . Client::VERSION . ' PHP/' . PHP_VERSION . ($options->userAgentSuffix === null ? '' : ' ' . $options->userAgentSuffix);
        return new HttpTransport($apiKey, $http, $requests, $streams, $options->baseUrl, $userAgent, $options->retries, $options->sleeper, new ErrorFactory());
    }
}
