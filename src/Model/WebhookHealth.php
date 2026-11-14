<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * State of the store's webhook endpoint. FAILING: at least 10 failed attempts and not paused yet, fix the endpoint before
 * pausesAt. PAUSED: it failed for 24 hours with at least 10 failed attempts; new events wait (up to 30 days) until the
 * merchant resumes it in the dashboard. Faults of Stablevoy's own delivery service never count against it.
 */
final class WebhookHealth
{
    public const ACTIVE = 'active';
    public const FAILING = 'failing';
    public const PAUSED = 'paused';

    public function __construct(
        /** ACTIVE, FAILING or PAUSED. */
        public readonly string $status,
        /** First failed delivery since the last successful one; null while deliveries succeed. */
        public readonly ?\DateTimeImmutable $failingSince,
        /** Failed deliveries since failingSince. */
        public readonly int $failedAttempts,
        /** While it fails and is not paused: the earliest moment it is paused if it keeps failing; null otherwise. */
        public readonly ?\DateTimeImmutable $pausesAt,
        public readonly ?\DateTimeImmutable $pausedAt,
        /** The last error when it was paused. */
        public readonly ?string $pauseReason,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('status'),
            $f->nullableDate('failingSince'),
            $f->int('failedAttempts'),
            $f->nullableDate('pausesAt'),
            $f->nullableDate('pausedAt'),
            $f->nullableString('pauseReason'),
        );
    }
}
