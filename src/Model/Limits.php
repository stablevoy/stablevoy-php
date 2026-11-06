<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The smallest and largest invoice the platform accepts, in US cents, and how long an invoice can be paid, in minutes. */
final class Limits
{
    public function __construct(
        public readonly int $minInvoiceUsdCents,
        public readonly int $maxInvoiceUsdCents,
        /** The lifetime of an invoice when neither the request nor the store names one. */
        public readonly int $defaultInvoiceLifetimeMinutes,
        /** The bounds of expiresInMinutes and of a store's payment window. */
        public readonly int $minInvoiceLifetimeMinutes,
        public readonly int $maxInvoiceLifetimeMinutes,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->int('minInvoiceUsdCents'),
            $f->int('maxInvoiceUsdCents'),
            $f->int('defaultInvoiceLifetimeMinutes'),
            $f->int('minInvoiceLifetimeMinutes'),
            $f->int('maxInvoiceLifetimeMinutes'),
        );
    }
}
