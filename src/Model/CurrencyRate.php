<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A rate of a currency in US dollars per unit, with where it came from. */
final class CurrencyRate
{
    public function __construct(
        /** A decimal string such as "1.0992". */
        public readonly string $usdPerUnit,
        /** 'ecb', 'merchant' or 'usd'. */
        public readonly string $source,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('usdPerUnit'), $f->string('source'));
    }
}
