<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * The amounts an invoice of the store may have now, in US cents: the bounds of the account (the platform ones, or a
 * higher or lower cap set for the account on request) narrowed by the store's own minimum and maximum. Use it to decide
 * whether to offer the payment method; never compute it from GET /v1/limits.
 */
final class InvoiceRange
{
    public function __construct(
        public readonly int $minUsdCents,
        public readonly int $maxUsdCents,
    ) {}

    /** True when an invoice of $amountUsdCents would be accepted by these bounds. */
    public function contains(int $amountUsdCents): bool
    {
        return $amountUsdCents >= $this->minUsdCents && $amountUsdCents <= $this->maxUsdCents;
    }

    public static function fromFields(Fields $f): self
    {
        return new self($f->int('minUsdCents'), $f->int('maxUsdCents'));
    }
}
