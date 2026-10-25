<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** No complete answer: the connection failed, broke, or ran out of time. */
final class CurlNetworkException extends \RuntimeException implements NetworkExceptionInterface
{
    public function __construct(string $message, int $curlError, private readonly RequestInterface $request)
    {
        parent::__construct($message, $curlError);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
