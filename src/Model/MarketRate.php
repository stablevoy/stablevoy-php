<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The market rate of a currency stored now. usable false: older than the platform allows, so it prices no invoice. */
final class MarketRate
{
    public function __construct(
        public readonly string $usdPerUnit,
        public readonly string $source,
        public readonly \DateTimeImmutable $rateAt,
        public readonly bool $usable,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('usdPerUnit'), $f->string('source'), $f->date('rateAt'), $f->bool('usable'));
    }
}
