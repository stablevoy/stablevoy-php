<?php

declare(strict_types=1);

namespace Stablevoy\Model;

/** The answer to creating an invoice: the invoice, the hosted payment page to send the buyer to, and what was left out. */
final class CreatedInvoice
{
    /**
     * @param list<string> $unavailableNetworks enabled networks left out of this invoice right now
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly string $checkoutUrl,
        public readonly array $unavailableNetworks,
        public readonly bool $replayed,
    ) {}

    public static function fromFields(Fields $f): self
    {
        return new self(Invoice::fromFields($f), $f->string('checkoutUrl'), $f->strings('unavailableNetworks'), $f->bool('replayed'));
    }
}
