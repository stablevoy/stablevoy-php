<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A store's own fixed rate, set in the dashboard and used for every invoice of the store in that currency. */
final class StoreRate
{
    public function __construct(
        public readonly string $usdPerUnit,
        public readonly \DateTimeImmutable $setAt,
        /** True when it is more than 10% away from the market rate. It is still used. */
        public readonly bool $divergesFromMarket,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('usdPerUnit'), $f->date('setAt'), $f->bool('divergesFromMarket'));
    }
}
