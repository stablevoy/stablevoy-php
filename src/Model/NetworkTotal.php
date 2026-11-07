<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The received money on one network in one token. */
final class NetworkTotal
{
    public function __construct(
        public readonly string $network,
        /** USDT or USDC. */
        public readonly string $token,
        public readonly int $payments,
        /** Millionths of a dollar, as a string: "251200000" is $251.20. */
        public readonly string $receivedUsdMicros,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->string('network'), $f->string('token'), $f->int('payments'), $f->string('receivedUsdMicros'));
    }
}
