<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** Where a store's payout wallet of each family comes from: the store's own (STORE) or the account's (ACCOUNT); null when the store has none. */
final class PayoutSources
{
    public const STORE = 'store';
    public const ACCOUNT = 'account';

    public function __construct(
        public readonly ?string $evm,
        public readonly ?string $tron,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self($f->nullableString('evm'), $f->nullableString('tron'));
    }
}
