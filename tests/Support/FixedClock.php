<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

use Stablevoy\Time\Clock;

final class FixedClock implements Clock
{
    public function __construct(public int $now) {}

    public function now(): int
    {
        return $this->now;
    }
}
