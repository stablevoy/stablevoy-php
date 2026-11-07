<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/**
 * A payment to the payout wallet on a direct network (Tron) that matched no invoice, usually an exchange that took its
 * withdrawal fee out of the amount. Attach it to an invoice in the Stablevoy dashboard; an API key cannot.
 */
final class UnmatchedTransfer
{
    public function __construct(
        public readonly string $id,
        /** Network key, for example tron. */
        public readonly string $network,
        public readonly string $networkName,
        /** The payout wallet the transfer arrived at. */
        public readonly string $payoutAddress,
        public readonly string $fromAddress,
        public readonly string $txHash,
        public readonly string $tokenSymbol,
        /** What arrived, in millionths of a dollar, as a string: "98500137" is $98.500137. */
        public readonly string $amountUsdMicros,
        public readonly \DateTimeImmutable $detectedAt,
        /** Prefix of the transaction page on the block explorer; Tronscan takes the hash without its 0x prefix. */
        public readonly string $explorerTxUrl,
        /**
         * How many active stores of the account offer this network. The payout wallet belongs to the account, not to one
         * store, so 1 means the transfer is certainly this store's and more means it may belong to any of that many stores.
         */
        public readonly int $sharedWithStores,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('id'),
            $f->string('network'),
            $f->string('networkName'),
            $f->string('payoutAddress'),
            $f->string('fromAddress'),
            $f->string('txHash'),
            $f->string('tokenSymbol'),
            $f->string('amountUsdMicros'),
            $f->date('detectedAt'),
            $f->string('explorerTxUrl'),
            $f->int('sharedWithStores'),
        );
    }
}
