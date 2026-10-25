<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;

final class FakeClientException extends \RuntimeException implements ClientExceptionInterface {}
