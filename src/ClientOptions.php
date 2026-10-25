<?php

declare(strict_types=1);

namespace Stablevoy;

use Stablevoy\Http\RetryPolicy;
use Stablevoy\Http\Sleeper;
use Stablevoy\Http\SystemSleeper;

/** How the client talks to the API. The defaults are what production needs. */
final class ClientOptions
{
    public function __construct(
        public readonly string $baseUrl = 'https://api.stablevoy.com',
        public readonly RetryPolicy $retries = new RetryPolicy(),
        public readonly Sleeper $sleeper = new SystemSleeper(),
        public readonly ?string $userAgentSuffix = null,
    ) {}
}
