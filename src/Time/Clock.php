<?php

declare(strict_types=1);

namespace Stablevoy\Time;

interface Clock
{
    /** Seconds since the Unix epoch. */
    public function now(): int;
}
