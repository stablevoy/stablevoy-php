<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** An invoice as the API returns it. Amounts are integers in US cents. */
final class Invoice
{
    /**
     * @param list<DepositAddress> $depositAddresses
     * @param list<Payment> $payments
     */
    public function __construct(
        public readonly string $id,
        public readonly string $storeId,
        public readonly string $status,
        public readonly int $amountUsdCents,
        public readonly int $feeBps,
        public readonly string $invoiceSalt,
        public readonly ?string $externalReference,
        public readonly ?string $returnUrl,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $paidAt,
        public readonly ?string $holdReason,
        public readonly \DateTimeImmutable $createdAt,
        public readonly array $depositAddresses,
        public readonly array $payments,
        /** Where the invoice came from; null only from an API older than 31 October 2026, which did not send it. */
        public readonly ?InvoiceSource $source = null,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(
            $f->string('id'),
            $f->string('storeId'),
            $f->string('status'),
            $f->int('amountUsdCents'),
            $f->int('feeBps'),
            $f->string('invoiceSalt'),
            $f->nullableString('externalReference'),
            $f->nullableString('returnUrl'),
            $f->date('expiresAt'),
            $f->nullableDate('paidAt'),
            $f->nullableString('holdReason'),
            $f->date('createdAt'),
            array_map(
                static fn(mixed $item, int $i): DepositAddress => DepositAddress::fromFields(Fields::of($item, $f->path('depositAddresses') . "[$i]")),
                $f->list('depositAddresses'),
                array_keys($f->list('depositAddresses')),
            ),
            array_map(
                static fn(mixed $item, int $i): Payment => Payment::fromFields(Fields::of($item, $f->path('payments') . "[$i]")),
                $f->list('payments'),
                array_keys($f->list('payments')),
            ),
            array_key_exists('source', $f->raw()) ? InvoiceSource::fromNullableFields($f->raw()['source'] === null ? null : $f->object('source')) : null,
        );
    }

    /** True once the order can be fulfilled (paid, overpaid or paid late). */
    public function isPaid(): bool
    {
        return InvoiceStatus::isPaid($this->status);
    }
}
