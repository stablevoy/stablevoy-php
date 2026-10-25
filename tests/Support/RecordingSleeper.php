<?php

declare(strict_types=1);

namespace Stablevoy\Tests\Support;

use Stablevoy\Http\Sleeper;

final class RecordingSleeper implements Sleeper
{
    /** @var list<int> */
    public array $slept = [];

    public function sleep(int $seconds): void
    {
        $this->slept[] = $seconds;
    }
}
