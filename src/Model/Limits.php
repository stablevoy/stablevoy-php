<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The smallest and largest invoice the platform accepts, in US cents. */
final class Limits
{
    public function __construct(
        public readonly int $minInvoiceUsdCents,
        public readonly int $maxInvoiceUsdCents,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->int('minInvoiceUsdCents'), $f->int('maxInvoiceUsdCents'));
    }
}
