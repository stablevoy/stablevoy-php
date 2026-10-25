<?php

declare(strict_types=1);

namespace Stablevoy\Http;

/** Waits between retries; tests pass a recording one. */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
