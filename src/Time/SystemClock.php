<?php

declare(strict_types=1);

namespace Stablevoy\Time;

final class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
