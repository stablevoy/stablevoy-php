<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** Where the buyer pays on one network: a one-time deposit address, or on Tron the merchant's own wallet. */
final class DepositAddress
{
    public function __construct(
        public readonly string $network,
        public readonly string $address,
        public readonly string $payoutAddress,
        public readonly int $amountDueUsdCents,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('network'), $f->string('address'), $f->string('payoutAddress'), $f->int('amountDueUsdCents'));
    }
}
