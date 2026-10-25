<?php

declare(strict_types=1);

namespace Stablevoy\Http;

/**
 * When to retry: on 429, 503 and a failed connection, at most maxRetries times, waiting what Retry-After asks (or
 * 1, 2, 4 seconds without it). A wait longer than maxWaitSeconds is not slept through: the exception goes to the
 * caller with retryAfterSeconds, so a request never hangs for minutes during maintenance.
 */
final class RetryPolicy
{
    public function __construct(
        public readonly int $maxRetries = 2,
        public readonly int $maxWaitSeconds = 10,
    ) {
        if ($maxRetries < 0 || $maxWaitSeconds < 0) {
            throw new \InvalidArgumentException('maxRetries and maxWaitSeconds must not be negative');
        }
    }

    public static function none(): self
    {
        return new self(0, 0);
    }

    /**
     * Seconds to wait before the next attempt, or null when the call must not be retried.
     *
     * @param int $attempt how many attempts were made so far, starting at 1
     */
    public function waitBefore(int $attempt, ?int $retryAfterSeconds): ?int
    {
        if ($attempt > $this->maxRetries) {
            return null;
        }
        $wait = $retryAfterSeconds ?? 1 << ($attempt - 1);
        return $wait <= $this->maxWaitSeconds ? $wait : null;
    }
}
