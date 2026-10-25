<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The live price list: the free volume per payout wallet, the fee and the cap written into the contracts (50 bps is 0.5%). */
final class Pricing
{
    public function __construct(
        public readonly int $freeVolumeUsdCents,
        public readonly int $feeBps,
        public readonly int $maxFeeBps,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->int('freeVolumeUsdCents'), $f->int('feeBps'), $f->int('maxFeeBps'));
    }
}
