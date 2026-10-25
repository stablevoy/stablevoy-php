<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** A transfer to the invoice. The amount is in the token's smallest units, as a string: "19990000" is 19.99 USDC. */
final class Payment
{
    public const STATUS_DETECTED = 'detected';
    public const STATUS_CONFIRMED = 'confirmed';

    public function __construct(
        public readonly string $id,
        public readonly string $network,
        public readonly int $amountDueUsdCents,
        public readonly string $token,
        public readonly string $amount,
        public readonly string $txHash,
        public readonly string $status,
        public readonly string $screeningStatus,
        public readonly \DateTimeImmutable $detectedAt,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('id'),
            $f->string('network'),
            $f->int('amountDueUsdCents'),
            $f->string('token'),
            $f->string('amount'),
            $f->string('txHash'),
            $f->string('status'),
            $f->string('screeningStatus'),
            $f->date('detectedAt'),
        );
    }
}
