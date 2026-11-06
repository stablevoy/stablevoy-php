<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The account's payout wallets per network family, null when not set. Read only: they change in the dashboard with a wallet signature. */
final class PayoutAddresses
{
    public function __construct(
        public readonly ?string $evm,
        public readonly ?string $tron,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->nullableString('evm'), $f->nullableString('tron'));
    }
}
