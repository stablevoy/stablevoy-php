<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A stablecoin accepted on a network. */
final class Token
{
    public function __construct(
        public readonly string $symbol,
        public readonly string $address,
        public readonly int $decimals,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('symbol'), $f->string('address'), $f->int('decimals'));
    }
}
