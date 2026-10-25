<?php

declare(strict_types=1);

namespace Stablevoy\Http;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/** A request that cannot be sent at all, such as one without a URL or a method. */
final class CurlRequestException extends \InvalidArgumentException implements RequestExceptionInterface
{
    public function __construct(string $message, private readonly RequestInterface $request)
    {
        parent::__construct($message);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
